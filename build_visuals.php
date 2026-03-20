<?php

$file = "microfin_platform/public_website/demo.php";
$content = file_get_contents($file);

$startStr = "    <style>";
$endStr = "    <script>";

$start = strpos($content, $startStr);
$end = strpos($content, $endStr);

if ($start !== false && $end !== false) {
    echo "Found! Size: " . strlen($content) . "\n";
    $before = substr($content, 0, $start);
    $after = substr($content, $end);
    
    $newVisuals = <<<'HTML'
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #0f172a;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --bg-light: #f8fafc;
            --text-dark: #0f172a;
            --text-gray: #475569;
            --text-light: #94a3b8;
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .split-layout {
            display: flex;
            min-height: 100vh;
            flex-wrap: wrap;
        }

        /* Left Side: Info */
        .info-side {
            flex: 1 1 400px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .info-content {
            max-width: 480px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .info-side::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.1) 0%, transparent 40%);
            z-index: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 40px;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: #fff;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 30px;
        }

        .info-side h1 {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .info-side p.lead {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.8);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .features-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .features-list .material-symbols-rounded {
            color: #10b981;
            font-size: 24px;
        }

        .features-list h4 {
            font-size: 1.05rem;
            margin-bottom: 4px;
        }
        
        .features-list p {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.6);
        }

        /* Right Side: Form */
        .form-side {
            flex: 1 1 500px;
            background: #ffffff;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .demo-card {
            width: 100%;
            max-width: 500px;
        }

        .demo-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .demo-card .subtitle {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-bottom: 32px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: var(--text-gray);
        }

        .form-row {
            display: flex;
            gap: 16px;
        }
        .form-row .form-group { flex: 1; }

        .input-field {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text-dark);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .text-danger { color: #ef4444; }

        /* OTP group */
        .otp-group {
            display: none;
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
            font-size: 1rem;
        }

        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .btn-outline { background: transparent; border: 1.5px solid #cbd5e1; color: var(--text-dark); }
        .btn-outline:hover { background: var(--bg-light); }
        .btn-block { width: 100%; }

        .success-view { text-align: center; padding: 40px 0; }
        .success-view .material-symbols-rounded { font-size: 64px; color: #10b981; margin-bottom: 20px; }
        .success-view h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; }
        .success-view p { color: var(--text-gray); margin-bottom: 30px; line-height: 1.6; }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .split-layout { flex-direction: column; }
            .info-side { padding: 40px 24px; }
            .form-side { padding: 40px 24px; }
            .form-row { flex-direction: column; gap: 0; }
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="split-layout">
        <!-- Left Side: Information -->
        <div class="info-side">
            <div class="info-content">
                <a href="index.php" class="back-btn">
                    <span class="material-symbols-rounded">arrow_back</span> Back to Home
                </a>
                
                <div class="logo-wrap">
                    <span class="material-symbols-rounded">public</span> MicroFin
                </div>
                
                <h1>Modernize your cooperative today.</h1>
                <p class="lead">Join over 100+ microfinance institutions leveraging our cloud platform to streamline operations and expand their reach.</p>
                
                <ul class="features-list">
                    <li>
                        <span class="material-symbols-rounded">gpp_good</span>
                        <div>
                            <h4>Bank-Grade Security</h4>
                            <p>End-to-end encryption and strict tenant isolation.</p>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">bolt</span>
                        <div>
                            <h4>Instant Provisioning</h4>
                            <p>Get your isolated environment spun up in minutes.</p>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">payments</span>
                        <div>
                            <h4>Transparent Pricing</h4>
                            <p>Predictable monthly plans designed for growth.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="form-side">
            <div class="demo-card">
                <?php if ($form_success): ?>
                    <div class="success-view">
                        <span class="material-symbols-rounded">check_circle</span>
                        <h3>Request Received!</h3>
                        <p>Thanks for your interest. A MicroFin sales engineer will contact you shortly to set up your environment.</p>
                        <a href="index.php" class="btn btn-primary">
                            <span class="material-symbols-rounded" style="font-size:18px; margin-right:6px;">home</span>
                            Back to Home
                        </a>
                    </div>
                <?php else: ?>
                    <h2>Contact Sales</h2>
                    <p class="subtitle">Enter your details below and our team will be in touch.</p>

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
                                <label>Business Email <span class="text-danger">*</span></label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="email" class="input-field" id="work_email" name="company_email" placeholder="you@institution.com" required>
                                    <button type="button" id="btn-send-otp" class="btn btn-outline" style="white-space: nowrap; padding: 0 16px;">Send OTP</button>
                                </div>
                                <small id="email-help-text" style="color: #94a3b8; font-size: 0.8rem; margin-top: 6px; display:block;">Requires verification before submission.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" class="input-field" name="location" placeholder="e.g. City, Region or Country">
                        </div>

                        <div class="form-group">
                            <label>Subscription Plan <span class="text-danger">*</span></label>
                            <select class="input-field" name="plan_tier" style="margin-bottom: 0.5rem;" required>
                                <option value="">-- Choose a Plan --</option>
                                <option value="Starter">Starter (₱4,999/mo)</option>
                                <option value="Enterprise">Enterprise (₱14,999/mo)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Proof of Legitimacy Documents <span class="text-danger">*</span></label>
                            <input type="file" class="input-field" name="legitimacy_documents[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" style="padding: 8px;" multiple required>
                            <small style="color: #94a3b8; font-size: 0.8rem; margin-top: 4px; display:block;">Upload 1 to 5 files (images, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, RTF, ODT, ODS, ODP).</small>
                        </div>

                        <!-- OTP Input Group -->
                        <div class="otp-group" id="otp-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label style="margin:0;">Enter 6-Digit OTP <span class="text-danger">*</span></label>
                                <span id="otp-countdown" style="font-size: 0.8rem; font-weight: 600; color: #b45309;"></span>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" class="input-field" name="otp_code" id="otp_code" placeholder="123456" maxlength="6">
                                <button type="button" id="btn-verify-otp" class="btn btn-primary" style="padding: 0 20px;">Verify</button>
                            </div>
                            <div id="otp-status-msg" style="font-size: 0.85rem; margin-top: 8px; font-weight: 500;"></div>
                            <input type="hidden" name="is_otp_verified" id="is_otp_verified" value="0">
                        </div>

                        <button type="submit" id="btn-final-submit" class="btn btn-primary btn-block" style="opacity: 0.5; pointer-events: none; padding: 14px; font-size: 1.05rem;">Submit Request</button>
                        <small id="form-block-note" style="display: block; text-align: center; margin-top: 12px; color: #ef4444; font-weight: 500;">Verify your email to enable submission.</small>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
HTML;
    
    file_put_contents($file, $before . $newVisuals . "\n" . $after);
    echo "DONE\n";
}

?>