<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Check if user actually needs to change password
$stmt = $pdo->prepare("SELECT force_password_change FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !(bool)$user["force_password_change"]) {
    // Password already changed — route to correct setup step
    $tenant_id = $_SESSION['tenant_id'] ?? '';
    $step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
    $step_stmt->execute([$tenant_id]);
    $step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);

    if ($step_data && !(bool)$step_data['setup_completed']) {
        $setup_step = (int)($step_data['setup_current_step'] ?? 0);
        // If still at step 0 after password change, move to step 1
        if ($setup_step === 0 && !empty($step_data) && !(bool)$step_data['setup_completed']) {
            $pdo->prepare('UPDATE tenants SET setup_current_step = 1 WHERE tenant_id = ?')->execute([$tenant_id]);
            $setup_step = 1;
        }
        $setup_routes = [
            1 => 'setup_loan_products.php',
            2 => 'setup_credit.php',
            3 => 'setup_website.php',
            4 => 'setup_branding.php',
        ];
        if (isset($setup_routes[$setup_step])) {
            header('Location: ' . $setup_routes[$setup_step]);
            exit;
        }
    }
    header("Location: ../admin_panel/admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 0 WHERE user_id = ?");
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES (?, ?, ?, ?)");
            $log->execute(["PASSWORD_CHANGED", "user", "User completed forced password reset", $_SESSION["tenant_id"]]);
            
            // After password change, progress to step 1 (loan products)
            $tenant_id = $_SESSION['tenant_id'] ?? '';
            $pdo->prepare('UPDATE tenants SET setup_current_step = 1 WHERE tenant_id = ?')->execute([$tenant_id]);
            
            header('Location: setup_loan_products.php');
            exit;
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}

// Fetch tenant branding
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$t_primary = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : '#0f172a';
$t_text = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$t_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#475569';
$t_bg = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f8fafc';
$t_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$t_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { background: <?php echo htmlspecialchars($t_card); ?>; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { margin-top: 0; color: <?php echo htmlspecialchars($t_text); ?>; font-size: 24px; }
        p { color: <?php echo htmlspecialchars($t_muted); ?>; margin-bottom: 24px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: <?php echo htmlspecialchars($t_muted); ?>; font-weight: 500; font-size: 14px; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-family: inherit; color: <?php echo htmlspecialchars($t_text); ?>; }
        input[type="password"]:focus { outline: none; border-color: <?php echo htmlspecialchars($t_primary); ?>; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        button { width: 100%; padding: 12px; background: <?php echo htmlspecialchars($t_primary); ?>; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Change Your Password</h2>
        <p>For your security, you must change your temporary password before accessing your account.</p>
        
        <?php if(!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <button type="submit">Update Password</button>
        </form>
    </div>
</body>
</html>

