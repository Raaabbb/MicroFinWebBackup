<?php
// site.php â€” Tenant Public Website
// URL: site.php?site=tenant-slug
// No authentication required.

require_once 'backend/db_connect.php';

$slug = trim($_GET['site'] ?? '');
$error_page = false;
$error_msg = '';
$data = null;

if ($slug === '') {
    $error_page = true;
    $error_msg = 'No website specified.';
} else {
    // Backward-compatible select: older DBs may not yet have new website columns.
    $website_columns = [];
    try {
        $cols_stmt = $pdo->query('SHOW COLUMNS FROM tenant_website_content');
        foreach ($cols_stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $website_columns[$col['Field']] = true;
        }
    } catch (PDOException $ex) {
        $website_columns = [];
    }

    $optional_columns = [
        'stats_json' => "'[]'",
        'stats_heading' => "''",
        'stats_subheading' => "''",
        'stats_image_path' => "''",
        'hero_badge_text' => "''",
        'footer_description' => "''",
    ];

    $optional_select_parts = [];
    foreach ($optional_columns as $column_name => $fallback_sql) {
        if (isset($website_columns[$column_name])) {
            $optional_select_parts[] = "w.$column_name";
        } else {
            $optional_select_parts[] = "$fallback_sql AS $column_name";
        }
    }

    $stmt = $pdo->prepare(
        "SELECT
            t.tenant_id, t.tenant_name, t.tenant_slug, t.status,
            b.logo_path, b.font_family, b.theme_primary_color, b.theme_secondary_color,
            b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card,
            w.layout_template, w.hero_title, w.hero_subtitle, w.hero_description,
            w.hero_cta_text, w.hero_cta_url, w.hero_image_path,
            w.about_heading, w.about_body, w.about_image_path,
            w.services_heading, w.services_json,
            " . implode(",\n            ", $optional_select_parts) . ",
            w.contact_address, w.contact_phone, w.contact_email, w.contact_hours,
            w.custom_css, w.meta_description
        FROM tenants t
        LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id
        LEFT JOIN tenant_website_content w ON t.tenant_id = w.tenant_id
        WHERE t.tenant_slug = ?"
    );
    $stmt->execute([$slug]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        $error_page = true;
        $error_msg = 'This website does not exist.';
    } elseif ($data['status'] !== 'Active') {
        $error_page = true;
        $error_msg = 'This organization is currently inactive.';
    } else {
        // Check public_website_enabled toggle
        $toggle_stmt = $pdo->prepare('SELECT is_enabled FROM tenant_feature_toggles WHERE tenant_id = ? AND toggle_key = ?');
        $toggle_stmt->execute([$data['tenant_id'], 'public_website_enabled']);
        $toggle = $toggle_stmt->fetch();

        if (!$toggle || !(int)$toggle['is_enabled']) {
            $error_page = true;
            $error_msg = 'This organization has not enabled their public website.';
        } elseif (!$data['layout_template']) {
            $error_page = true;
            $error_msg = 'This organization has not set up their website yet.';
        }
    }
}

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

function settingAsBool($value, $default = true) {
    if ($value === null) {
        return $default;
    }
    $normalized = strtolower(trim((string)$value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

// --- Error Page ---
if ($error_page) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Not Available</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #334155; }
        .error-card { background: #fff; border-radius: 16px; padding: 48px; text-align: center; max-width: 440px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .error-card .icon { font-size: 56px; color: #94a3b8; margin-bottom: 16px; }
        .error-card h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 8px; }
        .error-card p { color: #64748b; font-size: 0.9rem; line-height: 1.6; }
        .error-card a { display: inline-block; margin-top: 20px; color: #4f46e5; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        .error-card a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="material-symbols-rounded icon">language</span>
        <h1>Website Not Available</h1>
        <p><?php echo htmlspecialchars($error_msg); ?></p>
        <a href="javascript:history.back()">Go Back</a>
    </div>
</body>
</html>
<?php
    exit;
}

// --- Prepare Data ---
$e = function($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); };

$tenant_name = $data['tenant_name'];
$layout = $data['layout_template'];
$logo = $data['logo_path'] ?? '';

$primary   = $data['theme_primary_color'] ?: '#dc2626';
$secondary = $data['theme_secondary_color'] ?: '#991b1b';
$text_main = $data['theme_text_main'] ?: '#0f172a';
$text_muted = $data['theme_text_muted'] ?: '#64748b';
$bg_body   = $data['theme_bg_body'] ?: '#f8fafc';
$bg_card   = $data['theme_bg_card'] ?: '#ffffff';
$font_family = $data['font_family'] ?: 'Inter';

$hero_title = $data['hero_title'] ?: 'Welcome';
$hero_subtitle = $data['hero_subtitle'] ?: '';
$hero_desc = $data['hero_description'] ?: '';
$hero_cta_text = $data['hero_cta_text'] ?: 'Learn More';
$hero_cta_url = $data['hero_cta_url'] ?: '#about';
$hero_image = $data['hero_image_path'] ?: '';

$about_heading = $data['about_heading'] ?: 'About Us';
$about_body = $data['about_body'] ?: '';
$about_image = $data['about_image_path'] ?: '';

$services_heading = $data['services_heading'] ?: 'Our Services';
$services = json_decode($data['services_json'] ?? '[]', true) ?: [];

$contact_address = $data['contact_address'] ?: '';
$contact_phone = $data['contact_phone'] ?: '';
$contact_email = $data['contact_email'] ?: '';
$contact_hours = $data['contact_hours'] ?: '';
$custom_css = $data['custom_css'] ?? '';
$meta_desc = $data['meta_description'] ?? '';

$hero_badge_text = $data['hero_badge_text'] ?? '';
$footer_description = $data['footer_description'] ?? '';
$stats_heading = $data['stats_heading'] ?? '';
$stats_subheading = $data['stats_subheading'] ?? '';
$stats_image = $data['stats_image_path'] ?? '';
$stats = json_decode($data['stats_json'] ?? '[]', true) ?: [];

$website_settings = [
    'website_show_about' => '1',
    'website_show_services' => '1',
    'website_show_contact' => '1',
    'website_show_download' => '1',
    'website_download_title' => 'Download Our App',
    'website_download_description' => 'Get the app for faster loan tracking and updates.',
    'website_download_button_text' => 'Download App',
    'website_download_url' => '',
    'website_hero_background' => '',
    'website_show_stats' => '1',
    'website_show_loan_calc' => '1',
    'website_show_partners' => '0',
    'website_partners_json' => '[]'
];
$settings_stmt = $pdo->prepare("
    SELECT setting_key, setting_value
    FROM system_settings
    WHERE tenant_id = ?
      AND setting_key IN (
        'website_show_about',
        'website_show_services',
        'website_show_contact',
        'website_show_download',
        'website_download_title',
        'website_download_description',
        'website_download_button_text',
        'website_download_url',
        'website_hero_background',
        'website_show_stats',
        'website_show_loan_calc',
        'website_show_partners',
        'website_partners_json'
      )
");
$settings_stmt->execute([$data['tenant_id']]);
foreach ($settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['setting_key'], $website_settings)) {
        $website_settings[$row['setting_key']] = (string)($row['setting_value'] ?? '');
    }
}

$show_about = settingAsBool($website_settings['website_show_about'], true);
$show_services = settingAsBool($website_settings['website_show_services'], true);
$show_contact = settingAsBool($website_settings['website_show_contact'], true);
$show_download = settingAsBool($website_settings['website_show_download'], true);
$show_stats = settingAsBool($website_settings['website_show_stats'], true);
$show_loan_calc = settingAsBool($website_settings['website_show_loan_calc'], true);
$show_partners = settingAsBool($website_settings['website_show_partners'], false);
$partners = json_decode($website_settings['website_partners_json'] ?? '[]', true) ?: [];

$download_title = trim($website_settings['website_download_title']);
$download_description = trim($website_settings['website_download_description']);
$download_button_text = trim($website_settings['website_download_button_text']);
$download_url = trim($website_settings['website_download_url']);
$hero_bg_path = trim($website_settings['website_hero_background']);

// Fetch tenant stats for display
$stats_stmt = $pdo->prepare("SELECT COUNT(*) as total_clients FROM clients WHERE tenant_id = ? AND client_status = 'Active'");
$stats_stmt->execute([$data['tenant_id']]);
$total_clients = (int)$stats_stmt->fetchColumn();

$stats_loans_stmt = $pdo->prepare("SELECT COUNT(*) as total_loans FROM loans WHERE tenant_id = ? AND loan_status = 'Active'");
$stats_loans_stmt->execute([$data['tenant_id']]);
$total_loans = (int)$stats_loans_stmt->fetchColumn();

$show_download_section = $show_download && $download_url !== '';

// Fetch active loan products for loan calculator
$loan_products = [];
try {
    $lp_stmt = $pdo->prepare('SELECT product_name, product_type, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months, processing_fee_percentage FROM loan_products WHERE tenant_id = ? AND is_active = 1 ORDER BY product_name');
    $lp_stmt->execute([$data['tenant_id']]);
    $loan_products = $lp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $loan_products = [];
}

if ($download_title === '') {
    $download_title = 'Download Our App';
}
if ($download_button_text === '') {
    $download_button_text = 'Download App';
}

// --- Color Palette Generator ---
function adjustColor($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    if ($percent > 0) {
        $r = round($r + (255 - $r) * $percent / 100);
        $g = round($g + (255 - $g) * $percent / 100);
        $b = round($b + (255 - $b) * $percent / 100);
    } else {
        $factor = (100 + $percent) / 100;
        $r = round($r * $factor);
        $g = round($g * $factor);
        $b = round($b * $factor);
    }
    return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

function generatePalette($primary, $secondary, $text_main, $text_muted, $bg_body, $bg_card) {
    return [
        'primary'                       => $primary,
        'primary-container'             => adjustColor($primary, -25),
        'on-primary'                    => '#ffffff',
        'on-primary-container'          => adjustColor($primary, 60),
        'on-primary-fixed'              => adjustColor($primary, -50),
        'on-primary-fixed-variant'      => adjustColor($primary, -40),
        'primary-fixed'                 => adjustColor($primary, 75),
        'primary-fixed-dim'             => adjustColor($primary, 55),

        'secondary'                     => $secondary,
        'secondary-container'           => adjustColor($secondary, 70),
        'on-secondary'                  => '#ffffff',
        'on-secondary-container'        => adjustColor($secondary, -30),
        'secondary-fixed'               => adjustColor($secondary, 70),
        'secondary-fixed-dim'           => adjustColor($secondary, 50),
        'on-secondary-fixed'            => adjustColor($secondary, -50),
        'on-secondary-fixed-variant'    => adjustColor($secondary, -30),

        'tertiary'                      => adjustColor($secondary, -20),
        'tertiary-container'            => adjustColor($secondary, 60),
        'on-tertiary'                   => '#ffffff',
        'on-tertiary-container'         => adjustColor($secondary, -40),
        'tertiary-fixed'                => adjustColor($secondary, 65),
        'tertiary-fixed-dim'            => adjustColor($secondary, 45),

        'background'                    => $bg_body,
        'on-background'                 => $text_main,
        'surface'                       => $bg_body,
        'surface-bright'                => $bg_body,
        'surface-dim'                   => adjustColor($bg_body, -15),
        'surface-tint'                  => $primary,
        'surface-container-lowest'      => $bg_card,
        'surface-container-low'         => adjustColor($bg_body, -3),
        'surface-container'             => adjustColor($bg_body, -6),
        'surface-container-high'        => adjustColor($bg_body, -10),
        'surface-container-highest'     => adjustColor($bg_body, -15),
        'surface-variant'               => adjustColor($bg_body, -12),
        'on-surface'                    => $text_main,
        'on-surface-variant'            => $text_muted,

        'outline'                       => adjustColor($text_muted, 25),
        'outline-variant'               => adjustColor($text_muted, 55),

        'inverse-surface'               => adjustColor($text_main, -10),
        'inverse-on-surface'            => adjustColor($bg_body, 10),
        'inverse-primary'               => adjustColor($primary, 55),

        'error'                         => '#ba1a1a',
        'on-error'                      => '#ffffff',
        'error-container'               => '#ffdad6',
        'on-error-container'            => '#410002',
    ];
}

$palette = generatePalette($primary, $secondary, $text_main, $text_muted, $bg_body, $bg_card);

// --- Include Selected Template ---
$template_map = [
    'template1' => __DIR__ . '/templates/template1.php',
    'template2' => __DIR__ . '/templates/template2.php',
    'template3' => __DIR__ . '/templates/template3.php',
];

$template_file = $template_map[$layout] ?? $template_map['template1'];
include $template_file;