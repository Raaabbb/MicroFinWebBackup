<?php
session_start();
require_once "../backend/db_connect.php";

if (isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true) {
    // Re-check force_password_change in case user closed browser during password reset
    if (!empty($_SESSION['user_id'])) {
        $fpc_stmt = $pdo->prepare('SELECT force_password_change FROM users WHERE user_id = ?');
        $fpc_stmt->execute([$_SESSION['user_id']]);
        $fpc_row = $fpc_stmt->fetch(PDO::FETCH_ASSOC);
        if ($fpc_row && (bool)$fpc_row['force_password_change']) {
            header('Location: force_change_password.php');
            exit;
        }
    }
    header("Location: ../admin_panel/admin.php");
    exit;
}

// URL format: ?s=<slug>  — no key required
// Impersonate: ?s=<slug>&impersonate=1  (super admin, session-protected)
$site_slug = trim($_GET["s"] ?? "");
$tenant = null;
$tenant_error = '';
$login_error = '';

// Check for Super Admin Impersonation (uses ?s=slug&impersonate=1)
if ($site_slug !== '' && isset($_GET['impersonate']) && $_GET['impersonate'] == '1' && isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    $tenant_stmt = $pdo->prepare('SELECT t.tenant_id, t.tenant_name, t.tenant_slug, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ?');
    $tenant_stmt->execute([$site_slug]);
    $tenant = $tenant_stmt->fetch();
    if ($tenant) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['tenant_id'] = $tenant['tenant_id'];
        $_SESSION['tenant_slug'] = $tenant['tenant_slug'];
        $_SESSION['tenant_name'] = $tenant['tenant_name'];
        $_SESSION['user_id'] = 0;
        $_SESSION['username'] = 'Super Admin (Ghost)';
        $_SESSION['role_name'] = 'Super Admin';
        $_SESSION['ui_theme'] = (($_SESSION['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

        $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('IMPERSONATION', 'user', 'Super Admin initiated impersonation session', ?)");
        $log->execute([$tenant['tenant_id']]);

        header("Location: ../admin_panel/admin.php");
        exit;
    }
}

// Regular access — only ?s=<slug> is required
if ($site_slug === '') {
    $tenant_error = 'Missing site identifier. Please use the login link provided to you.';
} else {
    $tenant_stmt = $pdo->prepare('SELECT t.tenant_id, t.tenant_name, t.tenant_slug, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path, t.status, t.setup_completed, t.setup_current_step, t.onboarding_deadline FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ?');
    $tenant_stmt->execute([$site_slug]);
    $tenant = $tenant_stmt->fetch();

    if (!$tenant) {
        $tenant_error = 'Invalid login link. Please contact your administrator.';
    } else {
        // Enforce 30-day onboarding deadline
        if ($tenant['status'] === 'Active' && !(bool)$tenant['setup_completed'] && $tenant['onboarding_deadline']) {
            $deadline = new DateTime($tenant['onboarding_deadline']);
            $now = new DateTime();
            if ($now > $deadline) {
                $pdo->prepare("UPDATE tenants SET status = 'Suspended' WHERE tenant_id = ?")->execute([$tenant['tenant_id']]);
                $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('DEADLINE_EXPIRED', 'tenant', 'Tenant suspended due to 30-day onboarding deadline expiration', ?)")->execute([$tenant['tenant_id']]);
                $tenant['status'] = 'Suspended';
            }
        }

        if ($tenant['status'] !== 'Active') {
            $tenant_error = 'This workspace is currently inactive or suspended. Please contact support.';
            $tenant = null;
        }
    }
}


if ($tenant && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $login_error = 'Email and password are required.';
    } else {
        $user_stmt = $pdo->prepare('SELECT u.user_id, u.username, u.password_hash, u.force_password_change, u.role_id, u.user_type, u.status, u.ui_theme, r.role_name, r.is_system_role FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.email = ? AND u.tenant_id = ?');
        $user_stmt->execute([$email, $tenant['tenant_id']]);
        $user = $user_stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $login_error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $login_error = 'Account is suspended. Please contact your administrator.';
        } else {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['tenant_id'] = $tenant['tenant_id'];
            $_SESSION['tenant_name'] = $tenant['tenant_name'];
            $_SESSION['tenant_slug'] = $tenant['tenant_slug'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['theme'] = $tenant['theme_primary_color'] ?: '#0f172a';
            $_SESSION['ui_theme'] = (($user['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
            if ($user['user_type'] === 'Employee') {
                // Differentiate Admin vs Staff based on is_system_role or role_name
                $is_admin = ((bool)$user['is_system_role'] || stripos($user['role_name'], 'Admin') !== false);

                if ($is_admin) {
                     // Admin routing: password → branding → website → billing → dashboard
                    if (isset($user['force_password_change']) && (bool)$user['force_password_change']) {
                        header('Location: force_change_password.php');
                        exit;
                    }

                    if (!(bool)$tenant['setup_completed']) {
                        $setup_step = (int)($tenant['setup_current_step'] ?? 0);
                        $setup_routes = [
                            0 => 'force_change_password.php',
                            1 => 'setup_loan_products.php',
                            2 => 'setup_credit.php',
                            3 => 'setup_website.php',
                            4 => 'setup_branding.php',
                            5 => 'setup_billing.php',
                        ];
                        if (isset($setup_routes[$setup_step])) {
                            header('Location: ' . $setup_routes[$setup_step]);
                            exit;
                        }
                        // step >= 6: proceed to admin.php
                    }

                    header('Location: ../admin_panel/admin.php');
                    exit;
                } else {
                    // Regular Staff routing
                    if (isset($user['force_password_change']) && (bool)$user['force_password_change']) {
                        header('Location: ../admin_panel/staff/setup_wizard.php');
                        exit;
                    }
                    header('Location: ../admin_panel/staff/dashboard.php');
                    exit;
                }
            } else {
                // Client routing (placeholder, or keep current fallback)
                header('Location: ../admin_panel/admin.php');
                exit;
            }
        }
    }
}


$theme_color = $tenant['theme_primary_color'] ?? '#0f172a';
$theme_text_main = $tenant['theme_text_main'] ?? '#0f172a';
$theme_text_muted = $tenant['theme_text_muted'] ?? '#64748b';
$theme_bg_body = $tenant['theme_bg_body'] ?? '#f8fafc';
$theme_bg_card = $tenant['theme_bg_card'] ?? '#ffffff';
$theme_font = $tenant['font_family'] ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Login Portal</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <style>
        :root {
            --brand-color: <?php echo htmlspecialchars($theme_color); ?>;
            --brand-bg: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --card-bg: <?php echo htmlspecialchars($theme_bg_card); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
            --font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family);
        }

        body {
            background-color: var(--brand-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background-color 0.5s ease;
        }

        .login-container {
            background: var(--card-bg);
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            padding: 48px 40px;
            position: relative;
            overflow: hidden;
            transition: background 0.5s ease;
        }

        /* Top brand accent bar */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: var(--brand-color);
            transition: background 0.5s ease;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: var(--brand-color);
            color: #ffffff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.5s ease;
            background-size: cover;
            background-position: center;
        }
        
        .brand-logo .material-symbols-rounded {
            font-size: 32px;
        }

        #company-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
            transition: color 0.5s ease;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: color 0.5s ease;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            color: var(--text-main);
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.5); /* subtle transparency over custom card-bg */
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--brand-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-login:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .footer-text {
            text-align: center;
            margin-top: 32px;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: color 0.5s ease;
        }
        
        .footer-text a {
            color: var(--brand-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* Loading overlay */
        .overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        /* Error State for invalid/missing ?tid */
        .error-state {
            display: none;
            text-align: center;
            padding: 20px 0;
        }
        
        .error-state.visible {
            display: block;
        }
        
        .login-form {
            display: block;
        }
        
        .login-form.hidden {
            display: none;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <?php if ($tenant_error !== ''): ?>
        <div id="error-view" class="error-state visible">
            <span class="material-symbols-rounded" style="font-size: 64px; color: #ef4444; margin-bottom: 16px;">gpp_bad</span>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; color: #0f172a;">Invalid Access Link</h2>
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 24px;">This login portal requires a secure, Private URL provided by your administrator.</p>
            <p style="color: #94a3b8; font-size: 0.85rem;"><?php echo htmlspecialchars($tenant_error); ?></p>
        </div>
        <?php else: ?>

        <div id="form-view" class="login-form">
            <div class="brand-header">
                <?php if (!empty($tenant['logo_path'])): ?>
                <div class="brand-logo" id="logo-icon-container" style="background-image: url('<?php echo htmlspecialchars($tenant['logo_path']); ?>'); background-color: transparent;">
                </div>
                <?php else: ?>
                <div class="brand-logo" id="logo-icon-container">
                    <span class="material-symbols-rounded" id="logo-icon">account_balance</span>
                </div>
                <?php endif; ?>
                <h1 id="company-name"><?php echo htmlspecialchars($tenant['tenant_name']); ?> Workspace</h1>
            </div>

            <form id="login-form" method="POST" action="login.php?s=<?php echo urlencode($site_slug); ?>">
                <?php if ($login_error !== ''): ?>
                <div style="background-color: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1rem; text-align: left;">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Staff Email</label>
                    <input type="email" name="email" class="form-control" placeholder="employee@company.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between;">
                        <label>Password</label>
                        <a href="forgot_password.php?s=<?= htmlspecialchars($site_slug) ?>" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">Forgot?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-login" id="submit-btn">
                    Access Dashboard <span class="material-symbols-rounded" style="font-size: 18px;">arrow_forward</span>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="footer-text">
            Powered securely by <a href="../public_website/index.html">MicroFin</a>
        </div>

        <!-- Loader -->
        <div class="overlay" id="loader">
            <span class="material-symbols-rounded" style="font-size: 40px; color: var(--brand-color); animation: spin 1s linear infinite;">sync</span>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>




