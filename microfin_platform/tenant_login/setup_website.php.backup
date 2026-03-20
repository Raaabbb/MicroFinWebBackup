<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';

if ($tenant_id === '') {
    header('Location: login.php');
    exit;
}

// Branding must be completed before website setup.
$branding_check = $pdo->prepare('SELECT branding_id, theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$branding_check->execute([$tenant_id]);
$branding = $branding_check->fetch(PDO::FETCH_ASSOC);

$accent = ($branding['theme_primary_color'] ?? '#0284c7');
$t_text = ($branding['theme_text_main'] ?? '#0f172a');
$t_muted = ($branding['theme_text_muted'] ?? '#64748b');
$t_bg = ($branding['theme_bg_body'] ?? '#f8fafc');
$t_card = ($branding['theme_bg_card'] ?? '#ffffff');
$t_font = ($branding['font_family'] ?? 'Inter');
$error = '';

// Check current setup step — this page is step 3 (website)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 3) {
    $setup_routes = [2 => 'setup_branding.php', 1 => 'setup_billing.php'];
    if (isset($setup_routes[$current_step])) {
        header('Location: ' . $setup_routes[$current_step]);
    } else {
        header('Location: ../admin_panel/admin.php');
    }
    exit;
}

$download_url_setting_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'website_download_url' LIMIT 1");
$download_url_setting_stmt->execute([$tenant_id]);
$download_url_setting = $download_url_setting_stmt->fetchColumn();
$system_download_url = trim((string) ($download_url_setting ?: ''));

$bg_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'website_hero_background' LIMIT 1");
$bg_stmt->execute([$tenant_id]);
$hero_bg_setting = $bg_stmt->fetchColumn();
$system_hero_background = trim((string) ($hero_bg_setting ?: ''));

$defaults = [
    'layout_template' => 'template1',
    'hero_title' => 'Welcome to ' . $tenant_name,
    'hero_subtitle' => 'Your trusted microfinance partner',
    'hero_description' => '',
    'about_body' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'contact_address' => '',
    'contact_hours' => '',
    'website_show_about' => '1',
    'website_show_services' => '1',
    'website_show_contact' => '1',
    'website_show_download' => '1',
    'website_download_title' => 'Download Our App',
    'website_download_description' => 'Get the app for faster loan tracking and updates.',
    'website_download_button_text' => 'Download App',
    'website_download_url' => $system_download_url
];

$form = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layout = $_POST['layout_template'] ?? 'template1';
    if (!in_array($layout, ['template1', 'template2', 'template3'], true)) {
        $layout = 'template1';
    }

    $hero_title = trim($_POST['hero_title'] ?? '');
    $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
    $hero_description = trim($_POST['hero_description'] ?? '');
    $about_body = trim($_POST['about_body'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_address = trim($_POST['contact_address'] ?? '');
    $contact_hours = trim($_POST['contact_hours'] ?? '');

    $show_about = isset($_POST['website_show_about']) ? '1' : '0';
    $show_services = isset($_POST['website_show_services']) ? '1' : '0';
    $show_contact = isset($_POST['website_show_contact']) ? '1' : '0';
    $show_download = isset($_POST['website_show_download']) ? '1' : '0';

    $download_title = trim($_POST['website_download_title'] ?? '');
    $download_description = trim($_POST['website_download_description'] ?? '');
    $download_button_text = trim($_POST['website_download_button_text'] ?? '');
    $download_url = $system_download_url;
    if ($download_url !== '' && filter_var($download_url, FILTER_VALIDATE_URL) === false) {
        $download_url = '';
    }

    $hero_background_path = $system_hero_background;
    if (isset($_FILES['hero_background']) && (int) $_FILES['hero_background']['error'] === UPLOAD_ERR_OK) {
        $original_name = $_FILES['hero_background']['name'];
        $tmp_name = $_FILES['hero_background']['tmp_name'];
        $size_bytes = (int) $_FILES['hero_background']['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($extension, $allowed, true) && $size_bytes <= (3 * 1024 * 1024)) {
            $upload_dir = __DIR__ . '/../uploads/tenant_logos';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            $safe_tenant = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
            $new_name = $safe_tenant . '_bg_' . time() . '.' . $extension;
            $dest = rtrim($upload_dir, '/') . '/' . $new_name;
            if (move_uploaded_file($tmp_name, $dest)) {
                $app_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
                $hero_background_path = ($app_path === '' ? '/' : $app_path) . '/uploads/tenant_logos/' . $new_name;
            }
        }
    }

    $form = [
        'layout_template' => $layout,
        'hero_title' => $hero_title,
        'hero_subtitle' => $hero_subtitle,
        'hero_description' => $hero_description,
        'about_body' => $about_body,
        'contact_phone' => $contact_phone,
        'contact_email' => $contact_email,
        'contact_address' => $contact_address,
        'contact_hours' => $contact_hours,
        'website_show_about' => $show_about,
        'website_show_services' => $show_services,
        'website_show_contact' => $show_contact,
        'website_show_download' => $show_download,
        'website_download_title' => $download_title,
        'website_download_description' => $download_description,
        'website_download_button_text' => $download_button_text,
        'website_download_url' => $download_url
    ];

    if ($hero_title === '') {
        $error = 'Hero title is required.';
    } else {
        $about_heading = 'About Us';
        $services_heading = 'Our Services';
        $services_json = json_encode([], JSON_UNESCAPED_UNICODE);
        $hero_cta_text = ($show_download === '1' && $download_url !== '') ? 'Download App' : 'Learn More';
        $hero_cta_url = ($show_download === '1' && $download_url !== '') ? '#download' : '#about';

        $upsert_website = $pdo->prepare('
            INSERT INTO tenant_website_content
                (tenant_id, layout_template, hero_title, hero_subtitle, hero_description,
                 hero_cta_text, hero_cta_url, hero_image_path,
                 about_heading, about_body, about_image_path,
                 services_heading, services_json,
                 contact_address, contact_phone, contact_email, contact_hours,
                 custom_css, meta_description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                layout_template = VALUES(layout_template),
                hero_title = VALUES(hero_title),
                hero_subtitle = VALUES(hero_subtitle),
                hero_description = VALUES(hero_description),
                hero_cta_text = VALUES(hero_cta_text),
                hero_cta_url = VALUES(hero_cta_url),
                hero_image_path = VALUES(hero_image_path),
                about_heading = VALUES(about_heading),
                about_body = VALUES(about_body),
                about_image_path = VALUES(about_image_path),
                services_heading = VALUES(services_heading),
                services_json = VALUES(services_json),
                contact_address = VALUES(contact_address),
                contact_phone = VALUES(contact_phone),
                contact_email = VALUES(contact_email),
                contact_hours = VALUES(contact_hours),
                custom_css = VALUES(custom_css),
                meta_description = VALUES(meta_description)
        ');

        $upsert_website->execute([
            $tenant_id,
            $layout,
            $hero_title,
            $hero_subtitle,
            $hero_description,
            $hero_cta_text,
            $hero_cta_url,
            '',
            $about_heading,
            $about_body,
            '',
            $services_heading,
            $services_json,
            $contact_address,
            $contact_phone,
            $contact_email,
            $contact_hours,
            '',
            ''
        ]);

        $website_config = [
            'website_show_about' => $show_about,
            'website_show_services' => $show_services,
            'website_show_contact' => $show_contact,
            'website_show_download' => $show_download,
            'website_download_title' => ($download_title !== '' ? $download_title : 'Download Our App'),
            'website_download_description' => ($download_description !== '' ? $download_description : 'Get the app for faster loan tracking and updates.'),
            'website_download_button_text' => ($download_button_text !== '' ? $download_button_text : 'Download App'),
            'website_download_url' => $download_url,
            'website_hero_background' => $hero_background_path
        ];

        $boolean_setting_keys = [
            'website_show_about',
            'website_show_services',
            'website_show_contact',
            'website_show_download'
        ];

        $setting_upsert = $pdo->prepare('
            INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_category = VALUES(setting_category),
                data_type = VALUES(data_type),
                updated_at = CURRENT_TIMESTAMP
        ');

        foreach ($website_config as $key => $value) {
            $data_type = in_array($key, $boolean_setting_keys, true) ? 'Boolean' : 'String';
            $setting_upsert->execute([$tenant_id, $key, $value, 'Website', $data_type]);
        }

        $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
            ->execute([$tenant_id, 'public_website_enabled']);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'WEBSITE_SETUP', 'tenant', 'Public website configured during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 4, setup_completed = TRUE WHERE tenant_id = ? AND setup_current_step = 3')->execute([$tenant_id]);

        header('Location: ../admin_panel/admin.php');
        exit;
    }
}

$e = function ($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Website - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; min-height: 100vh; padding: 20px; }
        .wizard-card { background: <?php echo htmlspecialchars($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 95%; max-width: 1600px; margin: 0 auto; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo $e($accent); ?>, #0ea5e9); padding: 28px 32px; color: #ffffff; }
        .wizard-header h1 { font-size: 1.45rem; font-weight: 700; margin-bottom: 6px; }
        .wizard-header p { opacity: 0.9; font-size: 0.9rem; }
        .step-indicator { display: flex; gap: 8px; margin-top: 14px; }
        .step { width: 44px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.35); }
        .step.active { background: #ffffff; }
        .wizard-body { padding: 30px 32px 34px; }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .section { border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .section h3 { font-size: 1rem; margin-bottom: 4px; color: #0f172a; }
        .section p { color: #64748b; font-size: 0.85rem; margin-bottom: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.84rem; font-weight: 500; color: #475569; }
        .form-control, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.92rem; font-family: inherit; color: #0f172a; }
        textarea { min-height: 90px; resize: vertical; }
        .template-option { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; display: flex; gap: 8px; align-items: center; font-size: 0.88rem; }
        .template-option input { margin-top: 1px; }
        .check-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .check-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 0.88rem; color: #0f172a; }
        .check-item input { margin-right: 6px; }
        .actions { margin-top: 18px; }
        .btn-primary { width: 100%; border: 0; border-radius: 10px; padding: 12px 14px; font-size: 0.95rem; font-weight: 600; color: #ffffff; background: <?php echo $e($accent); ?>; cursor: pointer; }
        .btn-primary:hover { filter: brightness(0.93); }
        .setup-layout { display: grid; grid-template-columns: 450px 1fr; gap: 32px; align-items: start; }
        .live-preview-panel { border: 1px solid #dbeafe; border-radius: 12px; overflow: hidden; background: #f8fafc; position: sticky; top: 18px; }
        .preview-panel-header { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; background: #ffffff; }
        .preview-panel-header h3 { font-size: 0.95rem; color: #0f172a; margin-bottom: 4px; }
        .preview-panel-header p { font-size: 0.8rem; color: #64748b; margin-bottom: 0; }
        .preview-canvas { padding: 14px; background: linear-gradient(180deg, #f1f5f9, #eef2ff); }
        .preview-frame { border: 1px solid #cbd5e1; border-radius: 14px; overflow: hidden; background: #ffffff; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); }
        .preview-topbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: linear-gradient(120deg, <?php echo $e($accent); ?>, #0ea5e9); color: #ffffff; }
        .preview-brand { font-size: 0.8rem; font-weight: 700; max-width: 44%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .preview-links { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
        .preview-link { font-size: 0.72rem; background: rgba(255, 255, 255, 0.22); padding: 3px 7px; border-radius: 999px; }
        .preview-shell { display: grid; grid-template-columns: 1fr; min-height: 400px; }
        .preview-sidebar { display: none; background: #f8fafc; border-right: 1px solid #e2e8f0; padding: 14px 10px; }
        .preview-sidebar div { padding: 7px 8px; border-radius: 6px; font-size: 0.75rem; color: #334155; }
        .preview-sidebar div:first-child { background: #dbeafe; color: #1e3a8a; font-weight: 600; }
        .preview-content { padding: 14px; }
        .preview-hero { border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; padding: 14px; margin-bottom: 10px; }
        .preview-hero .preview-subtitle { font-size: 0.7rem; color: #0ea5e9; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 6px; }
        .preview-hero h4 { font-size: 1rem; color: #0f172a; margin-bottom: 7px; line-height: 1.25; }
        .preview-hero p { color: #475569; font-size: 0.78rem; line-height: 1.42; margin-bottom: 10px; }
        .preview-cta { display: inline-block; text-decoration: none; font-size: 0.72rem; font-weight: 700; border-radius: 8px; padding: 8px 10px; color: #ffffff; background: <?php echo $e($accent); ?>; }
        .preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .preview-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #ffffff; }
        .preview-card h5 { font-size: 0.8rem; color: #0f172a; margin-bottom: 7px; }
        .preview-card p { font-size: 0.74rem; color: #475569; line-height: 1.4; margin-bottom: 6px; }
        .preview-card ul { list-style: none; margin: 0; padding: 0; }
        .preview-card li { font-size: 0.74rem; color: #475569; line-height: 1.42; margin-bottom: 5px; }
        .preview-card li::before { content: "-"; margin-right: 6px; color: #0ea5e9; }
        .preview-download-btn { display: inline-block; margin-top: 4px; text-decoration: none; font-size: 0.72rem; font-weight: 700; border-radius: 8px; padding: 7px 10px; color: #0f172a; background: #e0f2fe; }
        /* --- Modern Minimal --- */
        .template-template1 .preview-canvas { background: #f8fafc; }
        .template-template1 .preview-topbar { background: transparent; color: #0f172a; padding: 20px 24px; border: none; }
        .template-template1 .preview-brand { font-size: 1.15rem; color: #0f172a; }
        .template-template1 .preview-link { background: transparent; color: #475569; font-weight: 500; font-size: 0.8rem; padding: 0 10px; transition: 0.2s; }
        .template-template1 .preview-link:hover { color: <?php echo $e($accent); ?>; }
        .template-template1 .preview-hero { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 40px 24px; text-align: center; box-shadow: 0 12px 32px rgba(0,0,0,0.03); margin-bottom: 24px; }
        .template-template1 .preview-hero h4 { font-size: 1.6rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; margin-bottom: 12px; }
        .template-template1 .preview-hero p { font-size: 0.9rem; color: #64748b; }
        .template-template1 .preview-cta { background: #0f172a; color: #ffffff; border-radius: 12px; padding: 12px 32px; font-weight: 600; box-shadow: 0 4px 12px rgba(15,23,42,0.15); margin-top: 10px; transition: 0.2s; }
        .template-template1 .preview-cta:hover { background: <?php echo $e($accent); ?>; transform: translateY(-2px); }
        .template-template1 .preview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .template-template1 .preview-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.02); transition: 0.3s; }
        .template-template1 .preview-card:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.06); transform: translateY(-4px); border-color: <?php echo $e($accent); ?>; }
        .template-template1 .preview-card h5 { font-size: 0.9rem; color: #0f172a; font-weight: 700; margin-bottom: 10px; }
        .template-template1 .preview-footer { background: #ffffff; color: #94a3b8; border-top: 1px solid #f1f5f9; text-align: center; padding: 24px; margin-top: 24px; transition: 0.2s; }
        .template-template1 .preview-footer strong { color: #0f172a; font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.8rem; }

        /* --- Corporate Pro --- */
        .template-template2 .preview-canvas { background: #f1f5f9; }
        .template-template2 .preview-topbar { background: <?php echo $e($accent); ?>; padding: 14px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); justify-content: flex-start; gap: 32px; }
        .template-template2 .preview-brand { font-size: 1.05rem; color: #ffffff; letter-spacing: 0.05em; font-weight: 700; margin-right: auto; }
        .template-template2 .preview-links { gap: 12px; }
        .template-template2 .preview-link { background: rgba(255,255,255,0.15); color: #ffffff; font-weight: 500; padding: 6px 14px; border-radius: 4px; transition: 0.2s; }
        .template-template2 .preview-link:hover { background: #ffffff; color: <?php echo $e($accent); ?>; }
        .template-template2 .preview-hero { text-align: left; background: #ffffff; border: none; border-left: 4px solid <?php echo $e($accent); ?>; padding: 32px 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-radius: 4px; }
        .template-template2 .preview-hero h4 { font-size: 1.4rem; color: #1e293b; margin-bottom: 10px; }
        .template-template2 .preview-cta { background: <?php echo $e($accent); ?>; border-radius: 4px; padding: 10px 24px; margin-top: 8px; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: 0.2s; }
        .template-template2 .preview-cta:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .template-template2 .preview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .template-template2 .preview-card { background: #ffffff; border: 1px solid #e2e8f0; border-top: 3px solid #cbd5e1; border-radius: 4px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: 0.2s; }
        .template-template2 .preview-card:hover { border-top-color: <?php echo $e($accent); ?>; box-shadow: 0 6px 16px rgba(0,0,0,0.06); transform: translateY(-2px); }
        .template-template2 .preview-card h5 { font-size: 0.95rem; color: #1e293b; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; margin-bottom: 12px; }
        .template-template2 .preview-footer { background: #1e293b; color: #94a3b8; text-align: left; padding: 20px 24px; border-top: none; margin-top: 24px; transition: 0.2s; border-radius: 0 0 14px 14px; }
        .template-template2 .preview-footer strong { color: #ffffff; font-size: 0.85rem; display: block; margin-bottom: 6px; }

        /* --- Creative Bold --- */
        .template-template3 .preview-topbar { display: none; }
        .template-template3 .preview-shell { grid-template-columns: 180px minmax(0, 1fr); background: #f8fafc; min-height: 520px; align-items: stretch;}
        .template-template3 .preview-sidebar { display: flex !important; flex-direction: column; gap: 8px; background: #0f172a; padding: 28px 20px; border-radius: 0 0 0 14px; box-shadow: 4px 0 16px rgba(0,0,0,0.1); z-index: 2; border-right: 1px solid #1e293b; }
        .template-template3 .preview-sidebar::before { content: '<?php echo addslashes($tenant_name); ?>'; font-weight: 800; color: #ffffff; padding: 0 8px 24px 8px; margin-bottom: 20px; border-bottom: 2px solid <?php echo $e($accent); ?>; font-size: 1.1rem; line-height: 1.2; text-transform: uppercase; letter-spacing: -0.02em; }
        .template-template3 .preview-sidebar div { color: #94a3b8; background: transparent; padding: 12px 14px; font-weight: 600; border-radius: 8px; transition: 0.2s; font-size: 0.85rem; cursor: pointer; }
        .template-template3 .preview-sidebar div:hover { background: #1e293b; color: #ffffff; transform: translateX(6px); }
        .template-template3 .preview-sidebar div:first-child { background: <?php echo $e($accent); ?>; color: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .template-template3 .preview-content { padding: 32px; background: #f1f5f9; display: flex; flex-direction: column; }
        .template-template3 .preview-hero { background: <?php echo $e($accent); ?>; color: #ffffff; border: none; padding: 36px 28px; border-radius: 16px; margin-bottom: 28px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); position: relative; overflow: hidden; text-align: left; }
        .template-template3 .preview-hero::after { content: ''; position: absolute; right: -20%; top: -50%; width: 50%; height: 200%; background: rgba(255,255,255,0.1); transform: rotate(15deg); }
        .template-template3 .preview-hero h4 { font-size: 1.7rem; color: #ffffff; margin-bottom: 12px; font-weight: 800; letter-spacing: -0.03em; position: relative; z-index: 1; }
        .template-template3 .preview-hero p, .template-template3 .preview-hero .preview-subtitle { color: rgba(255,255,255,0.9); position: relative; z-index: 1; }
        .template-template3 .preview-cta { background: #ffffff; color: <?php echo $e($accent); ?>; border-radius: 99px; padding: 12px 28px; font-weight: 800; box-shadow: 0 4px 16px rgba(0,0,0,0.1); margin-top: 12px; transition: 0.2s; position: relative; z-index: 1; }
        .template-template3 .preview-cta:hover { transform: scale(1.05); }
        .template-template3 .preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .template-template3 .preview-card { background: #ffffff; border: none; padding: 24px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); transition: 0.3s; position: relative; overflow: hidden; }
        .template-template3 .preview-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: #cbd5e1; transition: 0.3s; }
        .template-template3 .preview-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
        .template-template3 .preview-card:hover::before { background: <?php echo $e($accent); ?>; }
        .template-template3 .preview-card h5 { font-size: 1rem; color: #0f172a; font-weight: 800; margin-bottom: 10px; }
        .template-template3 .preview-footer { background: #0f172a; color: #64748b; text-align: center; padding: 24px; font-size: 0.75rem; margin-top: auto; border-radius: 0 0 14px 0; }
        .template-template3 .preview-footer strong { color: #ffffff; font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.85rem; }

        /* --- Global Setup Overrides --- */
        
        @media (max-width: 1020px) {
            .setup-layout { grid-template-columns: 1fr; }
            .live-preview-panel { position: static; }
        }
        @media (max-width: 760px) {
            .grid-2, .grid-3, .check-row { grid-template-columns: 1fr; }
            .wizard-header { padding: 22px 20px; }
            .wizard-body { padding: 20px; }
            .preview-grid { grid-template-columns: 1fr; }
            .template-template3 .preview-shell { grid-template-columns: 1fr; }
            .template-template3 .preview-sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Setup Public Website</h1>
            <p>Choose a template and set the information your visitors should see for <?php echo $e($tenant_name); ?>.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
            </div>
        </div>

        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo $e($error); ?></div>
            <?php endif; ?>

            <div class="setup-layout">
                  <form method="POST" id="websiteSetupForm" enctype="multipart/form-data">
                    <div class="section">
                        <h3>Choose Layout Template</h3>
                        <p>Select how your public website content is arranged.</p>
                        <div class="grid-3">
                            <label class="template-option">
                                <input type="radio" name="layout_template" value="template1" <?php echo $form['layout_template'] === 'template1' ? 'checked' : ''; ?>> Template 1
                            </label>
                            <label class="template-option">
                                <input type="radio" name="layout_template" value="template2" <?php echo $form['layout_template'] === 'template2' ? 'checked' : ''; ?>> Template 2
                            </label>
                            <label class="template-option">
                                <input type="radio" name="layout_template" value="template3" <?php echo $form['layout_template'] === 'template3' ? 'checked' : ''; ?>> Template 3
                            </label>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Hero Information</h3>
                        <p>This appears first when users open your website.</p>
                        <div class="form-group">
                            <label>Hero Title</label>
                            <input class="form-control" type="text" name="hero_title" value="<?php echo $e($form['hero_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Hero Background Image</label>
                            <input class="form-control" type="file" name="hero_background" accept=".jpg,.jpeg,.png,.webp">
                            <p style="margin-top: 6px; font-size: 0.78rem; color: #64748b;">Max 3MB. Recommended size: 1920x1080.</p>
                        </div>
                        <div class="form-group">
                            <label>Hero Subtitle</label>
                            <input class="form-control" type="text" name="hero_subtitle" value="<?php echo $e($form['hero_subtitle']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Hero Description</label>
                            <textarea name="hero_description"><?php echo $e($form['hero_description']); ?></textarea>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Public Information</h3>
                        <p>Add info and contact details for visitors.</p>
                        <div class="form-group">
                            <label>About Description</label>
                            <textarea name="about_body"><?php echo $e($form['about_body']); ?></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input class="form-control" type="text" name="contact_phone" value="<?php echo $e($form['contact_phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input class="form-control" type="email" name="contact_email" value="<?php echo $e($form['contact_email']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Contact Address</label>
                            <textarea name="contact_address"><?php echo $e($form['contact_address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Business Hours</label>
                            <input class="form-control" type="text" name="contact_hours" value="<?php echo $e($form['contact_hours']); ?>" placeholder="Mon-Fri 8:00 AM - 5:00 PM">
                        </div>
                    </div>

                    <div class="section">
                        <h3>Visibility and App Download</h3>
                        <p>Control what sections are visible and where users can download your app.</p>

                        <div class="check-row">
                            <label class="check-item"><input type="checkbox" name="website_show_about" value="1" <?php echo $form['website_show_about'] === '1' ? 'checked' : ''; ?>> Show About</label>
                            <label class="check-item"><input type="checkbox" name="website_show_services" value="1" <?php echo $form['website_show_services'] === '1' ? 'checked' : ''; ?>> Show Services</label>
                            <label class="check-item"><input type="checkbox" name="website_show_contact" value="1" <?php echo $form['website_show_contact'] === '1' ? 'checked' : ''; ?>> Show Contact</label>
                            <label class="check-item"><input type="checkbox" name="website_show_download" value="1" <?php echo $form['website_show_download'] === '1' ? 'checked' : ''; ?>> Show Download Section</label>
                        </div>

                        <div class="grid-2" style="margin-top: 12px;">
                            <div class="form-group">
                                <label>Download Title</label>
                                <input class="form-control" type="text" name="website_download_title" value="<?php echo $e($form['website_download_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Download Button Text</label>
                                <input class="form-control" type="text" name="website_download_button_text" value="<?php echo $e($form['website_download_button_text']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Download Description</label>
                            <textarea name="website_download_description"><?php echo $e($form['website_download_description']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>App Download URL</label>
                            <input class="form-control" type="text" value="<?php echo $e($form['website_download_url'] !== '' ? $form['website_download_url'] : 'System-managed (not set yet)'); ?>" readonly>
                            <input type="hidden" name="website_download_url" value="<?php echo $e($form['website_download_url']); ?>">
                            <p style="margin-top: 6px; font-size: 0.78rem; color: #64748b;">This link is provided by the system configuration and cannot be edited here.</p>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn-primary" type="submit">Complete Setup</button>
                    </div>
                </form>

                <aside class="live-preview-panel" aria-label="Live Website Preview">
                    <div class="preview-panel-header">
                        <h3>Preview</h3>
                        <p>Preview updates as you type, toggle sections, or switch templates.</p>
                    </div>
                      <div class="preview-canvas template-preset1" id="livePreview">
                        <div class="preview-frame">
                            <div class="preview-topbar">
                                <span class="preview-brand"><?php echo $e($tenant_name); ?></span>
                                <div class="preview-links">
                                    <span class="preview-link" id="previewNavAbout">About</span>
                                    <span class="preview-link" id="previewNavServices">Services</span>
                                    <span class="preview-link" id="previewNavContact">Contact</span>
                                    <span class="preview-link" id="previewNavDownload">Download</span>
                                </div>
                            </div>

                            <div class="preview-shell">
                                <div class="preview-sidebar" id="previewSidebar">
                                    <div>Home</div>
                                    <div>About</div>
                                    <div>Services</div>
                                    <div>Contact</div>
                                    <div>Download</div>
                                </div>

                                <div class="preview-content">
                                    <section class="preview-hero">
                                        <p class="preview-subtitle" id="previewHeroSubtitle"></p>
                                        <h4 id="previewHeroTitle"></h4>
                                        <p id="previewHeroDescription"></p>
                                        <a class="preview-cta" id="previewHeroButton" href="#">Download App</a>
                                    </section>

                                    <div class="preview-grid">
                                        <section class="preview-card" id="previewAboutSection">
                                            <h5>About</h5>
                                            <p id="previewAboutBody"></p>
                                              <div style="display: flex; gap: 10px; margin-top: 15px;">
                                                  <div style="flex: 1; text-align: center; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px;">
                                                      <div style="font-weight: 700; color: <?php echo $e($accent); ?>;">150+</div>
                                                      <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase;">Active Clients</div>
                                                  </div>
                                                  <div style="flex: 1; text-align: center; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px;">
                                                      <div style="font-weight: 700; color: <?php echo $e($accent); ?>;">400+</div>
                                                      <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase;">Loans Funded</div>
                                                  </div>
                                              </div>
                                            <h5>Services</h5>
                                            <ul>
                                                <li>Quick loan application</li>
                                                <li>Flexible repayment options</li>
                                                <li>Friendly customer support</li>
                                            </ul>
                                        </section>

                                        <section class="preview-card" id="previewContactSection">
                                            <h5>Contact</h5>
                                            <p id="previewContactPhone"></p>
                                            <p id="previewContactEmail"></p>
                                            <p id="previewContactAddress"></p>
                                            <p id="previewContactHours"></p>
                                        </section>

                                        <section class="preview-card" id="previewDownloadSection">
                                            <h5 id="previewDownloadTitle"></h5>
                                            <p id="previewDownloadDescription"></p>
                                            <a class="preview-download-btn" id="previewDownloadButton" href="#">Download App</a>
                                        </section>
                                    </div>
                                </div>
                            </div>
                            <!-- Footer added to complete the layout UI -->
                            <div class="preview-footer">
                                <strong><?php echo $e($tenant_name); ?></strong>
                                <span>&copy; <?php echo date('Y'); ?> All rights reserved.</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('websiteSetupForm');
            var livePreview = document.getElementById('livePreview');
            if (!form || !livePreview) {
                return;
            }

            var previewNodes = {
                navAbout: document.getElementById('previewNavAbout'),
                navServices: document.getElementById('previewNavServices'),
                navContact: document.getElementById('previewNavContact'),
                navDownload: document.getElementById('previewNavDownload'),
                heroTitle: document.getElementById('previewHeroTitle'),
                heroSubtitle: document.getElementById('previewHeroSubtitle'),
                heroDescription: document.getElementById('previewHeroDescription'),
                heroButton: document.getElementById('previewHeroButton'),
                aboutSection: document.getElementById('previewAboutSection'),
                servicesSection: document.getElementById('previewServicesSection'),
                contactSection: document.getElementById('previewContactSection'),
                downloadSection: document.getElementById('previewDownloadSection'),
                aboutBody: document.getElementById('previewAboutBody'),
                contactPhone: document.getElementById('previewContactPhone'),
                contactEmail: document.getElementById('previewContactEmail'),
                contactAddress: document.getElementById('previewContactAddress'),
                contactHours: document.getElementById('previewContactHours'),
                downloadTitle: document.getElementById('previewDownloadTitle'),
                downloadDescription: document.getElementById('previewDownloadDescription'),
                downloadButton: document.getElementById('previewDownloadButton')
            };

            function valueOf(name) {
                var field = form.elements[name];
                if (!field) {
                    return '';
                }
                return String(field.value || '').trim();
            }

            function checked(name) {
                var field = form.elements[name];
                return !!(field && field.checked);
            }

            function setVisible(node, isVisible) {
                if (!node) {
                    return;
                }
                node.style.display = isVisible ? '' : 'none';
            }

            function setText(node, value, fallback) {
                if (!node) {
                    return;
                }
                node.textContent = value !== '' ? value : fallback;
            }

            function refreshPreview() {
                var selectedTemplate = 'preset1';
                var templateField = form.querySelector('input[name="layout_template"]:checked');
                if (templateField) {
                    selectedTemplate = templateField.value;
                }

                livePreview.classList.remove('template-preset1', 'template-preset2', 'template-preset3');
                if (selectedTemplate === 'preset2') {
                    livePreview.classList.add('template-preset2');
                } else if (selectedTemplate === 'preset3') {
                    livePreview.classList.add('template-preset3');
                } else {
                    livePreview.classList.add('template-preset1');
                }

                var heroTitle = valueOf('hero_title');
                var heroSubtitle = valueOf('hero_subtitle');
                var heroDescription = valueOf('hero_description');
                var aboutBody = valueOf('about_body');
                var contactPhone = valueOf('contact_phone');
                var contactEmail = valueOf('contact_email');
                var contactAddress = valueOf('contact_address');
                var contactHours = valueOf('contact_hours');
                var downloadTitle = valueOf('website_download_title');
                var downloadDescription = valueOf('website_download_description');
                var downloadButtonText = valueOf('website_download_button_text');
                var downloadUrl = valueOf('website_download_url');

                var showAbout = checked('website_show_about');
                var showServices = checked('website_show_services');
                var showContact = checked('website_show_contact');
                var showDownload = checked('website_show_download');

                setText(previewNodes.heroTitle, heroTitle, 'Welcome to your organization');
                setText(previewNodes.heroSubtitle, heroSubtitle, 'Your trusted microfinance partner');
                setText(previewNodes.heroDescription, heroDescription, 'Share what makes your organization unique to your visitors.');
                setText(previewNodes.aboutBody, aboutBody, 'Add your organization story so visitors can quickly understand your mission.');
                setText(previewNodes.contactPhone, contactPhone !== '' ? 'Phone: ' + contactPhone : '', 'Phone: Not set');
                setText(previewNodes.contactEmail, contactEmail !== '' ? 'Email: ' + contactEmail : '', 'Email: Not set');
                setText(previewNodes.contactAddress, contactAddress !== '' ? 'Address: ' + contactAddress : '', 'Address: Not set');
                setText(previewNodes.contactHours, contactHours !== '' ? 'Hours: ' + contactHours : '', 'Hours: Not set');
                setText(previewNodes.downloadTitle, downloadTitle, 'Download Our App');
                setText(previewNodes.downloadDescription, downloadDescription, 'Get the app for faster loan tracking and updates.');

                setVisible(previewNodes.aboutSection, showAbout);
                setVisible(previewNodes.servicesSection, showServices);
                setVisible(previewNodes.contactSection, showContact);
                setVisible(previewNodes.downloadSection, showDownload);

                setVisible(previewNodes.navAbout, showAbout);
                setVisible(previewNodes.navServices, showServices);
                setVisible(previewNodes.navContact, showContact);
                setVisible(previewNodes.navDownload, showDownload);

                var buttonText = showDownload ? (downloadButtonText || 'Download App') : 'Learn More';
                if (previewNodes.heroButton) {
                    previewNodes.heroButton.textContent = buttonText;
                    previewNodes.heroButton.href = (showDownload && downloadUrl !== '') ? downloadUrl : '#';
                    previewNodes.heroButton.target = (showDownload && downloadUrl !== '') ? '_blank' : '_self';
                    previewNodes.heroButton.rel = (showDownload && downloadUrl !== '') ? 'noopener noreferrer' : '';
                }

                if (previewNodes.downloadButton) {
                    previewNodes.downloadButton.textContent = downloadButtonText || 'Download App';
                    previewNodes.downloadButton.href = downloadUrl !== '' ? downloadUrl : '#';
                    previewNodes.downloadButton.target = downloadUrl !== '' ? '_blank' : '_self';
                    previewNodes.downloadButton.rel = downloadUrl !== '' ? 'noopener noreferrer' : '';
                }
            }

            form.addEventListener('input', refreshPreview);
            form.addEventListener('change', refreshPreview);
            refreshPreview();
        })();
    </script>
</body>
</html>
