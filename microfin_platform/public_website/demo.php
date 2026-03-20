<?php
session_start();
require_once '../backend/db_connect.php';

$form_success = false;
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_demo') {
    $institution_name = trim($_POST['institution_name'] ?? '');
    $contact_first_name = trim($_POST['contact_first_name'] ?? '');
    $contact_last_name = trim($_POST['contact_last_name'] ?? '');
    $contact_mi = trim($_POST['contact_mi'] ?? '');
    $contact_suffix = trim($_POST['contact_suffix'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $plan_tier = trim($_POST['plan_tier'] ?? '');
    $plan_tier = $plan_tier === '' ? null : $plan_tier;
    $company_email = trim($_POST['company_email'] ?? '');
    $demo_schedule_date = trim($_POST['demo_schedule_date'] ?? '');

    $is_otp_verified = false;
    if (isset($_SESSION['verified_contact_email']) && $_SESSION['verified_contact_email'] === $company_email) {
        $is_otp_verified = true;
    }

    if ($institution_name === '' || $company_email === '') {
        $form_error = 'Institution Name and Work Email are required.';
    } elseif (!$is_otp_verified) {
        $form_error = 'Email has not been verified. Please complete OTP verification.';
    } else {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ?");
        $check_stmt->execute([$company_email]);
        $duplicate_count = $check_stmt->fetchColumn();

        if ($duplicate_count > 0) {
            $form_error = 'A demo request with this email already exists. Our team will contact you shortly.';
        } else {
            try {
                $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $institution_name)));
                $tenant_id = $base_slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

                $stmt = $pdo->prepare("
                    INSERT INTO tenants (
                        tenant_id, tenant_name, first_name, last_name,
                        mi, suffix, branch_name, plan_tier,
                        email, demo_schedule_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Demo Requested')
                ");
                $stmt->execute([
                    $tenant_id, $institution_name, $contact_first_name, $contact_last_name,
                    $contact_mi, $contact_suffix, $location, $plan_tier,
                    $company_email, $demo_schedule_date
                ]);

                // Handle Business Permit Upload
                if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/business_permits/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_ext = pathinfo($_FILES['business_permit']['name'], PATHINFO_EXTENSION);
                    $file_name = $tenant_id . '_permit.' . $file_ext;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $file_path)) {
                        $permit_relative_path = '../uploads/business_permits/' . $file_name;
                        $update_stmt = $pdo->prepare("UPDATE tenants SET business_permit_path = ? WHERE tenant_id = ?");
                        $update_stmt->execute([$permit_relative_path, $tenant_id]);
                    }
                }

                $form_success = true;
                unset($_SESSION['verified_contact_email']);
            } catch (PDOException $e) {
                $form_error = 'An error occurred while submitting your request. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | MicroFin</title>
    <meta name="description" content="Contact MicroFin, the cloud banking platform built for Microfinance Institutions. Fill out the form and our team will be in touch.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #0B0F1A;
            --surface-dark: #121826;
            --surface-light: #1A2235;
            --primary-glow: rgba(59, 130, 246, 0.15);
            --accent: #8B5CF6;
            --accent-hover: #7c3aed;
            --bg-light: #121826;
            --text-dark: #F8FAFC;
            --text-gray: #9ca3af;
            --text-light: #64748b;
            --shadow-lg: 0 15px 40px rgba(0,0,0,0.6);
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            padding: 40px 20px;
            position: relative;
        }

        /* Subtle pattern overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(37, 99, 235, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.06) 0%, transparent 40%),
                        radial-gradient(circle at 50% 80%, rgba(37, 99, 235, 0.04) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }

        .back-btn {
            position: fixed;
            top: 24px;
            left: 24px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.25s ease;
            z-index: 10;
        }

        .back-btn:hover {
            color: var(--text-dark);
            background: rgba(255,255,255,0.15);
            transform: translateX(-2px);
        }

        .back-btn .material-symbols-rounded {
            font-size: 20px;
            transition: transform 0.2s;
        }

        .back-btn:hover .material-symbols-rounded {
            transform: translateX(-3px);
        }

        .demo-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 560px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Brand header above card */
        .page-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .page-brand .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .page-brand .logo-text {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .page-brand p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
        }

        /* Form Card */
        .demo-card {
            background: var(--text-dark);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
        }

        .demo-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .demo-card .subtitle {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }

        /* Form fields */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--text-gray);
        }

        .form-row {
            display: flex;
            gap: 12px;
        }

        .form-row .form-group { flex: 1; }

        .input-field {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 8px;
            background: rgba(0,0,0,0.2);
            color: var(--text-dark);
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .text-danger { color: #ef4444; }

        /* OTP group */
        .otp-group {
            display: none;
            background: var(--surface-dark);
            padding: 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 18px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .btn-primary { background: linear-gradient(135deg, #3B82F6 0%, var(--accent) 100%); color: #ffffff; box-shadow: 0 0 15px var(--primary-glow); }
        .btn-primary:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--text-dark); }
        .btn-outline:hover {
            background: var(--bg-light);
        }

        .btn-block {
            width: 100%;
            padding: 13px;
            font-size: 1rem;
        }

        /* Success state */
        .success-view {
            text-align: center;
            padding: 32px 0;
        }

        .success-view .material-symbols-rounded {
            font-size: 56px;
            color: #10b981;
            margin-bottom: 16px;
        }

        .success-view h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .success-view p {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        .success-view .btn {
            margin-top: 8px;
        }

        /* Error alert */
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .demo-card { padding: 28px 20px; }
            .form-row { flex-direction: column; gap: 0; }
            .back-btn { top: 12px; left: 12px; padding: 8px 14px; font-size: 0.85rem; }
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <!-- Back Button -->
    <a href="index.php" class="back-btn" id="back-btn">
        <span class="material-symbols-rounded">arrow_back</span>
        Back to Home
    </a>

    <div class="demo-wrapper">
        <!-- Brand -->
        <div class="page-brand">
            <div class="logo">
                <span class="material-symbols-rounded">public</span>
                <span class="logo-text">MicroFin</span>
            </div>
            <p>Contact our team for your institution</p>
        </div>

        <!-- Form Card -->
        <div class="demo-card">
            <?php if ($form_success): ?>
                <div class="success-view">
                    <span class="material-symbols-rounded">check_circle</span>
                    <h3>Request Received!</h3>
                    <p>Thanks for your interest. A MicroFin sales engineer will contact you shortly.</p>
                    <a href="index.php" class="btn btn-primary">
                        <span class="material-symbols-rounded" style="font-size:18px; margin-right:6px;">home</span>
                        Back to Home
                    </a>
                </div>
            <?php else: ?>
                <h2>Contact Us</h2>
                <p class="subtitle">Fill out the form and our team will get back to you.</p>

                <?php if ($form_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($form_error); ?></div>
                <?php endif; ?>

                <form id="demo-form" method="POST" action="demo.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="request_demo">

                    <div class="form-group">
                        <label>Institution Name <span class="text-danger">*</span></label>
                        <input type="text" class="input-field" name="institution_name" placeholder="e.g. Sacred Hearts Savings" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" class="input-field" name="contact_first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="input-field" name="contact_last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>M.I.</label>
                            <input type="text" class="input-field" name="contact_mi" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" class="input-field" name="contact_suffix" placeholder="e.g. Jr, Sr" maxlength="10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" class="input-field" name="location" placeholder="e.g. City, Region or Country">
                    </div>

                    <div class="form-group">
                        <label>Subscription Plan (Optional)</label>
                        <select class="input-field" name="plan_tier">
                            <option value="">Decide After Demo</option>
                            <option value="Starter">Starter (Up to 1,000 Borrowers, 250 Staff)</option>
                            <option value="Pro">Pro (Up to 5,000 Borrowers, 2,000 Staff)</option>
                            <option value="Enterprise">Enterprise (Unlimited)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Business Email <span class="text-danger">*</span></label>
                        <div style="display: flex; gap: 10px;">
                            <input type="email" class="input-field" name="company_email" id="work_email" placeholder="ceo@institution.com" required>
                            <button type="button" id="btn-send-otp" class="btn btn-outline" style="padding: 0 15px; white-space: nowrap;">Send OTP</button>
                        </div>
                        <small id="email-help-text" style="color: #94a3b8; font-size: 0.8rem; margin-top: 4px; display:block;">Requires verification before submission.</small>
                    </div>

                    <div class="form-group">
                        <label>Business Permit <span class="text-muted" style="font-size: 0.8rem;">(Optional)</span></label>
                        <input type="file" class="input-field" name="business_permit" accept=".pdf,.jpg,.jpeg,.png" style="padding: 8px;">
                    </div>

                    <!-- OTP Input Group -->
                    <div class="otp-group" id="otp-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="font-weight:500; font-size:0.85rem; color:var(--text-gray); margin:0;">Enter 6-Digit OTP <span class="text-danger">*</span></label>
                            <span id="otp-countdown" style="font-size: 0.8rem; font-weight: 600; color: #b45309;"></span>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" class="input-field" name="otp_code" id="otp_code" placeholder="123456" maxlength="6">
                            <button type="button" id="btn-verify-otp" class="btn btn-primary" style="padding: 0 15px;">Verify</button>
                        </div>
                        <div id="otp-status-msg" style="font-size: 0.85rem; margin-top: 8px; font-weight: 500;"></div>
                        <input type="hidden" name="is_otp_verified" id="is_otp_verified" value="0">
                    </div>

                    <div class="form-group">
                        <label>Preferred Contact Schedule <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="input-field" name="demo_schedule_date" id="demo_schedule_date" required>
                    </div>

                    <button type="submit" id="btn-final-submit" class="btn btn-primary btn-block" style="opacity: 0.5; pointer-events: none;">Contact Us</button>
                    <small id="form-block-note" style="display: block; text-align: center; margin-top: 10px; color: #ef4444;">Verify your email to enable submission.</small>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const demoForm = document.getElementById('demo-form');
        if (!demoForm) return;

        // Date/Time picker: disallow past dates
        const dateInput = document.getElementById('demo_schedule_date');
        if (dateInput) {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            dateInput.min = now.toISOString().slice(0, 16);
        }

        // OTP Elements
        const btnSendOtp = document.getElementById('btn-send-otp');
        const btnVerifyOtp = document.getElementById('btn-verify-otp');
        const otpGroup = document.getElementById('otp-group');
        const emailInput = document.getElementById('work_email');
        const otpInput = document.getElementById('otp_code');
        const otpMsg = document.getElementById('otp-status-msg');
        const btnFinalSubmit = document.getElementById('btn-final-submit');
        const formBlockNote = document.getElementById('form-block-note');
        const isOtpVerified = document.getElementById('is_otp_verified');
        const emailHelpText = document.getElementById('email-help-text');
        const otpCountdown = document.getElementById('otp-countdown');

        // OTP expiry countdown (5 minutes)
        let otpExpiryInterval = null;
        function startOtpExpiry() {
            if (otpExpiryInterval) clearInterval(otpExpiryInterval);
            let remaining = 300; // 5 minutes
            const updateExpiry = () => {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                if (otpCountdown) {
                    if (remaining > 60) {
                        otpCountdown.style.color = '#b45309';
                        otpCountdown.innerText = `Expires in ${mins}:${secs.toString().padStart(2, '0')}`;
                    } else if (remaining > 0) {
                        otpCountdown.style.color = '#ef4444';
                        otpCountdown.innerText = `Expires in ${remaining}s`;
                    } else {
                        otpCountdown.style.color = '#ef4444';
                        otpCountdown.innerText = 'Expired';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = 'OTP expired. Please request a new one.';
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Resend OTP';
                        clearInterval(otpExpiryInterval);
                        otpExpiryInterval = null;

                        // Mark OTP as expired in database
                        const expireData = new FormData();
                        expireData.append('action', 'expire_otp');
                        expireData.append('email', emailInput.value.trim());
                        fetch('api/api_demo.php', { method: 'POST', body: expireData });
                    }
                }
                remaining--;
            };
            updateExpiry();
            otpExpiryInterval = setInterval(updateExpiry, 1000);
        }

        // Cooldown timer for failed OTP attempts
        let cooldownInterval = null;
        function startCooldown(seconds) {
            btnSendOtp.disabled = true;
            let remaining = seconds;
            const updateTimer = () => {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                btnSendOtp.innerHTML = `Retry in ${mins}:${secs.toString().padStart(2, '0')}`;
                if (remaining <= 0) {
                    clearInterval(cooldownInterval);
                    cooldownInterval = null;
                    btnSendOtp.disabled = false;
                    btnSendOtp.innerHTML = 'Send OTP';
                    if (emailHelpText) {
                        emailHelpText.style.color = '#94a3b8';
                        emailHelpText.innerText = 'Requires verification before submission.';
                    }
                }
                remaining--;
            };
            updateTimer();
            cooldownInterval = setInterval(updateTimer, 1000);
        }

        // Send OTP
        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                if (!email) { alert("Please enter a valid business email first."); return; }

                btnSendOtp.disabled = true;
                btnSendOtp.innerHTML = 'Sending...';

                // Show hint after 30 seconds
                const slowHintTimer = setTimeout(() => {
                    if (emailHelpText) {
                        emailHelpText.style.color = '#b45309';
                        emailHelpText.innerText = 'Still connecting... please wait.';
                    }
                }, 30000);

                // Abort after 60 seconds
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('api/api_demo.php', { method: 'POST', body: formData, signal: controller.signal })
                .then(res => res.json())
                .then(data => {
                    clearTimeout(slowHintTimer);
                    clearTimeout(timeoutId);

                    if (data.success) {
                        otpGroup.style.display = 'block';
                        btnSendOtp.innerHTML = 'OTP Sent';
                        btnSendOtp.classList.remove('btn-outline');
                        btnSendOtp.style.backgroundColor = '#10b981';
                        btnSendOtp.style.color = 'var(--text-dark)';
                        btnSendOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';

                        otpMsg.innerText = data.message;

                        if (emailHelpText) {
                            emailHelpText.style.color = '#10b981';
                            emailHelpText.innerText = 'OTP sent! Check your inbox.';
                        }
                        startOtpExpiry(); // Start 5-minute countdown
                    } else {
                        // Failed - allow immediate retry
                        if (emailHelpText) {
                            emailHelpText.style.color = '#ef4444';
                            emailHelpText.innerText = data.message;
                        }
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Resend OTP';
                    }
                })
                .catch((err) => {
                    clearTimeout(slowHintTimer);
                    clearTimeout(timeoutId);
                    if (emailHelpText) {
                        emailHelpText.style.color = '#ef4444';
                        if (err.name === 'AbortError') {
                            emailHelpText.innerText = 'Request timed out. Please try again.';
                        } else {
                            emailHelpText.innerText = 'Connection error. Please try again.';
                        }
                    }
                    btnSendOtp.disabled = false;
                    btnSendOtp.innerHTML = 'Resend OTP';
                });
            });
        }

        // Verify OTP
        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                const code = otpInput.value.trim();
                if (code.length !== 6) {
                    otpMsg.style.color = '#ef4444';
                    otpMsg.innerText = 'Please enter a valid 6-digit OTP.';
                    return;
                }

                btnVerifyOtp.disabled = true;
                btnVerifyOtp.innerHTML = 'Verifying...';

                const formData = new FormData();
                formData.append('action', 'verify_otp');
                formData.append('email', email);
                formData.append('otp_code', code);

                fetch('api/api_demo.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Stop expiry countdown and show verified
                        if (otpExpiryInterval) {
                            clearInterval(otpExpiryInterval);
                            otpExpiryInterval = null;
                        }
                        if (otpCountdown) {
                            otpCountdown.style.color = '#10b981';
                            otpCountdown.innerText = 'Verified';
                        }
                        btnVerifyOtp.innerHTML = 'Verified';
                        btnVerifyOtp.style.backgroundColor = '#10b981';
                        btnVerifyOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        otpMsg.innerText = data.message;
                        emailInput.readOnly = true;
                        otpInput.readOnly = true;
                        isOtpVerified.value = '1';
                        btnFinalSubmit.style.opacity = '1';
                        btnFinalSubmit.style.pointerEvents = 'auto';
                        formBlockNote.style.color = '#10b981';
                        formBlockNote.innerText = 'You may now submit your request.';
                    } else {
                        btnVerifyOtp.disabled = false;
                        btnVerifyOtp.innerHTML = 'Verify';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = data.message;
                    }
                })
                .catch(() => { btnVerifyOtp.disabled = false; btnVerifyOtp.innerHTML = 'Verify'; });
            });
        }

        // Submit guard
        demoForm.addEventListener('submit', (e) => {
            if (isOtpVerified.value === '0') {
                e.preventDefault();
                alert("Please verify your email with the OTP before submitting.");
                return;
            }
            const submitBtn = demoForm.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite; font-size: 18px; margin-right: 8px; vertical-align: middle;">sync</span> Submitting...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';
        });
    });
    </script>
</body>
</html>
