<?php
session_start();

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../backend/db_connect.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'provision_tenant') {
        $tenant_name = trim($_POST['tenant_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $custom_slug = trim($_POST['custom_slug'] ?? '');
        $plan_tier = trim($_POST['plan_tier'] ?? 'Starter');
        if (!array_key_exists($plan_tier, $plan_pricing_map)) {
            $plan_tier = 'Starter';
        }
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $mi = trim($_POST['mi'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $branch_name = trim($_POST['branch_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');

        if ($custom_slug !== '') {
            $tenant_slug = preg_replace('/[^a-z0-9-]/', '', strtolower($custom_slug));
        } else {
            $tenant_slug = preg_replace('/[^a-z0-9]/', '', strtolower($tenant_name));
        }

        $mrr = $plan_pricing_map[$plan_tier];

        if ($tenant_name === '' || $admin_email === '') {
            $_SESSION['sa_error'] = 'Institution name and admin email are required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        } else {
            $check = $pdo->prepare('SELECT tenant_id FROM tenants WHERE tenant_slug = ?');
            $check->execute([$tenant_slug]);
            if ($check->fetch()) {
                $tenant_slug .= substr(bin2hex(random_bytes(3)), 0, 6);
            }

            $existing_check = $pdo->prepare("SELECT tenant_id FROM tenants WHERE tenant_name = ? AND status IN ('Demo Requested', 'Contacted') LIMIT 1");
            $existing_check->execute([$tenant_name]);
            $existing = $existing_check->fetch();

            if ($existing) {
                $tenant_id = $existing['tenant_id'];
                $update = $pdo->prepare("UPDATE tenants SET tenant_slug = ?, email = ?, first_name = ?, last_name = ?, mi = ?, suffix = ?, branch_name = ?, contact_number = ?, status = 'Active', plan_tier = ?, mrr = ?, onboarding_deadline = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE tenant_id = ?");
                $update->execute([$tenant_slug, $admin_email, $first_name, $last_name, $mi, $suffix, $branch_name, $contact_number, $plan_tier, $mrr, $tenant_id]);
            } else {
                $tenant_id = $tenant_slug;
                $insert = $pdo->prepare("INSERT INTO tenants (tenant_id, tenant_name, tenant_slug, email, first_name, last_name, mi, suffix, branch_name, contact_number, status, plan_tier, mrr, onboarding_deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                $insert->execute([$tenant_id, $tenant_name, $tenant_slug, $admin_email, $first_name, $last_name, $mi, $suffix, $branch_name, $contact_number, $plan_tier, $mrr]);
            }

            $role_insert = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Admin', 'Default system administrator', TRUE)");
            $role_insert->execute([$tenant_id]);
            $new_role_id = $pdo->lastInsertId();

            $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $user_insert = $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status) VALUES (?, 'admin', ?, ?, TRUE, ?, 'Employee', 'Active')");
            $user_insert->execute([$tenant_id, $admin_email, $password_hash, $new_role_id]);

            $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('TENANT_PROVISIONED', 'tenant', ?, ?)");
            $log->execute(["Provisioned new tenant: {$tenant_name} ({$plan_tier} Plan)", $tenant_id]);

            if (isset($_POST['data_migration']) && $_POST['data_migration'] == '1') {
                $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('DATA_MIGRATION_REQUIRED', 'tenant', 'Client requested legacy data migration', ?)");
                $log->execute([$tenant_id]);
            }

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

                $mail->setFrom(SMTP_USER, 'MicroFin Provisioning');
                $mail->addAddress($admin_email);

                $mail->isHTML(true);
                $mail->Subject = 'MicroFin - Your Instance is Ready!';
                $mail->Body    = $message;

                $mail->send();
                $email_status = ' An email has been sent to the admin.';
            } catch (Exception $e) {
                $email_status = " (Email failed: {$mail->ErrorInfo})";
            }

            $_SESSION['sa_flash'] = 'Tenant provisioned successfully.' . $email_status;
            header('Location: super_admin.php?section=tenants');
            exit;
        }
    } elseif ($action === 'toggle_status') {
        $tenant_id = $_POST['tenant_id'] ?? '';
        $new_status = $_POST['new_status'] ?? 'Active';

        $update = $pdo->prepare("UPDATE tenants SET status = ? WHERE tenant_id = ?");
        $update->execute([$new_status, $tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('TENANT_STATUS_CHANGE', 'tenant', ?, ?)");
        $log->execute(["Tenant status changed to {$new_status}", $tenant_id]);

        $_SESSION['sa_flash'] = "Tenant status updated to {$new_status}.";
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'reject_tenant') {
        $tenant_id = $_POST['tenant_id'] ?? '';

        $update = $pdo->prepare("UPDATE tenants SET status = 'Rejected' WHERE tenant_id = ?");
        $update->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('TENANT_REJECTED', 'tenant', 'Tenant application rejected', ?)");
        $log->execute([$tenant_id]);

        $_SESSION['sa_flash'] = "Tenant has been rejected.";
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'update_settings') {
        $log = $pdo->query("INSERT INTO audit_logs (action_type, entity_type, description) VALUES ('SETTINGS_UPDATE', 'platform', 'Global platform settings were updated by Admin')");

        $_SESSION['sa_flash'] = "Platform global settings saved successfully.";
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

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status IN ('Demo Requested', 'Contacted') AND deleted_at IS NULL");
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
    SELECT t.tenant_id, t.tenant_name, t.tenant_slug, t.first_name, t.last_name, t.mi, t.suffix,
           t.branch_name, t.contact_number, t.email, t.status, t.plan_tier, t.mrr, t.created_at,
           COALESCE(doc_summary.document_count, 0) AS legitimacy_document_count,
           doc_summary.document_paths AS legitimacy_document_paths
    FROM tenants t
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

// Audit Logs: initial 100 + distinct action types
$logs_stmt = $pdo->query("
    SELECT al.log_id, al.action_type, al.entity_type, al.entity_id,
           al.description, al.ip_address, al.created_at,
           u.username, u.email AS user_email,
           t.tenant_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    LEFT JOIN tenants t ON al.tenant_id = t.tenant_id
    ORDER BY al.log_id DESC LIMIT 100
");
$audit_logs = $logs_stmt->fetchAll();

$action_types_stmt = $pdo->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type");
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

// Recent 5 tenants for dashboard quick-glance
$recent_tenants_stmt = $pdo->query("
    SELECT tenant_name, status, plan_tier, created_at
    FROM tenants WHERE deleted_at IS NULL
    ORDER BY created_at DESC LIMIT 5
");
$recent_tenants = $recent_tenants_stmt->fetchAll();
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

                <div class="nav-section-label">Management</div>
                <a href="#tenants" class="nav-item" data-target="tenants">
                    <span class="material-symbols-rounded">domain</span>
                    <span>Tenants</span>
                </a>

                <div class="nav-section-label">Analytics</div>
                <a href="#reports" class="nav-item" data-target="reports">
                    <span class="material-symbols-rounded">monitoring</span>
                    <span>Reports</span>
                </a>
                <a href="#sales" class="nav-item" data-target="sales">
                    <span class="material-symbols-rounded">point_of_sale</span>
                    <span>Revenue</span>
                </a>

                <div class="nav-section-label">System</div>
                <a href="#audit-logs" class="nav-item" data-target="audit-logs">
                    <span class="material-symbols-rounded">history</span>
                    <span>Audit Logs</span>
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
                            <button class="btn btn-primary" onclick="document.querySelector('.nav-item[data-target=tenants]').click(); setTimeout(()=>document.getElementById('btn-create-tenant').click(), 100);">
                                <span class="material-symbols-rounded">add</span> Provision Tenant
                            </button>
                            <button class="btn btn-outline" onclick="document.querySelector('.nav-item[data-target=reports]').click();">
                                <span class="material-symbols-rounded">monitoring</span> View Reports
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
                            <a href="#tenants" class="stat-link" onclick="document.querySelector('.nav-item[data-target=tenants]').click(); setTimeout(()=>document.getElementById('tenant-status-filter').value='Demo Requested', 100);">
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
                            <div class="charts-grid">
                                <div class="card">
                                    <h3>User Growth</h3>
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
                            <div class="card">
                                <h3>Revenue Trends</h3>
                                <div class="chart-container">
                                    <canvas id="chart-sales-trends"></canvas>
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
                                            case 'Demo Requested': $rt_badge = 'badge-amber'; break;
                                            case 'Contacted': $rt_badge = 'badge-blue'; break;
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

                            <!-- Platform Health -->
                            <div class="card">
                                <h3>Platform Health</h3>
                                <div class="health-metrics">
                                    <div class="metric-row">
                                        <span>Active Tenants</span>
                                        <div class="progress-bar-wrapper">
                                            <div class="progress-bar" style="--progress: <?php echo min($active_tenants * 10, 100); ?>%; --bar-color: var(--accent-green);"></div>
                                            <span class="progress-text"><?php echo $active_tenants; ?> active</span>
                                        </div>
                                    </div>
                                    <div class="metric-row">
                                        <span>Users</span>
                                        <div class="progress-bar-wrapper">
                                            <div class="progress-bar" style="--progress: <?php echo min($active_users * 2, 100); ?>%; --bar-color: var(--primary-color);"></div>
                                            <span class="progress-text"><?php echo $active_users; ?> active / <?php echo $inactive_users; ?> inactive</span>
                                        </div>
                                    </div>
                                    <div class="metric-row">
                                        <span>Pending</span>
                                        <div class="progress-bar-wrapper">
                                            <div class="progress-bar" style="--progress: <?php echo min($pending_applications * 20, 100); ?>%; --bar-color: var(--accent-amber);"></div>
                                            <span class="progress-text"><?php echo $pending_applications; ?> awaiting review</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 2: TENANT MANAGEMENT -->
                <!-- ============================================================ -->
                <section id="tenants" class="view-section">
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Tenant Management</h3>
                                <p class="text-muted">Manage all tenant organizations and demo requests.</p>
                            </div>
                            <div class="actions-flex">
                                <select id="tenant-status-filter" class="form-control" style="width: 200px;">
                                    <option value="all">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Demo Requested">Demo Requested</option>
                                    <option value="Contacted">Contacted</option>
                                    <option value="Accepted">Accepted</option>
                                    <option value="Suspended">Suspended</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Archived">Archived</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                                <div class="search-box">
                                    <span class="material-symbols-rounded">search</span>
                                    <input type="text" id="tenant-search" placeholder="Search tenants...">
                                </div>
                                <button class="btn btn-primary" id="btn-create-tenant">
                                    <span class="material-symbols-rounded">add</span> Provision New Tenant
                                </button>
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
                                            No tenants found. Click "Provision New Tenant" to add your first institution.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($tenant_rows as $t): ?>
                                    <tr data-status="<?php echo htmlspecialchars($t['status']); ?>">
                                        <td>
                                            <?php echo htmlspecialchars($t['tenant_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['tenant_slug'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $contact = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                                            echo htmlspecialchars($contact ?: '—');
                                            ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['email'] ?? '—'); ?></small>
                                            <br>
                                            <?php $doc_count = (int)($t['legitimacy_document_count'] ?? 0); ?>
                                            <?php if ($doc_count > 0): ?>
                                                <small style="color:#166534; font-weight:500;"><?php echo $doc_count; ?> legitimacy doc<?php echo $doc_count === 1 ? '' : 's'; ?> uploaded</small>
                                            <?php else: ?>
                                                <small style="color:#b91c1c; font-weight:500;">No legitimacy docs uploaded</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $t['status'];
                                            $badge_class = '';
                                            $badge_style = '';
                                            switch ($status) {
                                                case 'Active': $badge_class = 'badge-green'; break;
                                                case 'Suspended': $badge_style = 'background:#fee2e2; color:#b91c1c;'; break;
                                                case 'Demo Requested': $badge_style = 'background:#fef08a; color:#b45309;'; break;
                                                case 'Contacted': $badge_style = 'background:#dbeafe; color:#1e40af;'; break;
                                                case 'Accepted': $badge_style = 'background:#d1fae5; color:#065f46;'; break;
                                                case 'Rejected': $badge_style = 'background:#fee2e2; color:#991b1b;'; break;
                                                case 'Draft': $badge_style = 'background:#f3f4f6; color:#6b7280;'; break;
                                                default: break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>" <?php if ($badge_style) echo "style=\"{$badge_style}\""; ?>>
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['plan_tier'] ?? '—'); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format((float)($t['mrr'] ?? 0), 2)); ?></td>
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

                                                <?php if (in_array($status, ['Demo Requested', 'Contacted'])): ?>
                                                    <!-- Approve: opens provision modal -->
                                                    <button class="btn btn-primary btn-sm btn-provision-from-demo"
                                                        data-tenant-name="<?php echo htmlspecialchars($t['tenant_name']); ?>"
                                                        data-company-email="<?php echo htmlspecialchars($t['email'] ?? ''); ?>"
                                                        data-plan-tier="<?php echo htmlspecialchars($t['plan_tier'] ?? 'Starter'); ?>"
                                                        data-first-name="<?php echo htmlspecialchars($t['first_name'] ?? ''); ?>"
                                                        data-last-name="<?php echo htmlspecialchars($t['last_name'] ?? ''); ?>"
                                                        data-mi="<?php echo htmlspecialchars($t['mi'] ?? ''); ?>"
                                                        data-suffix="<?php echo htmlspecialchars($t['suffix'] ?? ''); ?>"
                                                        data-branch-name="<?php echo htmlspecialchars($t['branch_name'] ?? ''); ?>"
                                                        data-contact-number="<?php echo htmlspecialchars($t['contact_number'] ?? ''); ?>"
                                                        title="Approve & Provision">
                                                        <span class="material-symbols-rounded" style="font-size:16px;">check</span> Approve
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
                                                    <!-- Deactivate -->
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <input type="hidden" name="new_status" value="Archived">
                                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#6b7280;" title="Deactivate Tenant">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">archive</span>
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
                    <div class="card filter-bar">
                        <div class="filter-row">
                            <div class="form-group">
                                <label>Period</label>
                                <select id="sales-period" class="form-control">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly" selected>Monthly</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" id="sales-date-from" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" id="sales-date-to" class="form-control">
                            </div>
                            <div class="form-group" style="align-self: flex-end;">
                                <button class="btn btn-primary" id="btn-apply-sales-filter">
                                    <span class="material-symbols-rounded">filter_alt</span> Apply
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Summary Cards -->
                    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="stat-card">
                            <div class="stat-icon bg-green">
                                <span class="material-symbols-rounded">account_balance</span>
                            </div>
                            <div class="stat-details">
                                <p>Total Revenue</p>
                                <h3 id="sales-total-revenue">₱0.00</h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-blue">
                                <span class="material-symbols-rounded">receipt_long</span>
                            </div>
                            <div class="stat-details">
                                <p>Total Transactions</p>
                                <h3 id="sales-total-transactions">0</h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-purple">
                                <span class="material-symbols-rounded">trending_up</span>
                            </div>
                            <div class="stat-details">
                                <p>Avg Transaction</p>
                                <h3 id="sales-avg-transaction">₱0.00</h3>
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
                            <h3>Revenue Over Time</h3>
                            <div class="chart-container">
                                <canvas id="chart-revenue"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card">
                        <h3>Transaction History</h3>
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
                <!-- SECTION 5: AUDIT LOGS -->
                <!-- ============================================================ -->
                <section id="audit-logs" class="view-section">
                    <div class="card filter-bar">
                        <div class="filter-row">
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
                    </div>

                    <div class="card">
                        <h3>Audit Trail</h3>
                        <div class="table-responsive">
                            <table class="admin-table" id="audit-logs-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Tenant</th>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($audit_logs) === 0): ?>
                                    <tr><td colspan="7" class="text-muted" style="text-align:center; padding:2rem;">No audit logs available.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? $log['user_email'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['tenant_name'] ?? 'Platform'); ?></td>
                                        <td><span class="badge badge-blue"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($log['entity_type'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? '—'); ?></td>
                                        <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
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
                        <button class="settings-tab active" data-settings-target="settings-branding">System Branding</button>
                        <button class="settings-tab" data-settings-target="settings-limits">Tenant Limits</button>
                        <button class="settings-tab" data-settings-target="settings-accounts">Registered Accounts</button>
                    </div>

                    <!-- Sub-section: Branding -->
                    <div id="settings-branding" class="settings-panel active">
                        <div class="card">
                            <h3>Platform Branding</h3>
                            <p class="text-muted" style="margin-bottom: 24px;">Configure platform name and appearance settings.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_settings">
                                <div class="form-group">
                                    <label>Platform Name</label>
                                    <input type="text" class="form-control" name="platform_name" value="MicroFin" style="max-width: 400px;">
                                </div>
                                <div class="form-group">
                                    <label>Tagline</label>
                                    <input type="text" class="form-control" name="platform_tagline" value="Multi-Tenant Microfinance Platform" style="max-width: 400px;">
                                </div>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>

                    <!-- Sub-section: Tenant Limits -->
                    <div id="settings-limits" class="settings-panel">
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

                    <!-- Sub-section: Registered Accounts -->
                    <div id="settings-accounts" class="settings-panel">
                        <div class="card">
                            <div class="card-header-flex mb-4">
                                <div>
                                    <h3>Registered System Accounts</h3>
                                    <p class="text-muted">Master platform accounts with admin access.</p>
                                </div>
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

    <!-- Create Tenant Modal Wizard -->
    <div id="modal-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Provision New Tenant</h2>
                <button class="icon-btn" id="close-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="provision_tenant">
                <div class="form-group">
                    <label>Company / Institution Name</label>
                    <input type="text" class="form-control" name="tenant_name" placeholder="e.g. Village Microfinance" required>
                    <small class="text-muted">The system will auto-generate a unique instance identifier from this name.</small>
                </div>
                <div class="form-group">
                    <label>Custom Site Slug (Optional)</label>
                    <input type="text" class="form-control" name="custom_slug" placeholder="e.g. village-microfinance">
                    <small class="text-muted">Will be used in the login URL: .../login.php?s=<strong>slug</strong></small>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Contact First Name</label>
                        <input type="text" class="form-control" name="first_name" placeholder="Juan">
                    </div>
                    <div>
                        <label>Contact Last Name</label>
                        <input type="text" class="form-control" name="last_name" placeholder="Dela Cruz">
                    </div>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>M.I.</label>
                        <input type="text" class="form-control" name="mi" placeholder="A" maxlength="5">
                    </div>
                    <div>
                        <label>Suffix</label>
                        <select class="form-control" name="suffix">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Tenant CEO / Primary Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" placeholder="ceo@village.com" required>
                        <small class="text-muted">A secure, private login link will be emailed to this address.</small>
                    </div>
                    <div>
                        <label>Contact Number</label>
                        <input type="text" class="form-control" name="contact_number" placeholder="09171234567">
                    </div>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Location / Branch</label>
                        <input type="text" class="form-control" name="branch_name" placeholder="e.g. Marilao, Bulacan">
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
                <div class="form-group">
                    <label>Data Migration Required? <span style="font-size: 0.8rem; color: #ef4444; font-weight: normal; margin-left: 8px;">(Coming Soon)</span></label>
                    <label class="switch" style="opacity: 0.5; cursor: not-allowed;" title="Feature temporarily locked">
                        <input type="checkbox" name="data_migration" value="1" disabled>
                        <span class="slider round" style="cursor: not-allowed;"></span>
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-tenant"><span class="material-symbols-rounded">rocket_launch</span> Provision Instance</button>
                </div>
            </form>
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
