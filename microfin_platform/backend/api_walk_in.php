<?php
header('Content-Type: application/json');
session_start();

require_once 'db_connect.php';

if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if (($_SESSION['user_type'] ?? '') !== 'Employee') {
    echo json_encode(['status' => 'error', 'message' => 'Only staff members can perform this action.']);
    exit;
}

$content_type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$is_json_payload = strpos($content_type, 'application/json') !== false;
$data = [];

if ($is_json_payload) {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    if (!is_array($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
        exit;
    }
} else {
    $data = $_POST;
}

$tenant_id = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Session is missing tenant context.']);
    exit;
}

$tenant_upload_key = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
if (!is_string($tenant_upload_key) || $tenant_upload_key === '') {
    $tenant_upload_key = 'tenant';
}

function boolFromInput($value): bool {
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return ((int) $value) === 1;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    return false;
}

function normalizeDocumentIds($raw_ids): array {
    $items = [];

    if (is_array($raw_ids)) {
        $items = $raw_ids;
    } elseif ($raw_ids !== null && $raw_ids !== '') {
        $items = [$raw_ids];
    }

    $normalized = [];
    foreach ($items as $doc_id) {
        $id = (int) $doc_id;
        if ($id > 0) {
            $normalized[$id] = $id;
        }
    }

    return array_values($normalized);
}

function collectUploadedDocuments(array $uploaded_file_field, string $tenant_upload_key, string $application_number, array &$saved_paths): array {
    if (!isset($uploaded_file_field['name']) || !is_array($uploaded_file_field['name'])) {
        return [];
    }

    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $upload_relative_dir = 'uploads/walk_in_documents/' . $tenant_upload_key . '/' . date('Y') . '/' . date('m') . '/' . strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '_', $application_number));
    $upload_absolute_dir = __DIR__ . '/../' . $upload_relative_dir;

    if (!is_dir($upload_absolute_dir) && !mkdir($upload_absolute_dir, 0775, true) && !is_dir($upload_absolute_dir)) {
        throw new Exception('Unable to prepare document upload folder.');
    }

    $documents = [];

    foreach ($uploaded_file_field['name'] as $doc_type_key => $original_name_raw) {
        $doc_type_id = (int) $doc_type_key;
        if ($doc_type_id <= 0) {
            continue;
        }

        $error_code = $uploaded_file_field['error'][$doc_type_key] ?? UPLOAD_ERR_NO_FILE;
        if ((int) $error_code === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ((int) $error_code !== UPLOAD_ERR_OK) {
            throw new Exception('One of the selected documents failed to upload.');
        }

        $tmp_name = (string) ($uploaded_file_field['tmp_name'][$doc_type_key] ?? '');
        $size_bytes = (int) ($uploaded_file_field['size'][$doc_type_key] ?? 0);

        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            throw new Exception('Uploaded file source is invalid.');
        }
        if ($size_bytes <= 0) {
            throw new Exception('Uploaded documents cannot be empty.');
        }
        if ($size_bytes > (10 * 1024 * 1024)) {
            throw new Exception('Each uploaded document must be 10MB or smaller.');
        }

        $safe_original_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $original_name_raw));
        $ext = strtolower((string) pathinfo($safe_original_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions, true)) {
            throw new Exception('Invalid document file type. Allowed: PDF, JPG, JPEG, PNG, DOC, DOCX.');
        }

        $stored_name = 'doc_' . $doc_type_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest_path = rtrim($upload_absolute_dir, '/\\') . DIRECTORY_SEPARATOR . $stored_name;

        if (!move_uploaded_file($tmp_name, $dest_path)) {
            throw new Exception('Failed to save an uploaded document on the server.');
        }

        $saved_paths[] = $dest_path;
        $documents[] = [
            'document_type_id' => $doc_type_id,
            'original_name' => $safe_original_name,
            'stored_name' => $stored_name,
            'relative_path' => $upload_relative_dir . '/' . $stored_name,
            'size_bytes' => $size_bytes,
            'uploaded_at' => date('c')
        ];
    }

    return $documents;
}

function tableHasColumn(PDO $pdo, string $table_name, string $column_name): bool {
    static $cache = [];

    $cache_key = strtolower($table_name . '.' . $column_name);
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table_name, $column_name]);
    $exists = (bool) $stmt->fetchColumn();
    $cache[$cache_key] = $exists;

    return $exists;
}

$first_name = trim((string) ($data['first_name'] ?? ''));
$last_name = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phone = trim((string) ($data['phone_number'] ?? ''));
$dob = trim((string) ($data['date_of_birth'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirm_password = (string) ($data['confirm_password'] ?? '');

$product_id = (int) ($data['product_id'] ?? 0);
$requested_amount_raw = (string) ($data['requested_amount'] ?? '');
$requested_amount = is_numeric($requested_amount_raw) ? (float) $requested_amount_raw : 0.0;
$loan_term_months = (int) ($data['loan_term_months'] ?? 0);
$monthly_income_raw = (string) ($data['monthly_income'] ?? '');
$monthly_income = is_numeric($monthly_income_raw) ? (float) $monthly_income_raw : 0.0;
$loan_purpose = trim((string) ($data['loan_purpose'] ?? ''));

$documents_complete = boolFromInput($data['documents_complete'] ?? false);
$missing_documents_notes = trim((string) ($data['missing_documents_notes'] ?? ''));
$submitted_document_type_ids = normalizeDocumentIds($data['submitted_document_type_ids'] ?? []);

$walk_in_action = strtolower(trim((string) ($data['walk_in_action'] ?? 'draft')));
if (!in_array($walk_in_action, ['draft', 'submit'], true)) {
    $walk_in_action = 'draft';
}

if ($first_name === '' || $last_name === '' || $email === '' || $dob === '' || $password === '' || $confirm_password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required account fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'Password confirmation does not match.']);
    exit;
}

if ($product_id <= 0 || $requested_amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Loan product and requested amount are required.']);
    exit;
}

if ($loan_term_months <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Loan term must be at least 1 month.']);
    exit;
}

if ($monthly_income <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Monthly income is required to assess credit limit.']);
    exit;
}

$normalized_doc_type_ids = $submitted_document_type_ids;

function generateUniqueUsername(PDO $pdo, string $tenant_id, string $first_name, string $last_name): string {
    $base = strtolower(trim($first_name . '.' . $last_name));
    $base = preg_replace('/[^a-z0-9.]+/', '', $base);
    $base = trim((string) $base, '.');
    if ($base === '') {
        $base = 'client';
    }

    for ($i = 0; $i < 20; $i++) {
        $candidate = $base;
        if ($i > 0) {
            $candidate .= (string) random_int(100, 9999);
        }

        $check_stmt = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
        $check_stmt->execute([$tenant_id, $candidate]);
        if (!$check_stmt->fetchColumn()) {
            return $candidate;
        }
    }

    return $base . (string) random_int(10000, 99999);
}

function generateApplicationNumber(PDO $pdo): string {
    for ($i = 0; $i < 20; $i++) {
        $candidate = 'APP-' . date('YmdHis') . '-' . (string) random_int(1000, 9999);
        $check_stmt = $pdo->prepare('SELECT 1 FROM loan_applications WHERE application_number = ? LIMIT 1');
        $check_stmt->execute([$candidate]);
        if (!$check_stmt->fetchColumn()) {
            return $candidate;
        }
        usleep(50000);
    }

    return 'APP-' . date('YmdHis') . '-' . (string) random_int(10000, 99999);
}

try {
    $saved_uploaded_file_paths = [];
    $pdo->beginTransaction();

    $dup_stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
    $dup_stmt->execute([$email, $tenant_id]);
    if ($dup_stmt->fetchColumn()) {
        throw new Exception('Email is already registered in this branch/company.');
    }

    $role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client' AND tenant_id = ? LIMIT 1");
    $role_stmt->execute([$tenant_id]);
    $role_id = (int) $role_stmt->fetchColumn();

    if ($role_id <= 0) {
        $insert_role = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Client', 'Client app access', 1)");
        $insert_role->execute([$tenant_id]);
        $role_id = (int) $pdo->lastInsertId();
    }

    $username = generateUniqueUsername($pdo, $tenant_id, $first_name, $last_name);
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $user_insert = $pdo->prepare('
        INSERT INTO users (
            tenant_id, username, email, phone_number, password_hash, email_verified,
            first_name, last_name, date_of_birth,
            role_id, user_type, status
        ) VALUES (
            ?, ?, ?, ?, ?, 1,
            ?, ?, ?,
            ?, \'Client\', \'Active\'
        )
    ');
    $user_insert->execute([
        $tenant_id,
        $username,
        $email,
        ($phone !== '' ? $phone : null),
        $password_hash,
        $first_name,
        $last_name,
        $dob,
        $role_id
    ]);

    $new_user_id = (int) $pdo->lastInsertId();

    $employee_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $employee_stmt->execute([$session_user_id, $tenant_id]);
    $registered_by = $employee_stmt->fetchColumn();
    $registered_by = $registered_by !== false ? (int) $registered_by : null;

    $client_insert = $pdo->prepare('
        INSERT INTO clients (
            tenant_id, user_id, first_name, last_name,
            date_of_birth, contact_number, present_street, email_address,
            registration_date, registered_by
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURDATE(), ?
        )
    ');
    $client_insert->execute([
        $tenant_id,
        $new_user_id,
        $first_name,
        $last_name,
        $dob,
        ($phone !== '' ? $phone : null),
        ($address !== '' ? $address : null),
        $email,
        $registered_by
    ]);

    $new_client_id = (int) $pdo->lastInsertId();

    if (tableHasColumn($pdo, 'clients', 'monthly_income')) {
        $monthly_income_update_stmt = $pdo->prepare('UPDATE clients SET monthly_income = ? WHERE client_id = ? AND tenant_id = ?');
        $monthly_income_update_stmt->execute([$monthly_income, $new_client_id, $tenant_id]);
    }

    $product_stmt = $pdo->prepare('
        SELECT product_id, product_name, product_type, interest_rate, min_amount, max_amount, min_term_months, max_term_months
        FROM loan_products
        WHERE tenant_id = ? AND product_id = ?
        LIMIT 1
    ');
    $product_stmt->execute([$tenant_id, $product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Selected loan product is not available for this tenant.');
    }

    $product_min_amount = (float) $product['min_amount'];
    $product_max_amount = (float) $product['max_amount'];

    if ($requested_amount < $product_min_amount || $requested_amount > $product_max_amount) {
        throw new Exception('Requested amount is outside the selected product limits.');
    }

    $min_term = (int) $product['min_term_months'];
    $max_term = (int) $product['max_term_months'];
    if ($loan_term_months < $min_term) {
        $loan_term_months = $min_term;
    }
    if ($loan_term_months > $max_term) {
        $loan_term_months = $max_term;
    }

    $application_number = generateApplicationNumber($pdo);

    $uploaded_documents = [];
    if (!$is_json_payload && isset($_FILES['uploaded_documents']) && is_array($_FILES['uploaded_documents'])) {
        $uploaded_documents = collectUploadedDocuments($_FILES['uploaded_documents'], $tenant_upload_key, $application_number, $saved_uploaded_file_paths);
    }

    $uploaded_doc_type_ids = [];
    foreach ($uploaded_documents as $uploaded_doc) {
        $doc_type_id = (int) ($uploaded_doc['document_type_id'] ?? 0);
        if ($doc_type_id > 0) {
            $uploaded_doc_type_ids[$doc_type_id] = $doc_type_id;
            $normalized_doc_type_ids[$doc_type_id] = $doc_type_id;
        }
    }

    $uploaded_doc_type_ids = array_values($uploaded_doc_type_ids);
    $normalized_doc_type_ids = array_values(array_unique(array_map('intval', $normalized_doc_type_ids)));

    $required_doc_stmt = $pdo->query('SELECT document_type_id FROM document_types WHERE is_active = 1 AND is_required = 1');
    $required_document_type_ids = array_map('intval', $required_doc_stmt->fetchAll(PDO::FETCH_COLUMN));

    $missing_required_document_type_ids = array_values(array_diff($required_document_type_ids, $normalized_doc_type_ids));
    $missing_required_uploaded_type_ids = array_values(array_diff($required_document_type_ids, $uploaded_doc_type_ids));

    if ($walk_in_action === 'submit' && $documents_complete) {
        if (!empty($missing_required_uploaded_type_ids)) {
            throw new Exception('Please upload all required documents before submitting. If some are still missing, save as Draft.');
        }
    }

    $status_for_save = ($walk_in_action === 'submit' && $documents_complete) ? 'Submitted' : 'Draft';
    $submitted_date = $status_for_save === 'Submitted' ? date('Y-m-d H:i:s') : null;

    $application_data = [
        'registration_channel' => 'walk-in',
        'monthly_income' => $monthly_income,
        'documents_complete' => $documents_complete,
        'submitted_document_type_ids' => $normalized_doc_type_ids,
        'uploaded_document_type_ids' => $uploaded_doc_type_ids,
        'uploaded_documents' => $uploaded_documents,
        'required_document_type_ids' => $required_document_type_ids,
        'missing_required_document_type_ids' => $missing_required_document_type_ids,
        'missing_required_uploaded_type_ids' => $missing_required_uploaded_type_ids,
        'missing_documents_notes' => $missing_documents_notes,
        'draft_reason' => ($status_for_save === 'Draft' ? 'Incomplete documents or explicit draft save' : null),
        'created_by_user_id' => $session_user_id
    ];

    $application_insert = $pdo->prepare('
        INSERT INTO loan_applications (
            application_number, client_id, tenant_id, product_id,
            requested_amount, loan_term_months, interest_rate,
            loan_purpose, application_data, application_status, submitted_date
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?
        )
    ');

    $application_insert->execute([
        $application_number,
        $new_client_id,
        $tenant_id,
        $product_id,
        $requested_amount,
        $loan_term_months,
        (float) $product['interest_rate'],
        ($loan_purpose !== '' ? $loan_purpose : null),
        json_encode($application_data, JSON_UNESCAPED_UNICODE),
        $status_for_save,
        $submitted_date
    ]);

    $new_application_id = (int) $pdo->lastInsertId();

    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, ?, 'loan_application', ?, ?)");
    $audit_action = $status_for_save === 'Submitted' ? 'WALK_IN_SUBMITTED' : 'WALK_IN_DRAFT';
    $audit_description = $status_for_save === 'Submitted'
        ? 'Walk-in client registration and application submitted'
        : 'Walk-in client registration saved as draft application';
    $audit_stmt->execute([
        $session_user_id > 0 ? $session_user_id : null,
        $tenant_id,
        $audit_action,
        $new_application_id,
        $audit_description
    ]);

    $pdo->commit();

    $message = $status_for_save === 'Submitted'
        ? 'Client registered and loan application submitted. The client can now log in with email and password.'
        : 'Client registered and loan application saved as Draft for follow-up documents.';

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'client_id' => $new_client_id,
        'application_id' => $new_application_id,
        'application_status' => $status_for_save,
        'application_number' => $application_number,
        'uploaded_document_count' => count($uploaded_documents)
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($saved_uploaded_file_paths) && is_array($saved_uploaded_file_paths)) {
        foreach ($saved_uploaded_file_paths as $saved_path) {
            if (is_string($saved_path) && is_file($saved_path)) {
                @unlink($saved_path);
            }
        }
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
