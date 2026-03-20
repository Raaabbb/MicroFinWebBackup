<?php
session_start();
require_once "../../backend/db_connect.php";

// 1. Authentication Check
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: ../../tenant_login/login.php");
    exit;
}

// 2. Authorization Check (Only Employees)
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: ../admin.php");
    exit;
}

// 3. Setup Wizard Check
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$check_stmt = $pdo->prepare('SELECT force_password_change, role_id, ui_theme FROM users WHERE user_id = ? AND tenant_id = ?');
$check_stmt->execute([$user_id, $tenant_id]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || $user_data['force_password_change']) {
    header('Location: setup_wizard.php');
    exit;
}

$ui_theme = (($user_data['ui_theme'] ?? ($_SESSION['ui_theme'] ?? 'light')) === 'dark') ? 'dark' : 'light';
$_SESSION['ui_theme'] = $ui_theme;

// 4. Load Permissions
$role_id = $user_data['role_id'];
$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    WHERE rp.role_id = ?
');
$perm_stmt->execute([$role_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

// Helper function for view
function has_permission($code) {
    global $permissions;
    return in_array($code, $permissions);
}

// Fetch Pending Applications
$pending_applications = [];
if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')) {
    $apps_stmt = $pdo->prepare("
        SELECT 
            la.application_id, la.application_number, la.requested_amount, 
            la.application_status, la.submitted_date, la.created_at,
            c.first_name, c.last_name,
            lp.product_name
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.tenant_id = ? AND la.application_status NOT IN ('Approved', 'Rejected', 'Cancelled', 'Withdrawn')
        ORDER BY COALESCE(la.submitted_date, la.created_at) DESC
    ");
    $apps_stmt->execute([$tenant_id]);
    $pending_applications = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Clients
$all_clients = [];
if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')) {
    $clients_stmt = $pdo->prepare("
        SELECT client_id, first_name, last_name, email_address, contact_number, client_status, registration_date 
        FROM clients 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC
    ");
    $clients_stmt->execute([$tenant_id]);
    $all_clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$loan_products = [];
$loan_products_stmt = $pdo->prepare("\n    SELECT product_id, product_name, product_type, min_amount, max_amount, min_term_months, max_term_months, interest_rate\n    FROM loan_products\n    WHERE tenant_id = ? AND is_active = 1\n    ORDER BY product_name ASC\n");
$loan_products_stmt->execute([$tenant_id]);
$loan_products = $loan_products_stmt->fetchAll(PDO::FETCH_ASSOC);

$document_types = [];
$document_types_stmt = $pdo->query("\n    SELECT document_type_id, document_name, loan_purpose, is_required\n    FROM document_types\n    WHERE is_active = 1\n    ORDER BY is_required DESC, document_name ASC\n");
$document_types = $document_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tenant branding from tenant_branding table
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_secondary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$theme_color = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : ($_SESSION['theme'] ?? '#0f172a');
$theme_sidebar = ($tenant_brand && $tenant_brand['theme_secondary_color']) ? $tenant_brand['theme_secondary_color'] : '#0f172a';
$theme_text_main = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$theme_text_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#64748b';
$theme_bg_body = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f8fafc';
$theme_bg_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$theme_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'Inter';
$logo_path = ($tenant_brand && $tenant_brand['logo_path']) ? $tenant_brand['logo_path'] : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - <?php echo htmlspecialchars($_SESSION['tenant_name']); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($theme_color); ?>;
            --primary-light: <?php echo htmlspecialchars($theme_color); ?>1A;
            --bg-body: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --bg-surface: <?php echo htmlspecialchars($theme_bg_card); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
            --border-color: #e2e8f0;
            --sidebar-width: 260px;
            --sidebar-bg: <?php echo htmlspecialchars($theme_sidebar); ?>;
            --font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        [data-theme="dark"] {
            --bg-body: #0b1220;
            --bg-surface: #111827;
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --primary-light: rgba(59, 130, 246, 0.2);
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-surface);
            color: var(--text-main);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .icon-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 10;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .company-info h2 {
            font-size: 1rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        .company-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .sidebar-nav {
            padding: 24px 16px;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }

        .nav-item.active {
            background: var(--primary-color);
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .top-header {
            height: 72px;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 16px 6px 6px;
            background: var(--bg-body);
            border-radius: 30px;
            border: 1px solid var(--border-color);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .btn-walk-in {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-walk-in:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        /* Views */
        .view-container {
            padding: 32px;
            flex: 1;
            display: none; /* Hide by default, manage via JS */
        }
        
        .view-container.active {
            display: block;
        }

        .welcome-card {
            background: var(--bg-surface);
            border-radius: 16px;
            padding: 32px;
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .welcome-icon {
            width: 64px;
            height: 64px;
            background: var(--primary-light);
            color: var(--primary-color);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .welcome-icon .material-symbols-rounded {
            font-size: 32px;
        }

        .welcome-text h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .welcome-text p {
            color: var(--text-muted);
        }

        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .widget-card {
            background: var(--bg-surface);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-window {
            background: var(--bg-surface);
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--text-main);
        }
        .form-group input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border-color);
            border-radius: 8px; font-size: 14px; color: var(--text-main);
        }
        .form-group select,
        .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border-color);
            border-radius: 8px; font-size: 14px; color: var(--text-main); background: #fff;
        }
        .form-group textarea {
            min-height: 84px;
            resize: vertical;
        }
        .form-hint {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .form-grid-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .section-title {
            margin: 6px 0 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .document-list {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            max-height: 180px;
            overflow-y: auto;
            background: #f8fafc;
        }
        .document-item {
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }
        .document-item:last-child {
            margin-bottom: 0;
        }
        .document-item-main {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            cursor: pointer;
        }
        .document-upload-wrap {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--border-color);
        }
        .document-upload-input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            color: var(--text-main);
            font-size: 12px;
        }
        .document-badge {
            display: inline-block;
            margin-left: 6px;
            font-size: 11px;
            color: #166534;
            background: #dcfce7;
            border-radius: 999px;
            padding: 1px 7px;
            font-weight: 600;
        }
        .btn-cancel {
            padding: 10px 24px; border: 1px solid var(--border-color); background: #fff;
            border-radius: 8px; cursor: pointer; font-weight: 500;
        }
        .btn-draft {
            padding: 10px 24px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-main);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-submit {
            padding: 10px 24px; border: none; background: var(--primary-color); color: #fff;
            border-radius: 8px; cursor: pointer; font-weight: 500;
        }

        @media (max-width: 640px) {
            .form-grid-two {
                grid-template-columns: 1fr;
            }

            .modal-footer {
                flex-wrap: wrap;
            }

            .btn-cancel,
            .btn-draft,
            .btn-submit {
                width: 100%;
            }
        }

    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="company-logo">
                <span class="material-symbols-rounded">account_balance</span>
            </div>
            <div class="company-info">
                <h2><?php echo htmlspecialchars($_SESSION['tenant_name']); ?></h2>
                <p>Employee Portal</p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#home" class="nav-item active" data-target="home">
                <span class="material-symbols-rounded">home</span>
                <span>Home</span>
            </a>

            <!-- DYNAMIC PERMISSION-BASED MENU ITEMS -->
            <?php if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
                <a href="#clients" class="nav-item" data-target="clients">
                    <span class="material-symbols-rounded">group</span>
                    <span>Client Management</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS')): ?>
                <a href="#loans" class="nav-item" data-target="loans">
                    <span class="material-symbols-rounded">real_estate_agent</span>
                    <span>Loans Management</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
                <a href="#applications" class="nav-item" data-target="applications">
                    <span class="material-symbols-rounded">description</span>
                    <span>Applications</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                <a href="#payments" class="nav-item" data-target="payments">
                    <span class="material-symbols-rounded">payments</span>
                    <span>Payments/Transactions</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_REPORTS')): ?>
                <a href="#reports" class="nav-item" data-target="reports">
                    <span class="material-symbols-rounded">bar_chart</span>
                    <span>Reports & Analytics</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission('VIEW_USERS') || has_permission('CREATE_USERS')): ?>
                <a href="#users" class="nav-item" data-target="users">
                    <span class="material-symbols-rounded">manage_accounts</span>
                    <span>User Management</span>
                </a>
            <?php endif; ?>
        </nav>

        <div style="padding: 24px 16px; border-top: 1px solid var(--border-color);">
            <a href="../../tenant_login/logout.php" class="nav-item" style="color: #ef4444;">
                <span class="material-symbols-rounded">logout</span>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-title" id="page-title">Home</div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <button id="theme-toggle" class="icon-btn" title="Toggle Light/Dark Mode">
                    <span class="material-symbols-rounded"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
                </button>
                <?php if (has_permission('CREATE_CLIENTS')): ?>
                <button class="btn-walk-in" onclick="document.getElementById('walkInModal').style.display='flex'">
                    <span class="material-symbols-rounded" style="font-size: 20px;">person_add</span>
                    Walk-In
                </button>
                <?php endif; ?>
                
                <div class="user-profile">
                    <?php 
                        $name_parts = explode(' ', $_SESSION['username']);
                        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                    ?>
                    <div class="avatar"><?php echo $initials; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Home View -->
        <div id="home" class="view-container active">
            <!-- Welcome Header -->
            <div class="welcome-card">
                <div class="welcome-icon">
                    <span class="material-symbols-rounded">waving_hand</span>
                </div>
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($name_parts[0]); ?>!</h1>
                    <p>Here is an overview of your active tasks and workspace.</p>
                </div>
            </div>

            <!-- Dynamic Widgets (Placeholders for now) -->
            <div class="widgets-grid">
                
                <?php if (has_permission('VIEW_LOANS')): ?>
                <div class="widget-card">
                    <h3 style="font-size: 1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--primary-color);">task</span>
                        Pending Loan Reviews
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">You have 0 loan applications currently awaiting your attention.</p>
                </div>
                <?php endif; ?>

                <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                <div class="widget-card">
                    <h3 style="font-size: 1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--primary-color);">receipt_long</span>
                        Today's Collections
                    </h3>
                    <div style="font-size: 2rem; font-weight: 700; color: var(--text-main);">₱0.00</div>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Total payments processed today.</p>
                </div>
                <?php endif; ?>

                <div class="widget-card">
                    <h3 style="font-size: 1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--primary-color);">notifications</span>
                        Recent Activity
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">No recent notifications.</p>
                </div>
            </div>
        </div>

        <?php if (has_permission('VIEW_REPORTS')): ?>
        <!-- Reports View -->
        <div id="reports" class="view-container">
            <div class="welcome-card">
                <div class="welcome-icon" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color);">
                    <span class="material-symbols-rounded">analytics</span>
                </div>
                <div class="welcome-text">
                    <h1>Reports & Analytics</h1>
                    <p>View system reports and generate insights.</p>
                </div>
            </div>
            
            <div class="widgets-grid">
                <div class="widget-card">
                    <h3 style="font-size: 1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" style="color: var(--primary-color);">summarize</span>
                        Generate Report
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Select a report type to generate.</p>
                    <button class="btn-walk-in" style="margin-top: 15px;" onclick="alert('Report generation to be implemented')">
                        Run Report
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
        <div id="clients" class="view-container">
            <div class="welcome-card" style="margin-bottom: 24px;">
                <div class="welcome-text">
                    <h1>Client Management</h1>
                    <p>View and manage all registered clients for your branch.</p>
                </div>
            </div>

            <div class="widget-card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Client Name</th>
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Email</th>
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Phone</th>
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Registered</th>
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Status</th>
                            <th style="padding: 16px; font-weight: 500; color: var(--text-muted);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_clients)): ?>
                            <tr>
                                <td colspan="6" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                    No clients registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_clients as $client): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 16px; font-weight: 500;">
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($client['email_address'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($client['contact_number'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding: 16px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($client['registration_date']))); ?>
                                    </td>
                                    <td style="padding: 16px;">
                                        <span style="background: <?php echo $client['client_status'] === 'Active' ? '#dcfce7' : '#f1f5f9'; ?>; 
                                                     color: <?php echo $client['client_status'] === 'Active' ? '#166534' : '#475569'; ?>; 
                                                     padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500;">
                                            <?php echo htmlspecialchars($client['client_status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 16px;">
                                        <button class="btn-walk-in" style="padding: 6px 12px; font-size: 0.8rem; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color);" onclick="alert('View profile logic to be implemented')">View Profile</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS')): ?>
        <div id="loans" class="view-container">
            <div class="welcome-card">
                <div class="welcome-text">
                    <h1>Loans Management</h1>
                    <p>Loan approval and drafting interface to be structured here.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
        <div id="applications" class="view-container">
            <div class="welcome-card" style="margin-bottom: 24px;">
                <div class="welcome-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <span class="material-symbols-rounded">description</span>
                </div>
                <div class="welcome-text">
                    <h1>Applications</h1>
                    <p>Review and process pending client loan applications here.</p>
                </div>
            </div>

            <div class="widget-card" style="overflow-x: auto;">
                <h3 style="font-size: 1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: var(--primary-color);">list_alt</span>
                    Pending Applications
                </h3>
                
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">App Number</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Client Name</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Product</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Amount</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Date</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Status</th>
                            <th style="padding: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_applications)): ?>
                            <tr>
                                <td colspan="7" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                    No pending applications found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_applications as $app): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 12px; font-size: 0.9rem; font-weight: 500;"><?php echo htmlspecialchars($app['application_number']); ?></td>
                                    <td style="padding: 12px; font-size: 0.9rem;"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                                    <td style="padding: 12px; font-size: 0.9rem; color: var(--text-muted);"><?php echo htmlspecialchars($app['product_name']); ?></td>
                                    <td style="padding: 12px; font-size: 0.9rem; font-weight: 600;">₱<?php echo number_format($app['requested_amount'], 2); ?></td>
                                    <td style="padding: 12px; font-size: 0.9rem; color: var(--text-muted);">
                                        <?php 
                                            $date = $app['submitted_date'] ? $app['submitted_date'] : $app['created_at'];
                                            echo htmlspecialchars(date('M d, Y', strtotime($date))); 
                                        ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <span style="background: rgba(245, 158, 11, 0.1); color: #d97706; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($app['application_status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;">
                                        <button class="btn-walk-in" style="padding: 6px 12px; font-size: 0.8rem; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color);" onclick="alert('View application logic here')">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('PROCESS_PAYMENTS')): ?>
        <div id="payments" class="view-container">
            <div class="welcome-card">
                <div class="welcome-text">
                    <h1>Payments & Transactions</h1>
                    <p>Payment processing and transaction logs interface to be structured here.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('VIEW_USERS') || has_permission('CREATE_USERS')): ?>
        <!-- User Management View -->
        <div id="users" class="view-container">
            <div class="welcome-card">
                <div class="welcome-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                    <span class="material-symbols-rounded">manage_accounts</span>
                </div>
                <div class="welcome-text">
                    <h1>User Management</h1>
                    <p>Manage system access, view employee lists, and invite new users.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Walk-In Modal -->
    <div class="modal-overlay" id="walkInModal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 style="font-size: 20px; font-weight: 600;">Register Walk-In Client</h3>
                <span class="material-symbols-rounded" style="cursor: pointer; color: var(--text-muted);" onclick="document.getElementById('walkInModal').style.display='none'">close</span>
            </div>
            <div class="modal-body">
                <form id="walkInForm" enctype="multipart/form-data">
                    <input type="hidden" name="walk_in_action" id="walkInAction" value="draft">

                    <div class="section-title">Client Account</div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label>Physical Address</label>
                        <input type="text" name="address">
                    </div>

                    <div class="form-group">
                        <label>Password (for Mobile App login)</label>
                        <input type="password" name="password" minlength="8" required>
                        <p class="form-hint">Minimum 8 characters.</p>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" minlength="8" required>
                    </div>

                    <div class="section-title">Loan Request</div>
                    <div class="form-group">
                        <label>Loan Product</label>
                        <select name="product_id" id="walkInProduct" required>
                            <option value="">Select a loan product</option>
                            <?php foreach ($loan_products as $product): ?>
                                <option
                                    value="<?php echo (int) $product['product_id']; ?>"
                                    data-min-amount="<?php echo htmlspecialchars((string) $product['min_amount']); ?>"
                                    data-max-amount="<?php echo htmlspecialchars((string) $product['max_amount']); ?>"
                                    data-min-term="<?php echo (int) $product['min_term_months']; ?>"
                                    data-max-term="<?php echo (int) $product['max_term_months']; ?>"
                                >
                                    <?php echo htmlspecialchars($product['product_name'] . ' (' . $product['product_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-two">
                        <div class="form-group">
                            <label>Requested Amount (PHP)</label>
                            <input type="number" name="requested_amount" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Loan Term (Months)</label>
                            <input type="number" name="loan_term_months" min="1" step="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Monthly Income (PHP)</label>
                        <input type="number" name="monthly_income" min="0.01" step="0.01" required>
                        <p class="form-hint">Required as basis for initial credit limit assessment.</p>
                    </div>
                    <div class="form-group">
                        <label>Loan Purpose</label>
                        <textarea name="loan_purpose" placeholder="Describe the purpose of the loan request"></textarea>
                    </div>

                    <div class="section-title">Document Status</div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
                            <input type="checkbox" name="documents_complete" id="documentsComplete" value="1" style="width: auto;">
                            All required documents are submitted
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Submitted / Collected Documents</label>
                        <div class="document-list">
                            <?php foreach ($document_types as $doc): ?>
                                <div class="document-item">
                                    <label class="document-item-main">
                                        <input
                                            type="checkbox"
                                            class="doc-collected-checkbox"
                                            data-doc-id="<?php echo (int) $doc['document_type_id']; ?>"
                                            name="submitted_document_type_ids[]"
                                            value="<?php echo (int) $doc['document_type_id']; ?>"
                                            style="width: auto; margin-top: 2px;"
                                        >
                                        <span>
                                            <?php echo htmlspecialchars($doc['document_name']); ?>
                                            <?php if ((int) $doc['is_required'] === 1): ?><span class="document-badge">Required</span><?php endif; ?>
                                        </span>
                                    </label>
                                    <div class="document-upload-wrap">
                                        <input
                                            type="file"
                                            class="document-upload-input"
                                            data-doc-id="<?php echo (int) $doc['document_type_id']; ?>"
                                            name="uploaded_documents[<?php echo (int) $doc['document_type_id']; ?>]"
                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                        >
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="form-hint">Staff should upload each collected document. If anything is still missing, save as Draft and continue later.</p>
                    </div>
                    <div class="form-group">
                        <label>Missing Documents / Follow-up Notes</label>
                        <textarea name="missing_documents_notes" placeholder="List pending documents or remarks for follow-up"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" type="button" onclick="document.getElementById('walkInModal').style.display='none'">Cancel</button>
                <button class="btn-draft" type="button" onclick="submitWalkIn('draft')">Save as Draft</button>
                <button class="btn-submit" type="button" onclick="submitWalkIn('submit')">Create Account & Submit</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navItems = document.querySelectorAll('.nav-item[data-target]');
            const views = document.querySelectorAll('.view-container');
            const pageTitle = document.getElementById('page-title');
            const uploadInputs = document.querySelectorAll('.document-upload-input');
            const collectedCheckboxes = document.querySelectorAll('.doc-collected-checkbox');
            const htmlElement = document.documentElement;
            const themeToggleBtn = document.getElementById('theme-toggle');

            function applyTheme(theme) {
                htmlElement.setAttribute('data-theme', theme);
                if (themeToggleBtn) {
                    const icon = themeToggleBtn.querySelector('span');
                    if (icon) {
                        icon.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
                    }
                }
            }

            async function persistTheme(theme) {
                try {
                    await fetch('../../backend/api_theme_preference.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ theme: theme })
                    });
                } catch (error) {
                    // No-op: keep UI responsive even if persistence fails.
                }
            }

            if (themeToggleBtn) {
                applyTheme(htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
                themeToggleBtn.addEventListener('click', () => {
                    const currentTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    applyTheme(newTheme);
                    persistTheme(newTheme);
                });
            }

            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Remove active from all nav items
                    navItems.forEach(nav => nav.classList.remove('active'));
                    // Add active to clicked nav item
                    item.classList.add('active');

                    // Hide all views
                    views.forEach(view => view.classList.remove('active'));
                    
                    // Show target view
                    const targetId = item.getAttribute('data-target');
                    const targetView = document.getElementById(targetId);
                    if (targetView) {
                        targetView.classList.add('active');
                    }
                    
                    // Update Page Title
                    pageTitle.textContent = item.querySelector('span:last-child').textContent;
                    
                    // Update URL Hash without jumping
                    history.pushState(null, null, `#${targetId}`);
                });
            });

            // Handle initial direct link loads
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                const targetNav = document.querySelector(`.nav-item[data-target="${hash}"]`);
                if (targetNav) {
                    targetNav.click();
                }
            }

            uploadInputs.forEach(input => {
                input.addEventListener('change', () => {
                    const docId = input.dataset.docId;
                    const checkbox = document.querySelector(`.doc-collected-checkbox[data-doc-id="${docId}"]`);
                    if (checkbox && input.files && input.files.length > 0) {
                        checkbox.checked = true;
                    }
                });
            });

            collectedCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        return;
                    }
                    const docId = checkbox.dataset.docId;
                    const uploadInput = document.querySelector(`.document-upload-input[data-doc-id="${docId}"]`);
                    if (uploadInput) {
                        uploadInput.value = '';
                    }
                });
            });
        });

        async function submitWalkIn(action) {
            const form = document.getElementById('walkInForm');
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            formData.set('walk_in_action', action === 'submit' ? 'submit' : 'draft');
            formData.set('documents_complete', document.getElementById('documentsComplete').checked ? '1' : '0');

            try {
                // Ensure api_walk_in exits in backend folder
                const res = await fetch('../../backend/api_walk_in.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await res.json();
                if(result.status === 'success') {
                    alert(result.message || 'Walk-in registration saved successfully.');
                    form.reset();
                    document.getElementById('walkInModal').style.display = 'none';
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred during registration.');
            }
        }
    </script>
</body>
</html>
