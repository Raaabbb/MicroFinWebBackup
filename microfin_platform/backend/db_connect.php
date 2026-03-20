<?php
// backend/db_connect.php
// Centralized, secure database connection wrapper using PDO.

$charset = 'utf8mb4';

// Database configuration (Localhost)
$host = 'localhost';
$port = 3306;
$db   = 'microfin_db';
$user = 'root';
$pass = '1234';

// SMTP Global Configuration
define('SMTP_USER', 'microfin.statements@gmail.com');
define('SMTP_PASS', 'ttnq cabw jcbs kbfg');

// Data Source Name
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Schema guard for newer website customization flows.
    // This keeps older databases compatible without a manual migration step.
    try {
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS tenant_website_content (\n                tenant_id VARCHAR(50) PRIMARY KEY,\n                layout_template ENUM('template1', 'template2', 'template3') DEFAULT 'template1',\n                hero_title VARCHAR(255) NULL,\n                hero_subtitle VARCHAR(255) NULL,\n                hero_description TEXT NULL,\n                hero_cta_text VARCHAR(100) DEFAULT 'Learn More',\n                hero_cta_url VARCHAR(255) DEFAULT '#about',\n                hero_image_path VARCHAR(500) NULL,\n                about_heading VARCHAR(255) DEFAULT 'About Us',\n                about_body TEXT NULL,\n                about_image_path VARCHAR(500) NULL,\n                services_heading VARCHAR(255) DEFAULT 'Our Services',\n                services_json LONGTEXT NULL,\n                contact_address TEXT NULL,\n                contact_phone VARCHAR(100) NULL,\n                contact_email VARCHAR(255) NULL,\n                contact_hours VARCHAR(255) NULL,\n                custom_css LONGTEXT NULL,\n                meta_description VARCHAR(255) NULL,\n                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                CONSTRAINT fk_tenant_website_content_tenant\n                    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n        ");
    } catch (\PDOException $migrationError) {
        error_log('Schema guard warning (tenant_website_content): ' . $migrationError->getMessage());
    }

    // Migrate layout_template ENUM from old names to template1/2/3
    try {
        $pdo->exec("ALTER TABLE tenant_website_content MODIFY COLUMN layout_template ENUM('template1', 'template2', 'template3') DEFAULT 'template1'");
        $pdo->exec("UPDATE tenant_website_content SET layout_template = 'template1' WHERE layout_template NOT IN ('template1','template2','template3') OR layout_template IS NULL");
    } catch (\PDOException $e) {
        // Already migrated or table doesn't exist yet
    }

    // Add setup step tracking column for onboarding wizard
    try {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN setup_current_step INT DEFAULT 0 COMMENT 'Onboarding step: 0=password_reset, 1=billing, 2=branding, 3=website, 4=done'");
        // Backfill existing tenants based on their current progress
        $pdo->exec("
            UPDATE tenants t SET setup_current_step =
                CASE
                    WHEN t.setup_completed = TRUE THEN 4
                    WHEN EXISTS (SELECT 1 FROM tenant_website_content w WHERE w.tenant_id = t.tenant_id) THEN 3
                    WHEN EXISTS (SELECT 1 FROM tenant_branding br WHERE br.tenant_id = t.tenant_id) THEN 2
                    WHEN EXISTS (SELECT 1 FROM tenant_billing_payment_methods b WHERE b.tenant_id = t.tenant_id) THEN 1
                    ELSE 0
                END
        ");
    } catch (\PDOException $migrationError) {
        // Column already exists — ignore
    }

    // Add card style columns to tenant_branding
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN theme_border_color VARCHAR(10) DEFAULT '#e2e8f0' COMMENT 'Card border/divider color'");
    } catch (\PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN card_border_width TINYINT DEFAULT 1 COMMENT 'Card border width in px (0-3)'");
    } catch (\PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN card_shadow VARCHAR(10) DEFAULT 'sm' COMMENT 'Card shadow: none, sm, md, lg'");
    } catch (\PDOException $e) {}
} catch (\PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Critical System Error: Unable to establish database connection.',
            'debug' => $e->getMessage()
        ]);
    exit;
}
?>
