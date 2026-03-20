<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || empty($_SESSION['tenant_id'])) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>No valid tenant session.</p>");
}

require_once '../backend/db_connect.php';

$tenant_id   = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'] ?? 'Company Admin';
$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$ui_theme = (($_SESSION['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
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

// ==========================================
// POST Handler — Save Website Content (PRG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_website_content') {
    $layout = 'template1'; // Template 2/3 are temporarily unavailable.

    $hero_title       = trim($_POST['hero_title'] ?? '');
    $hero_subtitle    = trim($_POST['hero_subtitle'] ?? '');
    $hero_description = trim($_POST['hero_description'] ?? '');
    $hero_cta_text    = trim($_POST['hero_cta_text'] ?? 'Learn More');
    $hero_cta_url     = trim($_POST['hero_cta_url'] ?? '#about');
    $hero_image_path  = trim($_POST['hero_image_path'] ?? '');

    $about_heading    = trim($_POST['about_heading'] ?? 'About Us');
    $about_body       = trim($_POST['about_body'] ?? '');
    $about_image_path = trim($_POST['about_image_path'] ?? '');

    $services_heading = trim($_POST['services_heading'] ?? 'Our Services');
    $svc_titles = $_POST['service_title'] ?? [];
    $svc_descs  = $_POST['service_description'] ?? [];
    $svc_icons  = $_POST['service_icon'] ?? [];
    $services = [];
    for ($i = 0; $i < count($svc_titles); $i++) {
        if (trim($svc_titles[$i]) !== '') {
            $services[] = [
                'title'       => trim($svc_titles[$i]),
                'description' => trim($svc_descs[$i] ?? ''),
                'icon'        => trim($svc_icons[$i] ?? 'star')
            ];
        }
    }
    $services_json = json_encode($services, JSON_UNESCAPED_UNICODE);

    $contact_address  = trim($_POST['contact_address'] ?? '');
    $contact_phone    = trim($_POST['contact_phone'] ?? '');
    $contact_email    = trim($_POST['contact_email'] ?? '');
    $contact_hours    = trim($_POST['contact_hours'] ?? '');
    $custom_css       = trim($_POST['custom_css'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    $website_config = [
        'website_show_about' => isset($_POST['website_show_about']) ? '1' : '0',
        'website_show_services' => isset($_POST['website_show_services']) ? '1' : '0',
        'website_show_contact' => isset($_POST['website_show_contact']) ? '1' : '0',
        'website_show_download' => isset($_POST['website_show_download']) ? '1' : '0',
        'website_download_title' => trim($_POST['website_download_title'] ?? 'Download Our App'),
        'website_download_description' => trim($_POST['website_download_description'] ?? 'Get the app for faster loan tracking and updates.'),
        'website_download_button_text' => trim($_POST['website_download_button_text'] ?? 'Download App'),
        'website_download_url' => trim($_POST['website_download_url'] ?? '')
    ];

    $upsert = $pdo->prepare('
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
            hero_title = VALUES(hero_title), hero_subtitle = VALUES(hero_subtitle),
            hero_description = VALUES(hero_description),
            hero_cta_text = VALUES(hero_cta_text), hero_cta_url = VALUES(hero_cta_url),
            hero_image_path = VALUES(hero_image_path),
            about_heading = VALUES(about_heading), about_body = VALUES(about_body),
            about_image_path = VALUES(about_image_path),
            services_heading = VALUES(services_heading), services_json = VALUES(services_json),
            contact_address = VALUES(contact_address), contact_phone = VALUES(contact_phone),
            contact_email = VALUES(contact_email), contact_hours = VALUES(contact_hours),
            custom_css = VALUES(custom_css), meta_description = VALUES(meta_description)
    ');
    $upsert->execute([
        $tenant_id, $layout, $hero_title, $hero_subtitle, $hero_description,
        $hero_cta_text, $hero_cta_url, $hero_image_path,
        $about_heading, $about_body, $about_image_path,
        $services_heading, $services_json,
        $contact_address, $contact_phone, $contact_email, $contact_hours,
        $custom_css, $meta_description
    ]);

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

    // Auto-enable the public website toggle
    $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
        ->execute([$tenant_id, 'public_website_enabled']);

    $_SESSION['editor_flash'] = 'Website saved and published successfully!';
    header('Location: website_editor.php');
    exit;
}

// ==========================================
// Load existing data
// ==========================================
$flash_msg = '';
if (isset($_SESSION['editor_flash'])) {
    $flash_msg = $_SESSION['editor_flash'];
    unset($_SESSION['editor_flash']);
}

// Branding for sidebar theming
$brand_stmt = $pdo->prepare('SELECT t.tenant_name, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
$primary_color = ($brand && $brand['theme_primary_color']) ? $brand['theme_primary_color'] : '#4f46e5';
$sidebar_color = ($brand && $brand['theme_secondary_color']) ? $brand['theme_secondary_color'] : '#0f172a';
$text_main     = ($brand && $brand['theme_text_main']) ? $brand['theme_text_main'] : '#0f172a';
$text_muted    = ($brand && $brand['theme_text_muted']) ? $brand['theme_text_muted'] : '#64748b';
$bg_body       = ($brand && $brand['theme_bg_body']) ? $brand['theme_bg_body'] : '#f8fafc';
$bg_card       = ($brand && $brand['theme_bg_card']) ? $brand['theme_bg_card'] : '#ffffff';
$font_family   = ($brand && $brand['font_family']) ? $brand['font_family'] : 'Inter';
$logo_path     = ($brand && $brand['logo_path']) ? $brand['logo_path'] : '';
$company_name  = ($brand && $brand['tenant_name']) ? $brand['tenant_name'] : $tenant_name;

// Website content
$ws_stmt = $pdo->prepare('SELECT * FROM tenant_website_content WHERE tenant_id = ?');
$ws_stmt->execute([$tenant_id]);
$ws = $ws_stmt->fetch(PDO::FETCH_ASSOC);

if (!$ws) {
    $ws = [
        'layout_template' => 'template1',
        'hero_title' => '', 'hero_subtitle' => '', 'hero_description' => '',
        'hero_cta_text' => 'Learn More', 'hero_cta_url' => '#about', 'hero_image_path' => '',
        'about_heading' => 'About Us', 'about_body' => '', 'about_image_path' => '',
        'services_heading' => 'Our Services', 'services_json' => '[]',
        'contact_address' => '', 'contact_phone' => '', 'contact_email' => '', 'contact_hours' => '',
        'custom_css' => '', 'meta_description' => ''
    ];
}
if (($ws['layout_template'] ?? '') !== 'template1') {
    $ws['layout_template'] = 'template1';
}

$website_defaults = [
    'website_show_about' => '1',
    'website_show_services' => '1',
    'website_show_contact' => '1',
    'website_show_download' => '1',
    'website_download_title' => 'Download Our App',
    'website_download_description' => 'Get the app for faster loan tracking and updates.',
    'website_download_button_text' => 'Download App',
    'website_download_url' => ''
];
$website_config = $website_defaults;
$settings_stmt = $pdo->prepare("\n    SELECT setting_key, setting_value\n    FROM system_settings\n    WHERE tenant_id = ?\n      AND setting_key IN (\n        'website_show_about',\n        'website_show_services',\n        'website_show_contact',\n        'website_show_download',\n        'website_download_title',\n        'website_download_description',\n        'website_download_button_text',\n        'website_download_url'\n      )\n");
$settings_stmt->execute([$tenant_id]);
foreach ($settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['setting_key'], $website_config)) {
        $website_config[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
}

$services = json_decode($ws['services_json'] ?? '[]', true) ?: [];

$e = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
$site_url = '../site.php?site=' . urlencode($tenant_slug);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Editor — <?php echo $e($company_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($font_family); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="admin.css">
    <style>
        :root {
            --primary-color: <?php echo $e($primary_color); ?>;
            --primary-rgb: <?php echo hexToRgb($primary_color); ?>;
            --sidebar-bg: <?php echo $e($sidebar_color); ?>;
            --sidebar-color-dark: <?php echo $e($sidebar_color); ?>;
            --text-main: <?php echo $e($text_main); ?>;
            --text-muted: <?php echo $e($text_muted); ?>;
            --bg-body: <?php echo $e($bg_body); ?>;
            --bg-card: <?php echo $e($bg_card); ?>;
            --font-family: '<?php echo $e($font_family); ?>', sans-serif;
        }
        <?php if ($logo_path): ?>
        .logo-circle { background-image: url('<?php echo $e($logo_path); ?>'); background-size: cover; background-position: center; }
        .logo-circle .material-symbols-rounded { display: none; }
        <?php endif; ?>

        /* Template Picker */
        .template-picker { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .template-option { cursor: pointer; }
        .template-option input[type="radio"] { display: none; }
        .template-card { border: 2px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 20px; text-align: center; transition: all 0.2s; }
        .template-option input:checked + .template-card { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.04); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); }
        .template-card:hover { border-color: var(--primary-color); }
        .template-option.is-disabled { cursor: not-allowed; }
        .template-option.is-disabled .template-card { opacity: 0.55; border-style: dashed; }
        .template-option.is-disabled .template-card:hover { border-color: var(--border-color, #e2e8f0); }
        .template-coming-soon { width: 100%; height: 100%; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: var(--text-muted, #64748b); background: rgba(148, 163, 184, 0.08); }
        .template-card h4 { margin: 12px 0 4px; font-size: 0.95rem; font-weight: 600; }
        .template-card p { font-size: 0.8rem; color: var(--text-muted, #64748b); }
        .template-thumb { width: 100%; height: 140px; border-radius: 8px; background: var(--bg-secondary, #f1f5f9); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .template-thumb svg { width: 90%; height: 90%; }

        /* Content Tabs */
        .editor-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--border-color, #e2e8f0); margin-bottom: 24px; }
        .editor-tab { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--text-muted, #64748b); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; font-family: inherit; }
        .editor-tab:hover { color: var(--text-primary, #0f172a); }
        .editor-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 6px; }
        .form-group .hint { font-size: 0.75rem; color: var(--text-muted, #64748b); margin-top: 4px; }
        .form-group .hint a { color: var(--primary-color); }
        .form-group .hint code { background: var(--bg-secondary, #f1f5f9); padding: 1px 5px; border-radius: 3px; font-size: 0.8rem; }
        .form-input, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; font-size: 0.9rem; font-family: var(--font-family); background: var(--bg-primary, #fff); color: var(--text-primary, #0f172a); transition: border-color 0.15s; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Service Rows */
        .service-row { display: grid; grid-template-columns: 1fr 2fr 120px 40px; gap: 10px; align-items: start; margin-bottom: 12px; padding: 14px; border-radius: 8px; background: var(--bg-secondary, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); }
        .service-row .form-input, .service-row .form-textarea { font-size: 0.85rem; }
        .service-row .form-textarea { min-height: 60px; }
        .btn-remove { width: 36px; height: 36px; border: none; background: none; cursor: pointer; color: #ef4444; border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .btn-remove:hover { background: #fee2e2; }

        /* Cards */
        .editor-card { background: var(--bg-primary, #fff); border-radius: 12px; padding: 28px; border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 20px; }
        .editor-card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; }
        .editor-card .card-desc { font-size: 0.85rem; color: var(--text-muted, #64748b); margin-bottom: 24px; }

        /* Save Bar */
        .save-bar { position: sticky; bottom: 0; z-index: 10; background: var(--bg-primary, #fff); border-top: 1px solid var(--border-color, #e2e8f0); padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .save-bar .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; text-decoration: none; }
        .save-bar .btn-primary { background: var(--primary-color); color: #fff; }
        .save-bar .btn-primary:hover { opacity: 0.9; }
        .save-bar .btn-outline { background: transparent; border: 1px solid var(--border-color, #e2e8f0); color: var(--text-primary, #0f172a); }
        .save-bar .btn-outline:hover { background: var(--bg-secondary, #f8fafc); }

        .btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--primary-color); font-family: inherit; margin-top: 8px; }
        .btn-add:hover { background: rgba(var(--primary-rgb), 0.04); border-color: var(--primary-color); }

        /* Flash */
        .flash-msg { margin: 0 0 20px; padding: 12px 16px; border-radius: 8px; background: #dcfce7; color: #166534; font-weight: 500; font-size: 0.9rem; }

        /* Section Nav */
        .section-nav { display: flex; flex-direction: column; gap: 2px; }
        .section-nav .nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; color: rgba(255,255,255,0.6); text-decoration: none; cursor: pointer; transition: all 0.15s; }
        .section-nav .nav-link:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.9); }
        .section-nav .nav-link.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: 600; }
        .section-nav .nav-link .material-symbols-rounded { font-size: 20px; }
        .editor-section { display: none; }
        .editor-section.active { display: block; }

        .preview-frame { width: 100%; height: 650px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; background: #fff; }

        @media (max-width: 768px) {
            .template-picker { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .service-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-circle"><span class="material-symbols-rounded">diamond</span></div>
                <h2 class="company-name-display"><?php echo $e($company_name); ?></h2>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-item">
                    <span class="material-symbols-rounded">arrow_back</span>
                    <span>Back to Admin</span>
                </a>
            </nav>

            <div style="padding: 0 8px; margin-top: 16px;">
                <div class="section-nav" id="editor-nav">
                    <a class="nav-link active" data-section="section-template">
                        <span class="material-symbols-rounded">view_quilt</span> Layout Template
                    </a>
                    <a class="nav-link" data-section="section-content">
                        <span class="material-symbols-rounded">edit_note</span> Edit Content
                    </a>
                    <a class="nav-link" data-section="section-preview">
                        <span class="material-symbols-rounded">visibility</span> Preview
                    </a>
                </div>
            </div>

            <div style="margin-top: auto; padding: 16px 12px;">
                <a href="<?php echo $e($site_url); ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.6); text-decoration: none; padding: 10px 14px; border-radius: 8px; background: rgba(255,255,255,0.08);">
                    <span class="material-symbols-rounded" style="font-size: 18px;">open_in_new</span>
                    View Live Site
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1 id="page-title">Website Editor</h1>
            </header>

            <div class="views-container" style="padding: 24px 32px;">
                <?php if ($flash_msg): ?>
                <div class="flash-msg"><?php echo $e($flash_msg); ?></div>
                <?php endif; ?>

                <form method="POST" id="editor-form">
                    <input type="hidden" name="action" value="save_website_content">

                    <!-- SECTION: Layout Template -->
                    <div class="editor-section active" id="section-template">
                        <div class="editor-card">
                            <h3>Choose a Layout</h3>
                            <p class="card-desc">Select the visual structure for your public website. Each template arranges the same content differently.</p>
                            <div class="template-picker">
                                <label class="template-option">
                                    <input type="radio" name="layout_template" value="template1" checked>
                                    <div class="template-card">
                                        <div class="template-thumb">
                                            <svg viewBox="0 0 200 150" fill="none"><rect width="200" height="150" rx="4" fill="#f1f5f9"/><rect x="10" y="8" width="180" height="55" rx="4" fill="rgba(79,70,229,0.15)"/><rect x="20" y="22" width="80" height="6" rx="2" fill="rgba(79,70,229,0.4)"/><rect x="20" y="32" width="60" height="4" rx="2" fill="rgba(79,70,229,0.2)"/><rect x="10" y="72" width="85" height="4" rx="2" fill="#cbd5e1"/><rect x="10" y="80" width="85" height="3" rx="1" fill="#e2e8f0"/><rect x="105" y="68" width="85" height="28" rx="4" fill="#e2e8f0"/><rect x="10" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/><rect x="72" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/><rect x="134" y="105" width="56" height="36" rx="4" fill="rgba(79,70,229,0.08)"/></svg>
                                        </div>
                                        <h4>Template 1</h4>
                                        <p>Card-based hero with stats overlay. Bold & impactful.</p>
                                    </div>
                                </label>
                                <label class="template-option is-disabled" title="Under Development">
                                    <input type="radio" name="layout_template" value="template2" disabled>
                                    <div class="template-card">
                                        <div class="template-thumb">
                                            <div class="template-coming-soon">Not Available Yet</div>
                                        </div>
                                        <h4>Template 2</h4>
                                        <p>Under Development</p>
                                    </div>
                                </label>
                                <label class="template-option is-disabled" title="Under Development">
                                    <input type="radio" name="layout_template" value="template3" disabled>
                                    <div class="template-card">
                                        <div class="template-thumb">
                                            <div class="template-coming-soon">Not Available Yet</div>
                                        </div>
                                        <h4>Template 3</h4>
                                        <p>Under Development</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: Edit Content -->
                    <div class="editor-section" id="section-content">
                        <div class="editor-tabs">
                            <button type="button" class="editor-tab active" data-tab="tab-hero">Hero</button>
                            <button type="button" class="editor-tab" data-tab="tab-about">About Us</button>
                            <button type="button" class="editor-tab" data-tab="tab-services">Services</button>
                            <button type="button" class="editor-tab" data-tab="tab-contact">Contact</button>
                            <button type="button" class="editor-tab" data-tab="tab-visibility">Visibility & Download</button>
                            <button type="button" class="editor-tab" data-tab="tab-advanced">Advanced</button>
                        </div>

                        <!-- Hero Tab -->
                        <div class="tab-content active" id="tab-hero">
                            <div class="editor-card">
                                <h3>Hero / Banner Section</h3>
                                <p class="card-desc">The first thing visitors see. Make it count.</p>
                                <div class="form-group">
                                    <label>Title</label>
                                    <input type="text" name="hero_title" class="form-input" value="<?php echo $e($ws['hero_title']); ?>" placeholder="Welcome to Our Institution">
                                </div>
                                <div class="form-group">
                                    <label>Subtitle</label>
                                    <input type="text" name="hero_subtitle" class="form-input" value="<?php echo $e($ws['hero_subtitle']); ?>" placeholder="Your Trusted Microfinance Partner">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="hero_description" class="form-textarea" rows="3" placeholder="A brief description of your organization..."><?php echo $e($ws['hero_description']); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>CTA Button Text</label>
                                        <input type="text" name="hero_cta_text" class="form-input" value="<?php echo $e($ws['hero_cta_text']); ?>" placeholder="Learn More">
                                    </div>
                                    <div class="form-group">
                                        <label>CTA Button Link</label>
                                        <input type="text" name="hero_cta_url" class="form-input" value="<?php echo $e($ws['hero_cta_url']); ?>" placeholder="#about">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Hero Background Image URL</label>
                                    <input type="text" name="hero_image_path" class="form-input" value="<?php echo $e($ws['hero_image_path']); ?>" placeholder="https://images.unsplash.com/...">
                                    <p class="hint">Paste an external image URL. For best results use a wide landscape image (1920x1080 or similar).</p>
                                </div>
                            </div>
                        </div>

                        <!-- About Tab -->
                        <div class="tab-content" id="tab-about">
                            <div class="editor-card">
                                <h3>About Us Section</h3>
                                <p class="card-desc">Tell visitors about your organization, mission, and history.</p>
                                <div class="form-group">
                                    <label>Section Heading</label>
                                    <input type="text" name="about_heading" class="form-input" value="<?php echo $e($ws['about_heading']); ?>" placeholder="About Us">
                                </div>
                                <div class="form-group">
                                    <label>Body Text</label>
                                    <textarea name="about_body" class="form-textarea" rows="6" placeholder="Tell your story..."><?php echo $e($ws['about_body']); ?></textarea>
                                    <p class="hint">Line breaks will be preserved on the live site.</p>
                                </div>
                                <div class="form-group">
                                    <label>Image URL (optional)</label>
                                    <input type="text" name="about_image_path" class="form-input" value="<?php echo $e($ws['about_image_path']); ?>" placeholder="https://...">
                                </div>
                            </div>
                        </div>

                        <!-- Services Tab -->
                        <div class="tab-content" id="tab-services">
                            <div class="editor-card">
                                <h3>Services / Products</h3>
                                <p class="card-desc">List the financial services your institution offers.</p>
                                <div class="form-group">
                                    <label>Section Heading</label>
                                    <input type="text" name="services_heading" class="form-input" value="<?php echo $e($ws['services_heading']); ?>" placeholder="Our Services">
                                </div>
                                <div id="services-list">
                                    <?php if (empty($services)): ?>
                                    <p style="color: var(--text-muted, #64748b); font-size: 0.85rem; padding: 12px 0;">No services added yet. Click below to add one.</p>
                                    <?php endif; ?>
                                    <?php foreach ($services as $svc): ?>
                                    <div class="service-row">
                                        <input type="text" name="service_title[]" class="form-input" value="<?php echo $e($svc['title']); ?>" placeholder="Service name">
                                        <textarea name="service_description[]" class="form-textarea" rows="2" placeholder="Brief description"><?php echo $e($svc['description']); ?></textarea>
                                        <input type="text" name="service_icon[]" class="form-input" value="<?php echo $e($svc['icon']); ?>" placeholder="Icon name">
                                        <button type="button" class="btn-remove" onclick="this.closest('.service-row').remove()" title="Remove">
                                            <span class="material-symbols-rounded">close</span>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add" onclick="addServiceRow()">
                                    <span class="material-symbols-rounded">add</span> Add Service
                                </button>
                                <p class="hint" style="margin-top: 12px;">Icon names use <a href="https://fonts.google.com/icons?icon.set=Material+Symbols" target="_blank">Material Symbols</a> (e.g. <code>payments</code>, <code>savings</code>, <code>account_balance</code>, <code>credit_card</code>, <code>handshake</code>)</p>
                            </div>
                        </div>

                        <!-- Contact Tab -->
                        <div class="tab-content" id="tab-contact">
                            <div class="editor-card">
                                <h3>Contact Information & Footer</h3>
                                <p class="card-desc">Displayed in the footer of your public website.</p>
                                <div class="form-group">
                                    <label>Company Address</label>
                                    <textarea name="contact_address" class="form-textarea" rows="2" placeholder="123 Main St, City, Province"><?php echo $e($ws['contact_address']); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="contact_phone" class="form-input" value="<?php echo $e($ws['contact_phone']); ?>" placeholder="+63 912 345 6789">
                                    </div>
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="contact_email" class="form-input" value="<?php echo $e($ws['contact_email']); ?>" placeholder="info@company.com">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Office Hours</label>
                                    <input type="text" name="contact_hours" class="form-input" value="<?php echo $e($ws['contact_hours']); ?>" placeholder="Mon-Fri 8:00 AM - 5:00 PM">
                                </div>
                            </div>
                        </div>

                        <!-- Visibility & Download Tab -->
                        <div class="tab-content" id="tab-visibility">
                            <div class="editor-card">
                                <h3>Section Visibility</h3>
                                <p class="card-desc">Choose which sections are shown on your public website.</p>
                                <div class="form-group">
                                    <label><input type="checkbox" name="website_show_about" value="1" <?php echo $website_config['website_show_about'] === '1' ? 'checked' : ''; ?>> Show About Section</label>
                                </div>
                                <div class="form-group">
                                    <label><input type="checkbox" name="website_show_services" value="1" <?php echo $website_config['website_show_services'] === '1' ? 'checked' : ''; ?>> Show Services Section</label>
                                </div>
                                <div class="form-group">
                                    <label><input type="checkbox" name="website_show_contact" value="1" <?php echo $website_config['website_show_contact'] === '1' ? 'checked' : ''; ?>> Show Contact Details</label>
                                </div>
                                <div class="form-group">
                                    <label><input type="checkbox" name="website_show_download" value="1" <?php echo $website_config['website_show_download'] === '1' ? 'checked' : ''; ?>> Show App Download Section</label>
                                </div>

                                <hr style="border: 0; border-top: 1px solid var(--border-color, #e2e8f0); margin: 20px 0;">

                                <h3 style="font-size: 1rem; margin-bottom: 8px;">App Download Content</h3>
                                <p class="card-desc">This section is best for your app install link only.</p>
                                <div class="form-group">
                                    <label>Download Section Title</label>
                                    <input type="text" name="website_download_title" class="form-input" value="<?php echo $e($website_config['website_download_title']); ?>" placeholder="Download Our App">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="website_download_description" class="form-textarea" rows="3" placeholder="Tell users why they should install your app."><?php echo $e($website_config['website_download_description']); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Button Text</label>
                                        <input type="text" name="website_download_button_text" class="form-input" value="<?php echo $e($website_config['website_download_button_text']); ?>" placeholder="Download App">
                                    </div>
                                    <div class="form-group">
                                        <label>App Download URL</label>
                                        <input type="url" name="website_download_url" class="form-input" value="<?php echo $e($website_config['website_download_url']); ?>" placeholder="https://play.google.com/store/apps/details?id=...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Tab -->
                        <div class="tab-content" id="tab-advanced">
                            <div class="editor-card">
                                <h3>Advanced Settings</h3>
                                <p class="card-desc">Optional customizations for your website.</p>
                                <div class="form-group">
                                    <label>SEO Meta Description</label>
                                    <input type="text" name="meta_description" class="form-input" value="<?php echo $e($ws['meta_description']); ?>" placeholder="A brief description for search engines..." maxlength="255">
                                    <p class="hint">Shown in search engine results. Max 255 characters.</p>
                                </div>
                                <div class="form-group">
                                    <label>Custom CSS</label>
                                    <textarea name="custom_css" class="form-textarea" rows="8" placeholder="/* Add custom CSS overrides here */" style="font-family: monospace; font-size: 0.85rem;"><?php echo $e($ws['custom_css']); ?></textarea>
                                    <p class="hint">Advanced: add CSS rules to override template styles. Use with caution.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: Preview -->
                    <div class="editor-section" id="section-preview">
                        <div class="editor-card">
                            <h3>Live Preview</h3>
                            <p class="card-desc">This shows your currently published website. Save changes first to see updates here.</p>
                            <div style="margin: 16px 0; display: flex; gap: 8px;">
                                <button type="button" class="btn-add" onclick="document.getElementById('preview-iframe').src = document.getElementById('preview-iframe').src">
                                    <span class="material-symbols-rounded">refresh</span> Refresh Preview
                                </button>
                                <a href="<?php echo $e($site_url); ?>" target="_blank" class="btn-add" style="text-decoration: none;">
                                    <span class="material-symbols-rounded">open_in_new</span> Open in New Tab
                                </a>
                            </div>
                            <iframe id="preview-iframe" src="<?php echo $e($site_url); ?>" class="preview-frame"></iframe>
                        </div>
                    </div>

                    <!-- Save Bar -->
                    <div class="save-bar">
                        <a href="admin.php" style="font-size: 0.85rem; color: var(--text-muted, #64748b); text-decoration: none; display: flex; align-items: center; gap: 6px;">
                            <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span> Back to Admin Panel
                        </a>
                        <div style="display: flex; gap: 10px;">
                            <a href="<?php echo $e($site_url); ?>" target="_blank" class="btn btn-outline">
                                <span class="material-symbols-rounded" style="font-size: 18px;">visibility</span> Preview
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-rounded" style="font-size: 18px;">save</span> Save & Publish
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Section navigation (sidebar)
        const navLinks = document.querySelectorAll('#editor-nav .nav-link');
        const sections = document.querySelectorAll('.editor-section');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const target = link.dataset.section;
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                sections.forEach(s => s.classList.remove('active'));
                const el = document.getElementById(target);
                if (el) el.classList.add('active');
                document.getElementById('page-title').textContent = link.textContent.trim();
            });
        });

        // Content tabs
        const tabs = document.querySelectorAll('.editor-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                tabContents.forEach(tc => tc.classList.remove('active'));
                const target = document.getElementById(tab.dataset.tab);
                if (target) target.classList.add('active');
            });
        });

        // Auto-dismiss flash
        const flash = document.querySelector('.flash-msg');
        if (flash) {
            setTimeout(() => { flash.style.opacity = '0'; flash.style.transition = 'opacity 0.3s'; setTimeout(() => flash.remove(), 300); }, 4000);
        }
    });

    function addServiceRow() {
        const list = document.getElementById('services-list');
        const emptyMsg = list.querySelector('p');
        if (emptyMsg) emptyMsg.remove();
        const row = document.createElement('div');
        row.className = 'service-row';
        row.innerHTML = `
            <input type="text" name="service_title[]" class="form-input" placeholder="Service name">
            <textarea name="service_description[]" class="form-textarea" rows="2" placeholder="Brief description"></textarea>
            <input type="text" name="service_icon[]" class="form-input" placeholder="star" value="star">
            <button type="button" class="btn-remove" onclick="this.closest('.service-row').remove()" title="Remove">
                <span class="material-symbols-rounded">close</span>
            </button>
        `;
        list.appendChild(row);
    }
    </script>
</body>
</html>