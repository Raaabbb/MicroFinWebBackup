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
    $setup_routes = [0 => 'force_change_password.php', 1 => 'setup_loan_products.php', 2 => 'setup_credit.php', 4 => 'setup_branding.php'];
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
    $layout = 'template1'; // Template 2/3 are temporarily unavailable.

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

        $pdo->prepare('UPDATE tenants SET setup_current_step = 4 WHERE tenant_id = ? AND setup_current_step = 3')->execute([$tenant_id]);

        header('Location: setup_branding.php');
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
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        .template-option { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; display: flex; gap: 8px; align-items: center; font-size: 0.88rem; cursor: pointer; }
        .template-option input { margin-top: 1px; }
        .template-option:has(input:checked) { background: #dbeafe; border-color: #0ea5e9; }
        .template-option.unavailable { cursor: not-allowed; opacity: 0.6; border-style: dashed; }
        .template-option.unavailable span { color: #64748b; }
        .check-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .check-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 0.88rem; color: #0f172a; }
        .check-item input { margin-right: 6px; }
        .actions { margin-top: 18px; }
        .btn-primary { width: 100%; border: 0; border-radius: 10px; padding: 12px 14px; font-size: 0.95rem; font-weight: 600; color: #ffffff; background: <?php echo $e($accent); ?>; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { filter: brightness(0.93); }
        .setup-layout { display: grid; grid-template-columns: 450px 1fr; gap: 32px; align-items: start; }
        .live-preview-panel { border: 1px solid #dbeafe; border-radius: 12px; overflow: hidden; background: #f8fafc; position: sticky; top: 18px; }
        .preview-panel-header { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; background: #ffffff; }
        .preview-panel-header h3 { font-size: 0.95rem; color: #0f172a; margin-bottom: 4px; }
        .preview-panel-header p { font-size: 0.8rem; color: #64748b; margin-bottom: 0; }
        .preview-canvas { padding: 14px; background: linear-gradient(180deg, #f1f5f9, #eef2ff); }
        
        /* ========== TEMPLATE 1: Modern Material ========== */
        .template-template1 { background: #f8fafc !important; }
        .template-template1 .preview-frame { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; background: #f8fafc; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); }
        .template-template1 .preview-header { padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .template-template1 .preview-logo { font-size: 0.95rem; font-weight: 800; color: <?php echo $e($accent); ?>; font-family: 'Manrope', sans-serif; letter-spacing: -0.02em; }
        .template-template1 .preview-nav { display: flex; gap: 20px; }
        .template-template1 .preview-nav-item { font-size: 0.78rem; color: #64748b; cursor: pointer; transition: 0.2s; padding: 4px 8px; border-radius: 6px; }
        .template-template1 .preview-nav-item:hover { background: #f1f5f9; color: <?php echo $e($accent); ?>; }
        .template-template1 .preview-content { padding: 28px 24px; background: #f8fafc; }
        .template-template1 .preview-hero { display: grid; grid-template-columns: 1fr 120px; gap: 20px; align-items: center; margin-bottom: 28px; }
        .template-template1 .preview-hero-left { }
        .template-template1 .preview-hero-subtitle { display: inline-block; font-size: 0.65rem; font-weight: 700; color: <?php echo $e($accent); ?>; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 10px; background: <?php echo $e($accent); ?>15; padding: 4px 10px; border-radius: 99px; }
        .template-template1 .preview-hero-title { font-size: 1.6rem; font-weight: 800; color: <?php echo $e($accent); ?>; letter-spacing: -0.03em; margin-bottom: 10px; line-height: 1.15; font-family: 'Manrope', sans-serif; }
        .template-template1 .preview-hero-desc { font-size: 0.82rem; color: #64748b; max-width: 400px; line-height: 1.5; margin-bottom: 16px; }
        .template-template1 .preview-hero-image { width: 120px; height: 120px; border-radius: 16px; background: linear-gradient(135deg, <?php echo $e($accent); ?>20, <?php echo $e($accent); ?>08); display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .template-template1 .preview-hero-image::after { content: '🏦'; font-size: 2rem; }
        .template-template1 .preview-stats-card { position: absolute; bottom: -10px; left: -10px; background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); padding: 6px 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .template-template1 .preview-stats-num { font-size: 0.85rem; font-weight: 800; color: <?php echo $e($accent); ?>; }
        .template-template1 .preview-stats-label { font-size: 0.55rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .template-template1 .preview-cta { display: inline-block; padding: 10px 24px; background: linear-gradient(135deg, <?php echo $e($accent); ?>, <?php echo $e($accent); ?>cc); color: #ffffff; border-radius: 8px; font-size: 0.8rem; font-weight: 700; transition: 0.2s; text-decoration: none; }
        .template-template1 .preview-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .template-template1 .preview-step { padding: 16px 12px; background: #f1f5f9; border-radius: 12px; transition: 0.3s; }
        .template-template1 .preview-step:nth-child(2) { background: <?php echo $e($accent); ?>; }
        .template-template1 .preview-step:nth-child(2) .preview-step-num,
        .template-template1 .preview-step:nth-child(2) h6,
        .template-template1 .preview-step:nth-child(2) p { color: #ffffff !important; }
        .template-template1 .preview-step-num { font-size: 1.4rem; font-weight: 800; color: <?php echo $e($accent); ?>15; margin-bottom: 8px; }
        .template-template1 .preview-step h6 { font-size: 0.78rem; font-weight: 700; color: <?php echo $e($accent); ?>; margin-bottom: 4px; }
        .template-template1 .preview-step p { font-size: 0.65rem; color: #64748b; line-height: 1.4; }
        .template-template1 .preview-sections { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .template-template1 .preview-section-card { padding: 20px 14px; background: #ffffff; border-radius: 12px; border: 1px solid <?php echo $e($accent); ?>08; transition: 0.3s; }
        .template-template1 .preview-section-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .template-template1 .preview-section-card h5 { font-size: 0.82rem; font-weight: 700; color: <?php echo $e($accent); ?>; margin-bottom: 8px; }
        .template-template1 .preview-section-card p { font-size: 0.72rem; color: #64748b; line-height: 1.5; }
        .template-template1 .preview-footer { padding: 20px 24px; border-top: 1px solid #f1f5f9; background: #ffffff; text-align: center; }
        .template-template1 .preview-footer-text { font-size: 0.7rem; color: #94a3b8; }

        /* ========== TEMPLATE 2: Editorial ========== */
        .template-template2 { background: #fafafa !important; }
        .template-template2 .preview-frame { border: none; border-radius: 0; overflow: hidden; background: #fafafa; }
        .template-template2 .preview-header { padding: 12px 24px; background: #fafafa; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e5e5; }
        .template-template2 .preview-logo { font-size: 0.9rem; font-weight: 700; color: <?php echo $e($accent); ?>; font-family: 'Georgia', serif; }
        .template-template2 .preview-nav { display: flex; gap: 16px; }
        .template-template2 .preview-nav-item { font-size: 0.72rem; color: #737373; cursor: pointer; transition: 0.2s; }
        .template-template2 .preview-nav-item:hover { color: <?php echo $e($accent); ?>; }
        .template-template2 .preview-content { background: #fafafa; padding: 0; }
        .template-template2 .preview-hero { padding: 32px 24px; border-bottom: 1px solid #e5e5e5; }
        .template-template2 .preview-hero-subtitle { font-size: 0.65rem; color: #a3a3a3; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 8px; }
        .template-template2 .preview-hero-title { font-size: 1.8rem; font-weight: 700; color: <?php echo $e($accent); ?>; margin-bottom: 10px; font-family: 'Georgia', serif; line-height: 1.15; letter-spacing: -0.02em; }
        .template-template2 .preview-hero-desc { font-size: 0.82rem; color: #737373; line-height: 1.6; }
        .template-template2 .preview-hero-cta { display: inline-flex; align-items: center; gap: 6px; color: <?php echo $e($accent); ?>; font-size: 0.78rem; font-weight: 700; margin-top: 12px; text-decoration: none; }
        .template-template2 .preview-hero-cta::after { content: '→'; }
        .template-template2 .preview-narrative-image { width: 100%; height: 120px; background: linear-gradient(180deg, #e5e5e5, #fafafa); position: relative; overflow: hidden; }
        .template-template2 .preview-narrative-image::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 40%; background: linear-gradient(transparent, #fafafa); }
        .template-template2 .preview-narrative-badge { position: absolute; bottom: 12px; left: 16px; background: <?php echo $e($accent); ?>; color: #fff; font-size: 0.55rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; padding: 4px 10px; }
        .template-template2 .preview-cta { display: none; }
        .template-template2 .preview-sections { display: grid; grid-template-columns: 7fr 5fr; gap: 12px; padding: 20px 24px; }
        .template-template2 .preview-section-card { background: #ffffff; padding: 18px; border: 1px solid #e5e5e5; transition: 0.2s; }
        .template-template2 .preview-section-card:hover { border-color: <?php echo $e($accent); ?>30; }
        .template-template2 .preview-section-card .card-num { font-size: 0.6rem; font-weight: 700; color: #a3a3a3; letter-spacing: 0.1em; margin-bottom: 8px; }
        .template-template2 .preview-section-card h5 { font-size: 0.85rem; font-weight: 700; color: <?php echo $e($accent); ?>; margin-bottom: 6px; font-family: 'Georgia', serif; }
        .template-template2 .preview-section-card p { font-size: 0.72rem; color: #737373; line-height: 1.5; }
        .template-template2 .preview-section-card .explore-link { font-size: 0.68rem; color: <?php echo $e($accent); ?>; font-weight: 700; margin-top: 8px; display: inline-block; }
        .template-template2 .preview-quote { padding: 24px; background: #f5f5f5; text-align: center; }
        .template-template2 .preview-quote blockquote { font-size: 1rem; font-family: 'Georgia', serif; font-weight: 700; color: <?php echo $e($accent); ?>; line-height: 1.4; font-style: italic; }
        .template-template2 .preview-quote cite { font-size: 0.65rem; color: #737373; display: block; margin-top: 8px; font-style: normal; font-weight: 600; }
        .template-template2 .preview-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 24px; }
        .template-template2 .preview-stat { border-left: 2px solid <?php echo $e($accent); ?>; padding-left: 12px; }
        .template-template2 .preview-stat-num { font-size: 1.4rem; font-weight: 700; color: <?php echo $e($accent); ?>; font-family: 'Georgia', serif; }
        .template-template2 .preview-stat-label { font-size: 0.6rem; color: #737373; text-transform: uppercase; letter-spacing: 0.05em; }
        .template-template2 .preview-footer { padding: 16px 24px; background: #fafafa; text-align: left; border-top: 1px solid #e5e5e5; }
        .template-template2 .preview-footer-text { font-size: 0.68rem; color: #a3a3a3; }

        /* ========== TEMPLATE 3: Energetic ========== */
        .template-template3 { background: #f0fdf4 !important; }
        .template-template3 .preview-frame { border: none; border-radius: 16px; overflow: hidden; background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 50%, #f0f9ff 100%); }
        .template-template3 .preview-header { padding: 14px 24px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); display: flex; justify-content: space-between; align-items: center; }
        .template-template3 .preview-logo { font-size: 0.95rem; font-weight: 800; color: <?php echo $e($accent); ?>; letter-spacing: -0.03em; }
        .template-template3 .preview-nav { display: flex; gap: 14px; }
        .template-template3 .preview-nav-item { font-size: 0.72rem; color: #64748b; cursor: pointer; transition: 0.2s; }
        .template-template3 .preview-nav-item:hover { color: <?php echo $e($accent); ?>; }
        .template-template3 .preview-cta-btn { padding: 6px 14px; background: <?php echo $e($accent); ?>; color: #fff; border-radius: 10px; font-size: 0.7rem; font-weight: 700; }
        .template-template3 .preview-content { padding: 20px 24px; }
        .template-template3 .preview-hero { display: grid; grid-template-columns: 1fr 140px; gap: 16px; align-items: center; margin-bottom: 24px; }
        .template-template3 .preview-hero-left { }
        .template-template3 .preview-hero-subtitle { display: inline-flex; align-items: center; gap: 4px; font-size: 0.65rem; font-weight: 700; color: <?php echo $e($accent); ?>; background: <?php echo $e($accent); ?>12; padding: 4px 10px; border-radius: 99px; margin-bottom: 10px; }
        .template-template3 .preview-hero-subtitle::before { content: '🚀'; font-size: 0.7rem; }
        .template-template3 .preview-hero-title { font-size: 1.5rem; font-weight: 800; color: <?php echo $e($accent); ?>; margin-bottom: 8px; line-height: 1.15; letter-spacing: -0.02em; }
        .template-template3 .preview-hero-desc { font-size: 0.78rem; color: #64748b; line-height: 1.5; margin-bottom: 14px; }
        .template-template3 .preview-hero-actions { display: flex; gap: 8px; }
        .template-template3 .preview-cta { display: inline-block; padding: 10px 20px; background: <?php echo $e($accent); ?>; color: #ffffff; border-radius: 12px; font-size: 0.78rem; font-weight: 700; transition: 0.2s; text-decoration: none; }
        .template-template3 .preview-cta-secondary { display: inline-block; padding: 10px 20px; color: <?php echo $e($accent); ?>; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.78rem; font-weight: 700; }
        .template-template3 .preview-hero-image { width: 140px; height: 110px; border-radius: 20px; background: linear-gradient(135deg, <?php echo $e($accent); ?>15, <?php echo $e($accent); ?>05); display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .template-template3 .preview-hero-image::after { content: '⚡'; font-size: 2.2rem; }
        .template-template3 .preview-float-card { position: absolute; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 5px 8px; border-radius: 8px; font-size: 0.55rem; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .template-template3 .preview-float-card.top-right { top: -6px; right: -6px; color: <?php echo $e($accent); ?>; }
        .template-template3 .preview-float-card.bottom-left { bottom: -6px; left: -6px; color: <?php echo $e($accent); ?>; }
        .template-template3 .preview-sections { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
        .template-template3 .preview-section-card { background: rgba(255,255,255,0.6); backdrop-filter: blur(10px); padding: 16px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.3); box-shadow: 0 2px 8px rgba(0,0,0,0.03); transition: 0.3s; }
        .template-template3 .preview-section-card:first-child { grid-column: span 2; }
        .template-template3 .preview-section-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .template-template3 .preview-section-card h5 { font-size: 0.82rem; font-weight: 800; color: <?php echo $e($accent); ?>; margin-bottom: 6px; }
        .template-template3 .preview-section-card p { font-size: 0.7rem; color: #64748b; line-height: 1.5; }
        .template-template3 .preview-journey { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .template-template3 .preview-journey-step { background: #f8fafc; padding: 14px 10px; border-radius: 16px; text-align: center; transition: 0.3s; }
        .template-template3 .preview-journey-step:hover { background: <?php echo $e($accent); ?>; }
        .template-template3 .preview-journey-step:hover * { color: #fff !important; }
        .template-template3 .preview-journey-num { font-size: 1.2rem; font-weight: 800; color: <?php echo $e($accent); ?>15; margin-bottom: 4px; }
        .template-template3 .preview-journey-step h6 { font-size: 0.72rem; font-weight: 700; color: <?php echo $e($accent); ?>; margin-bottom: 2px; }
        .template-template3 .preview-journey-step p { font-size: 0.6rem; color: #64748b; line-height: 1.3; }
        .template-template3 .preview-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; border-radius: 0 0 16px 16px; }
        .template-template3 .preview-footer-text { font-size: 0.68rem; color: #94a3b8; }

        /* ========== Media Queries ========== */
        @media (max-width: 1020px) {
            .setup-layout { grid-template-columns: 1fr; }
            .live-preview-panel { position: static; }
        }
        @media (max-width: 760px) {
            .grid-2, .grid-3, .check-row { grid-template-columns: 1fr; }
            .wizard-header { padding: 22px 20px; }
            .wizard-body { padding: 20px; }
            .template-template1 .preview-hero { grid-template-columns: 1fr; }
            .template-template1 .preview-hero-image { display: none; }
            .template-template1 .preview-steps { grid-template-columns: 1fr; }
            .template-template1 .preview-sections { grid-template-columns: 1fr; }
            .template-template2 .preview-sections { grid-template-columns: 1fr; }
            .template-template2 .preview-stats { grid-template-columns: 1fr; }
            .template-template3 .preview-hero { grid-template-columns: 1fr; }
            .template-template3 .preview-hero-image { display: none; }
            .template-template3 .preview-sections { grid-template-columns: 1fr; }
            .template-template3 .preview-section-card:first-child { grid-column: span 1; }
            .template-template3 .preview-journey { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Setup Public Website</h1>
            <p>Choose a design template and set the information your visitors should see for <?php echo $e($tenant_name); ?>.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
        </div>

        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo $e($error); ?></div>
            <?php endif; ?>

            <div class="setup-layout">
                <form method="POST" id="websiteSetupForm" enctype="multipart/form-data">
                    <div class="section">
                        <h3>Choose Design Template</h3>
                        <p>Template 1 is currently available. Templates 2 and 3 are under development.</p>
                        <div class="grid-3">
                            <label class="template-option">
                                <input type="radio" name="layout_template" value="template1" checked oninput="updatePreview()"> 
                                <span>Template 1</span>
                            </label>
                            <label class="template-option unavailable" title="Under Development">
                                <input type="radio" name="layout_template" value="template2" disabled>
                                <span>Template 2 - Under Development</span>
                            </label>
                            <label class="template-option unavailable" title="Under Development">
                                <input type="radio" name="layout_template" value="template3" disabled>
                                <span>Template 3 - Under Development</span>
                            </label>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Hero Information</h3>
                        <p>This appears prominently when users first visit your website.</p>
                        <div class="form-group">
                            <label>Hero Title *</label>
                            <input class="form-control" type="text" name="hero_title" value="<?php echo $e($form['hero_title']); ?>" required oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Hero Subtitle</label>
                            <input class="form-control" type="text" name="hero_subtitle" value="<?php echo $e($form['hero_subtitle']); ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Hero Description</label>
                            <textarea name="hero_description" oninput="updatePreview()"><?php echo $e($form['hero_description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Hero Background Image</label>
                            <input class="form-control" type="file" name="hero_background" accept=".jpg,.jpeg,.png,.webp">
                            <p style="margin-top: 6px; font-size: 0.78rem; color: #64748b;">Max 3MB. Recommended: 1920x1080px</p>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Public Information</h3>
                        <p>Contact details and company information for visitors.</p>
                        <div class="form-group">
                            <label>About Description</label>
                            <textarea name="about_body" oninput="updatePreview()"><?php echo $e($form['about_body']); ?></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input class="form-control" type="text" name="contact_phone" value="<?php echo $e($form['contact_phone']); ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input class="form-control" type="email" name="contact_email" value="<?php echo $e($form['contact_email']); ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Company Address</label>
                            <textarea name="contact_address" oninput="updatePreview()"><?php echo $e($form['contact_address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Business Hours</label>
                            <input class="form-control" type="text" name="contact_hours" value="<?php echo $e($form['contact_hours']); ?>" placeholder="Mon-Fri 8:00 AM - 5:00 PM" oninput="updatePreview()">
                        </div>
                    </div>

                    <div class="section">
                        <h3>Sections & Downloads</h3>
                        <p>Control which sections appear and app download options.</p>
                        <div class="check-row">
                            <label class="check-item"><input type="checkbox" name="website_show_about" value="1" <?php echo $form['website_show_about'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show About</label>
                            <label class="check-item"><input type="checkbox" name="website_show_services" value="1" <?php echo $form['website_show_services'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Services</label>
                            <label class="check-item"><input type="checkbox" name="website_show_contact" value="1" <?php echo $form['website_show_contact'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Contact</label>
                            <label class="check-item"><input type="checkbox" name="website_show_download" value="1" <?php echo $form['website_show_download'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Download</label>
                        </div>
                        <div class="grid-2" style="margin-top: 12px;">
                            <div class="form-group">
                                <label>Download Section Title</label>
                                <input class="form-control" type="text" name="website_download_title" value="<?php echo $e($form['website_download_title']); ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Download Button Text</label>
                                <input class="form-control" type="text" name="website_download_button_text" value="<?php echo $e($form['website_download_button_text']); ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Download Description</label>
                            <textarea name="website_download_description" oninput="updatePreview()"><?php echo $e($form['website_download_description']); ?></textarea>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn-primary" type="submit">Complete Setup</button>
                    </div>
                </form>

                <aside class="live-preview-panel" aria-label="Live Website Preview">
                    <div class="preview-panel-header">
                        <h3>Live Preview</h3>
                        <p>See updates as you make changes to your content.</p>
                    </div>
                    <div class="preview-canvas" id="previewContainer">
                        <!-- Templates will be inserted here via JavaScript -->
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        var accentColor = '<?php echo $e($accent); ?>';
        var tenantName = '<?php echo $e($tenant_name); ?>';

        function updatePreview() {
            var form = document.getElementById('websiteSetupForm');
            var template = form.querySelector('input[name="layout_template"]:checked').value;
            var heroTitle = form.querySelector('input[name="hero_title"]').value || 'Welcome to ' + tenantName;
            var heroSubtitle = form.querySelector('input[name="hero_subtitle"]').value || 'Your trusted microfinance partner';
            var heroDesc = form.querySelector('textarea[name="hero_description"]').value;
            var aboutBody = form.querySelector('textarea[name="about_body"]').value;
            var contactPhone = form.querySelector('input[name="contact_phone"]').value;
            var contactEmail = form.querySelector('input[name="contact_email"]').value;
            var contactAddr = form.querySelector('textarea[name="contact_address"]').value;
            var contactHours = form.querySelector('input[name="contact_hours"]').value;
            
            var showAbout = form.querySelector('input[name="website_show_about"]').checked;
            var showServices = form.querySelector('input[name="website_show_services"]').checked;
            var showContact = form.querySelector('input[name="website_show_contact"]').checked;
            var showDownload = form.querySelector('input[name="website_show_download"]').checked;
            
            var downloadTitle = form.querySelector('input[name="website_download_title"]').value || 'Download Our App';
            var downloadDesc = form.querySelector('textarea[name="website_download_description"]').value || 'Get the app for faster access.';
            var downloadBtn = form.querySelector('input[name="website_download_button_text"]').value || 'Download App';

            var html = '';

            if (template === 'template1') {
                html = generateTemplate1(heroTitle, heroSubtitle, heroDesc, aboutBody, contactPhone, contactEmail, contactAddr, contactHours, downloadTitle, downloadDesc, downloadBtn, showAbout, showServices, showContact, showDownload);
            } else if (template === 'template2') {
                html = generateTemplate2(heroTitle, heroSubtitle, heroDesc, aboutBody, contactPhone, contactEmail, contactAddr, contactHours, downloadTitle, downloadDesc, downloadBtn, showAbout, showServices, showContact, showDownload);
            } else if (template === 'template3') {
                html = generateTemplate3(heroTitle, heroSubtitle, heroDesc, aboutBody, contactPhone, contactEmail, contactAddr, contactHours, downloadTitle, downloadDesc, downloadBtn, showAbout, showServices, showContact, showDownload);
            }

            var container = document.getElementById('previewContainer');
            container.innerHTML = '';
            container.className = 'preview-canvas template-' + template;
            var frame = document.createElement('div');
            frame.className = 'preview-frame';
            frame.innerHTML = html;
            container.appendChild(frame);
        }

        function generateTemplate1(title, subtitle, desc, about, phone, email, addr, hours, dlTitle, dlDesc, dlBtn, showAbout, showServices, showContact, showDownload) {
            var html = '<div class="preview-header"><div class="preview-logo">' + tenantName + '</div><div class="preview-nav">';
            if (showAbout) html += '<div class="preview-nav-item">About</div>';
            if (showServices) html += '<div class="preview-nav-item">Services</div>';
            if (showContact) html += '<div class="preview-nav-item">Contact</div>';
            html += '</div></div><div class="preview-content">';
            // Hero with image
            html += '<div class="preview-hero"><div class="preview-hero-left">';
            if (subtitle) html += '<span class="preview-hero-subtitle">' + subtitle + '</span>';
            html += '<h4 class="preview-hero-title">' + title + '</h4>';
            if (desc) html += '<p class="preview-hero-desc">' + desc + '</p>';
            html += '<a class="preview-cta">Get Started →</a>';
            html += '</div><div class="preview-hero-image"><div class="preview-stats-card"><div class="preview-stats-num">500+</div><div class="preview-stats-label">Members</div></div></div></div>';
            // Steps
            html += '<div class="preview-steps">';
            html += '<div class="preview-step"><div class="preview-step-num">01</div><h6>Apply</h6><p>Quick online form</p></div>';
            html += '<div class="preview-step"><div class="preview-step-num">02</div><h6>Review</h6><p>Fast decision</p></div>';
            html += '<div class="preview-step"><div class="preview-step-num">03</div><h6>Funded</h6><p>Direct to account</p></div>';
            html += '</div>';
            // Cards
            html += '<div class="preview-sections">';
            if (showAbout) html += '<div class="preview-section-card"><h5>About Us</h5><p>' + (about || 'Our mission and impact in communities.') + '</p></div>';
            if (showServices) html += '<div class="preview-section-card"><h5>Services</h5><p>Tailored loan products for every need.</p></div>';
            if (showContact) html += '<div class="preview-section-card"><h5>Contact</h5><p>' + (phone ? phone : '') + (email ? '<br/>' + email : '') + '</p></div>';
            html += '</div></div>';
            html += '<div class="preview-footer"><div class="preview-footer-text">&copy; ' + new Date().getFullYear() + ' ' + tenantName + '. All rights reserved.</div></div>';
            return html;
        }

        function generateTemplate2(title, subtitle, desc, about, phone, email, addr, hours, dlTitle, dlDesc, dlBtn, showAbout, showServices, showContact, showDownload) {
            var html = '<div class="preview-header"><div class="preview-logo">' + tenantName + '</div><div class="preview-nav">';
            if (showAbout) html += '<div class="preview-nav-item">About</div>';
            if (showServices) html += '<div class="preview-nav-item">Services</div>';
            if (showContact) html += '<div class="preview-nav-item">Contact</div>';
            html += '</div></div><div class="preview-content">';
            // Editorial hero
            html += '<div class="preview-hero">';
            if (subtitle) html += '<p class="preview-hero-subtitle">' + subtitle + '</p>';
            html += '<h4 class="preview-hero-title">' + title + '</h4>';
            if (desc) html += '<p class="preview-hero-desc">' + desc + '</p>';
            html += '<a class="preview-hero-cta">Learn More</a>';
            html += '</div>';
            // Narrative image
            html += '<div class="preview-narrative-image">';
            if (subtitle) html += '<div class="preview-narrative-badge">' + subtitle + '</div>';
            html += '</div>';
            // Bento cards
            html += '<div class="preview-sections">';
            var cardNum = 1;
            if (showServices) { html += '<div class="preview-section-card"><div class="card-num">0' + cardNum + '</div><h5>Our Services</h5><p>Loan products designed for your growth.</p><span class="explore-link">Explore →</span></div>'; cardNum++; }
            if (showAbout) { html += '<div class="preview-section-card"><div class="card-num">0' + cardNum + '</div><h5>About</h5><p>' + (about || 'Our commitment to empowerment.') + '</p><span class="explore-link">Explore →</span></div>'; cardNum++; }
            if (showContact) { html += '<div class="preview-section-card"><div class="card-num">0' + cardNum + '</div><h5>Contact</h5><p>' + (phone ? phone : '') + (email ? '<br/>' + email : '') + '</p><span class="explore-link">Explore →</span></div>'; cardNum++; }
            if (showDownload) { html += '<div class="preview-section-card"><div class="card-num">0' + cardNum + '</div><h5>' + dlTitle + '</h5><p>' + dlDesc + '</p><span class="explore-link">Explore →</span></div>'; }
            html += '</div>';
            // Quote
            if (showAbout && about) {
                html += '<div class="preview-quote"><blockquote>"' + about + '"</blockquote><cite>— ' + tenantName + '</cite></div>';
            }
            // Stats
            html += '<div class="preview-stats"><div class="preview-stat"><div class="preview-stat-num">500+</div><div class="preview-stat-label">Active Members</div></div><div class="preview-stat"><div class="preview-stat-num">1,200+</div><div class="preview-stat-label">Loans Funded</div></div></div>';
            html += '</div>';
            html += '<div class="preview-footer"><div class="preview-footer-text">&copy; ' + new Date().getFullYear() + ' ' + tenantName + '</div></div>';
            return html;
        }

        function generateTemplate3(title, subtitle, desc, about, phone, email, addr, hours, dlTitle, dlDesc, dlBtn, showAbout, showServices, showContact, showDownload) {
            var html = '<div class="preview-header"><div class="preview-logo">' + tenantName + '</div><div class="preview-nav">';
            if (showAbout) html += '<div class="preview-nav-item">About</div>';
            if (showServices) html += '<div class="preview-nav-item">Features</div>';
            if (showContact) html += '<div class="preview-nav-item">Contact</div>';
            html += '<div class="preview-cta-btn">' + dlBtn + ' ⚡</div>';
            html += '</div></div><div class="preview-content">';
            // Hero with image + floating cards
            html += '<div class="preview-hero"><div class="preview-hero-left">';
            if (subtitle) html += '<span class="preview-hero-subtitle">' + subtitle + '</span>';
            html += '<h4 class="preview-hero-title">' + title + '</h4>';
            if (desc) html += '<p class="preview-hero-desc">' + desc + '</p>';
            html += '<div class="preview-hero-actions"><a class="preview-cta">Get Started →</a>';
            if (showAbout) html += '<a class="preview-cta-secondary">Learn More</a>';
            html += '</div>';
            html += '</div><div class="preview-hero-image"><div class="preview-float-card top-right">✓ 500+</div><div class="preview-float-card bottom-left">📈 Growing</div></div></div>';
            // Bento features
            html += '<div class="preview-sections">';
            if (showServices) html += '<div class="preview-section-card"><h5>🎯 Our Services</h5><p>Fast processing, flexible terms, expert support for every borrower.</p></div>';
            if (showAbout) html += '<div class="preview-section-card"><h5>💡 About Us</h5><p>' + (about || 'Empowering growth through accessible finance.') + '</p></div>';
            if (showContact) html += '<div class="preview-section-card"><h5>📍 Contact</h5><p>' + (phone ? phone : '') + (email ? '<br/>' + email : '') + '</p></div>';
            if (showDownload) html += '<div class="preview-section-card"><h5>📱 ' + dlTitle + '</h5><p>' + dlDesc + '</p></div>';
            html += '</div>';
            // Journey steps
            html += '<div class="preview-journey">';
            html += '<div class="preview-journey-step"><div class="preview-journey-num">01</div><h6>Apply</h6><p>From any device</p></div>';
            html += '<div class="preview-journey-step"><div class="preview-journey-num">02</div><h6>Approved</h6><p>Quick review</p></div>';
            html += '<div class="preview-journey-step"><div class="preview-journey-num">03</div><h6>Funded</h6><p>Direct deposit</p></div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="preview-footer"><div class="preview-footer-text">&copy; ' + new Date().getFullYear() + ' ' + tenantName + '. All rights reserved.</div></div>';
            return html;
        }

        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
    </script>
</body>
</html>
