<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 5 (billing - final)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 5) {
    $setup_routes = [0 => 'force_change_password.php', 1 => 'setup_loan_products.php', 2 => 'setup_credit.php', 3 => 'setup_website.php', 4 => 'setup_branding.php'];
    if (isset($setup_routes[$current_step])) {
        header('Location: ' . $setup_routes[$current_step]);
    } else {
        header('Location: ../admin_panel/admin.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardholder_name = trim($_POST['cardholder_name'] ?? '');
    $card_number = trim($_POST['card_number'] ?? '');
    $exp_month = (int) ($_POST['exp_month'] ?? 0);
    $exp_year = (int) ($_POST['exp_year'] ?? 0);
    $card_brand = trim($_POST['card_brand'] ?? '');

    // Validate
    $card_clean = preg_replace('/\s+/', '', $card_number);
    if ($cardholder_name === '' || $card_clean === '') {
        $error = 'Cardholder name and card number are required.';
    } elseif (strlen($card_clean) < 13 || strlen($card_clean) > 19 || !ctype_digit($card_clean)) {
        $error = 'Please enter a valid card number (13-19 digits).';
    } elseif ($exp_month < 1 || $exp_month > 12) {
        $error = 'Please select a valid expiration month.';
    } elseif ($exp_year < (int) date('Y')) {
        $error = 'Expiration year cannot be in the past.';
    } else {
        $last_four = substr($card_clean, -4);

        // Encrypt the full card number with AES-256
        $encryption_key = defined('ENCRYPTION_KEY') ? constant('ENCRYPTION_KEY') : 'microfin_default_encryption_key_32b';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($card_clean, 'aes-256-cbc', $encryption_key, 0, $iv);
        $encrypted_with_iv = base64_encode($iv . '::' . base64_decode($encrypted));

        // Auto-detect card brand
        if ($card_brand === '') {
            $first_digit = $card_clean[0];
            $first_two = substr($card_clean, 0, 2);
            if ($first_digit === '4') $card_brand = 'Visa';
            elseif (in_array($first_two, ['51','52','53','54','55'])) $card_brand = 'Mastercard';
            elseif (in_array($first_two, ['34','37'])) $card_brand = 'Amex';
            else $card_brand = 'Other';
        }

        $stmt = $pdo->prepare('INSERT INTO tenant_billing_payment_methods (tenant_id, last_four_digits, card_brand, cardholder_name, exp_month, exp_year, card_number_encrypted, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)');
        $stmt->execute([$tenant_id, $last_four, $card_brand, $cardholder_name, $exp_month, $exp_year, $encrypted_with_iv]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 6, setup_completed = TRUE WHERE tenant_id = ? AND setup_current_step = 5')->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_SETUP', 'tenant', 'Payment method added during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        header('Location: ../admin_panel/admin.php');
        exit;
    }
}

// Fetch branding for styling
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
$accent = ($brand && $brand['theme_primary_color']) ? $brand['theme_primary_color'] : '#0284c7';
$t_text = ($brand && $brand['theme_text_main']) ? $brand['theme_text_main'] : '#0f172a';
$t_muted = ($brand && $brand['theme_text_muted']) ? $brand['theme_text_muted'] : '#64748b';
$t_bg = ($brand && $brand['theme_bg_body']) ? $brand['theme_bg_body'] : '#f1f5f9';
$t_card = ($brand && $brand['theme_bg_card']) ? $brand['theme_bg_card'] : '#ffffff';
$t_font = ($brand && $brand['font_family']) ? $brand['font_family'] : 'Inter';

$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';
$current_year = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Billing - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .wizard-card { background: <?php echo htmlspecialchars($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 100%; max-width: 560px; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo htmlspecialchars($accent); ?>, #8b5cf6); padding: 32px; color: white; }
        .wizard-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        .wizard-header p { opacity: 0.85; font-size: 0.9rem; }
        .step-indicator { display: flex; gap: 8px; margin-top: 16px; }
        .step { width: 40px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.3); }
        .step.active { background: white; }
        .wizard-body { padding: 32px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #475569; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #0f172a; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: <?php echo htmlspecialchars($accent); ?>; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .card-preview { background: linear-gradient(135deg, #1e293b, #334155); border-radius: 12px; padding: 24px; color: white; margin-bottom: 24px; position: relative; overflow: hidden; }
        .card-preview::after { content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .card-preview .card-number { font-size: 1.3rem; letter-spacing: 3px; font-weight: 600; margin: 20px 0 16px; font-family: monospace; }
        .card-preview .card-name { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .card-preview .card-expiry { font-size: 0.85rem; opacity: 0.8; position: absolute; bottom: 24px; right: 24px; }
        .card-preview .card-brand-display { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.95rem; transition: all 0.2s; }
        .btn-primary { background: <?php echo htmlspecialchars($accent); ?>; color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .security-note { display: flex; align-items: center; gap: 8px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 24px; font-size: 0.85rem; color: #166534; }
        small { color: #94a3b8; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Payment Method</h1>
            <p>Add a payment method for your <?php echo htmlspecialchars($tenant_name); ?> subscription.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step active"></div>
            </div>
        </div>
        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Card Preview -->
            <div class="card-preview">
                <div class="card-brand-display" id="preview-brand">VISA</div>
                <div class="card-number" id="preview-number">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
                <div class="card-name" id="preview-name">CARDHOLDER NAME</div>
                <div class="card-expiry" id="preview-expiry">MM/YY</div>
            </div>

            <div class="security-note">
                <span class="material-symbols-rounded" style="font-size: 18px;">lock</span>
                Your card details are encrypted with AES-256. We never store your CVC.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Cardholder Name</label>
                    <input type="text" class="form-control" name="cardholder_name" id="cardholder_name" placeholder="Juan Dela Cruz" required oninput="updateCardPreview();">
                </div>

                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" class="form-control" name="card_number" id="card_number" placeholder="4242 4242 4242 4242" maxlength="24" required oninput="formatCardNumber(this); updateCardPreview();">
                </div>

                <input type="hidden" name="card_brand" id="card_brand" value="">

                <div class="row-2">
                    <div class="form-group">
                        <label>Expiration Month</label>
                        <select class="form-control" name="exp_month" id="exp_month" required onchange="updateCardPreview();">
                            <option value="">Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT) . ' - ' . date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expiration Year</label>
                        <select class="form-control" name="exp_year" id="exp_year" required onchange="updateCardPreview();">
                            <option value="">Year</option>
                            <?php for ($y = $current_year; $y <= $current_year + 15; $y++): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-rounded">check_circle</span> Save & Complete Setup
                </button>
            </form>
        </div>
    </div>

    <script>
        function formatCardNumber(input) {
            let v = input.value.replace(/\D/g, '');
            let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
            input.value = formatted;
        }

        function updateCardPreview() {
            const name = document.getElementById('cardholder_name').value.toUpperCase() || 'CARDHOLDER NAME';
            const number = document.getElementById('card_number').value.replace(/\D/g, '');
            const month = document.getElementById('exp_month').value;
            const year = document.getElementById('exp_year').value;

            document.getElementById('preview-name').textContent = name;

            // Format card number for display
            let display = '';
            for (let i = 0; i < 16; i++) {
                if (i > 0 && i % 4 === 0) display += ' ';
                display += i < number.length ? number[i] : '\u2022';
            }
            document.getElementById('preview-number').textContent = display;

            // Expiry
            const mm = month ? month.toString().padStart(2, '0') : 'MM';
            const yy = year ? year.toString().slice(-2) : 'YY';
            document.getElementById('preview-expiry').textContent = mm + '/' + yy;

            // Auto-detect brand
            let brand = 'CARD';
            if (number.length > 0) {
                const first = number[0];
                const firstTwo = number.substring(0, 2);
                if (first === '4') brand = 'VISA';
                else if (['51','52','53','54','55'].includes(firstTwo)) brand = 'MASTERCARD';
                else if (['34','37'].includes(firstTwo)) brand = 'AMEX';
                else if (firstTwo === '36' || firstTwo === '38') brand = 'DINERS';
            }
            document.getElementById('preview-brand').textContent = brand;
            document.getElementById('card_brand').value = brand.charAt(0) + brand.slice(1).toLowerCase();
        }
    </script>
</body>
</html>
