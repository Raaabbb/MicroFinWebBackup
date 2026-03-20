<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 4 (branding)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 4) {
    $setup_routes = [0 => 'force_change_password.php', 1 => 'setup_loan_products.php', 2 => 'setup_credit.php', 3 => 'setup_website.php', 5 => 'setup_billing.php'];
    if (isset($setup_routes[$current_step])) {
        header('Location: ' . $setup_routes[$current_step]);
    } else {
        header('Location: ../admin_panel/admin.php');
    }
    exit;
}

$error = '';

$allowed_fonts = ['Inter', 'Poppins', 'Outfit', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Montserrat', 'DM Sans', 'Plus Jakarta Sans'];

$form_values = [
    'font_family' => 'Inter',
    'theme_primary_color' => '#dc2626',
    'theme_secondary_color' => '#991b1b',
    'theme_text_main' => '#0f172a',
    'theme_text_muted' => '#64748b',
    'theme_bg_body' => '#f8fafc',
    'theme_bg_card' => '#ffffff',
    'theme_border_color' => '#e2e8f0',
    'card_border_width' => '1',
    'card_shadow' => 'sm'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form_values as $key => $default_value) {
        $form_values[$key] = trim($_POST[$key] ?? $default_value);
    }

    $font_family = $form_values['font_family'];
    if (!in_array($font_family, $allowed_fonts, true)) {
        $font_family = 'Inter';
    }

    $primary_color = $form_values['theme_primary_color'];
    $secondary_color = $form_values['theme_secondary_color'];
    $text_main = $form_values['theme_text_main'];
    $text_muted = $form_values['theme_text_muted'];
    $bg_body = $form_values['theme_bg_body'];
    $bg_card = $form_values['theme_bg_card'];

    $border_color = $form_values['theme_border_color'];
    $border_width = max(0, min(3, round((float)$form_values['card_border_width'] * 10) / 10));
    $card_shadow = in_array($form_values['card_shadow'], ['none', 'sm', 'md', 'lg'], true) ? $form_values['card_shadow'] : 'sm';
    $form_values['card_border_width'] = (string)$border_width;
    $form_values['card_shadow'] = $card_shadow;

    $hex_pattern = '/^#[0-9a-fA-F]{6}$/';
    $skip_hex = ['font_family', 'card_border_width', 'card_shadow'];
    foreach ($form_values as $key => $value) {
        if (in_array($key, $skip_hex, true)) continue;
        if (!preg_match($hex_pattern, $value)) {
            $error = 'Please provide valid 6-digit hex color values for all palette fields.';
            break;
        }
    }

    $logo_path = '';
    if ($error === '') {
        if (!isset($_FILES['logo_file']) || (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'Company logo is required.';
        } elseif ((int) $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Logo upload failed. Please try again.';
        } else {
            $original_name = (string) ($_FILES['logo_file']['name'] ?? '');
            $tmp_name = (string) ($_FILES['logo_file']['tmp_name'] ?? '');
            $size_bytes = (int) ($_FILES['logo_file']['size'] ?? 0);
            $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

            if (!in_array($extension, $allowed_extensions, true)) {
                $error = 'Invalid logo format. Allowed formats: PNG, JPG, JPEG, WEBP, SVG.';
            } elseif ($size_bytes <= 0 || $size_bytes > (3 * 1024 * 1024)) {
                $error = 'Logo size must be between 1 byte and 3MB.';
            } elseif (!is_uploaded_file($tmp_name)) {
                $error = 'Uploaded logo file is invalid.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/tenant_logos';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
                    $error = 'Unable to create logo upload directory.';
                } else {
                    $safe_tenant_id = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
                    $file_name = $safe_tenant_id . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
                    $destination = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;

                    if (!move_uploaded_file($tmp_name, $destination)) {
                        $error = 'Failed to save uploaded logo.';
                    } else {
                        $app_base_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
                        if ($app_base_path === '') {
                            $app_base_path = '/';
                        }
                        $logo_path = $app_base_path . '/uploads/tenant_logos/' . $file_name;
                    }
                }
            }
        }
    }

    if ($error === '') {
        $stmt = $pdo->prepare('INSERT INTO tenant_branding (tenant_id, font_family, theme_primary_color, theme_secondary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, theme_border_color, card_border_width, card_shadow, logo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE font_family = VALUES(font_family), theme_primary_color = VALUES(theme_primary_color), theme_secondary_color = VALUES(theme_secondary_color), theme_text_main = VALUES(theme_text_main), theme_text_muted = VALUES(theme_text_muted), theme_bg_body = VALUES(theme_bg_body), theme_bg_card = VALUES(theme_bg_card), theme_border_color = VALUES(theme_border_color), card_border_width = VALUES(card_border_width), card_shadow = VALUES(card_shadow), logo_path = VALUES(logo_path)');
        $stmt->execute([$tenant_id, $font_family, $primary_color, $secondary_color, $text_main, $text_muted, $bg_body, $bg_card, $border_color, $border_width, $card_shadow, $logo_path]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BRANDING_SETUP', 'tenant', 'Tenant branding configured during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ? AND setup_current_step = 4')->execute([$tenant_id]);

        $_SESSION['theme'] = $primary_color;

        header('Location: setup_billing.php');
        exit;
    }
}

$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Branding - MicroFin</title>
    <link id="gfont-link" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;600;700&family=Open+Sans:wght@300;400;500;600;700&family=Lato:wght@300;400;500;600;700&family=Nunito:wght@300;400;500;600;700&family=Montserrat:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Inter", sans-serif;
            background: radial-gradient(circle at top right, #dbeafe 0%, #f8fafc 45%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wizard-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            width: 95%;
            max-width: 1600px;
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            padding: 28px 32px;
            color: white;
        }

        .wizard-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .wizard-header p { opacity: 0.9; font-size: 0.95rem; }

        .step-indicator { display: flex; gap: 8px; margin-top: 14px; }
        .step { width: 44px; height: 4px; border-radius: 999px; background: rgba(255,255,255,0.3); }
        .step.active { background: white; }

        .wizard-body { padding: 28px 32px 32px; }

        .wizard-layout {
            display: grid;
            grid-template-columns: 450px 1fr;
            gap: 32px;
            align-items: start;
        }

        .form-panel,
        .preview-panel {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            padding: 20px;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #334155;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.92rem;
            color: #0f172a;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }

        .form-hint { color: #64748b; font-size: 0.8rem; margin-top: 6px; display: block; }

        .color-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .color-input-group { display: flex; align-items: center; gap: 10px; }
        .color-input-group input[type="color"] {
            width: 42px;
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
            padding: 2px;
            background: #ffffff;
        }

        .logo-upload {
            border: 1px dashed #94a3b8;
            border-radius: 12px;
            padding: 12px;
            background: #f8fafc;
        }

        .color-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #f1f5f9;
        }
        .color-item:last-of-type { border-bottom: none; }
        .color-item-info { flex: 1; min-width: 0; }
        .color-item-info label { font-size: 0.85rem; font-weight: 600; color: #0f172a; margin-bottom: 1px; display: block; }
        .color-item-desc { font-size: 0.72rem; color: #94a3b8; }

        .sync-btn {
            display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px;
            border: 1px solid #cbd5e1; border-radius: 10px; background: #fff;
            color: #334155; font-size: 0.82rem; font-weight: 600; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
        }
        .sync-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .sync-btn.active { background: #059669; color: #fff; border-color: #059669; }
        .sync-btn.active:hover { background: #047857; border-color: #047857; }
        .sync-btn .material-symbols-rounded { font-size: 18px; }

        .btn-extract-palette {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            border: 1px solid #cbd5e1; border-radius: 10px; background: #fff;
            color: #334155; font-size: 0.82rem; font-weight: 600; cursor: pointer;
            font-family: inherit; transition: all 0.2s; margin-top: 8px; width: 100%;
            justify-content: center;
        }
        .btn-extract-palette:hover { background: #f1f5f9; border-color: #94a3b8; }
        .btn-extract-palette .material-symbols-rounded { font-size: 18px; }

        .shadow-opt {
            padding: 5px 10px; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #fff; color: #334155; font-size: 0.72rem; font-weight: 600;
            cursor: pointer; font-family: inherit; transition: all 0.15s;
        }
        .shadow-opt:hover { background: #f1f5f9; border-color: #94a3b8; }
        .shadow-opt.active { background: #059669; color: #fff; border-color: #059669; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.92rem;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #1d4ed8;
            color: #ffffff;
            width: 100%;
            justify-content: center;
            margin-top: 8px;
        }
        .btn-primary:hover { background: #1e40af; }

        .error {
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .preview-switch {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .preview-btn {
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #ffffff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 8px 12px;
            cursor: pointer;
            letter-spacing: 0.2px;
        }

        .preview-btn.active {
            background: #0f172a;
            border-color: #0f172a;
            color: #ffffff;
        }

        .preview-stage {
            --theme-primary: #dc2626;
            --theme-secondary: #991b1b;
            --theme-text-main: #0f172a;
            --theme-text-muted: #64748b;
            --theme-bg-body: #f8fafc;
            --theme-bg-card: #ffffff;
            --theme-border: #e2e8f0;
            --theme-border-subtle: #f1f5f9;
            --theme-border-color: #e2e8f0;
            --theme-card-border-width: 1px;
            --theme-card-shadow: 0 1px 3px rgba(0,0,0,0.08);

            border: 1px solid var(--theme-border);
            border-radius: 14px;
            background: var(--theme-bg-body);
            min-height: 390px;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .preview-screen { display: none; flex: 1; }
        .preview-screen.active { display: block; }

        .preview-shell {
            border: none;
            border-radius: 0;
            overflow: hidden;
            background: var(--theme-bg-card);
            min-height: 390px;
        }

        .brand-logo {
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .brand-logo img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .brand-logo span { font-weight: 700; }

        .muted { color: var(--theme-text-muted); }

        /* ── Admin Preview ── */
        .admin-layout {
            display: grid;
            grid-template-columns: 140px 1fr;
            min-height: 340px;
        }
        .admin-sidebar {
            background: var(--theme-bg-card);
            border-right: 1px solid var(--theme-border-color);
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .admin-sidebar-header {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 8px;
            border-bottom: 1px solid var(--theme-border);
        }
        .admin-sidebar-logo {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--theme-primary);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .admin-sidebar-logo .material-symbols-rounded { font-size: 14px; color: #fff; }
        .admin-sidebar-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: none; }
        .admin-sidebar-name { font-size: 0.58rem; font-weight: 700; color: var(--theme-text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .admin-sidebar-nav {
            flex: 1;
            padding: 6px 0;
            display: flex;
            flex-direction: column;
        }
        .admin-nav-item {
            display: flex; align-items: center; gap: 6px;
            padding: 5px 10px; cursor: default;
            font-size: 0.54rem; color: var(--theme-text-muted);
            border-radius: 0;
            transition: background 0.15s;
        }
        .admin-nav-item .material-symbols-rounded { font-size: 14px; }
        .admin-nav-item.active {
            background: rgba(var(--primary-r), var(--primary-g), var(--primary-b), 0.1);
            color: var(--theme-primary);
            font-weight: 600;
        }
        .admin-nav-spacer { flex: 1; }
        .admin-sidebar-footer {
            border-top: 1px solid var(--theme-border);
            padding: 4px 0;
        }
        .admin-nav-item.logout { color: #ef4444; }

        .admin-main { background: var(--theme-bg-body); display: flex; flex-direction: column; }
        .admin-topbar {
            height: 38px; background: var(--theme-bg-card); border-bottom: 1px solid var(--theme-border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 10px;
        }
        .admin-topbar-title { font-size: 0.72rem; font-weight: 600; color: var(--theme-text-main); }
        .admin-topbar-right { display: flex; align-items: center; gap: 6px; }
        .admin-topbar-avatar {
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--theme-primary); display: flex; align-items: center; justify-content: center;
            font-size: 0.52rem; font-weight: 700; color: #fff;
        }
        .admin-topbar-info { font-size: 0.52rem; color: var(--theme-text-muted); line-height: 1.2; }
        .admin-topbar-info strong { color: var(--theme-text-main); display: block; }

        .admin-dashboard-content { padding: 10px; flex: 1; }

        .admin-stat-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 10px; }
        .admin-stat-card {
            background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color); border-radius: 10px;
            padding: 8px; display: flex; align-items: center; gap: 6px; box-shadow: var(--theme-card-shadow);
        }
        .admin-stat-icon {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .admin-stat-icon .material-symbols-rounded { font-size: 15px; }
        .admin-stat-label { font-size: 0.52rem; color: var(--theme-text-muted); }
        .admin-stat-value { font-size: 0.78rem; font-weight: 700; color: var(--theme-text-main); }

        .admin-activity-card {
            background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color); border-radius: 10px; padding: 10px; box-shadow: var(--theme-card-shadow);
        }
        .admin-activity-title { font-size: 0.7rem; font-weight: 700; color: var(--theme-text-main); margin-bottom: 8px; }
        .admin-activity-item {
            display: flex; align-items: center; gap: 6px; padding: 4px 0;
            border-bottom: 1px solid var(--theme-border-subtle); font-size: 0.56rem;
        }
        .admin-activity-item:last-child { border-bottom: none; }
        .admin-activity-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .admin-activity-text { color: var(--theme-text-main); flex: 1; }
        .admin-activity-time { color: var(--theme-text-muted); white-space: nowrap; }

        /* ── Staff Preview ── */
        .staff-layout {
            display: grid;
            grid-template-columns: 82px 1fr;
            min-height: 340px;
        }
        .staff-sidebar {
            background: var(--theme-bg-card);
            border-right: var(--theme-card-border-width) solid var(--theme-border-color);
            padding: 10px 6px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            box-shadow: var(--theme-card-shadow);
        }
        .staff-sidebar-header {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            padding-bottom: 8px; margin-bottom: 4px; border-bottom: 1px solid var(--theme-border);
        }
        .staff-sidebar-logo {
            width: 30px; height: 30px; border-radius: 8px;
            background: rgba(var(--primary-r), var(--primary-g), var(--primary-b), 0.12);
            display: flex; align-items: center; justify-content: center;
        }
        .staff-sidebar-logo .material-symbols-rounded { font-size: 17px; color: var(--theme-primary); }
        .staff-sidebar-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; display: none; }
        .staff-sidebar-name { font-size: 0.56rem; font-weight: 700; color: var(--theme-text-main); text-align: center; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .staff-sidebar-sub { font-size: 0.48rem; color: var(--theme-text-muted); }

        .staff-nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 1px;
            padding: 5px 2px; border-radius: 8px;
            font-size: 0.52rem; color: var(--theme-text-muted); text-align: center;
        }
        .staff-nav-item .material-symbols-rounded { font-size: 16px; }
        .staff-nav-item.active { background: var(--theme-primary); color: #fff; }
        .staff-nav-item.logout { color: #ef4444; margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 6px; }

        .staff-main { background: var(--theme-bg-body); display: flex; flex-direction: column; }
        .staff-topbar {
            height: 36px; background: var(--theme-bg-card); border-bottom: 1px solid var(--theme-border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 10px;
        }
        .staff-topbar-title { font-size: 0.72rem; font-weight: 600; color: var(--theme-text-main); }
        .staff-topbar-right { display: flex; align-items: center; gap: 6px; }
        .staff-walkin-btn {
            font-size: 0.5rem; background: var(--theme-primary); color: #fff;
            border: none; border-radius: 6px; padding: 3px 7px;
            display: flex; align-items: center; gap: 2px; font-weight: 600;
        }
        .staff-walkin-btn .material-symbols-rounded { font-size: 12px; }
        .staff-avatar-pill {
            display: flex; align-items: center; gap: 4px;
            background: var(--theme-bg-body); border: 1px solid var(--theme-border);
            border-radius: 999px; padding: 2px 8px 2px 2px;
        }
        .staff-avatar-circle {
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--theme-primary); display: flex; align-items: center; justify-content: center;
            font-size: 0.5rem; font-weight: 700; color: #fff;
        }
        .staff-avatar-info { font-size: 0.48rem; color: var(--theme-text-muted); line-height: 1.2; }
        .staff-avatar-info strong { color: var(--theme-text-main); display: block; }

        .staff-dashboard-content { padding: 10px; flex: 1; }
        .staff-welcome-card {
            background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color); border-radius: 10px;
            padding: 10px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; box-shadow: var(--theme-card-shadow);
        }
        .staff-welcome-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(var(--primary-r), var(--primary-g), var(--primary-b), 0.12);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .staff-welcome-icon .material-symbols-rounded { font-size: 20px; color: var(--theme-primary); }
        .staff-welcome-text h4 { font-size: 0.72rem; color: var(--theme-text-main); margin: 0; }
        .staff-welcome-text p { font-size: 0.52rem; color: var(--theme-text-muted); margin: 2px 0 0; }

        .staff-widget-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .staff-widget-card {
            background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color); border-radius: 10px; padding: 8px; box-shadow: var(--theme-card-shadow);
        }
        .staff-widget-header { display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
        .staff-widget-header .material-symbols-rounded { font-size: 14px; color: var(--theme-primary); }
        .staff-widget-header span:last-child { font-size: 0.58rem; font-weight: 700; color: var(--theme-text-main); }
        .staff-widget-value { font-size: 0.9rem; font-weight: 700; color: var(--theme-text-main); }
        .staff-widget-sub { font-size: 0.48rem; color: var(--theme-text-muted); }

        /* ── Mobile / Client Preview ── */
        .phone-shell {
            width: 230px;
            margin: 14px auto;
            border: 8px solid #1e293b;
            border-radius: 28px;
            background: var(--theme-bg-body);
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.2);
        }
        .phone-notch {
            width: 80px; height: 20px;
            background: #1e293b;
            margin: 0 auto;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .phone-statusbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 4px 12px 2px; font-size: 0.46rem; color: var(--theme-text-muted);
        }

        .client-home-header {
            padding: 10px 12px 12px; color: var(--theme-text-main);
        }
        .client-greeting { font-size: 0.64rem; color: var(--theme-text-muted); }
        .client-name { font-size: 0.82rem; font-weight: 700; margin-top: 1px; }

        .client-balance-card {
            margin: 0 10px 8px; padding: 12px;
            background: var(--theme-primary); border-radius: 12px; color: #fff;
        }
        .client-balance-label { font-size: 0.5rem; opacity: 0.85; }
        .client-balance-amount { font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px; margin: 2px 0; }
        .client-balance-sub { font-size: 0.48rem; opacity: 0.75; }
        .client-balance-row { display: flex; justify-content: space-between; margin-top: 8px; }
        .client-balance-stat { text-align: center; }
        .client-balance-stat-val { font-size: 0.62rem; font-weight: 700; }
        .client-balance-stat-lbl { font-size: 0.42rem; opacity: 0.8; }

        .client-quick-actions {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px;
            margin: 0 10px 8px; text-align: center;
        }
        .client-action-btn {
            display: flex; flex-direction: column; align-items: center; gap: 2px;
            padding: 6px 2px; background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color);
            border-radius: 10px; font-size: 0.42rem; color: var(--theme-text-muted); box-shadow: var(--theme-card-shadow);
        }
        .client-action-btn .material-symbols-rounded { font-size: 16px; color: var(--theme-primary); }

        .client-section-title {
            font-size: 0.58rem; font-weight: 700; color: var(--theme-text-main);
            padding: 0 12px; margin-bottom: 4px;
        }
        .client-loan-card {
            margin: 0 10px 6px; padding: 8px 10px;
            background: var(--theme-bg-card); border: var(--theme-card-border-width) solid var(--theme-border-color); border-radius: 10px; box-shadow: var(--theme-card-shadow);
        }
        .client-loan-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; }
        .client-loan-name { font-size: 0.56rem; font-weight: 600; color: var(--theme-text-main); }
        .client-loan-badge {
            font-size: 0.4rem; font-weight: 700; padding: 1px 5px; border-radius: 999px;
            background: #dcfce7; color: #15803d;
        }
        .client-loan-progress { height: 4px; background: var(--theme-border); border-radius: 999px; margin: 4px 0 3px; overflow: hidden; }
        .client-loan-progress-fill { height: 100%; border-radius: 999px; background: var(--theme-primary); }
        .client-loan-details { display: flex; justify-content: space-between; font-size: 0.44rem; color: var(--theme-text-muted); }

        .phone-bottom-nav {
            display: flex; justify-content: space-around; align-items: center;
            padding: 6px 0; border-top: 1px solid var(--theme-border);
            background: var(--theme-bg-card);
        }
        .phone-nav-item { display: flex; flex-direction: column; align-items: center; gap: 1px; font-size: 0.4rem; color: var(--theme-text-muted); }
        .phone-nav-item .material-symbols-rounded { font-size: 16px; }
        .phone-nav-item.active { color: var(--theme-primary); }

        @media (max-width: 1024px) {
            .wizard-layout { grid-template-columns: 1fr; }
            .preview-stage { min-height: 320px; }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Choose Your Look</h1>
            <p>Pick a theme for <?php echo htmlspecialchars($tenant_name); ?> — you can always fine-tune it later in Settings.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
        </div>
        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" id="branding-form">
                <div class="wizard-layout">
                    <div class="form-panel">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                            <div class="panel-title" style="margin-bottom:0;">Your Brand Colors</div>
                            <button type="button" id="sync-btn" class="sync-btn" title="Automatically adjusts text colors for readability">
                                <span class="material-symbols-rounded">contrast</span>
                                Smart Contrast Sync: Off
                            </button>
                        </div>
                        <span class="form-hint" style="margin-bottom:12px;">Keeps text readable against your chosen backgrounds.</span>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Company Logo <span style="color:#dc2626;">*</span></label>
                            <div class="logo-upload">
                                <input type="file" class="form-control" id="logo_file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" required>
                                <span class="form-hint">PNG, JPG, WEBP, or SVG — max 3MB.</span>
                            </div>
                            <button type="button" id="extract-palette-btn" class="btn-extract-palette" style="display:none;">
                                <span class="material-symbols-rounded">palette</span>
                                Match Colors from Logo
                            </button>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Brand Color</label>
                                <span class="color-item-desc">Buttons, links, active highlights</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-primary" value="<?php echo htmlspecialchars($form_values['theme_primary_color']); ?>">
                                <input type="text" class="form-control" id="theme_primary_color" name="theme_primary_color" value="<?php echo htmlspecialchars($form_values['theme_primary_color']); ?>" maxlength="7">
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Page Background</label>
                                <span class="color-item-desc">Main area behind cards and content</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-bg-body" value="<?php echo htmlspecialchars($form_values['theme_bg_body']); ?>">
                                <input type="text" class="form-control" id="theme_bg_body" name="theme_bg_body" value="<?php echo htmlspecialchars($form_values['theme_bg_body']); ?>" maxlength="7">
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Card & Sidebar</label>
                                <span class="color-item-desc">Panels, cards, sidebar background</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-bg-card" value="<?php echo htmlspecialchars($form_values['theme_bg_card']); ?>">
                                <input type="text" class="form-control" id="theme_bg_card" name="theme_bg_card" value="<?php echo htmlspecialchars($form_values['theme_bg_card']); ?>" maxlength="7">
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Heading Text</label>
                                <span class="color-item-desc">Titles, nav labels, sidebar items</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-text-main" value="<?php echo htmlspecialchars($form_values['theme_text_main']); ?>">
                                <input type="text" class="form-control" id="theme_text_main" name="theme_text_main" value="<?php echo htmlspecialchars($form_values['theme_text_main']); ?>" maxlength="7">
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Body Text</label>
                                <span class="color-item-desc">Paragraphs, descriptions, timestamps</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-text-muted" value="<?php echo htmlspecialchars($form_values['theme_text_muted']); ?>">
                                <input type="text" class="form-control" id="theme_text_muted" name="theme_text_muted" value="<?php echo htmlspecialchars($form_values['theme_text_muted']); ?>" maxlength="7">
                            </div>
                        </div>

                        <input type="hidden" name="theme_secondary_color" id="theme_secondary_color" value="<?php echo htmlspecialchars($form_values['theme_secondary_color']); ?>">

                        <div class="panel-title" style="margin-top:18px; margin-bottom:6px; font-size:0.82rem;">Card & Sidebar Style</div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Border Color</label>
                                <span class="color-item-desc">Card & sidebar edges, dividers, separators</span>
                            </div>
                            <div class="color-input-group">
                                <input type="color" id="picker-border-color" value="<?php echo htmlspecialchars($form_values['theme_border_color']); ?>">
                                <input type="text" class="form-control" id="theme_border_color" name="theme_border_color" value="<?php echo htmlspecialchars($form_values['theme_border_color']); ?>" maxlength="7">
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Border Width</label>
                                <span class="color-item-desc">Thickness of card & sidebar borders (0 = none)</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="range" id="card_border_width" name="card_border_width" min="0" max="3" step="0.1" value="<?php echo htmlspecialchars($form_values['card_border_width']); ?>" style="width:120px; accent-color:var(--theme-primary, #dc2626);">
                                <span id="border-width-label" style="font-size:0.78rem; font-weight:600; color:#334155; min-width:32px;"><?php echo htmlspecialchars($form_values['card_border_width']); ?>px</span>
                            </div>
                        </div>

                        <div class="color-item">
                            <div class="color-item-info">
                                <label>Card & Sidebar Shadow</label>
                                <span class="color-item-desc">Depth & elevation of cards & sidebar</span>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <?php
                                $shadow_options = ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'];
                                foreach ($shadow_options as $val => $label): ?>
                                <button type="button" class="shadow-opt<?php echo $form_values['card_shadow'] === $val ? ' active' : ''; ?>" data-shadow="<?php echo $val; ?>"><?php echo $label; ?></button>
                                <?php endforeach; ?>
                                <input type="hidden" id="card_shadow" name="card_shadow" value="<?php echo htmlspecialchars($form_values['card_shadow']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Font Style</label>
                            <select class="form-control" id="font_family" name="font_family">
                                <?php foreach ($allowed_fonts as $f): ?>
                                <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $form_values['font_family'] === $f ? 'selected' : ''; ?> style="font-family:'<?php echo htmlspecialchars($f); ?>',sans-serif"><?php echo htmlspecialchars($f); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-rounded">arrow_forward</span>
                            Save & Continue
                        </button>
                    </div>

                    <div class="preview-panel">
                        <div class="panel-title">Preview</div>
                        <div class="preview-switch">
                            <button type="button" class="preview-btn active" data-view="admin">Admin View</button>
                            <button type="button" class="preview-btn" data-view="staff">Staff View</button>
                            <button type="button" class="preview-btn" data-view="mobile">Client App View</button>
                        </div>

                        <div class="preview-stage" id="preview-stage">
                            <!-- ═══ ADMIN VIEW ═══ -->
                            <div class="preview-screen active" data-preview="admin">
                                <div class="preview-shell admin-layout">
                                    <div class="admin-sidebar">
                                        <div class="admin-sidebar-header">
                                            <div class="admin-sidebar-logo">
                                                <span class="material-symbols-rounded">diamond</span>
                                                <img class="preview-logo-image" alt="Logo">
                                            </div>
                                            <div class="admin-sidebar-name"><?php echo htmlspecialchars($tenant_name); ?></div>
                                        </div>
                                        <div class="admin-sidebar-nav">
                                            <div class="admin-nav-item active"><span class="material-symbols-rounded">dashboard</span>Dashboard</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">groups</span>Staff & Roles</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">language</span>Website</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">receipt_long</span>Billing</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">settings</span>Settings</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">toggle_on</span>Toggles</div>
                                            <div class="admin-nav-item"><span class="material-symbols-rounded">manage_search</span>Audit Logs</div>
                                        </div>
                                        <div class="admin-sidebar-footer">
                                            <div class="admin-nav-item logout"><span class="material-symbols-rounded">logout</span>Logout</div>
                                        </div>
                                    </div>
                                    <div class="admin-main">
                                        <div class="admin-topbar">
                                            <div class="admin-topbar-title">Dashboard</div>
                                            <div class="admin-topbar-right">
                                                <span class="material-symbols-rounded" style="font-size:15px; color:var(--theme-text-muted); cursor:default;">dark_mode</span>
                                                <div class="admin-topbar-avatar">A</div>
                                                <div class="admin-topbar-info"><strong><?php echo htmlspecialchars($tenant_name); ?></strong>Admin</div>
                                            </div>
                                        </div>
                                        <div class="admin-dashboard-content">
                                            <div class="admin-stat-grid">
                                                <div class="admin-stat-card">
                                                    <div class="admin-stat-icon" style="background:rgba(var(--primary-r),var(--primary-g),var(--primary-b),0.1);">
                                                        <span class="material-symbols-rounded" style="color:var(--theme-primary);">book</span>
                                                    </div>
                                                    <div>
                                                        <div class="admin-stat-label">Total Clients</div>
                                                        <div class="admin-stat-value">1,240</div>
                                                    </div>
                                                </div>
                                                <div class="admin-stat-card">
                                                    <div class="admin-stat-icon" style="background:rgba(34,197,94,0.1);">
                                                        <span class="material-symbols-rounded" style="color:#22c55e;">group</span>
                                                    </div>
                                                    <div>
                                                        <div class="admin-stat-label">Active Staff</div>
                                                        <div class="admin-stat-value">24</div>
                                                    </div>
                                                </div>
                                                <div class="admin-stat-card">
                                                    <div class="admin-stat-icon" style="background:rgba(239,68,68,0.1);">
                                                        <span class="material-symbols-rounded" style="color:#ef4444;">warning</span>
                                                    </div>
                                                    <div>
                                                        <div class="admin-stat-label">Alerts</div>
                                                        <div class="admin-stat-value">3</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="admin-activity-card">
                                                <div class="admin-activity-title">Recent Activity</div>
                                                <div class="admin-activity-item">
                                                    <div class="admin-activity-dot" style="background:var(--theme-primary);"></div>
                                                    <span class="admin-activity-text">New staff account created</span>
                                                    <span class="admin-activity-time">2m ago</span>
                                                </div>
                                                <div class="admin-activity-item">
                                                    <div class="admin-activity-dot" style="background:#22c55e;"></div>
                                                    <span class="admin-activity-text">Loan product updated</span>
                                                    <span class="admin-activity-time">15m ago</span>
                                                </div>
                                                <div class="admin-activity-item">
                                                    <div class="admin-activity-dot" style="background:#f59e0b;"></div>
                                                    <span class="admin-activity-text">Payment method added</span>
                                                    <span class="admin-activity-time">1h ago</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ STAFF VIEW ═══ -->
                            <div class="preview-screen" data-preview="staff">
                                <div class="preview-shell staff-layout">
                                    <div class="staff-sidebar">
                                        <div class="staff-sidebar-header">
                                            <div class="staff-sidebar-logo">
                                                <span class="material-symbols-rounded">account_balance</span>
                                                <img class="preview-logo-image" alt="Logo">
                                            </div>
                                            <div class="staff-sidebar-name"><?php echo htmlspecialchars($tenant_name); ?></div>
                                            <div class="staff-sidebar-sub">Employee Portal</div>
                                        </div>
                                        <div class="staff-nav-item active"><span class="material-symbols-rounded">home</span>Home</div>
                                        <div class="staff-nav-item"><span class="material-symbols-rounded">group</span>Clients</div>
                                        <div class="staff-nav-item"><span class="material-symbols-rounded">real_estate_agent</span>Loans</div>
                                        <div class="staff-nav-item"><span class="material-symbols-rounded">description</span>Applications</div>
                                        <div class="staff-nav-item"><span class="material-symbols-rounded">payments</span>Payments</div>
                                        <div class="staff-nav-item"><span class="material-symbols-rounded">bar_chart</span>Reports</div>
                                        <div class="staff-nav-spacer" style="flex:1;"></div>
                                        <div class="staff-nav-item logout"><span class="material-symbols-rounded">logout</span>Sign Out</div>
                                    </div>
                                    <div class="staff-main">
                                        <div class="staff-topbar">
                                            <div class="staff-topbar-title">Home</div>
                                            <div class="staff-topbar-right">
                                                <div class="staff-walkin-btn"><span class="material-symbols-rounded">person_add</span>Walk-In</div>
                                                <div class="staff-avatar-pill">
                                                    <div class="staff-avatar-circle">J</div>
                                                    <div class="staff-avatar-info"><strong>Juan</strong>Loan Officer</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="staff-dashboard-content">
                                            <div class="staff-welcome-card">
                                                <div class="staff-welcome-icon"><span class="material-symbols-rounded">waving_hand</span></div>
                                                <div class="staff-welcome-text">
                                                    <h4>Welcome back, Juan!</h4>
                                                    <p>Here's your daily overview and pending tasks.</p>
                                                </div>
                                            </div>
                                            <div class="staff-widget-grid">
                                                <div class="staff-widget-card">
                                                    <div class="staff-widget-header">
                                                        <span class="material-symbols-rounded">task</span>
                                                        <span>Pending Loans</span>
                                                    </div>
                                                    <div class="staff-widget-value">8</div>
                                                    <div class="staff-widget-sub">Awaiting review</div>
                                                </div>
                                                <div class="staff-widget-card">
                                                    <div class="staff-widget-header">
                                                        <span class="material-symbols-rounded">receipt_long</span>
                                                        <span>Today's Collections</span>
                                                    </div>
                                                    <div class="staff-widget-value">₱48,200</div>
                                                    <div class="staff-widget-sub">12 payments received</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ MOBILE / CLIENT (LOANER) VIEW ═══ -->
                            <div class="preview-screen" data-preview="mobile">
                                <div class="phone-shell">
                                    <div class="phone-notch"></div>
                                    <div class="phone-statusbar">
                                        <span>9:41</span>
                                        <span style="display:flex;gap:3px;align-items:center;">
                                            <span class="material-symbols-rounded" style="font-size:10px;">signal_cellular_alt</span>
                                            <span class="material-symbols-rounded" style="font-size:10px;">wifi</span>
                                            <span class="material-symbols-rounded" style="font-size:10px;">battery_full</span>
                                        </span>
                                    </div>
                                    <div class="client-home-header">
                                        <div class="client-greeting">Good morning,</div>
                                        <div class="client-name" style="color:var(--theme-text-main);">Maria Santos</div>
                                    </div>
                                    <div class="client-balance-card">
                                        <div class="client-balance-label">Outstanding Balance</div>
                                        <div class="client-balance-amount">₱24,500.00</div>
                                        <div class="client-balance-sub">Next payment: Mar 28, 2026</div>
                                        <div class="client-balance-row">
                                            <div class="client-balance-stat">
                                                <div class="client-balance-stat-val">₱2,450</div>
                                                <div class="client-balance-stat-lbl">Monthly Due</div>
                                            </div>
                                            <div class="client-balance-stat">
                                                <div class="client-balance-stat-val">6 / 12</div>
                                                <div class="client-balance-stat-lbl">Payments Made</div>
                                            </div>
                                            <div class="client-balance-stat">
                                                <div class="client-balance-stat-val">On Time</div>
                                                <div class="client-balance-stat-lbl">Status</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="client-quick-actions">
                                        <div class="client-action-btn"><span class="material-symbols-rounded">payments</span>Pay Now</div>
                                        <div class="client-action-btn"><span class="material-symbols-rounded">add_circle</span>Apply</div>
                                        <div class="client-action-btn"><span class="material-symbols-rounded">calendar_month</span>Schedule</div>
                                        <div class="client-action-btn"><span class="material-symbols-rounded">chat</span>Support</div>
                                    </div>
                                    <div class="client-section-title">Active Loan</div>
                                    <div class="client-loan-card">
                                        <div class="client-loan-top">
                                            <span class="client-loan-name">Personal Loan</span>
                                            <span class="client-loan-badge">Active</span>
                                        </div>
                                        <div class="client-loan-progress"><div class="client-loan-progress-fill" style="width:50%;"></div></div>
                                        <div class="client-loan-details">
                                            <span>₱24,500 remaining</span>
                                            <span>₱49,000 total</span>
                                        </div>
                                    </div>
                                    <div class="phone-bottom-nav">
                                        <div class="phone-nav-item active"><span class="material-symbols-rounded">home</span>Home</div>
                                        <div class="phone-nav-item"><span class="material-symbols-rounded">receipt_long</span>Loans</div>
                                        <div class="phone-nav-item"><span class="material-symbols-rounded">notifications</span>Alerts</div>
                                        <div class="phone-nav-item"><span class="material-symbols-rounded">person</span>Profile</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const previewStage = document.getElementById('preview-stage');
        const previewButtons = document.querySelectorAll('.preview-btn');
        const previewScreens = document.querySelectorAll('.preview-screen');
        const logoInput = document.getElementById('logo_file');

        let autoSyncEnabled = false;

        function wireColorPair(pickerId, textId) {
            const picker = document.getElementById(pickerId);
            const input = document.getElementById(textId);
            if (!picker || !input) return;
            picker.addEventListener('input', () => { input.value = picker.value; updatePreview(); if (autoSyncEnabled) syncColors(); });
            input.addEventListener('input', () => {
                if (/^#[0-9a-fA-F]{6}$/.test(input.value)) picker.value = input.value;
                updatePreview(); if (autoSyncEnabled) syncColors();
            });
        }

        function hexToRgb(hex) {
            return { r: parseInt(hex.slice(1,3),16), g: parseInt(hex.slice(3,5),16), b: parseInt(hex.slice(5,7),16) };
        }
        function rgbToHex(r, g, b) {
            return '#' + [r,g,b].map(v => Math.max(0,Math.min(255,Math.round(v))).toString(16).padStart(2,'0')).join('');
        }
        function adjustBrightness(hex, pct) {
            const {r,g,b} = hexToRgb(hex), f = pct/100;
            if (f > 0) return rgbToHex(r+(255-r)*f, g+(255-g)*f, b+(255-b)*f);
            const d = 1+f; return rgbToHex(r*d, g*d, b*d);
        }
        function luminance(hex) {
            const {r,g,b} = hexToRgb(hex);
            const [rs,gs,bs] = [r,g,b].map(c => { c=c/255; return c<=0.03928 ? c/12.92 : Math.pow((c+0.055)/1.055,2.4); });
            return 0.2126*rs + 0.7152*gs + 0.0722*bs;
        }
        function contrastRatio(a,b) {
            const l1=luminance(a), l2=luminance(b);
            return (Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05);
        }

        function syncColors() {
            const primary = document.getElementById('theme_primary_color').value || '#dc2626';
            const bgBody  = document.getElementById('theme_bg_body').value || '#f8fafc';
            const bgCard  = document.getElementById('theme_bg_card').value || '#ffffff';
            const isDarkCard = luminance(bgCard) < 0.18;

            // 1) Auto-derive secondary from primary
            const secondary = isDarkCard ? adjustBrightness(primary, 30) : adjustBrightness(primary, -25);
            document.getElementById('theme_secondary_color').value = secondary;

            // 2) Pick heading text that works on BOTH card and page background
            const lightH = '#f1f5f9', darkH = '#0f172a';
            const lightMinH = Math.min(contrastRatio(lightH, bgCard), contrastRatio(lightH, bgBody));
            const darkMinH  = Math.min(contrastRatio(darkH, bgCard), contrastRatio(darkH, bgBody));
            const textMain = lightMinH > darkMinH ? lightH : darkH;
            document.getElementById('theme_text_main').value = textMain;
            document.getElementById('picker-text-main').value = textMain;

            // 3) Pick body text that works on BOTH surfaces
            const lightM = '#94a3b8', darkM = '#64748b';
            const lightMinM = Math.min(contrastRatio(lightM, bgCard), contrastRatio(lightM, bgBody));
            const darkMinM  = Math.min(contrastRatio(darkM, bgCard), contrastRatio(darkM, bgBody));
            const textMuted = lightMinM > darkMinM ? lightM : darkM;
            document.getElementById('theme_text_muted').value = textMuted;
            document.getElementById('picker-text-muted').value = textMuted;

            updatePreview();

        }

        function updatePreview() {
            const props = {
                '--theme-primary':   document.getElementById('theme_primary_color').value || '#dc2626',
                '--theme-secondary': document.getElementById('theme_secondary_color').value || '#991b1b',
                '--theme-text-main': document.getElementById('theme_text_main').value || '#0f172a',
                '--theme-text-muted':document.getElementById('theme_text_muted').value || '#64748b',
                '--theme-bg-body':   document.getElementById('theme_bg_body').value || '#f8fafc',
                '--theme-bg-card':   document.getElementById('theme_bg_card').value || '#ffffff'
            };
            Object.entries(props).forEach(([k,v]) => previewStage.style.setProperty(k,v));
            const rgb = hexToRgb(props['--theme-primary']);
            previewStage.style.setProperty('--primary-r', rgb.r);
            previewStage.style.setProperty('--primary-g', rgb.g);
            previewStage.style.setProperty('--primary-b', rgb.b);

            // Auto-compute border colors from card background
            const cardLum = luminance(props['--theme-bg-card']);
            const border = cardLum < 0.18 ? adjustBrightness(props['--theme-bg-card'], 18) : adjustBrightness(props['--theme-bg-card'], -8);
            const borderSubtle = cardLum < 0.18 ? adjustBrightness(props['--theme-bg-card'], 10) : adjustBrightness(props['--theme-bg-card'], -4);
            previewStage.style.setProperty('--theme-border', border);
            previewStage.style.setProperty('--theme-border-subtle', borderSubtle);

            // Border color, width, shadow
            const borderColor = document.getElementById('theme_border_color').value || '#e2e8f0';
            const borderWidth = document.getElementById('card_border_width').value || '1';
            const shadowVal = document.getElementById('card_shadow').value || 'sm';
            const shadowMap = { none: 'none', sm: '0 1px 3px rgba(0,0,0,0.08)', md: '0 4px 12px rgba(0,0,0,0.1)', lg: '0 8px 24px rgba(0,0,0,0.14)' };
            previewStage.style.setProperty('--theme-border-color', borderColor);
            previewStage.style.setProperty('--theme-card-border-width', borderWidth + 'px');
            previewStage.style.setProperty('--theme-card-shadow', shadowMap[shadowVal] || shadowMap.sm);
        }

        function updateLogoPreview() {
            const allLogoImages = document.querySelectorAll('.preview-logo-image');
            const allIconFallbacks = document.querySelectorAll('.admin-sidebar-logo > .material-symbols-rounded, .staff-sidebar-logo > .material-symbols-rounded');
            if (!logoInput || !logoInput.files || !logoInput.files[0]) {
                allLogoImages.forEach(img => { img.removeAttribute('src'); img.style.display = 'none'; });
                allIconFallbacks.forEach(el => { el.style.display = 'inline'; });
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                allLogoImages.forEach(img => { img.src = e.target.result; img.style.display = 'block'; });
                allIconFallbacks.forEach(el => { el.style.display = 'none'; });
            };
            reader.readAsDataURL(logoInput.files[0]);
        }

        function extractPaletteFromLogo() {
            if (!logoInput || !logoInput.files || !logoInput.files[0]) return;
            const file = logoInput.files[0];
            if (file.type === 'image/svg+xml') return; // can't sample SVG pixels

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const size = 64; // sample at small size for speed
                    canvas.width = size;
                    canvas.height = size;
                    ctx.drawImage(img, 0, 0, size, size);
                    const data = ctx.getImageData(0, 0, size, size).data;

                    // Collect non-white/non-black/non-transparent pixels
                    const pixels = [];
                    for (let i = 0; i < data.length; i += 4) {
                        const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
                        if (a < 128) continue; // skip transparent
                        const lum = (0.299*r + 0.587*g + 0.114*b);
                        if (lum > 245 || lum < 10) continue; // skip near-white/black
                        pixels.push([r, g, b]);
                    }
                    if (pixels.length < 5) return; // not enough color data

                    // Simple k-means with k=3 to find dominant clusters
                    const k = 3;
                    let centroids = pixels.filter((_, i) => i % Math.max(1, Math.floor(pixels.length / k)) === 0).slice(0, k);
                    if (centroids.length < k) return;

                    for (let iter = 0; iter < 10; iter++) {
                        const clusters = centroids.map(() => []);
                        pixels.forEach(px => {
                            let minDist = Infinity, nearest = 0;
                            centroids.forEach((c, ci) => {
                                const d = (px[0]-c[0])**2 + (px[1]-c[1])**2 + (px[2]-c[2])**2;
                                if (d < minDist) { minDist = d; nearest = ci; }
                            });
                            clusters[nearest].push(px);
                        });
                        centroids = clusters.map((cl, ci) => {
                            if (cl.length === 0) return centroids[ci];
                            const avg = [0, 0, 0];
                            cl.forEach(px => { avg[0] += px[0]; avg[1] += px[1]; avg[2] += px[2]; });
                            return avg.map(v => Math.round(v / cl.length));
                        });
                    }

                    // Sort by saturation (most saturated = brand color)
                    function saturation(rgb) {
                        const r = rgb[0]/255, g = rgb[1]/255, b = rgb[2]/255;
                        const max = Math.max(r,g,b), min = Math.min(r,g,b);
                        if (max === 0) return 0;
                        return (max - min) / max;
                    }
                    centroids.sort((a, b) => saturation(b) - saturation(a));

                    function rgbToHex(rgb) {
                        return '#' + rgb.map(v => Math.min(255, Math.max(0, v)).toString(16).padStart(2, '0')).join('');
                    }

                    // Apply: most saturated → Brand Color
                    const brandColor = rgbToHex(centroids[0]);
                    setColorField('picker-primary', 'theme_primary_color', brandColor);

                    // Second color → border color (desaturated variant)
                    if (centroids[1]) {
                        const borderHex = rgbToHex(centroids[1]);
                        setColorField('picker-border-color', 'theme_border_color', borderHex);
                    }

                    // Derive bg-body: very light tint of brand
                    const tint = centroids[0].map(v => Math.round(v + (255 - v) * 0.92));
                    setColorField('picker-bg-body', 'theme_bg_body', rgbToHex(tint));

                    // Card/sidebar: white or very near-white
                    setColorField('picker-bg-card', 'theme_bg_card', '#ffffff');

                    // Text: dark shade of brand
                    const dark = centroids[0].map(v => Math.round(v * 0.2));
                    setColorField('picker-text-main', 'theme_text_main', rgbToHex(dark));
                    setColorField('picker-text-muted', 'theme_text_muted', '#64748b');

                    updatePreview();
                    if (autoSyncEnabled) syncColors();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function setColorField(pickerId, textId, hex) {
            const picker = document.getElementById(pickerId);
            const text = document.getElementById(textId);
            if (picker) picker.value = hex;
            if (text) text.value = hex;
        }

        document.getElementById('sync-btn').addEventListener('click', function() {
            autoSyncEnabled = !autoSyncEnabled;
            this.classList.toggle('active', autoSyncEnabled);
            this.innerHTML = autoSyncEnabled
                ? '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: On'
                : '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: Off';
            if (autoSyncEnabled) syncColors();
        });

        previewButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-view');
                previewButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                previewScreens.forEach(screen => {
                    screen.classList.toggle('active', screen.getAttribute('data-preview') === target);
                });
            });
        });

        if (logoInput) logoInput.addEventListener('change', function() {
            updateLogoPreview();
            const btn = document.getElementById('extract-palette-btn');
            if (btn) btn.style.display = (logoInput.files && logoInput.files[0] && logoInput.files[0].type !== 'image/svg+xml') ? 'inline-flex' : 'none';
        });

        document.getElementById('extract-palette-btn').addEventListener('click', extractPaletteFromLogo);

        wireColorPair('picker-primary', 'theme_primary_color');
        wireColorPair('picker-text-main', 'theme_text_main');
        wireColorPair('picker-text-muted', 'theme_text_muted');
        wireColorPair('picker-bg-body', 'theme_bg_body');
        wireColorPair('picker-bg-card', 'theme_bg_card');
        wireColorPair('picker-border-color', 'theme_border_color');

        // Border width slider
        document.getElementById('card_border_width').addEventListener('input', function() {
            document.getElementById('border-width-label').textContent = this.value + 'px';
            updatePreview();
        });

        // Shadow option buttons
        document.querySelectorAll('.shadow-opt').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.shadow-opt').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('card_shadow').value = this.dataset.shadow;
                updatePreview();
            });
        });

        document.getElementById('font_family').addEventListener('change', function() {
            previewStage.style.fontFamily = "'" + this.value + "', sans-serif";
        });

        updatePreview();
        updateLogoPreview();
    </script>
</body>
</html>
