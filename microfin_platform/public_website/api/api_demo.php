<?php
session_start();
require_once '../../backend/db_connect.php';

// Require PHPMailer manually
require_once '../../vendor/PHPMailer/src/Exception.php';
require_once '../../vendor/PHPMailer/src/PHPMailer.php';
require_once '../../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid Request'];

// Helper: Get env var from multiple sources (Railway compatibility)
function env($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

// Email delivery tuning
$smtp_force_fallback = env('SMTP_FORCE_FALLBACK') === '1';
$smtp_timeout_env = env('SMTP_TIMEOUT_SECONDS');
$smtp_timeout_seconds = is_numeric($smtp_timeout_env) ? (int) $smtp_timeout_env : 8;
$smtp_timeout_seconds = max(3, min($smtp_timeout_seconds, 20));

// =============================================================================
// EMAIL DELIVERY FUNCTIONS (SMTP + HTTP API Fallbacks)
// =============================================================================

/**
 * Try sending via SMTP (Gmail)
 */
function sendViaSMTP($to, $subject, $htmlBody, $timeout) {
    $mail = new PHPMailer(true);
    $mail->Timeout = $timeout;
    $mail->SMTPKeepAlive = false;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'microfin.otp@gmail.com';
    $mail->Password = 'inpl gapi ynkr atvr';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('microfin.otp@gmail.com', 'MicroFin Verification');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;

    $mail->send();
    return true;
}

/**
 * Try sending via Resend API (HTTP-based, works on Railway)
 * Set RESEND_API_KEY environment variable
 */
function sendViaResend($to, $subject, $htmlBody) {
    $apiKey = env('RESEND_API_KEY');
    if (!$apiKey) return false;

    $fromEmail = env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');
    $fromName = env('RESEND_FROM_NAME', 'MicroFin Verification');

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => "{$fromName} <{$fromEmail}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody
        ])
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Try sending via SendGrid API (HTTP-based, works on Railway)
 * Set SENDGRID_API_KEY environment variable
 */
function sendViaSendGrid($to, $subject, $htmlBody) {
    $apiKey = env('SENDGRID_API_KEY');
    if (!$apiKey) return false;

    $fromEmail = env('SENDGRID_FROM_EMAIL', 'noreply@microfin.app');
    $fromName = env('SENDGRID_FROM_NAME', 'MicroFin Verification');

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'personalizations' => [['to' => [['email' => $to]]]],
            'from' => ['email' => $fromEmail, 'name' => $fromName],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $htmlBody]]
        ])
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Attempt email delivery through all available channels
 * Returns: ['success' => bool, 'method' => string, 'error' => string|null]
 */
function sendEmail($to, $subject, $htmlBody, $smtpTimeout, $skipSmtp = false) {
    $lastError = null;

    // 1. Try SMTP (Gmail) first unless forced to skip
    if (!$skipSmtp) {
        try {
            if (sendViaSMTP($to, $subject, $htmlBody, $smtpTimeout)) {
                return ['success' => true, 'method' => 'smtp', 'error' => null];
            }
        } catch (Exception $e) {
            $lastError = $e->getMessage();
        }
    }

    // 2. Try Resend API
    try {
        if (sendViaResend($to, $subject, $htmlBody)) {
            return ['success' => true, 'method' => 'resend', 'error' => null];
        }
    } catch (Exception $e) {
        $lastError = $lastError ?: $e->getMessage();
    }

    // 3. Try SendGrid API
    try {
        if (sendViaSendGrid($to, $subject, $htmlBody)) {
            return ['success' => true, 'method' => 'sendgrid', 'error' => null];
        }
    } catch (Exception $e) {
        $lastError = $lastError ?: $e->getMessage();
    }

    return ['success' => false, 'method' => null, 'error' => $lastError ?: 'All email delivery methods failed'];
}

if ($action === 'send_otp') {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $response['message'] = 'Invalid email address.';
    } else {
         // Generate 6-digit OTP
         $otp = sprintf("%06d", mt_rand(1, 999999));

         try {
             // Check if email already has a demo request
             $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ?");
             $check_stmt->execute([$email]);
             $duplicate_count = $check_stmt->fetchColumn();

             if ($duplicate_count > 0) {
                 $response['message'] = 'A demo request with this email already exists. Our team will contact you shortly.';
             } else {
                 // Invalidate older OTPs for this email first
                 $stmt = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending'");
                 $stmt->execute([$email]);

                 // Insert new OTP (using MySQL's NOW() to prevent PHP/DB timezone drift)
                 $stmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
                 if ($stmt->execute([$email, $otp])) {
                     // Build OTP email HTML
                     $subject = 'MicroFin - Your Verification Code';
                     $message = "
                     <html>
                     <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <h2>MicroFin Demo Verification</h2>
                        <p>Your one-time verification code is:</p>
                        <h1 style='color: #10b981; letter-spacing: 5px;'>{$otp}</h1>
                        <p>This code will expire in 5 minutes.</p>
                     </body>
                     </html>
                     ";

                     // Try all available email delivery methods
                     $emailResult = sendEmail($email, $subject, $message, $smtp_timeout_seconds, $smtp_force_fallback);

                     if ($emailResult['success']) {
                         $response['message'] = 'OTP sent to your email!';
                         $response['success'] = true;
                         $response['delivery_mode'] = $emailResult['method'];
                     } else {
                         // All methods failed - expire the OTP and return error
                         $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND otp_code = ?")->execute([$email, $otp]);
                         $response['message'] = 'Unable to send verification email. Please try again later.';
                     }
             } else {
                 $response['message'] = 'Database error generating OTP.';
             }
         } // End duplicate check
         } catch (\PDOException $e) {
             $response['message'] = 'System error: ' . $e->getMessage();
         }
    }
} 
elseif ($action === 'verify_otp') {
    $email = trim($_POST['email'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');

    try {
        // Find a matching, non-expired OTP using MySQL time context
        $stmt = $pdo->prepare("SELECT otp_id, (expires_at < NOW()) as is_expired FROM otp_verifications WHERE email = ? AND otp_code = ? AND status = 'Pending' ORDER BY otp_id DESC LIMIT 1");
        $stmt->execute([$email, $otp_code]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
             // Check if 5-minutes have passed via MySQL evaluation
             if ($record['is_expired']) {
                 
                 // Manually force expiry update
                 $upd = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE otp_id = ?");
                 $upd->execute([$record['otp_id']]);

                 $response['message'] = 'OTP has expired. Please request a new one.';
             } else {
                 // Valid! Mark as verified
                 $upd = $pdo->prepare("UPDATE otp_verifications SET status = 'Verified' WHERE otp_id = ?");
                 $upd->execute([$record['otp_id']]);

                 // Set session flag allowing final submission
                 $_SESSION['verified_contact_email'] = $email;

                 $response['success'] = true;
                 $response['message'] = 'Email successfully verified!';
             }
        } else {
             $response['message'] = 'Invalid OTP or originally requested email.';
        }
    } catch (\PDOException $e) {
        $response['message'] = 'System error: ' . $e->getMessage();
    }
}
elseif ($action === 'expire_otp') {
    // Called by frontend when countdown hits 0 - mark OTP as expired
    $email = trim($_POST['email'] ?? '');

    if ($email) {
        try {
            $stmt = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending'");
            $stmt->execute([$email]);
            $response['success'] = true;
            $response['message'] = 'OTP expired.';
        } catch (\PDOException $e) {
            $response['message'] = 'System error.';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;

