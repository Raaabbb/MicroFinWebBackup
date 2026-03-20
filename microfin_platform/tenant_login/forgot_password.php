<?php
session_start();
require_once "../backend/db_connect.php";

// Load PHPMailer
require '../vendor/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$site_slug = trim($_GET["s"] ?? "");
$tenant = null;
$message = '';
$message_type = '';

// Load tenant branding
if ($site_slug !== '') {
    $tenant_stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, b.theme_primary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ? AND t.status IN ('Active', 'Compromised')");
    $tenant_stmt->execute([$site_slug]);
    $tenant = $tenant_stmt->fetch();
}

$theme_color = $tenant ? ($tenant['theme_primary_color'] ?: '#2563eb') : '#2563eb';
$theme_text_main = $tenant ? ($tenant['theme_text_main'] ?: '#0f172a') : '#0f172a';
$theme_text_muted = $tenant ? ($tenant['theme_text_muted'] ?: '#64748b') : '#64748b';
$theme_bg_body = $tenant ? ($tenant['theme_bg_body'] ?: '#f8fafc') : '#f8fafc';
$theme_bg_card = $tenant ? ($tenant['theme_bg_card'] ?: '#ffffff') : '#ffffff';
$theme_font = $tenant ? ($tenant['font_family'] ?: 'Inter') : 'Inter';
$tenant_name = $tenant ? $tenant['tenant_name'] : 'System';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // We only process if tenant context is valid or if this is an admin lookup
    // Actually, users table needs tenant_id to be unique.
    if ($tenant) {
        $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, $tenant['tenant_id']]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $update->execute([$token, $expiry, $user['user_id']]);

            // Send Email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Allow local XAMPP to bypass SSL verifications if certs are missing
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom(SMTP_USER, $tenant['tenant_name'] . ' System');
                $mail->addAddress($email);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $domainName = $_SERVER['HTTP_HOST'];
                $reset_link = $protocol . $domainName . "/admin-draft/microfin_platform/tenant_login/reset_password.php?token=" . $token;

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                
                $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
                    <h2>Reset Your Password</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset the password for your account at <strong>{$tenant['tenant_name']}</strong>.</p>
                    <p>Click the button below to set a new password. This link will expire in 1 hour.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_link}' style='background-color: {$theme_color}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>{$reset_link}</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;' />
                    <p style='font-size: 12px; color: #999;'>If you didn't request a password reset, you can safely ignore this email.</p>
                </div>
                ";

                $mail->Body = $htmlBody;
                $mail->AltBody = "Reset your password using this link: {$reset_link} (Expires in 1 hour)";

                $mail->send();
            } catch (Exception $e) {
                // Log and show for debugging
                $email_send_error = $mail->ErrorInfo;
            }
        }
        
        if (isset($email_send_error)) {
            $message_type = 'error';
            $message = 'Failed to send email. Error: ' . $email_send_error;
        } else {
            $message_type = 'success';
            $message = 'If an account exists with that email in our system, a password reset link has been sent.';
        }
    } else {
        $message_type = 'error';
        $message = 'Invalid tenant or workspace link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-color: <?php echo htmlspecialchars($theme_color); ?>;
            --brand-bg: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --card-bg: <?php echo htmlspecialchars($theme_bg_card); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif; }
        body { background-color: var(--brand-bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .login-container { background: var(--card-bg); width: 100%; max-width: 440px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); padding: 48px 40px; text-align: center; }
        
        .header h1 { font-size: 1.5rem; color: var(--text-main); margin-bottom: 8px; }
        .header p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 32px; line-height: 1.5; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; color: var(--text-main); transition: all 0.2s ease; }
        .form-control:focus { outline: none; border-color: var(--brand-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-color) 20%, transparent); }
        
        .btn-submit { width: 100%; padding: 12px 24px; background: var(--brand-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; margin-top: 12px; }
        .btn-submit:hover { filter: brightness(0.9); transform: translateY(-1px); }
        
        .back-link { display: inline-block; margin-top: 24px; color: var(--text-muted); font-size: 0.9rem; text-decoration: none; font-weight: 500; }
        .back-link:hover { color: var(--text-main); }
        
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 24px; text-align: left; }
        .alert-success { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="header">
        <h1>Forgot Password?</h1>
        <p>Enter the email address associated with your <?php echo htmlspecialchars($tenant_name); ?> account, and we'll send you a link to reset your password.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="staff@example.com" required>
        </div>

        <button type="submit" class="btn-submit">Send Reset Link</button>
    </form>

    <a href="login.php<?php echo $site_slug ? '?s='.urlencode($site_slug) : ''; ?>" class="back-link">
        &larr; Back to Login
    </a>
</div>

</body>
</html>
