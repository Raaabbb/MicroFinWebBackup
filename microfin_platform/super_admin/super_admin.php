<?php
session_start();

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../backend/db_connect.php';
require_once '../backend/tenant_identity.php';

$ui_theme = (($_SESSION['ui_theme'] ?? 'dark') === 'dark') ? 'dark' : 'light';
if (!empty($_SESSION['super_admin_id'])) {
    $theme_stmt = $pdo->prepare('SELECT ui_theme FROM users WHERE user_id = ? LIMIT 1');
    $theme_stmt->execute([(int)$_SESSION['super_admin_id']]);
    $theme_row = $theme_stmt->fetch(PDO::FETCH_ASSOC);
    if ($theme_row && isset($theme_row['ui_theme'])) {
        $ui_theme = ($theme_row['ui_theme'] === 'dark') ? 'dark' : 'light';
        $_SESSION['ui_theme'] = $ui_theme;
    }
}

// Require PHPMailer manually
require_once '../vendor/PHPMailer/src/Exception.php';
require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sa_column_exists(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    $stmt->execute();
    $cache[$key] = (bool) $stmt->fetch();
    return $cache[$key];
}

$provision_success = '';
$provision_error = '';

// Read flash messages from session (PRG pattern)
if (isset($_SESSION['sa_flash'])) {
    $provision_success = $_SESSION['sa_flash'];
    unset($_SESSION['sa_flash']);
}
if (isset($_SESSION['sa_error'])) {
    $provision_error = $_SESSION['sa_error'];
    unset($_SESSION['sa_error']);
}

$plan_pricing_map = [
    'Starter' => 4999.00,
    'Growth' => 9999.00,
    'Pro' => 14999.00,
    'Enterprise' => 22999.00,
    'Unlimited' => 29999.00,
];

try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN request_type ENUM('tenant_application', 'talk_to_expert') NOT NULL DEFAULT 'tenant_application' AFTER status");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN assigned_expert_user_id INT NULL AFTER request_type");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_request_type_status (request_type, status)");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_request_type_created_at (request_type, created_at)");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_assigned_expert_user (assigned_expert_user_id)");
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'provision_tenant') {
        if (isset($_POST['tenant_id']) && trim((string) $_POST['tenant_id']) !== '') {
            $_SESSION['sa_error'] = 'Tenant ID is system-generated and cannot be set manually.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $tenant_name = trim($_POST['tenant_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $custom_slug = trim($_POST['custom_slug'] ?? '');
        $request_type = trim((string)($_POST['request_type'] ?? 'tenant_application'));
        if (!in_array($request_type, ['tenant_application', 'talk_to_expert'], true)) {
            $request_type = 'tenant_application';
        }
        $plan_tier = trim($_POST['plan_tier'] ?? 'Starter');
        if (!array_key_exists($plan_tier, $plan_pricing_map)) {
            $plan_tier = 'Starter';
        }

        $plan_limits_map = [
            'Starter' => ['clients' => 1000, 'users' => 250],
            'Growth' => ['clients' => 2500, 'users' => 750],
            'Pro' => ['clients' => 5000, 'users' => 2000],
            'Enterprise' => ['clients' => 10000, 'users' => 5000],
            'Unlimited' => ['clients' => -1, 'users' => -1],
        ];
        $max_c = $plan_limits_map[$plan_tier]['clients'];
        $max_u = $plan_limits_map[$plan_tier]['users'];
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $mi = trim($_POST['mi'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $company_address = trim($_POST['company_address'] ?? '');

        $slug_source = $custom_slug !== '' ? $custom_slug : $tenant_name;
        $base_tenant_slug = mf_normalize_tenant_slug($slug_source);
        if ($base_tenant_slug === '') {
            $base_tenant_slug = 'tenant';
        }

        $mrr = $plan_pricing_map[$plan_tier];

        if ($tenant_name === '' || $admin_email === '') {
            $_SESSION['sa_error'] = 'Institution name and admin email are required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        } else {
            $base_admin_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$last_name));
            if ($base_admin_username === '') {
                $base_admin_username = 'tenantadmin';
            }

            $existing_check = $pdo->prepare("SELECT tenant_id, request_type FROM tenants WHERE tenant_name = ? AND status IN ('Pending', 'Contacted', 'New', 'In Contact') LIMIT 1");
            $existing_check->execute([$tenant_name]);
            $existing = $existing_check->fetch();

            if ($existing) {
                $tenant_id = (string) $existing['tenant_id'];
                $existing_request_type = (string)($existing['request_type'] ?? 'tenant_application');
                $tenant_slug = mf_generate_unique_tenant_slug($pdo, $base_tenant_slug, $tenant_id);
                $update = $pdo->prepare("UPDATE tenants SET tenant_slug = ?, company_address = ?, status = 'Active', plan_tier = ?, mrr = ?, max_clients = ?, max_users = ?, onboarding_deadline = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE tenant_id = ?");
            $update->execute([$tenant_slug, $company_address, $plan_tier, $mrr, $max_c, $max_u, $tenant_id]);
            } else {
                $existing_request_type = $request_type;
                $tenant_id = mf_generate_tenant_id($pdo, 10);
                $tenant_slug = mf_generate_unique_tenant_slug($pdo, $base_tenant_slug);
                $insert = $pdo->prepare("INSERT INTO tenants (tenant_id, tenant_name, tenant_slug, company_address, status, request_type, plan_tier, mrr, max_clients, max_users, onboarding_deadline) VALUES (?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                $insert->execute([$tenant_id, $tenant_name, $tenant_slug, $company_address, $request_type, $plan_tier, $mrr, $max_c, $max_u]);
            }

            $existing_role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE tenant_id = ? AND role_name = 'Admin' LIMIT 1");
            $existing_role_stmt->execute([$tenant_id]);
            $new_role_id = (int)$existing_role_stmt->fetchColumn();
            if ($new_role_id <= 0) {
                $role_insert = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Admin', 'Default system administrator', TRUE)");
                $role_insert->execute([$tenant_id]);
                $new_role_id = (int)$pdo->lastInsertId();
            }

            $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $admin_username = $base_admin_username;
            $username_counter = 2;
            while (true) {
                $username_check = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
                $username_check->execute([$tenant_id, $admin_username]);
                if (!$username_check->fetchColumn()) {
                    break;
                }
                $admin_username = $base_admin_username . $username_counter;
                $username_counter++;
            }

            $existing_admin_user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1");
            $existing_admin_user_stmt->execute([$tenant_id, $admin_email]);
            $existing_admin_user_id = (int)$existing_admin_user_stmt->fetchColumn();

            if ($existing_admin_user_id > 0) {
                $user_update = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, force_password_change = TRUE, role_id = ?, user_type = 'Admin', status = 'Active', first_name = ?, last_name = ?, middle_name = ?, suffix = ?, deleted_at = NULL WHERE user_id = ?");
                $user_update->execute([
                    $admin_username,
                    $password_hash,
                    $new_role_id,
                    $first_name !== '' ? $first_name : null,
                    $last_name !== '' ? $last_name : null,
                    $mi !== '' ? $mi : null,
                    $suffix !== '' ? $suffix : null,
                    $existing_admin_user_id
                ]);
            } else {
                $user_insert = $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status, first_name, last_name, middle_name, suffix) VALUES (?, ?, ?, ?, TRUE, ?, 'Admin', 'Active', ?, ?, ?, ?)");
                $user_insert->execute([$tenant_id, $admin_username, $admin_email, $password_hash, $new_role_id, $first_name !== '' ? $first_name : null, $last_name !== '' ? $last_name : null, $mi !== '' ? $mi : null, $suffix !== '' ? $suffix : null]);
            }

            $admin_name = (string)($_SESSION['super_admin_username'] ?? 'super_admin');
            $provision_action_type = ($existing_request_type === 'talk_to_expert') ? 'LEAD_PROVISIONED' : 'TENANT_PROVISIONED';
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, ?, 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], $provision_action_type, "{$admin_name} had provisioned {$tenant_name} (ID: {$tenant_id}, Slug: {$tenant_slug}, Plan: {$plan_tier})", $tenant_id]);

            $private_url = 'http://localhost/admin-draft/microfin_platform/tenant_login/login.php?s=' . urlencode($tenant_slug);

            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
               <h2>Welcome to MicroFin, {$tenant_name}!</h2>
               <p>Your demo request has been approved and your instance has been provisioned.</p>
               <p><strong>Your Plan:</strong> {$plan_tier}</p>
               <p><strong>Login URL:</strong> <a href='{$private_url}'>{$private_url}</a></p>
               <p><strong>Temporary Password:</strong> <code style='background:#f4f4f5; padding:4px 8px; border-radius:4px; font-size:16px;'>{$temp_password}</code></p>
               <p>Please log in using the email address you originally registered with ({$admin_email}) and this temporary password to begin configuring your instance. You will be required to change your password and complete our First-Time Setup Wizard upon this first login.</p>
               <p>Please note: You have <strong>30 days</strong> to complete your initial setup. If setup is not complete by this deadline, your account will temporarily be drafted or suspended.</p>
               <p>We're excited to have you on board!</p>
            </body>
            </html>
            ";

            $mail = new PHPMailer(true);
            $email_status = '';
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('microfin.statements@gmail.com', 'MicroFin Provisioning');
                $mail->addAddress($admin_email);

                $mail->isHTML(true);
                $mail->Subject = 'MicroFin - Your Instance is Ready!';
                $mail->Body    = $message;

                $mail->send();
                $email_status = ' An email has been sent to the admin.';
            } catch (Exception $e) {
                $email_status = " (Email failed: {$mail->ErrorInfo})";
            }

            $_SESSION['sa_flash'] = 'Tenant provisioned successfully. Tenant ID: ' . $tenant_id . '.' . $email_status;
            header('Location: super_admin.php?section=tenants');
            exit;
        }
    } elseif ($action === 'send_talk_email') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        if ($tenant_id === '') {
            $_SESSION['sa_error'] = 'Missing lead tenant ID.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $lead_stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, t.request_type, owner.owner_email AS email, owner.owner_first_name AS first_name, owner.owner_last_name AS last_name
            FROM tenants t
            LEFT JOIN (
                SELECT u.tenant_id,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.first_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_first_name,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.last_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_last_name,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.email, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_email
                FROM users u
                WHERE u.tenant_id IS NOT NULL AND u.deleted_at IS NULL
                GROUP BY u.tenant_id
            ) owner ON owner.tenant_id = t.tenant_id
            WHERE t.tenant_id = ? AND t.deleted_at IS NULL LIMIT 1");
        $lead_stmt->execute([$tenant_id]);
        $lead = $lead_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lead) {
            $_SESSION['sa_error'] = 'Lead not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (($lead['request_type'] ?? '') !== 'talk_to_expert') {
            $_SESSION['sa_error'] = 'Email action is for Talk to an Expert leads only.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (empty($lead['email'])) {
            $_SESSION['sa_error'] = 'No admin email found for this lead.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $super_admin_email_stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? AND user_type = 'Super Admin' LIMIT 1");
        $super_admin_email_stmt->execute([(int)($_SESSION['super_admin_id'] ?? 0)]);
        $super_admin_email = (string)($super_admin_email_stmt->fetchColumn() ?: '');
        if ($super_admin_email === '') {
            $_SESSION['sa_error'] = 'Unable to determine the super admin email for this action.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $contact_name = trim(((string)($lead['first_name'] ?? '')) . ' ' . ((string)($lead['last_name'] ?? '')));
        $contact_name = $contact_name !== '' ? $contact_name : ((string)($lead['tenant_name'] ?? 'there'));
        $subject = 'MicroFin Consultation Follow-up for ' . (string)($lead['tenant_name'] ?? 'your institution');
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a;'>
                <p>Hi {$contact_name},</p>
                <p>Hi from MicroFin. Thank you for your inquiry.</p>
                <p>For additional concerns, please contact me at <a href='mailto:{$super_admin_email}'>{$super_admin_email}</a>.</p>
                <p>Best regards,<br>MicroFin Platform Team</p>
            </body>
            </html>
        ";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'microfin.statements@gmail.com';
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('microfin.statements@gmail.com', 'MicroFin Consult Team');
            $mail->addAddress((string)$lead['email']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            $upd = $pdo->prepare("UPDATE tenants SET status = 'In Contact' WHERE tenant_id = ?");
            $upd->execute([$tenant_id]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LEAD_EMAIL_SENT', 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], "Inquiry email sent to {$lead['tenant_name']} ({$lead['email']}) with contact {$super_admin_email}", $tenant_id]);

            $_SESSION['sa_flash'] = 'Consultation email sent successfully via microfin.statements@gmail.com.';
        } catch (Throwable $e) {
            $_SESSION['sa_error'] = 'Failed to send consultation email: ' . $mail->ErrorInfo;
        }

        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'close_inquiry') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        if ($tenant_id === '') {
            $_SESSION['sa_error'] = 'Missing lead tenant ID.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        $tenant_stmt = $pdo->prepare("SELECT tenant_name, request_type FROM tenants WHERE tenant_id = ? LIMIT 1");
        $tenant_stmt->execute([$tenant_id]);
        $tenant_row = $tenant_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant_row) {
            $_SESSION['sa_error'] = 'Lead not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        if (($tenant_row['request_type'] ?? '') !== 'talk_to_expert') {
            $_SESSION['sa_error'] = 'Close action is for inquiries only.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        $tenant_name = (string)($tenant_row['tenant_name'] ?? $tenant_id);

        $upd = $pdo->prepare("UPDATE tenants SET status = 'Archived' WHERE tenant_id = ?");
        $upd->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LEAD_CLOSED', 'tenant', ?, ?)");
        $log->execute([$_SESSION['super_admin_id'], "Inquiry closed for {$tenant_name}", $tenant_id]);
        $_SESSION['sa_flash'] = 'Inquiry closed.';
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'toggle_status') {
        $tenant_id = $_POST['tenant_id'] ?? '';
        $new_status = $_POST['new_status'] ?? 'Active';

        $update = $pdo->prepare("UPDATE tenants SET status = ? WHERE tenant_id = ?");
        $update->execute([$new_status, $tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_STATUS_CHANGE', 'tenant', ?, ?)");
        $log->execute([$_SESSION['super_admin_id'], "Tenant status changed to {$new_status}", $tenant_id]);

        $_SESSION['sa_flash'] = "Tenant status updated to {$new_status}.";
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'reject_tenant') {
        $tenant_id = $_POST['tenant_id'] ?? '';

        $update = $pdo->prepare("UPDATE tenants SET status = 'Rejected' WHERE tenant_id = ?");
        $update->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_REJECTED', 'tenant', 'Tenant application rejected', ?)");
        $log->execute([$_SESSION['super_admin_id'], $tenant_id]);

        $_SESSION['sa_flash'] = "Tenant has been rejected.";
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'update_tenant_slug') {
        $tenant_id = trim((string) ($_POST['tenant_id'] ?? ''));
        $requested_slug = trim((string) ($_POST['tenant_slug'] ?? ''));

        if ($tenant_id === '' || $requested_slug === '') {
            $_SESSION['sa_error'] = 'Tenant ID and new slug are required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $new_slug = mf_normalize_tenant_slug($requested_slug);
        if ($new_slug === '') {
            $_SESSION['sa_error'] = 'Please provide a valid slug using letters, numbers, or hyphens.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $tenant_stmt = $pdo->prepare('SELECT tenant_name, tenant_slug FROM tenants WHERE tenant_id = ? LIMIT 1');
        $tenant_stmt->execute([$tenant_id]);
        $tenant_row = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant_row) {
            $_SESSION['sa_error'] = 'Tenant not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $old_slug = (string) ($tenant_row['tenant_slug'] ?? '');
        if ($old_slug === $new_slug) {
            $_SESSION['sa_flash'] = 'Tenant slug is unchanged.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (mf_tenant_slug_exists($pdo, $new_slug, $tenant_id)) {
            $_SESSION['sa_error'] = 'Slug is already in use by another tenant.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $update_slug = $pdo->prepare('UPDATE tenants SET tenant_slug = ? WHERE tenant_id = ?');
        $update_slug->execute([$new_slug, $tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_SLUG_UPDATED', 'tenant', ?, ?)");
        $log->execute([$_SESSION['super_admin_id'], "Tenant slug changed from {$old_slug} to {$new_slug}", $tenant_id]);

        $_SESSION['sa_flash'] = 'Tenant slug updated successfully.';
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'create_super_admin') {
        $sa_username = trim($_POST['sa_username'] ?? '');
        $sa_email = trim($_POST['sa_email'] ?? '');
        $sa_password = $_POST['sa_password'] ?? '';

        if ($sa_username === '' || $sa_email === '' || $sa_password === '') {
            $_SESSION['sa_error'] = 'All fields are required to create a super admin.';
        } else {
            $check = $pdo->prepare("SELECT user_id FROM users WHERE (email = ? OR username = ?) AND deleted_at IS NULL");
            $check->execute([$sa_email, $sa_username]);
            if ($check->fetch()) {
                $_SESSION['sa_error'] = 'Email or Username already exists.';
            } else {
                $hash = password_hash($sa_password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, user_type, status, role_id) VALUES (NULL, ?, ?, ?, 'Super Admin', 'Active', NULL)");
                $insert->execute([$sa_username, $sa_email, $hash]);

                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description) VALUES (?, 'SUPER_ADMIN_CREATED', 'user', ?)");
                $log->execute([$_SESSION['super_admin_id'], "Created new super admin account: {$sa_username}"]);

                $_SESSION['sa_flash'] = 'Super admin account created successfully.';
            }
        }
        header('Location: super_admin.php?section=settings');
        exit;
    }
}

// ============================================================
// PHP QUERIES FOR ALL SECTIONS
// ============================================================

// Dashboard stat cards
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$active_tenants = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Active' AND deleted_at IS NULL");
$active_users = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE user_type = 'Super Admin' AND status = 'Active' AND deleted_at IS NULL");
$active_super_admin_accounts = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Inactive' AND deleted_at IS NULL");
$inactive_users = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE request_type = 'tenant_application' AND status = 'Pending' AND deleted_at IS NULL");
$pending_applications = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COALESCE(SUM(mrr), 0) AS total_mrr FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$total_mrr = number_format((float) $stmt->fetch()['total_mrr'], 2);

$pdo->exec("CREATE TABLE IF NOT EXISTS tenant_legitimacy_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(50) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
)");

// Tenant Management: all tenants
$tenant_rows_stmt = $pdo->query("
    SELECT t.tenant_id, t.tenant_name, t.tenant_slug, t.company_address, t.status, t.request_type, t.plan_tier, t.mrr, t.created_at,
        owner.owner_username,
        owner.owner_first_name,
        owner.owner_last_name,
        owner.owner_email,
        owner.owner_phone,
           COALESCE(doc_summary.document_count, 0) AS legitimacy_document_count,
           doc_summary.document_paths AS legitimacy_document_paths
    FROM tenants t
    LEFT JOIN (
     SELECT u.tenant_id,
         MIN(u.user_id) AS owner_user_id,
         SUBSTRING_INDEX(GROUP_CONCAT(u.username ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_username,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.first_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_first_name,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.last_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_last_name,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.email, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_email,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.phone_number, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_phone
     FROM users u
    WHERE u.tenant_id IS NOT NULL AND u.deleted_at IS NULL
     GROUP BY u.tenant_id
    ) owner ON owner.tenant_id = t.tenant_id
    LEFT JOIN (
        SELECT tenant_id,
               COUNT(*) AS document_count,
               GROUP_CONCAT(file_path ORDER BY document_id SEPARATOR '||') AS document_paths
        FROM tenant_legitimacy_documents
        GROUP BY tenant_id
    ) doc_summary ON doc_summary.tenant_id = t.tenant_id
    WHERE t.deleted_at IS NULL
    ORDER BY t.created_at DESC
");
$tenant_rows = $tenant_rows_stmt->fetchAll();

// Audit Logs: initial 100 + distinct action types (Only for Super Admins)
$logs_stmt = $pdo->query("
    SELECT al.log_id, al.action_type, al.entity_type, al.entity_id,
           al.description, al.ip_address, al.created_at,
           u.username, u.email AS user_email,
           t.tenant_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    LEFT JOIN tenants t ON al.tenant_id = t.tenant_id
    WHERE u.user_type = 'Super Admin'
    ORDER BY al.log_id DESC LIMIT 100
");
$audit_logs = $logs_stmt->fetchAll();

$action_types_stmt = $pdo->query("
    SELECT DISTINCT al.action_type 
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id 
    WHERE u.user_type = 'Super Admin' 
    ORDER BY al.action_type
");
$action_types = $action_types_stmt->fetchAll(PDO::FETCH_COLUMN);

// Settings: Registered admin accounts
$super_admins_stmt = $pdo->query("
    SELECT user_id, username, email, first_name, last_name, created_at, last_login
    FROM users WHERE user_type = 'Super Admin' AND deleted_at IS NULL
    ORDER BY created_at DESC
");
$super_admins_list = $super_admins_stmt->fetchAll();

// Active tenants for filter dropdowns
$active_tenants_list_stmt = $pdo->query("SELECT tenant_id, tenant_name FROM tenants WHERE deleted_at IS NULL ORDER BY tenant_name");
$tenants_for_filter = $active_tenants_list_stmt->fetchAll();

// Recent 5 actual tenant applications for dashboard quick-glance
$recent_tenants_stmt = $pdo->query("
        SELECT tenant_name, status, plan_tier, created_at
        FROM tenants
        WHERE deleted_at IS NULL
            AND (request_type = 'tenant_application' OR request_type IS NULL)
        ORDER BY created_at DESC
        LIMIT 5
");
$recent_tenants = $recent_tenants_stmt->fetchAll();

// Recent 5 Talk to an Expert inquiries in a separate dashboard card
$recent_inquiries_stmt = $pdo->query("
        SELECT tenant_name, status, created_at
        FROM tenants
        WHERE deleted_at IS NULL
            AND request_type = 'talk_to_expert'
        ORDER BY created_at DESC
        LIMIT 5
");
$recent_inquiries = $recent_inquiries_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin Platform Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="super_admin.css">
</head>
<body>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-circle">
                    <span class="material-symbols-rounded" style="font-size: 24px;">public</span>
                </div>
                <div class="brand-text">
                    <h2>MicroFin</h2>
                    <span>Platform Admin</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Overview</div>
                <a href="#dashboard" class="nav-item active" data-target="dashboard">
                    <span class="material-symbols-rounded">space_dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="#sales" class="nav-item" data-target="sales">
                    <span class="material-symbols-rounded">point_of_sale</span>
                    <span>Revenue</span>
                </a>

                <div class="nav-section-label">Management</div>
                <a href="#tenants" class="nav-item" data-target="tenants">
                    <span class="material-symbols-rounded">domain</span>
                    <span>Tenants</span>
                </a>

                <div class="nav-section-label">System</div>
                <a href="#reports" class="nav-item" data-target="reports">
                    <span class="material-symbols-rounded">monitoring</span>
                    <span>Reports</span>
                </a>
                <a href="#backup" class="nav-item" data-target="backup">
                    <span class="material-symbols-rounded">cloud_upload</span>
                    <span>Backup</span>
                </a>
                <a href="#settings" class="nav-item" data-target="settings">
                    <span class="material-symbols-rounded">settings</span>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item">
                    <span class="material-symbols-rounded">logout</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1 id="page-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <button id="theme-toggle" class="icon-btn" title="Toggle Light/Dark Mode">
                        <span class="material-symbols-rounded"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
                    </button>
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=System+Host&background=0284c7&color=fff" alt="Admin Avatar" class="avatar">
                        <div class="admin-info">
                            <span class="admin-name"><?php echo htmlspecialchars($_SESSION['super_admin_username'] ?? 'Admin'); ?></span>
                            <span class="admin-role">Admin</span>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($provision_error !== ''): ?>
            <div class="site-alert site-alert-error" style="margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 500;">
                <?php echo htmlspecialchars($provision_error); ?>
            </div>
            <?php endif; ?>

            <?php if ($provision_success !== ''): ?>
            <div class="site-alert site-alert-success" style="margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: 8px; background: #dcfce7; color: #166534; font-weight: 500;">
                <?php echo htmlspecialchars($provision_success); ?>
            </div>
            <?php endif; ?>

            <!-- Views Container -->
            <div class="views-container">

                <!-- ============================================================ -->
                <!-- SECTION 1: DASHBOARD (Analytics) -->
                <!-- ============================================================ -->
                <section id="dashboard" class="view-section active">
                    <!-- Welcome Banner -->
                    <div class="welcome-banner">
                        <div class="welcome-text">
                            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['super_admin_username'] ?? 'Admin'); ?></h2>
                            <p>Here's what's happening across the platform today.</p>
                        </div>
                        <div class="welcome-actions">
                            <button class="btn btn-primary" onclick="document.querySelector('.nav-item[data-target=tenants]').click();">
                                <span class="material-symbols-rounded">manage_accounts</span> Manage Tenants
                            </button>
                        </div>
                    </div>

                    <!-- Stat Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon bg-blue">
                                <span class="material-symbols-rounded">corporate_fare</span>
                            </div>
                            <div class="stat-details">
                                <p>Active Tenants</p>
                                <h3 id="stat-active-tenants"><?php echo $active_tenants; ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-green">
                                <span class="material-symbols-rounded">group</span>
                            </div>
                            <div class="stat-details">
                                <p>Super Admin Accounts</p>
                                <h3 id="stat-super-admin-accounts"><?php echo $active_super_admin_accounts; ?></h3>
                            </div>
                        </div>
                        <div class="stat-card stat-card-alert <?php echo $pending_applications > 0 ? 'has-pending' : ''; ?>">
                            <div class="stat-icon bg-amber">
                                <span class="material-symbols-rounded">pending_actions</span>
                            </div>
                            <div class="stat-details">
                                <p>Pending Applications</p>
                                <h3 id="stat-pending-apps"><?php echo $pending_applications; ?></h3>
                            </div>
                            <?php if ($pending_applications > 0): ?>
                            <a href="#tenants" class="stat-link" onclick="document.querySelector('.nav-item[data-target=tenants]').click(); setTimeout(()=>document.querySelector('.tenant-intake-tab[data-view=applications]').click(), 100);">
                                Review <span class="material-symbols-rounded" style="font-size:16px;">arrow_forward</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-purple">
                                <span class="material-symbols-rounded">payments</span>
                            </div>
                            <div class="stat-details">
                                <p>Monthly MRR</p>
                                <h3 id="stat-total-mrr">₱<?php echo $total_mrr; ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Bottom Grid: Charts + Recent Tenants -->
                    <div class="dashboard-bottom-grid">
                        <!-- Charts Column -->
                        <div class="dashboard-charts-col">
                            <div class="card" style="margin-bottom: 24px;">
                                <h3>Audit Trail</h3>
                                <div class="filter-row" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                                    <div class="form-group">
                                        <label>Action Type</label>
                                        <select id="audit-action-filter" class="form-control">
                                            <option value="">All Actions</option>
                                            <?php foreach ($action_types as $at): ?>
                                            <option value="<?php echo htmlspecialchars($at); ?>"><?php echo htmlspecialchars($at); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tenant</label>
                                        <select id="audit-tenant-filter" class="form-control">
                                            <option value="">All Tenants</option>
                                            <?php foreach ($tenants_for_filter as $tf): ?>
                                            <option value="<?php echo htmlspecialchars($tf['tenant_id']); ?>"><?php echo htmlspecialchars($tf['tenant_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Date From</label>
                                        <input type="date" id="audit-date-from" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Date To</label>
                                        <input type="date" id="audit-date-to" class="form-control">
                                    </div>
                                    <div class="form-group" style="align-self: flex-end;">
                                        <button class="btn btn-primary" id="btn-apply-audit-filter">
                                            <span class="material-symbols-rounded">filter_alt</span> Filter
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive audit-table-wrap">
                                    <table class="admin-table" id="audit-logs-table">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Username</th>
                                                <th>User / Email</th>
                                                <th>Tenant</th>
                                                <th>Action</th>
                                                <th>Entity</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($audit_logs) === 0): ?>
                                            <tr><td colspan="7" class="text-muted" style="text-align:center; padding:2rem;">No audit logs available.</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($audit_logs as $log): ?>
                                            <tr>
                                                <td><small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                <td><span style="font-family: monospace;"><?php echo htmlspecialchars($log['username'] ?? '—'); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['user_email'] ?? 'System'); ?></td>
                                                <td><?php echo htmlspecialchars($log['tenant_name'] ?? 'Platform'); ?></td>
                                                <td><span class="badge badge-blue"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['entity_type'] ?? '—'); ?></td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline btn-sm audit-detail-btn"
                                                        data-created-at="<?php echo htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-username="<?php echo htmlspecialchars((string)($log['username'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-user-email="<?php echo htmlspecialchars((string)($log['user_email'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-tenant-name="<?php echo htmlspecialchars((string)($log['tenant_name'] ?? 'Platform'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-action-type="<?php echo htmlspecialchars((string)($log['action_type'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-entity-type="<?php echo htmlspecialchars((string)($log['entity_type'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-description="<?php echo htmlspecialchars((string)($log['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <span class="material-symbols-rounded" style="font-size:16px;">visibility</span> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="charts-grid">
                                <div class="card">
                                    <div class="card-header-flex" style="margin-bottom: 12px;">
                                        <h3 style="margin: 0;">User Growth</h3>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <input type="date" id="user-growth-date-from" class="form-control" style="width: 150px;">
                                            <input type="date" id="user-growth-date-to" class="form-control" style="width: 150px;">
                                            <button type="button" id="btn-apply-user-growth-filter" class="btn btn-outline btn-sm">Apply</button>
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="chart-user-growth"></canvas>
                                    </div>
                                </div>
                                <div class="card">
                                    <h3>Tenant Activity (Status Breakdown)</h3>
                                    <div class="chart-container">
                                        <canvas id="chart-tenant-activity"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Tenants Sidebar -->
                        <div class="dashboard-sidebar-col">
                            <div class="card">
                                <div class="card-header-flex" style="margin-bottom: 16px;">
                                    <h3 style="margin-bottom: 0;">Recent Tenants</h3>
                                    <a href="#tenants" class="btn-text" onclick="document.querySelector('.nav-item[data-target=tenants]').click();">View All</a>
                                </div>
                                <div class="recent-tenants-list">
                                    <?php if (count($recent_tenants) === 0): ?>
                                    <div class="empty-state-mini">
                                        <span class="material-symbols-rounded">domain_add</span>
                                        <p>No tenants yet</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_tenants as $rt): ?>
                                    <div class="recent-tenant-item">
                                        <div class="recent-tenant-avatar">
                                            <?php echo strtoupper(substr($rt['tenant_name'], 0, 2)); ?>
                                        </div>
                                        <div class="recent-tenant-info">
                                            <span class="recent-tenant-name"><?php echo htmlspecialchars($rt['tenant_name']); ?></span>
                                            <span class="recent-tenant-meta">
                                                <?php echo htmlspecialchars($rt['plan_tier'] ?? 'Starter'); ?> &middot;
                                                <?php echo date('M d', strtotime($rt['created_at'])); ?>
                                            </span>
                                        </div>
                                        <?php
                                        $rt_status = $rt['status'];
                                        $rt_badge = '';
                                        switch ($rt_status) {
                                            case 'Active': $rt_badge = 'badge-green'; break;
                                            case 'Pending': $rt_badge = 'badge-amber'; break;
                                            case 'New': $rt_badge = 'badge-blue'; break;
                                            case 'In Contact': $rt_badge = 'badge-blue'; break;
                                            case 'Suspended': $rt_badge = 'badge-red'; break;
                                            default: $rt_badge = ''; break;
                                        }
                                        ?>
                                        <span class="badge badge-sm <?php echo $rt_badge; ?>"><?php echo htmlspecialchars($rt_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header-flex" style="margin-bottom: 16px;">
                                    <h3 style="margin-bottom: 0;">Recent Inquiries</h3>
                                    <a href="#tenants" class="btn-text" onclick="document.querySelector('.nav-item[data-target=tenants]').click(); setTimeout(()=>document.querySelector('.tenant-intake-tab[data-view=inquiries]').click(), 100);">View Inquiries</a>
                                </div>
                                <div class="recent-tenants-list">
                                    <?php if (count($recent_inquiries) === 0): ?>
                                    <div class="empty-state-mini">
                                        <span class="material-symbols-rounded">support_agent</span>
                                        <p>No inquiries yet</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_inquiries as $ri): ?>
                                    <div class="recent-tenant-item">
                                        <div class="recent-tenant-avatar">
                                            <?php echo strtoupper(substr($ri['tenant_name'], 0, 2)); ?>
                                        </div>
                                        <div class="recent-tenant-info">
                                            <span class="recent-tenant-name"><?php echo htmlspecialchars($ri['tenant_name']); ?></span>
                                            <span class="recent-tenant-meta">
                                                Talk to Expert &middot; <?php echo date('M d', strtotime($ri['created_at'])); ?>
                                            </span>
                                        </div>
                                        <?php
                                        $ri_status_raw = (string)($ri['status'] ?? 'Pending');
                                        if (in_array($ri_status_raw, ['Pending', 'New'], true)) {
                                            $ri_status = 'New';
                                            $ri_badge = 'badge-blue';
                                        } elseif (in_array($ri_status_raw, ['Contacted', 'In Contact'], true)) {
                                            $ri_status = 'In Contact';
                                            $ri_badge = 'badge-amber';
                                        } else {
                                            $ri_status = 'Closed';
                                            $ri_badge = 'badge-red';
                                        }
                                        ?>
                                        <span class="badge badge-sm <?php echo $ri_badge; ?>"><?php echo htmlspecialchars($ri_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 2: TENANT MANAGEMENT -->
                <!-- ============================================================ -->
                <section id="tenants" class="view-section">
                    <div class="settings-tabs" style="margin-bottom: 16px;">
                        <button class="settings-tab tenant-intake-tab active" data-view="tenants">Tenants</button>
                        <button class="settings-tab tenant-intake-tab" data-view="applications">Applications</button>
                        <button class="settings-tab tenant-intake-tab" data-view="inquiries">Inquiries</button>
                    </div>
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Tenant Management</h3>
                                <p class="text-muted">Manage all tenant organizations and inquiries.</p>
                            </div>
                            <div class="actions-flex">
                                <select id="tenant-status-filter" class="form-control" style="width: 200px;">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                <select id="application-status-filter" class="form-control" style="width: 200px; display: none;">
                                    <option value="all">All Application Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <select id="inquiry-status-filter" class="form-control" style="width: 200px; display: none;">
                                    <option value="all">All Inquiry Statuses</option>
                                    <option value="new">New</option>
                                    <option value="in_contact">In Contact</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <div class="search-box">
                                    <span class="material-symbols-rounded">search</span>
                                    <input type="text" id="tenant-search" placeholder="Search tenants...">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="tenants-table">
                                <thead>
                                    <tr>
                                        <th>Tenant Name</th>
                                        <th>Owner / Contact</th>
                                        <th>Status</th>
                                        <th>Plan</th>
                                        <th>MRR</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($tenant_rows) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                            <span class="material-symbols-rounded" style="font-size: 48px; display: block; margin-bottom: 0.5rem;">domain_add</span>
                                            No tenants found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($tenant_rows as $t): ?>
                                    <tr data-status="<?php echo htmlspecialchars($t['status']); ?>" data-request-type="<?php echo htmlspecialchars($t['request_type'] ?? 'tenant_application'); ?>">
                                        <td>
                                            <?php echo htmlspecialchars($t['tenant_name']); ?><br>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($t['tenant_id'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $owner_name = trim((string)($t['owner_first_name'] ?? '') . ' ' . (string)($t['owner_last_name'] ?? ''));
                                            $owner_username = trim((string)($t['owner_username'] ?? ''));
                                            echo htmlspecialchars($owner_name !== '' ? $owner_name : ($owner_username !== '' ? $owner_username : '—'));
                                            ?><br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($owner_username !== '' ? $owner_username : '—'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['owner_email'] ?? '—'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['owner_phone'] ?? '—'); ?></small>
                                            <br>
                                            <?php $doc_count = (int)($t['legitimacy_document_count'] ?? 0); ?>
                                            <?php if ($doc_count > 0): ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $t['status'];
                                            $request_type = (string)($t['request_type'] ?? 'tenant_application');
                                            $badge_class = '';
                                            $badge_style = '';
                                            $normalized_status = $status;
                                            if ($request_type === 'talk_to_expert') {
                                                if (in_array($status, ['Pending', 'New'], true)) {
                                                    $normalized_status = 'New';
                                                } elseif (in_array($status, ['Contacted', 'In Contact'], true)) {
                                                    $normalized_status = 'In Contact';
                                                } else {
                                                    $normalized_status = 'Closed';
                                                }
                                            } else {
                                                if (in_array($status, ['Active'], true)) {
                                                    $normalized_status = 'Active';
                                                } elseif (in_array($status, ['Suspended'], true)) {
                                                    $normalized_status = 'Suspended';
                                                } elseif (in_array($status, ['Rejected'], true)) {
                                                    $normalized_status = 'Rejected';
                                                } else {
                                                    $normalized_status = 'Pending';
                                                }
                                            }

                                            switch ($normalized_status) {
                                                case 'Active':
                                                    $badge_class = 'badge-green';
                                                    break;
                                                case 'Suspended':
                                                    $badge_style = 'background:#fee2e2; color:#b91c1c;';
                                                    break;
                                                case 'Rejected':
                                                    $badge_style = 'background:#fee2e2; color:#991b1b;';
                                                    break;
                                                case 'New':
                                                    $badge_style = 'background:#dbeafe; color:#1e3a8a;';
                                                    break;
                                                case 'In Contact':
                                                    $badge_style = 'background:#fef3c7; color:#b45309;';
                                                    break;
                                                case 'Closed':
                                                    $badge_style = 'background:#e5e7eb; color:#374151;';
                                                    break;
                                                default:
                                                    $badge_style = 'background:#fef08a; color:#b45309;';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>" <?php if ($badge_style) echo "style=\"{$badge_style}\""; ?>>
                                                <?php echo htmlspecialchars($normalized_status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['plan_tier'] ?? '—'); ?></td>
                                        <td>₱<?php echo number_format((float)($t['mrr'] ?? 0), 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <div style="display:flex; gap:0.5rem; flex-wrap: wrap;">
                                                <?php
                                                $doc_paths = [];
                                                if (!empty($t['legitimacy_document_paths'])) {
                                                    $doc_paths = array_filter(explode('||', $t['legitimacy_document_paths']));
                                                }
                                                foreach ($doc_paths as $doc_index => $doc_path):
                                                ?>
                                                    <a href="<?php echo htmlspecialchars($doc_path); ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener" title="View legitimacy document <?php echo $doc_index + 1; ?>">
                                                        <span class="material-symbols-rounded" style="font-size:16px;">description</span> Doc <?php echo $doc_index + 1; ?>
                                                    </a>
                                                <?php endforeach; ?>



                                                <?php if (($t['request_type'] ?? 'tenant_application') === 'talk_to_expert' && in_array($status, ['Pending', 'Contacted', 'New', 'In Contact'])): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="send_talk_email">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Send Consultation Email">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">mail</span> Email
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="close_inquiry">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Close Inquiry">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">task_alt</span> Close
                                                        </button>
                                                    </form>

                                                <?php elseif ($status === 'Pending'): ?>
                                                    <!-- Provision: opens provision modal -->
                                                    <?php
                                                    $startup_username = trim((string)($t['owner_username'] ?? ''));
                                                    $startup_user_label = $startup_username !== '' ? ('@' . $startup_username) : 'No startup user yet';
                                                    ?>
                                                    <button class="btn btn-sm btn-provision-from-demo"
                                                        style="background:#10b981; color:#fff; border:none; border-radius:10px; padding:6px 12px; font-weight:600; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 4px rgba(16,185,129,0.3);"
                                                        data-tenant-name="<?php echo htmlspecialchars($t['tenant_name']); ?>"
                                                        data-company-email="<?php echo htmlspecialchars($t['owner_email'] ?? ''); ?>"
                                                        data-plan-tier="<?php echo htmlspecialchars($t['plan_tier'] ?? 'Starter'); ?>"
                                                        data-request-type="<?php echo htmlspecialchars($t['request_type'] ?? 'tenant_application'); ?>"
                                                        data-first-name="<?php echo htmlspecialchars($t['owner_first_name'] ?? ''); ?>"
                                                        data-last-name="<?php echo htmlspecialchars($t['owner_last_name'] ?? ''); ?>"
                                                        data-mi=""
                                                        data-suffix=""
                                                        data-company-address="<?php echo htmlspecialchars($t['company_address'] ?? ''); ?>"
                                                        title="Provision Tenant (Startup User: <?php echo htmlspecialchars($startup_user_label); ?>)">
                                                        <span class="material-symbols-rounded" style="font-size:16px;">rocket_launch</span> Provision <?php echo htmlspecialchars($startup_user_label); ?>
                                                    </button>
                                                    <!-- Reject -->
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="reject_tenant">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#b91c1c; border-color:#fca5a5;" title="Reject">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">close</span> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($status === 'Active'): ?>
                                                    <!-- Impersonate -->
                                                    <a href="../tenant_login/login.php?s=<?php echo urlencode($t['tenant_slug']); ?>&impersonate=1" class="btn btn-outline btn-sm" target="_blank" title="Impersonate Tenant">
                                                        <span class="material-symbols-rounded" style="font-size:16px;">login</span>
                                                    </a>
                                                    <!-- Suspend -->
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <input type="hidden" name="new_status" value="Suspended">
                                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#b91c1c; border-color:#fca5a5;" title="Suspend Tenant">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">block</span>
                                                        </button>
                                                    </form>
                                                <?php elseif ($status === 'Suspended'): ?>
                                                    <!-- Reactivate -->
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <input type="hidden" name="new_status" value="Active">
                                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#166534; border-color:#86efac;" title="Reactivate Tenant">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> Reactivate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 3: REPORTS -->
                <!-- ============================================================ -->
                <section id="reports" class="view-section">
                    <div class="card filter-bar">
                        <div class="filter-row">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" id="report-date-from" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" id="report-date-to" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Tenant</label>
                                <select id="report-tenant-filter" class="form-control">
                                    <option value="">All Tenants</option>
                                    <?php foreach ($tenants_for_filter as $tf): ?>
                                    <option value="<?php echo htmlspecialchars($tf['tenant_id']); ?>"><?php echo htmlspecialchars($tf['tenant_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="align-self: flex-end;">
                                <button class="btn btn-primary" id="btn-apply-report-filter">
                                    <span class="material-symbols-rounded">filter_alt</span> Apply Filter
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tenant Activity Report -->
                    <div class="card">
                        <h3>Tenant Activity Report</h3>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin: 0 0 12px 0;">
                            <span class="badge badge-amber">Pending Application (Excluded)</span>
                            <span class="badge badge-green">Active</span>
                            <span class="badge badge-red">Inactive</span>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="report-tenant-activity">
                                <thead>
                                    <tr>
                                        <th>Tenant Name</th>
                                        <th>Status</th>
                                        <th>Legend</th>
                                        <th>Plan</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Click "Apply Filter" to load report data.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 4: SALES REPORT -->
                <!-- ============================================================ -->
                <section id="sales" class="view-section">
                    <!-- Revenue Overview (Consolidated) -->
                    <div class="stats-grid" style="grid-template-columns: repeat(1, 1fr); margin-bottom: 24px;">
                        <div class="stat-card">
                            <div class="stat-icon bg-purple">
                                <span class="material-symbols-rounded">payments</span>
                            </div>
                            <div class="stat-details">
                                <p>Total MRR</p>
                                <h3>₱<?php echo htmlspecialchars($total_mrr); ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tenants + Revenue Chart -->
                    <div class="dashboard-grid-2">
                        <div class="card">
                            <h3>Top Performing Tenants</h3>
                            <div class="table-responsive">
                                <table class="admin-table" id="top-tenants-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tenant</th>
                                            <th>Plan</th>
                                            <th>Revenue</th>
                                            <th>Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Click "Apply" to load sales data.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h3 style="margin: 0;">Monthly Revenue</h3>
                                <select id="revenue-period-filter" class="form-control" style="width: auto;">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="chart-container">
                                <canvas id="chart-revenue"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card">
                        <h3>Transaction History</h3>
                        <p class="text-muted" style="margin-bottom: 16px;">Payment history is consolidated here.</p>
                        <div class="table-responsive">
                            <table class="admin-table" id="sales-transactions-table">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Tenant</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">Click "Apply" to load transaction data.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>



                <!-- ============================================================ -->
                <!-- SECTION 6: BACKUP (Coming Soon) -->
                <!-- ============================================================ -->
                <section id="backup" class="view-section">
                    <div class="card">
                        <h3>Backup & Restore</h3>
                        <p class="text-muted">Database backups, restore points, and recovery management.</p>
                        <div class="placeholder-box" style="height: 300px;">
                            <span class="material-symbols-rounded" style="font-size: 64px; color: var(--text-muted);">cloud_upload</span>
                            <h3 style="color: var(--text-muted); margin: 0; font-size: 1.1rem;">Coming Soon</h3>
                            <p class="text-muted" style="max-width: 400px; text-align: center;">This feature is under development. Backup history, stored files, and restore points will appear here.</p>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 7: SETTINGS -->
                <!-- ============================================================ -->
                <section id="settings" class="view-section">
                    <!-- Settings sub-tabs -->
                    <div class="settings-tabs">
                        <button class="settings-tab active" data-settings-target="settings-limits">Tenant Limits</button>
                        <button class="settings-tab" data-settings-target="settings-accounts">Super Admin Accounts</button>
                    </div>

                    <!-- Sub-section: Tenant Limits -->
                    <div id="settings-limits" class="settings-panel active">
                        <div class="card">
                            <h3>Default Tenant Limits by Plan Tier</h3>
                            <p class="text-muted" style="margin-bottom: 24px;">Default resource limits applied when provisioning new tenants.</p>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Plan Tier</th>
                                            <th>Max Clients</th>
                                            <th>Max Users</th>
                                            <th>Storage (GB)</th>
                                            <th>MRR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981;">Starter</span></td>
                                            <td>1,000</td>
                                            <td>250</td>
                                            <td>5.00 GB</td>
                                            <td>₱4,999.00</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge" style="background:rgba(59,130,246,0.15); color:#3b82f6;">Growth</span></td>
                                            <td>2,500</td>
                                            <td>750</td>
                                            <td>10.00 GB</td>
                                            <td>₱9,999.00</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-blue">Pro</span></td>
                                            <td>5,000</td>
                                            <td>2,000</td>
                                            <td>25.00 GB</td>
                                            <td>₱14,999.00</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-purple">Enterprise</span></td>
                                            <td>10,000</td>
                                            <td>5,000</td>
                                            <td>50.00 GB</td>
                                            <td>₱22,999.00</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge" style="background:rgba(244,114,182,0.15); color:#db2777;">Unlimited</span></td>
                                            <td>Unlimited</td>
                                            <td>Unlimited</td>
                                            <td>Unlimited</td>
                                            <td>₱29,999.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-section: Super Admin Accounts -->
                    <div id="settings-accounts" class="settings-panel">
                        <div class="card">
                            <div class="card-header-flex mb-4">
                                <div>
                                    <h3>Super Admin Accounts</h3>
                                    <p class="text-muted">Master platform accounts with full administrative access.</p>
                                </div>
                                <button class="btn btn-primary" id="btn-create-super-admin">
                                    <span class="material-symbols-rounded">person_add</span> Create New Admin
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email Address</th>
                                            <th>Last Login</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($super_admins_list) === 0): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                                No system accounts found.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($super_admins_list as $admin): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($admin['user_id']); ?></td>
                                            <td style="font-weight: 500;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div class="activity-icon bg-blue" style="width: 28px; height: 28px; min-width: 28px;">
                                                        <span class="material-symbols-rounded" style="font-size: 16px;">person</span>
                                                    </div>
                                                    <?php echo htmlspecialchars($admin['username']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>"><?php echo htmlspecialchars($admin['email']); ?></a>
                                            </td>
                                            <td><?php echo $admin['last_login'] ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>


    <!-- Create Super Admin Modal -->
    <div id="modal-sa-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Create Super Admin</h2>
                <button class="icon-btn" id="close-sa-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="create_super_admin">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="sa_username" placeholder="e.g. jdelacruz" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" name="sa_email" placeholder="superadmin@microfin.com" required>
                </div>
                <div class="form-group">
                    <label>Temporary Password</label>
                    <input type="password" class="form-control" name="sa_password" required>
                    <small class="text-muted">Please provide this password to the new admin securely.</small>
                </div>
                <div class="modal-footer" style="margin-top:24px;">
                    <button type="button" class="btn btn-outline" id="cancel-sa-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Create Tenant Modal Wizard -->
    <div id="modal-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Provision New Tenant</h2>
                <button class="icon-btn" id="close-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="provision_tenant">
                <input type="hidden" name="request_type" value="tenant_application">
                <div class="form-group">
                    <label>Company / Institution Name</label>
                    <input type="text" class="form-control" name="tenant_name" placeholder="e.g. Village Microfinance" required>
                    <small class="text-muted">The system auto-generates a unique 10-character tenant ID.</small>
                </div>
                <div class="form-group">
                    <label>Custom Site Slug (Optional)</label>
                    <input type="text" class="form-control" name="custom_slug" placeholder="e.g. village-microfinance">
                    <small class="text-muted">Used in login URL: .../login.php?s=<strong>slug</strong>. Tenant ID is system-generated and immutable.</small>
                </div>
                <input type="hidden" name="first_name" value="">
                <input type="hidden" name="last_name" value="">
                <input type="hidden" name="mi" value="">
                <input type="hidden" name="suffix" value="">
                <div class="form-group row-2">
                    <div>
                        <label>Tenant CEO / Primary Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" placeholder="ceo@village.com" required>
                        <small class="text-muted">A secure, private login link will be emailed to this address.</small>
                    </div>
                    <div>
                        <label>Company Address</label>
                        <input type="text" class="form-control" name="company_address" placeholder="e.g. Marilao, Bulacan">
                    </div>
                    <div>
                        <label>Plan Tier</label>
                        <select class="form-control" name="plan_tier">
                            <option value="Starter">Starter (₱4,999/mo)</option>
                            <option value="Growth">Growth (₱9,999/mo)</option>
                            <option value="Pro">Pro (₱14,999/mo)</option>
                            <option value="Enterprise">Enterprise (₱22,999/mo)</option>
                            <option value="Unlimited">Unlimited (₱29,999/mo)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-tenant"><span class="material-symbols-rounded">rocket_launch</span> Provision Instance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Details Modal -->
    <div id="modal-audit-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Audit Log Details</h2>
                <button class="icon-btn" id="close-audit-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Timestamp</label>
                    <input type="text" id="audit-detail-created-at" class="form-control" readonly>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Username</label>
                        <input type="text" id="audit-detail-username" class="form-control" readonly>
                    </div>
                    <div>
                        <label>User / Email</label>
                        <input type="text" id="audit-detail-user-email" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Tenant</label>
                        <input type="text" id="audit-detail-tenant-name" class="form-control" readonly>
                    </div>
                    <div>
                        <label>Action</label>
                        <input type="text" id="audit-detail-action-type" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Entity</label>
                    <input type="text" id="audit-detail-entity-type" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="audit-detail-description" class="form-control" rows="6" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="close-audit-modal-footer">Close</button>
            </div>
        </div>
    </div>

    <script src="super_admin.js"></script>
    <script>
        document.querySelectorAll('.site-alert').forEach(function(el) {
            setTimeout(function() {
                el.style.transition = 'opacity 0.4s ease, margin 0.4s ease, padding 0.4s ease, max-height 0.4s ease';
                el.style.opacity = '0';
                el.style.maxHeight = el.offsetHeight + 'px';
                requestAnimationFrame(function() {
                    el.style.maxHeight = '0';
                    el.style.padding = '0 1rem';
                    el.style.margin = '0 2rem';
                });
                setTimeout(function() { el.remove(); }, 450);
            }, 5000);
        });
    </script>
</body>
</html>
