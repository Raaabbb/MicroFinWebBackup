<?php
session_start();
require_once "../../backend/db_connect.php";

// Check if user is logged in
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: ../../tenant_login/login.php");
    exit;
}

// Only Employees who need to change password should be here
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: ../admin.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Double check if they actually need to change password
$check_stmt = $pdo->prepare('SELECT force_password_change FROM users WHERE user_id = ? AND tenant_id = ?');
$check_stmt->execute([$user_id, $tenant_id]);
$needs_change = $check_stmt->fetchColumn();

if (!$needs_change) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Both fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare('UPDATE users SET password_hash = ?, force_password_change = FALSE WHERE user_id = ? AND tenant_id = ?');
            $update_stmt->execute([$hashed_password, $user_id, $tenant_id]);
            
            $success = 'Password changed successfully! Redirecting to dashboard...';
            // We could redirect immediately or let JS handle a 2-second delay for the success message
        } catch (Exception $e) {
            $error = 'An error occurred while updating your password. Please try again.';
        }
    }
}

// Fetch tenant branding
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$theme_color = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : ($_SESSION['theme'] ?? '#0f172a');
$theme_text_main = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$theme_text_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#64748b';
$theme_bg_body = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f8fafc';
$theme_bg_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$theme_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Setup Wizard</title>
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
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
        }

        body {
            background-color: var(--brand-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: <?php echo htmlspecialchars($theme_bg_card); ?>;
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            padding: 48px 40px;
            text-align: center;
        }

        .logo-circle {
            width: 64px;
            height: 64px;
            background: var(--brand-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .logo-circle .material-symbols-rounded {
            font-size: 32px;
        }

        h1 {
            color: var(--text-main);
            font-size: 1.5rem;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 24px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }

        .btn-submit {
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
        }

        .btn-submit:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: left;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

    </style>
</head>
<body>

    <div class="setup-container">
        <div class="logo-circle">
            <span class="material-symbols-rounded">shield_person</span>
        </div>
        <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['username'])[0] ?? 'Staff'); ?>!</h1>
        <p class="subtitle">For security reasons, you must change your temporary password before accessing the workspace.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">check_circle</span>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <script>
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 2000);
            </script>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="8">
                </div>
                <button type="submit" class="btn-submit">
                    Set Password <span class="material-symbols-rounded" style="font-size: 18px;">arrow_forward</span>
                </button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
