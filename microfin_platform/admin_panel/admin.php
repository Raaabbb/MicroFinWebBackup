<?php
require_once "../vendor/PHPMailer/src/Exception.php";
require_once "../vendor/PHPMailer/src/PHPMailer.php";
require_once "../vendor/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || empty($_SESSION['tenant_id'])) {
    http_response_code(403);
    die("<h1>403 Forbidden - Access Denied</h1><p>No valid tenant session could be identified. Please log in using your company's designated login link.</p>");
}

require_once '../backend/db_connect.php';

$tenant_id = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'] ?? 'Company Admin';
$role_name = $_SESSION['role'] ?? 'User';
$ui_theme = (($_SESSION['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

// Check if user still needs to change their password (e.g. closed browser during force change)
$user_id_check = $_SESSION['user_id'] ?? 0;
if ($user_id_check > 0) {
    $fpc_stmt = $pdo->prepare('SELECT force_password_change, ui_theme FROM users WHERE user_id = ?');
    $fpc_stmt->execute([$user_id_check]);
    $fpc_row = $fpc_stmt->fetch(PDO::FETCH_ASSOC);
    if ($fpc_row && isset($fpc_row['ui_theme'])) {
        $ui_theme = ($fpc_row['ui_theme'] === 'dark') ? 'dark' : 'light';
        $_SESSION['ui_theme'] = $ui_theme;
    }
    if ($fpc_row && (bool)$fpc_row['force_password_change']) {
        header('Location: ../tenant_login/force_change_password.php');
        exit;
    }
}

// Check if setup is completed. If not, redirect to setup wizard.
// For impersonating Super Admins, allow bypassing or handle it based on setup_completed.
$tenant_stmt = $pdo->prepare('SELECT setup_completed, setup_current_step FROM tenants WHERE tenant_id = ?');
$tenant_stmt->execute([$tenant_id]);
$tenant_data = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant_data && !(bool)$tenant_data['setup_completed']) {
    $setup_step = (int)($tenant_data['setup_current_step'] ?? 0);
    if ($setup_step < 6) {
        $setup_pages = [
            0 => '../tenant_login/force_change_password.php',
            1 => '../tenant_login/setup_loan_products.php',
            2 => '../tenant_login/setup_credit.php',
            3 => '../tenant_login/setup_website.php',
            4 => '../tenant_login/setup_branding.php',
            5 => '../tenant_login/setup_billing.php',
        ];
        header('Location: ' . ($setup_pages[$setup_step] ?? '../tenant_login/force_change_password.php'));
        exit;
    }
}

$default_settings = [
    'company_name' => $tenant_name,
    'primary_color' => '#4f46e5',
    'text_main' => '#0f172a',
    'text_muted' => '#64748b',
    'bg_body' => '#f8fafc',
    'bg_card' => '#ffffff',
    'font_family' => 'Inter',
    'logo_path' => '',
    'support_email' => '',
    'support_phone' => ''
];

$default_toggles = [
    'booking_system' => 0,
    'user_registration' => 0,
    'maintenance_mode' => 0,
    'email_notifications' => 0,
    'public_website_enabled' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $settings = [
        'company_name' => trim($_POST['company_name'] ?? $default_settings['company_name']),
        'primary_color' => trim($_POST['primary_color'] ?? $default_settings['primary_color']),
        'text_main' => trim($_POST['text_main'] ?? $default_settings['text_main']),
        'text_muted' => trim($_POST['text_muted'] ?? $default_settings['text_muted']),
        'bg_body' => trim($_POST['bg_body'] ?? $default_settings['bg_body']),
        'bg_card' => trim($_POST['bg_card'] ?? $default_settings['bg_card']),
        'font_family' => trim($_POST['font_family'] ?? $default_settings['font_family']),
        'logo_path' => trim($_POST['logo_path'] ?? ''),
        'support_email' => trim($_POST['support_email'] ?? ''),
        'support_phone' => trim($_POST['support_phone'] ?? '')
    ];

    $hex_pattern = '/^#[0-9a-fA-F]{6}$/';
    foreach (['primary_color', 'text_main', 'text_muted', 'bg_body', 'bg_card'] as $ck) {
        if (!preg_match($hex_pattern, $settings[$ck])) {
            $settings[$ck] = $default_settings[$ck];
        }
    }

    $allowed_fonts = ['Inter', 'Poppins', 'Outfit', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Montserrat', 'DM Sans', 'Plus Jakarta Sans'];
    if (!in_array($settings['font_family'], $allowed_fonts, true)) {
        $settings['font_family'] = 'Inter';
    }

    if ($settings['company_name'] === '') {
        $settings['company_name'] = $default_settings['company_name'];
    }

    $toggles = [
        'booking_system' => isset($_POST['toggle_booking_system']) ? 1 : 0,
        'user_registration' => isset($_POST['toggle_user_registration']) ? 1 : 0,
        'maintenance_mode' => isset($_POST['toggle_maintenance_mode']) ? 1 : 0,
        'email_notifications' => isset($_POST['toggle_email_notifications']) ? 1 : 0,
        'public_website_enabled' => isset($_POST['toggle_public_website_enabled']) ? 1 : 0
    ];

    $upsert_setting = $pdo->prepare('INSERT INTO system_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($settings as $key => $value) {
        // Skip keys that belong in the tenants table
        if (in_array($key, ['company_name', 'primary_color', 'text_main', 'text_muted', 'bg_body', 'bg_card', 'font_family', 'logo_path'])) {
            continue;
        }
        $upsert_setting->execute([$tenant_id, $key, $value]);
    }
    
    // Update tenants table for name, and tenant_branding for colors/logo/font
    $update_tenant = $pdo->prepare('UPDATE tenants SET tenant_name = ? WHERE tenant_id = ?');
    $update_tenant->execute([
        $settings['company_name'],
        $tenant_id
    ]);

    $upsert_branding = $pdo->prepare('INSERT INTO tenant_branding (tenant_id, theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE theme_primary_color = VALUES(theme_primary_color), theme_text_main = VALUES(theme_text_main), theme_text_muted = VALUES(theme_text_muted), theme_bg_body = VALUES(theme_bg_body), theme_bg_card = VALUES(theme_bg_card), font_family = VALUES(font_family), logo_path = VALUES(logo_path)');
    $upsert_branding->execute([
        $tenant_id,
        $settings['primary_color'],
        $settings['text_main'],
        $settings['text_muted'],
        $settings['bg_body'],
        $settings['bg_card'],
        $settings['font_family'],
        $settings['logo_path']
    ]);

    $upsert_toggle = $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)');
    foreach ($toggles as $key => $value) {
        $upsert_toggle->execute([$tenant_id, $key, $value]);
    }

    $_SESSION['tenant_name'] = $settings['company_name'];
    $_SESSION['theme'] = $settings['primary_color'];
    $_SESSION['admin_flash'] = 'Settings saved successfully.';

    header('Location: admin.php');
    exit;
}

// ==========================================
// POST Handler — Save Website Content
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_website_content') {
    $layout = 'template1'; // Template 2/3 are temporarily unavailable.

    $hero_title       = trim($_POST['hero_title'] ?? '');
    $hero_subtitle    = trim($_POST['hero_subtitle'] ?? '');
    $hero_description = trim($_POST['hero_description'] ?? '');
    $hero_cta_text    = trim($_POST['hero_cta_text'] ?? 'Learn More');
    $hero_cta_url     = trim($_POST['hero_cta_url'] ?? '#about');
    $hero_image_path  = trim($_POST['hero_image_path'] ?? '');
    $hero_badge_text  = trim($_POST['hero_badge_text'] ?? '');

    $about_heading    = trim($_POST['about_heading'] ?? 'About Us');
    $about_body       = trim($_POST['about_body'] ?? '');
    $about_image_path = trim($_POST['about_image_path'] ?? '');

    $services_heading = trim($_POST['services_heading'] ?? 'Our Services');
    $svc_titles = $_POST['service_title'] ?? [];
    $svc_descs  = $_POST['service_description'] ?? [];
    $svc_icons  = $_POST['service_icon'] ?? [];
    $services_arr = [];
    if (is_array($svc_titles)) {
        for ($i = 0; $i < count($svc_titles); $i++) {
            if (trim($svc_titles[$i]) !== '') {
                $services_arr[] = [
                    'title'       => trim($svc_titles[$i]),
                    'description' => trim($svc_descs[$i] ?? ''),
                    'icon'        => trim($svc_icons[$i] ?? 'star')
                ];
            }
        }
    }
    $services_json = json_encode($services_arr, JSON_UNESCAPED_UNICODE);

    // Stats section
    $stats_heading = trim($_POST['stats_heading'] ?? '');
    $stats_subheading = trim($_POST['stats_subheading'] ?? '');
    $stats_image_path = trim($_POST['stats_image_path'] ?? '');
    $stat_values = $_POST['stat_value'] ?? [];
    $stat_labels = $_POST['stat_label'] ?? [];
    $stats_arr = [];
    if (is_array($stat_values)) {
        for ($i = 0; $i < count($stat_values); $i++) {
            if (trim($stat_values[$i] ?? '') !== '' || trim($stat_labels[$i] ?? '') !== '') {
                $stats_arr[] = [
                    'value' => trim($stat_values[$i] ?? ''),
                    'label' => trim($stat_labels[$i] ?? '')
                ];
            }
        }
    }
    $stats_json = json_encode($stats_arr, JSON_UNESCAPED_UNICODE);

    $contact_address  = trim($_POST['contact_address'] ?? '');
    $contact_phone    = trim($_POST['contact_phone'] ?? '');
    $contact_email    = trim($_POST['contact_email'] ?? '');
    $contact_hours    = trim($_POST['contact_hours'] ?? '');
    $footer_description = trim($_POST['footer_description'] ?? '');
    $custom_css       = trim($_POST['custom_css'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    $website_config_post = [
        'website_show_about' => isset($_POST['website_show_about']) ? '1' : '0',
        'website_show_services' => isset($_POST['website_show_services']) ? '1' : '0',
        'website_show_contact' => isset($_POST['website_show_contact']) ? '1' : '0',
        'website_show_download' => isset($_POST['website_show_download']) ? '1' : '0',
        'website_show_stats' => isset($_POST['website_show_stats']) ? '1' : '0',
        'website_show_loan_calc' => isset($_POST['website_show_loan_calc']) ? '1' : '0',
        'website_show_partners' => isset($_POST['website_show_partners']) ? '1' : '0',
        'website_partners_json' => trim($_POST['website_partners_json'] ?? '[]'),
        'website_download_title' => trim($_POST['website_download_title'] ?? 'Download Our App'),
        'website_download_description' => trim($_POST['website_download_description'] ?? ''),
        'website_download_button_text' => trim($_POST['website_download_button_text'] ?? 'Download App'),
        'website_download_url' => trim($_POST['website_download_url'] ?? '')
    ];

    $upsert_wc = $pdo->prepare('
        INSERT INTO tenant_website_content
            (tenant_id, layout_template, hero_title, hero_subtitle, hero_description,
             hero_cta_text, hero_cta_url, hero_image_path, hero_badge_text,
             about_heading, about_body, about_image_path,
             services_heading, services_json,
             stats_json, stats_heading, stats_subheading, stats_image_path,
             contact_address, contact_phone, contact_email, contact_hours,
             footer_description, custom_css, meta_description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            layout_template = VALUES(layout_template),
            hero_title = VALUES(hero_title), hero_subtitle = VALUES(hero_subtitle),
            hero_description = VALUES(hero_description),
            hero_cta_text = VALUES(hero_cta_text), hero_cta_url = VALUES(hero_cta_url),
            hero_image_path = VALUES(hero_image_path), hero_badge_text = VALUES(hero_badge_text),
            about_heading = VALUES(about_heading), about_body = VALUES(about_body),
            about_image_path = VALUES(about_image_path),
            services_heading = VALUES(services_heading), services_json = VALUES(services_json),
            stats_json = VALUES(stats_json), stats_heading = VALUES(stats_heading),
            stats_subheading = VALUES(stats_subheading), stats_image_path = VALUES(stats_image_path),
            contact_address = VALUES(contact_address), contact_phone = VALUES(contact_phone),
            contact_email = VALUES(contact_email), contact_hours = VALUES(contact_hours),
            footer_description = VALUES(footer_description),
            custom_css = VALUES(custom_css), meta_description = VALUES(meta_description)
    ');
    $upsert_wc->execute([
        $tenant_id, $layout, $hero_title, $hero_subtitle, $hero_description,
        $hero_cta_text, $hero_cta_url, $hero_image_path, $hero_badge_text,
        $about_heading, $about_body, $about_image_path,
        $services_heading, $services_json,
        $stats_json, $stats_heading, $stats_subheading, $stats_image_path,
        $contact_address, $contact_phone, $contact_email, $contact_hours,
        $footer_description, $custom_css, $meta_description
    ]);

    $boolean_setting_keys = ['website_show_about', 'website_show_services', 'website_show_contact', 'website_show_download', 'website_show_stats', 'website_show_loan_calc', 'website_show_partners'];
    $setting_upsert = $pdo->prepare('
        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP
    ');
    foreach ($website_config_post as $key => $value) {
        $data_type = in_array($key, $boolean_setting_keys, true) ? 'Boolean' : 'String';
        $setting_upsert->execute([$tenant_id, $key, $value, 'Website', $data_type]);
    }

    $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
        ->execute([$tenant_id, 'public_website_enabled']);

    $_SESSION['admin_flash'] = 'Website saved and published successfully!';
    header('Location: admin.php?tab=website');
    exit;
}

// ==========================================
// Helper function for duplicate role checking
// ==========================================
function check_duplicate_permissions($pdo, $tenant_id, $incoming_perms, $exclude_role_id = null) {
    if (empty($incoming_perms)) {
        $incoming_perms = [];
    }
    // Normalize and sort incoming perms
    $incoming_perms_sorted = array_unique($incoming_perms);
    sort($incoming_perms_sorted);
    $incoming_str = implode(',', $incoming_perms_sorted);

    // Fetch existing roles for tenant
    $roles_stmt = $pdo->prepare('SELECT role_id, role_name FROM user_roles WHERE tenant_id = ?');
    $roles_stmt->execute([$tenant_id]);
    $existing_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($existing_roles as $r) {
        if ($exclude_role_id && $r['role_id'] == $exclude_role_id) {
            continue;
        }

        // Fetch perms for this role
        $perms_stmt = $pdo->prepare('SELECT p.permission_code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id WHERE rp.role_id = ?');
        $perms_stmt->execute([$r['role_id']]);
        $existing_perms = $perms_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $existing_perms_sorted = array_unique($existing_perms);
        sort($existing_perms_sorted);
        $existing_str = implode(',', $existing_perms_sorted);

        if ($incoming_str === $existing_str) {
            return $r['role_name']; // Found duplicate
        }
    }
    return false; // No duplicate
}

// ==========================================
// Form Handlers for Roles & Permissions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'create_role') {
            $role_name = trim($_POST['role_name'] ?? '');
            $initial_perms = $_POST['initial_permissions'] ?? [];

            if (empty($role_name)) {
                throw new Exception('Role name is required.');
            }

            // Check for duplicate permissions first
            $duplicate_role_name = check_duplicate_permissions($pdo, $tenant_id, $initial_perms);
            if ($duplicate_role_name) {
                throw new Exception("Cannot create role. The exact same set of permissions already exists in the role: '{$duplicate_role_name}'.");
            }
            
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, ?, ?, FALSE)');
            $stmt->execute([$tenant_id, $role_name, 'Custom role']);
            $new_role_id = $pdo->lastInsertId();

            if (!empty($initial_perms)) {
                $in_placeholders = str_repeat('?,', count($initial_perms) - 1) . '?';
                $lookup_stmt = $pdo->prepare("SELECT permission_id FROM permissions WHERE permission_code IN ($in_placeholders)");
                $lookup_stmt->execute($initial_perms);
                $perm_ids = $lookup_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($perm_ids)) {
                    $insert_values = [];
                    $insert_params = [];
                    foreach ($perm_ids as $pid) {
                        $insert_values[] = '(?, ?)';
                        $insert_params[] = $new_role_id;
                        $insert_params[] = $pid;
                    }
                    $map_stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(',', $insert_values));
                    $map_stmt->execute($insert_params);
                }
            }

            $pdo->commit();
            $_SESSION['admin_flash'] = 'Role created successfully.';
            header('Location: admin.php?tab=roles-list&role_id=' . $new_role_id);
            exit;
        }

        if ($action === 'delete_role') {
            $role_id = (int)($_POST['role_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM user_roles WHERE role_id = ? AND tenant_id = ? AND is_system_role = FALSE');
            $stmt->execute([$role_id, $tenant_id]);
            $_SESSION['admin_flash'] = 'Role deleted successfully.';
            header('Location: admin.php?tab=roles-list');
            exit;
        }

        if ($action === 'save_permissions') {
            $role_id = (int)($_POST['role_id'] ?? 0);
            $permissions = $_POST['permissions'] ?? [];
            
            $check = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND tenant_id = ?');
            $check->execute([$role_id, $tenant_id]);
            if ($check->fetchColumn() == 0) throw new Exception('Invalid role.');

            // Check for duplicate permissions before updating
            $duplicate_role_name = check_duplicate_permissions($pdo, $tenant_id, $permissions, $role_id);
            if ($duplicate_role_name) {
                throw new Exception("Cannot save permissions. The exact same set of permissions already exists in the role: '{$duplicate_role_name}'.");
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$role_id]);
            
            if (!empty($permissions) && is_array($permissions)) {
                $insert_stmt = $pdo->prepare('
                    INSERT INTO role_permissions (role_id, permission_id) 
                    SELECT ?, permission_id FROM permissions WHERE permission_code = ?
                ');
                foreach ($permissions as $code) {
                    $insert_stmt->execute([$role_id, $code]);
                }
            }
            
            $pdo->commit();
            $_SESSION['admin_flash'] = 'Permissions saved successfully.';
            header('Location: admin.php?tab=roles-list&role_id=' . $role_id);
            exit;
        }

    // ─── Toggle Staff Status ─────────────────────────────────
        if ($action === 'toggle_staff_status') {
            $target_user_id = trim($_POST['user_id'] ?? '');
            $new_status = ($_POST['new_status'] ?? 'Active') === 'Active' ? 'Active' : 'Suspended';
            if (empty($target_user_id)) throw new Exception('Invalid user.');
            $s = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ? AND tenant_id = ? AND user_type = \'Employee\'');
            $s->execute([$new_status, $target_user_id, $tenant_id]);
            $_SESSION['admin_flash'] = "Staff status updated to $new_status.";
            header('Location: admin.php?tab=staff-list');
            exit;
        }

        // ─── Edit Staff ──────────────────────────────────────────
        if ($action === 'edit_staff') {
            $target_user_id = trim($_POST['user_id'] ?? '');
            $first_name     = trim($_POST['first_name'] ?? '');
            $last_name      = trim($_POST['last_name'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $role_id        = (int)($_POST['role_id'] ?? 0);
            $status         = in_array($_POST['status'] ?? '', ['Active','Inactive','Suspended']) ? $_POST['status'] : 'Active';

            if (empty($target_user_id) || empty($first_name) || empty($last_name) || empty($email) || !$role_id) {
                throw new Exception('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }
            // Check email uniqueness (excluding themselves)
            $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ? AND user_id != ?');
            $dup->execute([$tenant_id, $email, $target_user_id]);
            if ($dup->fetchColumn() > 0) {
                throw new Exception('That email is already in use by another account.');
            }

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET email = ?, role_id = ?, status = ? WHERE user_id = ? AND tenant_id = ? AND user_type = \'Employee\'')
                ->execute([$email, $role_id, $status, $target_user_id, $tenant_id]);
            $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ? WHERE user_id = ? AND tenant_id = ?')
                ->execute([$first_name, $last_name, $target_user_id, $tenant_id]);
            $pdo->commit();

            $_SESSION['admin_flash'] = 'Staff account updated successfully.';
            header('Location: admin.php?tab=staff-list');
            exit;
        }

        if ($action === 'create_staff') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role_id = (int)($_POST['role_id'] ?? 0);
            $status = $_POST['status'] ?? 'Active';

            if (empty($first_name) || empty($last_name) || empty($email) || empty($role_id)) {
                throw new Exception('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }

            // Generate an initial username and password
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . '.' . $last_name));
            $username = $base_username;
            $counter = 1;
            
            // Ensure unique username in tenant
            while (true) {
                $check_username = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ?');
                $check_username->execute([$tenant_id, $username]);
                if ($check_username->fetchColumn() == 0) break;
                $username = $base_username . $counter++;
            }

            // Check if email exists in tenant
            $check_email = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ?');
            $check_email->execute([$tenant_id, $email]);
            if ($check_email->fetchColumn() > 0) {
                throw new Exception('A user with this email already exists in your organization.');
            }

            // Generate a secure random temporary password
            $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            $user_stmt = $pdo->prepare('INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status) VALUES (?, ?, ?, ?, TRUE, ?, \'Employee\', ?)');
            $user_stmt->execute([$tenant_id, $username, $email, $password_hash, $role_id, $status]);
            $new_user_id = $pdo->lastInsertId();

            $emp_stmt = $pdo->prepare('INSERT INTO employees (user_id, tenant_id, first_name, last_name, department, hire_date) VALUES (?, ?, ?, ?, \'Admin\', CURDATE())');
            $emp_stmt->execute([$new_user_id, $tenant_id, $first_name, $last_name]);

            $pdo->commit();
            // Get the tenant slug for the login link
            $slug_stmt = $pdo->prepare('SELECT tenant_slug FROM tenants WHERE tenant_id = ?');
            $slug_stmt->execute([$tenant_id]);
            $tenant_slug = $slug_stmt->fetchColumn();
            
            // Send the email
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
            $login_url = $base_url . "/tenant_login/login.php?s=" . urlencode($tenant_slug);
            
            $subject = "Welcome to " . $_SESSION['tenant_name'] . " - Employee Logins";
            $message = "Hello $first_name,\n\n"
                     . "An employee portal account has been created for you at " . $_SESSION['tenant_name'] . ".\n\n"
                     . "Please log in and set up your permanent password using the following details:\n\n"
                     . "Login URL: $login_url\n"
                     . "Temporary Password: $temp_password\n\n"
                     . "Note: You will be required to change this password on your first login.\n\n"
                     . "Best Regards,\n"
                     . $_SESSION['tenant_name'] . " Administration";
                     
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER; 
                $mail->Password   = SMTP_PASS; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom(SMTP_USER, $_SESSION['tenant_name'] . ' Administration');
                $mail->addAddress($email, $first_name . ' ' . $last_name);

                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                $_SESSION['admin_flash'] = "Staff account created! An email has been sent to them with instructions.";
            } catch (Exception $e) {
                // Fallback for local environments where mail() is not configured
                $_SESSION['admin_flash'] = "Staff account created! (Note: Email failed to send due to SMTP error: {$mail->ErrorInfo}. Please manually distribute this Temporary Password: <strong>$temp_password</strong> and instruct them to log into your portal at: <strong>../tenant_login/login.php?s=$tenant_slug</strong>)";
            }
            
            header('Location: admin.php?tab=staff-list');
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $_SESSION['admin_error'] = 'A role with this name already exists.';
        } else {
            $_SESSION['admin_error'] = $e->getMessage();
        }
        header('Location: admin.php?tab=roles-list');
        exit;
    }
}

// ==========================================
// Pre-fetch Data for UI Rendering
// ==========================================
// 1. Fetch Roles
$roles_stmt = $pdo->prepare('SELECT * FROM user_roles WHERE tenant_id = ? ORDER BY is_system_role DESC, created_at ASC');
$roles_stmt->execute([$tenant_id]);
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

$active_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['role_id'] ?? null);
$active_role = null;
foreach ($roles as $r) {
    if ($r['role_id'] == $active_role_id) {
        $active_role = $r;
        break;
    }
}

// 1.5 Fetch Staff/Employees
$staff_stmt = $pdo->prepare('
    SELECT u.user_id, u.role_id, u.email, u.status, e.first_name, e.last_name, r.role_name 
    FROM users u 
    JOIN employees e ON u.user_id = e.user_id 
    JOIN user_roles r ON u.role_id = r.role_id 
    WHERE u.tenant_id = ? AND u.user_type = ?
    ORDER BY e.created_at DESC
');
$staff_stmt->execute([$tenant_id, 'Employee']);
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Global Permissions
$perm_stmt = $pdo->query('SELECT * FROM permissions ORDER BY module ASC, permission_code ASC');
$all_permissions = $perm_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Prepare Active Codes for ALL Roles
$active_codes_by_role = [];
$active_stmt = $pdo->query('SELECT rp.role_id, p.permission_code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id');
while ($row = $active_stmt->fetch(PDO::FETCH_ASSOC)) {
    $r_id = $row['role_id'];
    if (!isset($active_codes_by_role[$r_id])) {
        $active_codes_by_role[$r_id] = [];
    }
    $active_codes_by_role[$r_id][] = $row['permission_code'];
}

// 4. Group Permissions for UI Output
$grouped_permissions = [];
foreach ($all_permissions as $p) {
    $mod = $p['module'];
    
    // Explicitly hide the "Roles" module so it cannot be toggled by admins for custom roles
    if ($mod === 'Roles') {
        continue;
    }
    
    if (!isset($grouped_permissions[$mod])) {
        $grouped_permissions[$mod] = [];
    }
    $grouped_permissions[$mod][] = $p;
}

$settings = $default_settings;
$toggles = $default_toggles;

// Get direct columns from tenants + branding
$tenant_settings_stmt = $pdo->prepare('SELECT t.tenant_name as company_name, b.theme_primary_color as primary_color, b.theme_text_main as text_main, b.theme_text_muted as text_muted, b.theme_bg_body as bg_body, b.theme_bg_card as bg_card, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_id = ?');
$tenant_settings_stmt->execute([$tenant_id]);
if ($t = $tenant_settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings = array_merge($settings, array_filter($t, function($v) { return $v !== null && $v !== ''; }));
}

$toggles_stmt = $pdo->prepare('SELECT toggle_key, is_enabled FROM tenant_feature_toggles WHERE tenant_id = ?');
$toggles_stmt->execute([$tenant_id]);
foreach ($toggles_stmt->fetchAll() as $row) {
    $toggles[$row['toggle_key']] = (int) $row['is_enabled'];
}

// ── Website Editor Data ──
$ws_stmt = $pdo->prepare('SELECT * FROM tenant_website_content WHERE tenant_id = ?');
$ws_stmt->execute([$tenant_id]);
$ws = $ws_stmt->fetch(PDO::FETCH_ASSOC);
if (!$ws) {
    $ws = [
        'layout_template' => 'template1',
        'hero_title' => '', 'hero_subtitle' => '', 'hero_description' => '',
        'hero_cta_text' => 'Learn More', 'hero_cta_url' => '#about', 'hero_image_path' => '',
        'hero_badge_text' => '',
        'about_heading' => 'About Us', 'about_body' => '', 'about_image_path' => '',
        'services_heading' => 'Our Services', 'services_json' => '[]',
        'stats_json' => '[]', 'stats_heading' => '', 'stats_subheading' => '', 'stats_image_path' => '',
        'contact_address' => '', 'contact_phone' => '', 'contact_email' => '', 'contact_hours' => '',
        'footer_description' => '',
        'custom_css' => '', 'meta_description' => ''
    ];
}
if (($ws['layout_template'] ?? '') !== 'template1') {
    $ws['layout_template'] = 'template1';
}
$website_config = [
    'website_show_about' => '1', 'website_show_services' => '1',
    'website_show_contact' => '1', 'website_show_download' => '1',
    'website_show_stats' => '1', 'website_show_loan_calc' => '1',
    'website_show_partners' => '0', 'website_partners_json' => '[]',
    'website_download_title' => 'Download Our App',
    'website_download_description' => 'Get the app for faster loan tracking and updates.',
    'website_download_button_text' => 'Download App',
    'website_download_url' => ''
];
$ws_settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ('website_show_about','website_show_services','website_show_contact','website_show_download','website_show_stats','website_show_loan_calc','website_show_partners','website_partners_json','website_download_title','website_download_description','website_download_button_text','website_download_url')");
$ws_settings_stmt->execute([$tenant_id]);
foreach ($ws_settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['setting_key'], $website_config)) {
        $website_config[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
}
$ws_services = json_decode($ws['services_json'] ?? '[]', true) ?: [];
$ws_stats = json_decode($ws['stats_json'] ?? '[]', true) ?: [];
// Pad stats to 4 slots
while (count($ws_stats) < 4) { $ws_stats[] = ['value' => '', 'label' => '']; }
$e = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$site_url = '../site.php?site=' . urlencode($tenant_slug);

$flash_message = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

// Helper to convert HEX to RGB for CSS rgba() values
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo htmlspecialchars($settings['company_name']); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($settings['font_family']); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="admin.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($settings['primary_color']); ?>;
            --primary-rgb: <?php echo hexToRgb($settings['primary_color']); ?>;
            --sidebar-bg: <?php echo htmlspecialchars($settings['bg_card']); ?>;
            --text-main: <?php echo htmlspecialchars($settings['text_main']); ?>;
            --text-muted: <?php echo htmlspecialchars($settings['text_muted']); ?>;
            --bg-body: <?php echo htmlspecialchars($settings['bg_body']); ?>;
            --bg-card: <?php echo htmlspecialchars($settings['bg_card']); ?>;
            --font-family: '<?php echo htmlspecialchars($settings['font_family']); ?>', sans-serif;
        }

        html[data-theme="dark"] {
            --bg-body: #0b1220;
            --bg-card: #111827;
            --sidebar-bg: #111827;
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --sidebar-text: #cbd5e1;
            --sidebar-active-bg: rgba(var(--primary-rgb), 0.24);
        }
        
        <?php if (!empty($settings['logo_path'])): ?>
        .logo-circle {
            background-image: url('<?php echo htmlspecialchars($settings['logo_path']); ?>');
            background-size: cover;
            background-position: center;
        }
        .logo-circle span {
            display: none;
        }
        <?php endif; ?>

        /* ── Website Editor ── */
        .we-template-picker { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .we-template-option { cursor: pointer; }
        .we-template-option input[type="radio"] { display: none; }
        .we-template-card { border: 2px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 20px; text-align: center; transition: all 0.2s; }
        .we-template-option input:checked + .we-template-card { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.04); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); }
        .we-template-card:hover { border-color: var(--primary-color); }
        .we-template-option.is-disabled { cursor: not-allowed; }
        .we-template-option.is-disabled .we-template-card { opacity: 0.55; border-style: dashed; }
        .we-template-option.is-disabled .we-template-card:hover { border-color: var(--border-color, #e2e8f0); }
        .we-template-coming-soon { width: 100%; height: 100%; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: var(--text-muted); background: rgba(148, 163, 184, 0.08); }
        .we-template-card h4 { margin: 12px 0 4px; font-size: 0.95rem; font-weight: 600; }
        .we-template-card p { font-size: 0.8rem; color: var(--text-muted); }
        .we-template-thumb { width: 100%; height: 140px; border-radius: 8px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .we-template-thumb svg { width: 90%; height: 90%; }

        .we-editor-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--border-color, #e2e8f0); margin-bottom: 24px; }
        .we-editor-tab { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; font-family: inherit; }
        .we-editor-tab:hover { color: var(--text-main); }
        .we-editor-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
        .we-tab-content { display: none; }
        .we-tab-content.active { display: block; }

        .we-editor-card { background: var(--bg-card); border-radius: 12px; padding: 28px; border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 20px; }
        .we-editor-card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; }
        .we-editor-card .we-card-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 24px; }

        .we-form-group { margin-bottom: 20px; }
        .we-form-group label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 6px; }
        .we-form-group .we-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        .we-form-group .we-hint a { color: var(--primary-color); }
        .we-form-group .we-hint code { background: var(--bg-body); padding: 1px 5px; border-radius: 3px; font-size: 0.8rem; }
        .we-form-input, .we-form-textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; font-size: 0.9rem; font-family: var(--font-family); background: var(--bg-card); color: var(--text-main); transition: border-color 0.15s; }
        .we-form-input:focus, .we-form-textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
        .we-form-textarea { resize: vertical; min-height: 100px; }
        .we-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .we-service-row { display: grid; grid-template-columns: 1fr 2fr 120px 40px; gap: 10px; align-items: start; margin-bottom: 12px; padding: 14px; border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border-color, #e2e8f0); }
        .we-service-row .we-form-input, .we-service-row .we-form-textarea { font-size: 0.85rem; }
        .we-service-row .we-form-textarea { min-height: 60px; }
        .we-btn-remove { width: 36px; height: 36px; border: none; background: none; cursor: pointer; color: #ef4444; border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .we-btn-remove:hover { background: #fee2e2; }
        .we-btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--primary-color); font-family: inherit; margin-top: 8px; }
        .we-btn-add:hover { background: rgba(var(--primary-rgb), 0.04); border-color: var(--primary-color); }

        .we-section-nav { display: flex; gap: 8px; margin-bottom: 24px; }
        .we-section-nav .we-nav-link { display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); text-decoration: none; cursor: pointer; transition: all 0.15s; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card); }
        .we-section-nav .we-nav-link:hover { border-color: var(--primary-color); color: var(--text-main); }
        .we-section-nav .we-nav-link.active { background: rgba(var(--primary-rgb), 0.08); border-color: var(--primary-color); color: var(--primary-color); font-weight: 600; }
        .we-section-nav .we-nav-link .material-symbols-rounded { font-size: 20px; }
        .we-editor-section { display: none; }
        .we-editor-section.active { display: block; }
        .we-preview-frame { width: 100%; height: 600px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; background: #fff; }
        .we-save-bar { margin-top: 24px; padding: 16px 0; display: flex; justify-content: flex-end; gap: 10px; }
        .we-save-bar .btn { display: inline-flex; align-items: center; gap: 6px; }

        @media (max-width: 768px) {
            .we-template-picker { grid-template-columns: 1fr; }
            .we-form-row { grid-template-columns: 1fr; }
            .we-service-row { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="background-color: transparent;">
                <div class="logo-circle">
                    <span class="material-symbols-rounded">diamond</span>
                </div>
                <h2 class="company-name-display"><?php echo htmlspecialchars($settings['company_name']); ?></h2>
            </div>

            <?php
            $active_view = 'dashboard';
            if (isset($_GET['tab'])) {
                if (in_array($_GET['tab'], ['staff-list', 'roles-list'])) {
                    $active_view = 'staff';
                } elseif ($_GET['tab'] === 'billing') {
                    $active_view = 'billing';
                } elseif ($_GET['tab'] === 'website') {
                    $active_view = 'website';
                } elseif (strpos($_GET['tab'], 'logs') !== false) {
                    $active_view = 'logs';
                }
            }
            $page_titles = [
                'dashboard' => 'Dashboard',
                'staff' => 'Staff & Roles',
                'website' => 'Website Editor',
                'settings' => 'Settings',
                'features' => 'Feature Toggles',
                'billing' => 'Billing & Subscription',
                'logs' => 'Audit Logs'
            ];
            $page_title = $page_titles[$active_view] ?? 'Dashboard';
            ?>
            <nav class="sidebar-nav">
                <a href="#dashboard" class="nav-item <?php echo $active_view === 'dashboard' ? 'active' : ''; ?>" data-target="dashboard">
                    <span class="material-symbols-rounded">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="#staff" class="nav-item <?php echo $active_view === 'staff' ? 'active' : ''; ?>" data-target="staff">
                    <span class="material-symbols-rounded">groups</span>
                    <span>Staff & Roles</span>
                </a>
                <a href="#website" class="nav-item <?php echo $active_view === 'website' ? 'active' : ''; ?>" data-target="website">
                    <span class="material-symbols-rounded">language</span>
                    <span>Website</span>
                </a>
                <a href="#billing" class="nav-item <?php echo $active_view === 'billing' ? 'active' : ''; ?>" data-target="billing">
                    <span class="material-symbols-rounded">receipt_long</span>
                    <span>Billing</span>
                </a>
                <a href="#settings" class="nav-item <?php echo $active_view === 'settings' ? 'active' : ''; ?>" data-target="settings">
                    <span class="material-symbols-rounded">settings</span>
                    <span>Settings</span>
                </a>
                <a href="#features" class="nav-item <?php echo $active_view === 'features' ? 'active' : ''; ?>" data-target="features">
                    <span class="material-symbols-rounded">toggle_on</span>
                    <span>Feature Toggles</span>
                </a>
                <a href="#logs" class="nav-item <?php echo $active_view === 'logs' ? 'active' : ''; ?>" data-target="logs">
                    <span class="material-symbols-rounded">manage_search</span>
                    <span>Audit Logs</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../tenant_login/logout.php" class="nav-item">
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
                    <h1 id="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="header-right">
                    <button id="theme-toggle" class="icon-btn" title="Toggle Light/Dark Mode">
                        <span class="material-symbols-rounded"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
                    </button>
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Super+Admin&background=random" alt="Admin Avatar"
                            class="avatar">
                        <div class="admin-info">
                            <span class="admin-name"><?php echo htmlspecialchars($settings['company_name']); ?></span>
                            <span class="admin-role"><?php echo htmlspecialchars($role_name); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($flash_message !== ''): ?>
            <div class="site-alert site-alert-success" style="margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: 8px; background: #dcfce7; color: #166534; font-weight: 500;">
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
            <?php endif; ?>

            <!-- Views Container -->
            <div class="views-container">

                <!-- Dashboard View -->
                <section id="dashboard" class="view-section <?php echo $active_view === 'dashboard' ? 'active' : ''; ?>">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"
                                style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color);">
                                <span class="material-symbols-rounded">book</span>
                            </div>
                            <div class="stat-details">
                                <p>Total Clients</p>
                                <h3>0</h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <span class="material-symbols-rounded">group</span>
                            </div>
                            <div class="stat-details">
                                <p>Active Staff</p>
                                <h3>0</h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                <span class="material-symbols-rounded">warning</span>
                            </div>
                            <div class="stat-details">
                                <p>System Alerts</p>
                                <h3>0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-widgets">
                        <div class="widget">
                            <h3>Recent Activity</h3>
                            <ul class="activity-list">
                                <li>
                                    <div class="activity-icon"><span class="material-symbols-rounded">info</span></div>
                                    <div class="activity-text">
                                        <p>No recent activity. Your tenant dashboard is ready for use.</p>
                                        <span>Just now</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- General Settings View -->
                <section id="settings" class="view-section <?php echo $active_view === 'settings' ? 'active' : ''; ?>">
                    <div class="settings-panel">
                        <form id="settings-form" method="POST" action="">
                            <input type="hidden" name="action" value="save_settings">
                            <input type="hidden" id="hidden-toggle-booking" name="toggle_booking_system" value="1" <?php echo ((int) $toggles['booking_system'] === 1) ? '' : 'disabled'; ?>>
                            <input type="hidden" id="hidden-toggle-registration" name="toggle_user_registration" value="1" <?php echo ((int) $toggles['user_registration'] === 1) ? '' : 'disabled'; ?>>
                            <input type="hidden" id="hidden-toggle-maintenance" name="toggle_maintenance_mode" value="1" <?php echo ((int) $toggles['maintenance_mode'] === 1) ? '' : 'disabled'; ?>>
                            <input type="hidden" id="hidden-toggle-emails" name="toggle_email_notifications" value="1" <?php echo ((int) $toggles['email_notifications'] === 1) ? '' : 'disabled'; ?>>
                            <input type="hidden" id="hidden-toggle-website" name="toggle_public_website_enabled" value="1" <?php echo ((int) ($toggles['public_website_enabled'] ?? 0) === 1) ? '' : 'disabled'; ?>>

                        <div class="card">
                            <h3>Branding Configuration</h3>
                            <div class="form-group">
                                <label for="company-name">Company Name</label>
                                <input type="text" id="company-name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Logo Upload</label>
                                <div class="file-upload">
                                    <input type="file" id="logo-upload" class="hidden-input">
                                    <label for="logo-upload" class="upload-btn">
                                        <span class="material-symbols-rounded">upload</span> Choose Image
                                    </label>
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                            <!-- Note: Added logo_path as a text input as planned for now to store the reference/CDN URL while file upload is implemented -->
                            <div class="form-group" style="margin-top: 10px;">
                                <label for="logo-path">Logo URL Path</label>
                                <input type="text" id="logo-path" name="logo_path" value="<?php echo htmlspecialchars($settings['logo_path']); ?>" class="form-control" placeholder="https://example.com/logo.png">
                                <small class="text-muted">Enter a direct URL to your logo.</small>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Theme Settings</h3>
                            <p class="text-muted">Customize the platform colors to match your brand.</p>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Font Family</label>
                                <select name="font_family" class="form-control">
                                    <?php
                                    $allowed_fonts_list = ['Inter', 'Poppins', 'Outfit', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Montserrat', 'DM Sans', 'Plus Jakarta Sans'];
                                    foreach ($allowed_fonts_list as $fnt):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($fnt); ?>" <?php echo $settings['font_family'] === $fnt ? 'selected' : ''; ?>><?php echo htmlspecialchars($fnt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="theme-colors">
                                <div class="form-group">
                                    <label>Primary Color</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" id="primary-color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['primary_color']); ?></span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Text Main</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="text_main" value="<?php echo htmlspecialchars($settings['text_main']); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['text_main']); ?></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Text Muted</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="text_muted" value="<?php echo htmlspecialchars($settings['text_muted']); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['text_muted']); ?></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Background Body</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="bg_body" value="<?php echo htmlspecialchars($settings['bg_body']); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['bg_body']); ?></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Background Card</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="bg_card" value="<?php echo htmlspecialchars($settings['bg_card']); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['bg_card']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Module: Contact Information</h3>
                            <div class="form-group">
                                <label>Support Email</label>
                                <input type="email" id="support-email" name="support_email" class="form-control" value="<?php echo htmlspecialchars($settings['support_email']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Support Phone</label>
                                <input type="text" id="support-phone" name="support_phone" class="form-control" value="<?php echo htmlspecialchars($settings['support_phone']); ?>">
                            </div>
                        </div>

                        <div class="action-bar">
                            <button class="btn btn-primary" id="save-settings" type="submit">Save Settings</button>
                        </div>
                        </form>
                    </div>
                </section>

                <!-- Feature Toggles View -->
                <section id="features" class="view-section <?php echo $active_view === 'features' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3>Core Modules & Toggles</h3>
                        <p class="text-muted">Instantly enable or disable core functionality across the entire platform.
                        </p>

                        <div class="toggle-list">
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Booking System</h4>
                                    <p>Allow users to create new bookings and reservations.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-booking_system" <?php echo ((int) $toggles['booking_system'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>User Registration</h4>
                                    <p>Allow new clients to sign up for accounts on the frontend.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-user_registration" <?php echo ((int) $toggles['user_registration'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item warning-toggle">
                                <div class="toggle-info">
                                    <h4>Maintenance Mode</h4>
                                    <p>Take the system offline for clients. Only admins can log in.</p>
                                </div>
                                <label class="switch warning">
                                    <input type="checkbox" id="toggle-maintenance_mode" <?php echo ((int) $toggles['maintenance_mode'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Email Notifications</h4>
                                    <p>Send automated emails for bookings, OTPs, and alerts.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-email_notifications" <?php echo ((int) $toggles['email_notifications'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Public Website</h4>
                                    <p>Enable a public-facing informational website for your organization.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-public_website_enabled" <?php echo ((int) ($toggles['public_website_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Billing & Subscription View -->
                <section id="billing" class="view-section <?php echo $active_view === 'billing' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3>Billing & Subscription</h3>
                        <p class="text-muted" style="margin-bottom: 24px;">Manage your subscription plan, payment methods, and view invoices.</p>

                        <div class="stats-grid" style="margin-bottom: 32px;">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color);">
                                    <span class="material-symbols-rounded">workspace_premium</span>
                                </div>
                                <div class="stat-details">
                                    <p>Current Plan</p>
                                    <h3><?php
                                        $plan_stmt = $pdo->prepare('SELECT plan_tier FROM tenants WHERE tenant_id = ?');
                                        $plan_stmt->execute([$tenant_id]);
                                        echo htmlspecialchars($plan_stmt->fetchColumn() ?: 'Starter');
                                    ?></h3>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                    <span class="material-symbols-rounded">credit_card</span>
                                </div>
                                <div class="stat-details">
                                    <p>Payment Methods</p>
                                    <h3><?php
                                        $pm_stmt = $pdo->prepare('SELECT COUNT(*) FROM tenant_billing_payment_methods WHERE tenant_id = ?');
                                        $pm_stmt->execute([$tenant_id]);
                                        echo $pm_stmt->fetchColumn();
                                    ?></h3>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                    <span class="material-symbols-rounded">receipt</span>
                                </div>
                                <div class="stat-details">
                                    <p>Open Invoices</p>
                                    <h3><?php
                                        $inv_stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_billing_invoices WHERE tenant_id = ? AND status = 'Open'");
                                        $inv_stmt->execute([$tenant_id]);
                                        echo $inv_stmt->fetchColumn();
                                    ?></h3>
                                </div>
                            </div>
                        </div>

                        <h4 style="margin-bottom: 16px;">Recent Invoices</h4>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $invoices_stmt = $pdo->prepare('SELECT * FROM tenant_billing_invoices WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10');
                                    $invoices_stmt->execute([$tenant_id]);
                                    $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($invoices)):
                                    ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <span class="material-symbols-rounded" style="font-size: 36px; display: block; margin-bottom: 0.5rem;">receipt_long</span>
                                            No invoices yet. Your billing history will appear here.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($invoices as $inv): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars($inv['billing_period_start'] . ' — ' . $inv['billing_period_end']); ?></td>
                                            <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars($inv['due_date']); ?></td>
                                            <td>
                                                <?php
                                                    $inv_class = 'badge-gray';
                                                    if ($inv['status'] === 'Paid') $inv_class = 'badge-green';
                                                    if ($inv['status'] === 'Open') $inv_class = 'badge-blue';
                                                    if ($inv['status'] === 'Void') $inv_class = 'badge-red';
                                                ?>
                                                <span class="badge <?php echo $inv_class; ?>"><?php echo htmlspecialchars($inv['status']); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Staff & Roles View -->
                <section id="staff" class="view-section <?php echo $active_view === 'staff' ? 'active' : ''; ?>">
                    <div class="tabs">
                        <button class="tab-btn <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'roles-list') ? 'active' : ''; ?>" data-tab="staff-list">Staff Accounts</button>
                        <button class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'roles-list') ? 'active' : ''; ?>" data-tab="roles-list">Roles & Permissions</button>
                    </div>

                    <div id="staff-list" class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'roles-list') ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header-flex">
                                <h3>Registered Staff</h3>
                                <button class="btn btn-primary btn-sm" onclick="document.getElementById('add-staff-modal').style.display='flex'">
                                    <span class="material-symbols-rounded">add</span> Add Staff
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staff_list)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                <span class="material-symbols-rounded" style="font-size: 36px; display: block; margin-bottom: 0.5rem;">person_add</span>
                                                No staff accounts yet. Click "Add Staff" to invite your first employee.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($staff_list as $staff): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div class="avatar" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 600;">
                                                                <?php echo htmlspecialchars(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($staff['email']); ?></td>
                                                    <td>
                                                        <span class="badge badge-gray"><?php echo htmlspecialchars($staff['role_name']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $status_class = 'badge-gray';
                                                            if ($staff['status'] === 'Active') $status_class = 'badge-green';
                                                            if ($staff['status'] === 'Suspended') $status_class = 'badge-red';
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($staff['status']); ?></span>
                                                    </td>
                                                    <td>
                                                         <div style="display:flex; gap:0.4rem; align-items:center;">
                                                             <!-- Edit -->
                                                             <button type="button" class="icon-btn btn-edit-staff" title="Edit Staff"
                                                                 data-user-id="<?php echo htmlspecialchars($staff['user_id']); ?>"
                                                                 data-first-name="<?php echo htmlspecialchars($staff['first_name']); ?>"
                                                                 data-last-name="<?php echo htmlspecialchars($staff['last_name']); ?>"
                                                                 data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                                                 data-role-id="<?php echo htmlspecialchars($staff['role_id']); ?>"
                                                                 data-status="<?php echo htmlspecialchars($staff['status']); ?>">
                                                                 <span class="material-symbols-rounded" style="font-size:18px;">edit</span>
                                                             </button>

                                                             <!-- Toggle Status -->
                                                             <form method="POST" action="admin.php" style="display:inline;" data-confirm-title="Update Staff Status" data-confirm-message="Change status of this staff member?" data-confirm-button="Confirm">
                                                                 <input type="hidden" name="action" value="toggle_staff_status">
                                                                 <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($staff['user_id']); ?>">
                                                                 <?php if ($staff['status'] === 'Active'): ?>
                                                                     <input type="hidden" name="new_status" value="Suspended">
                                                                     <button type="submit" class="icon-btn" title="Suspend Staff" style="color: #ef4444;">
                                                                         <span class="material-symbols-rounded" style="font-size:18px;">block</span>
                                                                     </button>
                                                                 <?php else: ?>
                                                                     <input type="hidden" name="new_status" value="Active">
                                                                     <button type="submit" class="icon-btn" title="Activate Staff" style="color: #22c55e;">
                                                                         <span class="material-symbols-rounded" style="font-size:18px;">check_circle</span>
                                                                     </button>
                                                                 <?php endif; ?>
                                                             </form>
                                                             <!-- Resend Email (Optional, if needed) -->
                                                             <!-- <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Resend invitation email to this staff member?');">
                                                                 <input type="hidden" name="action" value="resend_staff_invite">
                                                                 <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($staff['user_id']); ?>">
                                                                 <button type="submit" class="icon-btn" title="Resend Invitation" style="color: var(--primary-color);">
                                                                     <span class="material-symbols-rounded" style="font-size:18px;">mail</span>
                                                                 </button>
                                                             </form> -->
                                                         </div>
                                                     </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="roles-list" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'roles-list') ? 'active' : ''; ?>">
                        
                        <?php if (isset($_SESSION['admin_error'])): ?>
                        <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 500;">
                            <?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="roles-layout">
                            <!-- Left Sidebar: Role List -->
                            <div class="roles-sidebar card">
                                <div class="card-header-flex">
                                    <h3>Roles</h3>
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-create-role" onclick="document.getElementById('create-role-modal').style.display='flex'">
                                        <span class="material-symbols-rounded">add</span>
                                    </button>
                                    <script>
                                        // Inline fallback: guaranteed to attach even if admin.js fails
                                        document.getElementById('btn-create-role').addEventListener('click', function() {
                                            document.getElementById('create-role-modal').style.display = 'flex';
                                        });
                                    </script>
                                </div>
                                <div class="role-list-container">
                                    <?php if (empty($roles)): ?>
                                        <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No roles found.</div>
                                    <?php else: ?>
                                        <?php foreach ($roles as $role): ?>
                                            <a href="#" 
                                               data-role-id="<?php echo $role['role_id']; ?>"
                                               class="role-list-item <?php echo ($active_role_id == $role['role_id']) ? 'active' : ''; ?>" 
                                               style="text-decoration: none; color: inherit;">
                                                <span><?php echo htmlspecialchars($role['role_name']); ?></span>
                                                <?php if ((int)$role['is_system_role']): ?>
                                                    <span class="material-symbols-rounded" title="System Role">shield</span>
                                                <?php else: ?>
                                                    <span class="material-symbols-rounded">person</span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right Content: Permissions -->
                            <div class="roles-content card">
                                <?php if (empty($roles)): ?>
                                    <div id="empty-permissions-state" style="text-align: center; padding: 3rem 1rem;">
                                        <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border-color); margin-bottom: 1rem;">tune</span>
                                        <p style="color: var(--text-muted);">Select a role from the sidebar to view and edit its permissions.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($roles as $role_panel): 
                                        $is_active_panel = ($role_panel['role_id'] == $active_role_id);
                                        $is_admin_role = ((int)$role_panel['is_system_role'] && $role_panel['role_name'] === 'Admin');
                                        $panel_active_codes = $active_codes_by_role[$role_panel['role_id']] ?? [];
                                    ?>
                                        <div class="role-permissions-panel" id="role-panel-<?php echo $role_panel['role_id']; ?>" style="display: <?php echo $is_active_panel ? 'block' : 'none'; ?>;">
                                            <div class="roles-content-header">
                                                <div class="role-header-title">
                                                    <h3><?php echo htmlspecialchars($role_panel['role_name']); ?></h3>
                                                    <?php if ((int)$role_panel['is_system_role']): ?>
                                                        <span class="badge badge-blue">System Role</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray">Custom Role</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!(int)$role_panel['is_system_role']): ?>
                                                <form method="POST" action="admin.php" data-confirm-title="Delete Role" data-confirm-message="Are you sure you want to delete this role? Users assigned to it will lose permissions immediately." data-confirm-button="Delete Role">
                                                    <input type="hidden" name="action" value="delete_role">
                                                    <input type="hidden" name="role_id" value="<?php echo $role_panel['role_id']; ?>">
                                                    <button type="submit" class="btn btn-sm" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);">
                                                        <span class="material-symbols-rounded">delete</span> Delete Role
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="text-muted" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                                                Use the toggles below to customize what members with this role can do across the platform. <?php if ($is_admin_role) echo '<br><strong>Note: The Admin role has all permissions enabled by default and they cannot be modified.</strong>'; ?>
                                            </p>

                                            <form method="POST" action="admin.php">
                                                <input type="hidden" name="action" value="save_permissions">
                                                <input type="hidden" name="role_id" value="<?php echo $role_panel['role_id']; ?>">

                                                <div id="permissions-container-<?php echo $role_panel['role_id']; ?>" class="permissions-grid">
                                                    <?php 
                                                    $module_icons = [
                                                        'Applications' => 'description',
                                                        'Clients' => 'group',
                                                        'Loans' => 'real_estate_agent',
                                                        'Payments' => 'payments',
                                                        'Reports' => 'analytics',
                                                        'Roles' => 'shield_person',
                                                        'Users' => 'manage_accounts',
                                                        'System' => 'settings'
                                                    ];
                                                    foreach ($grouped_permissions as $moduleName => $perms): 
                                                    $icon = $module_icons[$moduleName] ?? 'tune';
                                                    ?>
                                                        <div class="permission-module">
                                                            <h4>
                                                                <span class="material-symbols-rounded"><?php echo $icon; ?></span>
                                                                <?php echo htmlspecialchars($moduleName); ?>
                                                            </h4>
                                                            <div class="toggle-list">
                                                                <?php foreach ($perms as $p): 
                                                                    $is_checked = $is_admin_role || in_array($p['permission_code'], $panel_active_codes);
                                                                ?>
                                                                    <div class="toggle-item">
                                                                        <div class="toggle-info">
                                                                            <h4 style="margin-bottom: 4px; font-weight: 600;"><?php echo htmlspecialchars($p['description']); ?></h4>
                                                                            <p style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace;"><?php echo htmlspecialchars($p['permission_code']); ?></p>
                                                                        </div>
                                                                        <label class="switch">
                                                                            <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($p['permission_code']); ?>" <?php echo $is_checked ? 'checked' : ''; ?> <?php echo $is_admin_role ? 'disabled' : ''; ?>>
                                                                            <span class="slider round <?php echo $is_admin_role ? 'disabled' : ''; ?>"></span>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <?php if (!$is_admin_role): ?>
                                                <div class="action-bar" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ═══ WEBSITE EDITOR ═══ -->
                <section id="website" class="view-section <?php echo $active_view === 'website' ? 'active' : ''; ?>">
                    <div class="we-section-nav" id="we-section-nav">
                        <a class="we-nav-link active" data-section="we-section-template">
                            <span class="material-symbols-rounded">view_quilt</span> Layout Template
                        </a>
                        <a class="we-nav-link" data-section="we-section-content">
                            <span class="material-symbols-rounded">edit_note</span> Edit Content
                        </a>
                    </div>

                    <form method="POST" id="we-editor-form">
                        <input type="hidden" name="action" value="save_website_content">

                        <!-- SECTION: Layout Template -->
                        <div class="we-editor-section active" id="we-section-template">
                            <div class="we-editor-card">
                                <h3>Choose a Layout</h3>
                                <p class="we-card-desc">Select the visual structure for your public website. Each template arranges the same content differently.</p>
                                <div class="we-template-picker">
                                    <label class="we-template-option">
                                        <input type="radio" name="layout_template" value="template1" checked>
                                        <div class="we-template-card">
                                            <div class="we-template-thumb">
                                                <svg viewBox="0 0 200 150" fill="none"><rect width="200" height="150" rx="4" fill="#f1f5f9"/><rect x="10" y="8" width="180" height="55" rx="4" fill="rgba(79,70,229,0.15)"/><rect x="20" y="22" width="80" height="6" rx="2" fill="rgba(79,70,229,0.4)"/><rect x="20" y="32" width="60" height="4" rx="2" fill="rgba(79,70,229,0.2)"/><rect x="10" y="72" width="85" height="4" rx="2" fill="#cbd5e1"/><rect x="10" y="80" width="85" height="3" rx="1" fill="#e2e8f0"/><rect x="105" y="68" width="85" height="28" rx="4" fill="#e2e8f0"/><rect x="10" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/><rect x="72" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/><rect x="134" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/></svg>
                                            </div>
                                            <h4>Template 1</h4>
                                            <p>Card-based hero with stats overlay. Bold &amp; impactful.</p>
                                        </div>
                                    </label>
                                    <label class="we-template-option is-disabled" title="Under Development">
                                        <input type="radio" name="layout_template" value="template2" disabled>
                                        <div class="we-template-card">
                                            <div class="we-template-thumb">
                                                <div class="we-template-coming-soon">Not Available Yet</div>
                                            </div>
                                            <h4>Template 2</h4>
                                            <p>Under Development</p>
                                        </div>
                                    </label>
                                    <label class="we-template-option is-disabled" title="Under Development">
                                        <input type="radio" name="layout_template" value="template3" disabled>
                                        <div class="we-template-card">
                                            <div class="we-template-thumb">
                                                <div class="we-template-coming-soon">Not Available Yet</div>
                                            </div>
                                            <h4>Template 3</h4>
                                            <p>Under Development</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="we-editor-card" style="margin-top: 20px;">
                                <h3>Live Preview</h3>
                                <p class="we-card-desc">This shows your currently published website. Save changes first to see updates here.</p>
                                <div style="margin: 16px 0; display: flex; gap: 8px;">
                                    <button type="button" class="we-btn-add" onclick="document.getElementById('we-preview-iframe').src = document.getElementById('we-preview-iframe').src">
                                        <span class="material-symbols-rounded">refresh</span> Refresh Preview
                                    </button>
                                    <a href="<?php echo $e($site_url); ?>" target="_blank" class="we-btn-add" style="text-decoration: none;">
                                        <span class="material-symbols-rounded">open_in_new</span> Open in New Tab
                                    </a>
                                </div>
                                <iframe id="we-preview-iframe" src="<?php echo $e($site_url); ?>" class="we-preview-frame"></iframe>
                            </div>
                        </div>

                        <!-- SECTION: Edit Content -->
                        <div class="we-editor-section" id="we-section-content">
                            <div class="we-editor-tabs">
                                <button type="button" class="we-editor-tab active" data-tab="we-tab-hero">Hero</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-about">About Us</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-services">Services</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-stats">Stats</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-contact">Contact</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-visibility">Visibility &amp; Download</button>
                                <button type="button" class="we-editor-tab" data-tab="we-tab-advanced">Advanced</button>
                            </div>

                            <div class="we-tab-content active" id="we-tab-hero">
                                <div class="we-editor-card">
                                    <h3>Hero / Banner Section</h3>
                                    <p class="we-card-desc">The first thing visitors see. Make it count.</p>
                                    <div class="we-form-group">
                                        <label>Title</label>
                                        <input type="text" name="hero_title" class="we-form-input" value="<?php echo $e($ws['hero_title']); ?>" placeholder="Welcome to Our Institution">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Subtitle</label>
                                        <input type="text" name="hero_subtitle" class="we-form-input" value="<?php echo $e($ws['hero_subtitle']); ?>" placeholder="Your Trusted Microfinance Partner">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Description</label>
                                        <textarea name="hero_description" class="we-form-textarea" rows="3" placeholder="A brief description of your organization..."><?php echo $e($ws['hero_description']); ?></textarea>
                                    </div>
                                    <div class="we-form-row">
                                        <div class="we-form-group">
                                            <label>CTA Button Text</label>
                                            <input type="text" name="hero_cta_text" class="we-form-input" value="<?php echo $e($ws['hero_cta_text']); ?>" placeholder="Learn More">
                                        </div>
                                        <div class="we-form-group">
                                            <label>CTA Button Link</label>
                                            <input type="text" name="hero_cta_url" class="we-form-input" value="<?php echo $e($ws['hero_cta_url']); ?>" placeholder="#about">
                                        </div>
                                    </div>
                                    <div class="we-form-group">
                                        <label>Hero Background Image URL</label>
                                        <input type="text" name="hero_image_path" class="we-form-input" value="<?php echo $e($ws['hero_image_path']); ?>" placeholder="https://images.unsplash.com/...">
                                        <p class="we-hint">Paste an external image URL. For best results use a wide landscape image (1920x1080 or similar).</p>
                                    </div>
                                    <div class="we-form-group">
                                        <label>Badge Text (small label above title)</label>
                                        <input type="text" name="hero_badge_text" class="we-form-input" value="<?php echo $e($ws['hero_badge_text']); ?>" placeholder="e.g. Trusted Since 2015">
                                        <p class="we-hint">A short badge shown above the hero title. Leave blank to hide.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-about">
                                <div class="we-editor-card">
                                    <h3>About Us Section</h3>
                                    <p class="we-card-desc">Tell visitors about your organization, mission, and history.</p>
                                    <div class="we-form-group">
                                        <label>Section Heading</label>
                                        <input type="text" name="about_heading" class="we-form-input" value="<?php echo $e($ws['about_heading']); ?>" placeholder="About Us">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Body Text</label>
                                        <textarea name="about_body" class="we-form-textarea" rows="6" placeholder="Tell your story..."><?php echo $e($ws['about_body']); ?></textarea>
                                        <p class="we-hint">Line breaks will be preserved on the live site.</p>
                                    </div>
                                    <div class="we-form-group">
                                        <label>Image URL (optional)</label>
                                        <input type="text" name="about_image_path" class="we-form-input" value="<?php echo $e($ws['about_image_path']); ?>" placeholder="https://...">
                                    </div>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-services">
                                <div class="we-editor-card">
                                    <h3>Services / Products</h3>
                                    <p class="we-card-desc">List the financial services your institution offers.</p>
                                    <div class="we-form-group">
                                        <label>Section Heading</label>
                                        <input type="text" name="services_heading" class="we-form-input" value="<?php echo $e($ws['services_heading']); ?>" placeholder="Our Services">
                                    </div>
                                    <div id="we-services-list">
                                        <?php if (empty($ws_services)): ?>
                                        <p style="color: var(--text-muted); font-size: 0.85rem; padding: 12px 0;">No services added yet. Click below to add one.</p>
                                        <?php endif; ?>
                                        <?php foreach ($ws_services as $svc): ?>
                                        <div class="we-service-row">
                                            <input type="text" name="service_title[]" class="we-form-input" value="<?php echo $e($svc['title']); ?>" placeholder="Service name">
                                            <textarea name="service_description[]" class="we-form-textarea" rows="2" placeholder="Brief description"><?php echo $e($svc['description']); ?></textarea>
                                            <input type="text" name="service_icon[]" class="we-form-input" value="<?php echo $e($svc['icon']); ?>" placeholder="Icon name">
                                            <button type="button" class="we-btn-remove" onclick="this.closest('.we-service-row').remove()" title="Remove">
                                                <span class="material-symbols-rounded">close</span>
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="we-btn-add" onclick="weAddServiceRow()">
                                        <span class="material-symbols-rounded">add</span> Add Service
                                    </button>
                                    <p class="we-hint" style="margin-top: 12px;">Icon names use <a href="https://fonts.google.com/icons?icon.set=Material+Symbols" target="_blank">Material Symbols</a> (e.g. <code>payments</code>, <code>savings</code>, <code>account_balance</code>)</p>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-stats">
                                <div class="we-editor-card">
                                    <h3>Trust Stats Section</h3>
                                    <p class="we-card-desc">Highlight key numbers that build trust with visitors.</p>
                                    <div class="we-form-group">
                                        <label>Section Heading</label>
                                        <input type="text" name="stats_heading" class="we-form-input" value="<?php echo $e($ws['stats_heading']); ?>" placeholder="Building Trust Through Numbers">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Section Subheading</label>
                                        <input type="text" name="stats_subheading" class="we-form-input" value="<?php echo $e($ws['stats_subheading']); ?>" placeholder="Our track record speaks for itself">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Stats Image URL (optional)</label>
                                        <input type="text" name="stats_image_path" class="we-form-input" value="<?php echo $e($ws['stats_image_path']); ?>" placeholder="https://images.unsplash.com/...">
                                        <p class="we-hint">Displayed beside the stat cards. Use a portrait-style image.</p>
                                    </div>
                                    <hr style="border: 0; border-top: 1px solid var(--border-color, #e2e8f0); margin: 20px 0;">
                                    <h3 style="font-size: 1rem; margin-bottom: 12px;">Stat Cards (up to 4)</h3>
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="we-form-row" style="margin-bottom: 8px;">
                                        <div class="we-form-group">
                                            <label>Value <?php echo $i + 1; ?></label>
                                            <input type="text" name="stat_value[]" class="we-form-input" value="<?php echo $e($ws_stats[$i]['value'] ?? ''); ?>" placeholder="e.g. 5,000+">
                                        </div>
                                        <div class="we-form-group">
                                            <label>Label <?php echo $i + 1; ?></label>
                                            <input type="text" name="stat_label[]" class="we-form-input" value="<?php echo $e($ws_stats[$i]['label'] ?? ''); ?>" placeholder="e.g. Active Members">
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-contact">
                                <div class="we-editor-card">
                                    <h3>Contact Information &amp; Footer</h3>
                                    <p class="we-card-desc">Displayed in the footer of your public website.</p>
                                    <div class="we-form-group">
                                        <label>Company Address</label>
                                        <textarea name="contact_address" class="we-form-textarea" rows="2" placeholder="123 Main St, City, Province"><?php echo $e($ws['contact_address']); ?></textarea>
                                    </div>
                                    <div class="we-form-row">
                                        <div class="we-form-group">
                                            <label>Phone Number</label>
                                            <input type="text" name="contact_phone" class="we-form-input" value="<?php echo $e($ws['contact_phone']); ?>" placeholder="+63 912 345 6789">
                                        </div>
                                        <div class="we-form-group">
                                            <label>Email Address</label>
                                            <input type="email" name="contact_email" class="we-form-input" value="<?php echo $e($ws['contact_email']); ?>" placeholder="info@company.com">
                                        </div>
                                    </div>
                                    <div class="we-form-group">
                                        <label>Office Hours</label>
                                        <input type="text" name="contact_hours" class="we-form-input" value="<?php echo $e($ws['contact_hours']); ?>" placeholder="Mon-Fri 8:00 AM - 5:00 PM">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Footer Description</label>
                                        <textarea name="footer_description" class="we-form-textarea" rows="3" placeholder="A short tagline or description shown in the footer..."><?php echo $e($ws['footer_description']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-visibility">
                                <div class="we-editor-card">
                                    <h3>Section Visibility</h3>
                                    <p class="we-card-desc">Choose which sections are shown on your public website.</p>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_about" value="1" <?php echo $website_config['website_show_about'] === '1' ? 'checked' : ''; ?>> Show About Section</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_services" value="1" <?php echo $website_config['website_show_services'] === '1' ? 'checked' : ''; ?>> Show Services Section</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_contact" value="1" <?php echo $website_config['website_show_contact'] === '1' ? 'checked' : ''; ?>> Show Contact Details</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_download" value="1" <?php echo $website_config['website_show_download'] === '1' ? 'checked' : ''; ?>> Show App Download Section</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_stats" value="1" <?php echo $website_config['website_show_stats'] === '1' ? 'checked' : ''; ?>> Show Trust Stats Section</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_loan_calc" value="1" <?php echo $website_config['website_show_loan_calc'] === '1' ? 'checked' : ''; ?>> Show Loan Calculator</label>
                                    </div>
                                    <div class="we-form-group">
                                        <label><input type="checkbox" name="website_show_partners" value="1" <?php echo $website_config['website_show_partners'] === '1' ? 'checked' : ''; ?>> Show Partners Strip</label>
                                    </div>
                                    <hr style="border: 0; border-top: 1px solid var(--border-color, #e2e8f0); margin: 20px 0;">
                                    <h3 style="font-size: 1rem; margin-bottom: 8px;">App Download Content</h3>
                                    <p class="we-card-desc">This section is best for your app install link only.</p>
                                    <div class="we-form-group">
                                        <label>Download Section Title</label>
                                        <input type="text" name="website_download_title" class="we-form-input" value="<?php echo $e($website_config['website_download_title']); ?>" placeholder="Download Our App">
                                    </div>
                                    <div class="we-form-group">
                                        <label>Description</label>
                                        <textarea name="website_download_description" class="we-form-textarea" rows="3" placeholder="Tell users why they should install your app."><?php echo $e($website_config['website_download_description']); ?></textarea>
                                    </div>
                                    <div class="we-form-row">
                                        <div class="we-form-group">
                                            <label>Button Text</label>
                                            <input type="text" name="website_download_button_text" class="we-form-input" value="<?php echo $e($website_config['website_download_button_text']); ?>" placeholder="Download App">
                                        </div>
                                        <div class="we-form-group">
                                            <label>App Download URL</label>
                                            <input type="url" name="website_download_url" class="we-form-input" value="<?php echo $e($website_config['website_download_url']); ?>" placeholder="https://play.google.com/store/apps/details?id=...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="we-tab-content" id="we-tab-advanced">
                                <div class="we-editor-card">
                                    <h3>Advanced Settings</h3>
                                    <p class="we-card-desc">Optional customizations for your website.</p>
                                    <div class="we-form-group">
                                        <label>SEO Meta Description</label>
                                        <input type="text" name="meta_description" class="we-form-input" value="<?php echo $e($ws['meta_description']); ?>" placeholder="A brief description for search engines..." maxlength="255">
                                        <p class="we-hint">Shown in search engine results. Max 255 characters.</p>
                                    </div>
                                    <div class="we-form-group">
                                        <label>Custom CSS</label>
                                        <textarea name="custom_css" class="we-form-textarea" rows="8" placeholder="/* Add custom CSS overrides here */" style="font-family: monospace; font-size: 0.85rem;"><?php echo $e($ws['custom_css']); ?></textarea>
                                        <p class="we-hint">Advanced: add CSS rules to override template styles. Use with caution.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="we-save-bar">
                            <a href="<?php echo $e($site_url); ?>" target="_blank" class="btn btn-outline">
                                <span class="material-symbols-rounded" style="font-size: 18px;">visibility</span> Preview Site
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-rounded" style="font-size: 18px;">save</span> Save &amp; Publish
                            </button>
                        </div>
                    </form>
                </section>

                <section id="logs" class="view-section <?php echo $active_view === 'logs' ? 'active' : ''; ?>">
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <h3>Audit Logs</h3>
                            <div class="search-box">
                                <span class="material-symbols-rounded">search</span>
                                <input type="text" placeholder="Search logs...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table log-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            No audit logs recorded yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Notification Toast -->
    <div id="toast" class="toast <?php echo $flash_message ? 'show' : ''; ?>">
        <span class="material-symbols-rounded">check_circle</span>
        <span id="toast-message"><?php echo htmlspecialchars($flash_message); ?></span>
    </div>

    <!-- Branded Confirmation Modal -->
    <div id="confirm-action-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 460px; max-width: 90vw;">
            <div class="modal-header">
                <h2 id="confirm-action-title">Confirm Action</h2>
                <button type="button" class="icon-btn" id="confirm-action-close">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirm-action-message" class="text-muted" style="margin: 0; line-height: 1.6;">Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="confirm-action-cancel" style="background: transparent; color: var(--text-main); border: 1px solid var(--border-color);">Cancel</button>
                <button type="button" class="btn" id="confirm-action-submit" style="background: var(--primary-color); color: #fff;">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-dismiss toast after 5 seconds
        (function() {
            var toast = document.getElementById('toast');
            if (toast && toast.classList.contains('show')) {
                // Auto-dismiss after 5 seconds
                setTimeout(function() {
                    toast.classList.remove('show');
                }, 5000);
                
                // Allow clicking to dismiss immediately
                toast.addEventListener('click', function() {
                    toast.classList.remove('show');
                });
            }
        })();
    </script>

    <!-- Add Staff Modal -->
    <div id="add-staff-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Add Staff Member</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('add-staff-modal').style.display='none'">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="create_staff">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>First Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Last Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span style="color:var(--danger-color);">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>

                    <div class="form-group">
                        <label>Role <span style="color:var(--danger-color);">*</span></label>
                        <select name="role_id" class="form-control" required>
                            <option value="">Select a role...</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('add-staff-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div id="create-role-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 600px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Create Custom Role</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('create-role-modal').style.display='none'"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="create_role">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Role Name <span style="color:var(--danger-color);">*</span></label>
                        <input type="text" class="form-control" name="role_name" id="create_role_name" placeholder="e.g. Moderator, Loan Officer" required>
                        <small class="text-muted">Role names are case-sensitive and must be unique.</small>
                    </div>

                    <div class="form-group">
                        <label>Load Preset Defaults</label>
                        <select id="role-preset" class="form-control" style="margin-bottom: 1rem;">
                            <option value="custom">Custom (No Preset)</option>
                            <option value="manager">Manager</option>
                            <option value="loan_officer">Loan Officer</option>
                            <option value="teller">Teller</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Initial Permissions</label>
                        <p class="text-muted" style="margin-bottom: 12px; font-size: 0.9rem;">Toggle the permissions this role should start with.</p>
                        <div id="create-role-permissions-container" style="max-height: 400px; overflow-y: auto; padding-right: 12px;">
                            <?php foreach ($grouped_permissions as $moduleName => $perms): ?>
                                <div class="permission-module" style="margin-bottom: 16px;">
                                    <h4 style="font-size: 0.9rem; margin-bottom: 8px; border-bottom: none;"><?php echo htmlspecialchars($moduleName); ?></h4>
                                    <div class="toggle-list" style="gap: 8px;">
                                        <?php foreach ($perms as $p): ?>
                                            <div class="toggle-item" style="padding: 8px 12px; border-radius: 6px; background: var(--bg-body); border: 1px solid var(--border-color);">
                                                <div class="toggle-info">
                                                    <h4 style="margin-bottom: 2px; font-weight: 500; font-size: 0.9rem; border-bottom: none;"><?php echo htmlspecialchars($p['description']); ?></h4>
                                                </div>
                                                <label class="switch" style="transform: scale(0.85); transform-origin: right;">
                                                    <input type="checkbox" name="initial_permissions[]" value="<?php echo htmlspecialchars($p['permission_code']); ?>">
                                                    <span class="slider round"></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('create-role-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="edit-staff-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Edit Staff Member</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('edit-staff-modal').style.display='none'">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="edit_staff">
                <input type="hidden" name="user_id" id="edit-staff-user-id">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>First Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="edit-staff-first-name" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Last Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="edit-staff-last-name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:var(--danger-color);">*</span></label>
                        <input type="email" class="form-control" name="email" id="edit-staff-email" required>
                    </div>
                    <div class="form-group">
                        <label>Role <span style="color:var(--danger-color);">*</span></label>
                        <select name="role_id" id="edit-staff-role-id" class="form-control" required>
                            <option value="">Select a role...</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit-staff-status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-staff-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="admin.js?v=<?php echo time(); ?>"></script>
    <script>
        // Shared branded confirmation for destructive/sensitive form actions.
        (function() {
            var modal = document.getElementById('confirm-action-modal');
            if (!modal) return;

            var titleEl = document.getElementById('confirm-action-title');
            var messageEl = document.getElementById('confirm-action-message');
            var confirmBtn = document.getElementById('confirm-action-submit');
            var cancelBtn = document.getElementById('confirm-action-cancel');
            var closeBtn = document.getElementById('confirm-action-close');
            var pendingForm = null;

            function closeModal() {
                modal.style.display = 'none';
                pendingForm = null;
            }

            function openModal(form) {
                pendingForm = form;
                titleEl.textContent = form.getAttribute('data-confirm-title') || 'Confirm Action';
                messageEl.textContent = form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
                confirmBtn.textContent = form.getAttribute('data-confirm-button') || 'Confirm';
                modal.style.display = 'flex';
            }

            document.querySelectorAll('form[data-confirm-message]').forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.dataset.confirmed === '1') {
                        form.dataset.confirmed = '0';
                        return;
                    }
                    event.preventDefault();
                    openModal(form);
                });
            });

            confirmBtn.addEventListener('click', function() {
                if (!pendingForm) return;
                var formToSubmit = pendingForm;
                closeModal();
                formToSubmit.dataset.confirmed = '1';
                formToSubmit.submit();
            });

            cancelBtn.addEventListener('click', closeModal);
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.style.display === 'flex') {
                    closeModal();
                }
            });
        })();

        // Wire up Edit Staff buttons
        document.querySelectorAll('.btn-edit-staff').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('edit-staff-user-id').value    = this.dataset.userId;
                document.getElementById('edit-staff-first-name').value = this.dataset.firstName;
                document.getElementById('edit-staff-last-name').value  = this.dataset.lastName;
                document.getElementById('edit-staff-email').value      = this.dataset.email;
                document.getElementById('edit-staff-status').value     = this.dataset.status;

                var roleSelect = document.getElementById('edit-staff-role-id');
                for (var i = 0; i < roleSelect.options.length; i++) {
                    if (roleSelect.options[i].value == this.dataset.roleId) {
                        roleSelect.selectedIndex = i;
                        break;
                    }
                }
                document.getElementById('edit-staff-modal').style.display = 'flex';
            });
        });
    </script>

    <script>
        // Live preview for ALL color pickers
        const colorMappings = [
            { id: 'primary-color', cssVar: '--primary-color', rgbVar: '--primary-rgb' }
        ];

        function hexToRgb(hex) {
            hex = hex.replace('#', '');
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            return r + ', ' + g + ', ' + b;
        }

        colorMappings.forEach(function(mapping) {
            const el = document.getElementById(mapping.id);
            if (!el) return;
            el.addEventListener('input', function() {
                document.documentElement.style.setProperty(mapping.cssVar, this.value);
                // Update the hex text display next to the picker
                const hexSpan = this.parentElement.querySelector('.color-hex');
                if (hexSpan) hexSpan.textContent = this.value;
                // If it has an RGB variant too (e.g. primary), update that
                if (mapping.rgbVar) {
                    document.documentElement.style.setProperty(mapping.rgbVar, hexToRgb(this.value));
                }
            });
        });
    </script>
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
    <script>
    // ── Website Editor JS ──
    (function() {
        // Section nav (Layout Template / Edit Content / Preview)
        var navLinks = document.querySelectorAll('#we-section-nav .we-nav-link');
        var sections = document.querySelectorAll('.we-editor-section');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                var target = link.dataset.section;
                navLinks.forEach(function(l) { l.classList.remove('active'); });
                link.classList.add('active');
                sections.forEach(function(s) { s.classList.remove('active'); });
                var el = document.getElementById(target);
                if (el) el.classList.add('active');
            });
        });

        // Content tabs (Hero / About / Services / Contact / Visibility / Advanced)
        var tabs = document.querySelectorAll('.we-editor-tab');
        var tabContents = document.querySelectorAll('.we-tab-content');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');
                tabContents.forEach(function(tc) { tc.classList.remove('active'); });
                var target = document.getElementById(tab.dataset.tab);
                if (target) target.classList.add('active');
            });
        });
    })();

    function weAddServiceRow() {
        var list = document.getElementById('we-services-list');
        var emptyMsg = list.querySelector('p');
        if (emptyMsg) emptyMsg.remove();
        var row = document.createElement('div');
        row.className = 'we-service-row';
        row.innerHTML = '<input type="text" name="service_title[]" class="we-form-input" placeholder="Service name">' +
            '<textarea name="service_description[]" class="we-form-textarea" rows="2" placeholder="Brief description"></textarea>' +
            '<input type="text" name="service_icon[]" class="we-form-input" placeholder="star" value="star">' +
            '<button type="button" class="we-btn-remove" onclick="this.closest(\'.we-service-row\').remove()" title="Remove"><span class="material-symbols-rounded">close</span></button>';
        list.appendChild(row);
    }
    </script>
</body>

</html>