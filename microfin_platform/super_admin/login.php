<?php
session_start();

if (isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    header('Location: super_admin.php');
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../backend/db_connect.php';

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error_msg = 'Email and password are required.';
    } else {
        // UPDATED QUERY: Point to 'users' table and check for 'Super Admin' user_type
        $stmt = $pdo->prepare("SELECT user_id AS super_admin_id, username, password_hash, ui_theme FROM users WHERE email = ? AND user_type = 'Super Admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['super_admin_logged_in'] = true;
            $_SESSION['super_admin_id'] = (int) $admin['super_admin_id'];
            $_SESSION['super_admin_username'] = $admin['username'];
            $_SESSION['ui_theme'] = (($admin['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

            header('Location: super_admin.php');
            exit;
        }

        $error_msg = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin - Super Admin Login</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    
    <style>
        :root {
            --brand-color: #0f172a; /* Slate 900 for Super Admin */
            --bg-color: #f8fafc;
            --surface-color: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .login-container {
            background-color: var(--surface-color);
            padding: 3rem 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo-header {
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 3rem;
            color: var(--brand-color);
            margin-bottom: 0.5rem;
        }

        .company-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-color);
            margin: 0 0 0.25rem 0;
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background-color: var(--brand-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background-color: #000000;
        }

        /* Loader Overlay */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .loader-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top-color: var(--brand-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="loader-overlay" id="loader">
        <div class="spinner"></div>
        <p style="margin-top: 1rem; color: var(--text-secondary); font-weight: 500;">Authenticating...</p>
    </div>

    <div class="login-container">
        <div class="logo-header">
            <span class="material-symbols-outlined logo-icon">admin_panel_settings</span>
            <h1 class="company-name">MicroFin OS</h1>
            <p class="subtitle">Platform Owner Login</p>
        </div>

        <form id="login-form" method="POST" action="">
            <?php if ($error_msg !== ''): ?>
            <div style="background-color: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1rem; text-align: left;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="superadmin@microfin.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">Sign In to Dashboard</button>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="../public_website/index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span class="material-symbols-outlined" style="font-size: 1rem;">arrow_back</span>
                    Back to Home
                </a>
            </div>
        </form>
    </div>

    <script src="login.js"></script>
</body>
</html>
