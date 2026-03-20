<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 1 (loan products)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 1) {
    if ($current_step === 0) {
        header('Location: force_change_password.php');
    } elseif ($current_step === 2) {
        header('Location: setup_credit.php');
    } elseif ($current_step === 3) {
        header('Location: setup_website.php');
    } elseif ($current_step === 4) {
        header('Location: setup_branding.php');
    } else {
        header('Location: ../admin_panel/admin.php');
    }
    exit;
}

$error = '';
$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';

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

$form = [
    'product_name' => '',
    'product_type' => 'Personal Loan',
    'description' => '',
    'min_amount' => '5000',
    'max_amount' => '100000',
    'interest_rate' => '3.00',
    'interest_type' => 'Diminishing',
    'min_term_months' => '3',
    'max_term_months' => '24',
    'processing_fee_percentage' => '2.00',
    'service_charge' => '0.00',
    'documentary_stamp' => '0.00',
    'insurance_fee_percentage' => '0.00',
    'penalty_rate' => '0.50',
    'penalty_type' => 'Daily',
    'grace_period_days' => '3',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? $default);
    }

    // Validate
    if ($form['product_name'] === '') {
        $error = 'Product name is required.';
    } elseif (!in_array($form['product_type'], ['Personal Loan', 'Business Loan', 'Emergency Loan'], true)) {
        $error = 'Invalid product type.';
    } elseif (!in_array($form['interest_type'], ['Fixed', 'Diminishing', 'Flat'], true)) {
        $error = 'Invalid interest type.';
    } elseif (!in_array($form['penalty_type'], ['Daily', 'Monthly', 'Flat'], true)) {
        $error = 'Invalid penalty type.';
    } elseif ((float)$form['min_amount'] <= 0 || (float)$form['max_amount'] <= 0) {
        $error = 'Loan amounts must be greater than zero.';
    } elseif ((float)$form['min_amount'] > (float)$form['max_amount']) {
        $error = 'Minimum amount cannot be greater than maximum amount.';
    } elseif ((int)$form['min_term_months'] < 1 || (int)$form['max_term_months'] < 1) {
        $error = 'Loan terms must be at least 1 month.';
    } elseif ((int)$form['min_term_months'] > (int)$form['max_term_months']) {
        $error = 'Minimum term cannot exceed maximum term.';
    } elseif ((float)$form['interest_rate'] < 0 || (float)$form['interest_rate'] > 100) {
        $error = 'Interest rate must be between 0 and 100.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO loan_products (tenant_id, product_name, product_type, description, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months, processing_fee_percentage, service_charge, documentary_stamp, insurance_fee_percentage, penalty_rate, penalty_type, grace_period_days, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)');
        $stmt->execute([
            $tenant_id,
            $form['product_name'],
            $form['product_type'],
            $form['description'],
            (float)$form['min_amount'],
            (float)$form['max_amount'],
            (float)$form['interest_rate'],
            $form['interest_type'],
            (int)$form['min_term_months'],
            (int)$form['max_term_months'],
            (float)$form['processing_fee_percentage'],
            (float)$form['service_charge'],
            (float)$form['documentary_stamp'],
            (float)$form['insurance_fee_percentage'],
            (float)$form['penalty_rate'],
            $form['penalty_type'],
            (int)$form['grace_period_days'],
        ]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LOAN_PRODUCT_SETUP', 'loan_product', 'First loan product created during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 2 WHERE tenant_id = ? AND setup_current_step = 1')->execute([$tenant_id]);

        header('Location: setup_credit.php');
        exit;
    }
}

$e = function ($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Loan Products - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo $e($t_font); ?>', sans-serif; background: <?php echo $e($t_bg); ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .wizard-card { background: <?php echo $e($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 100%; max-width: 720px; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo $e($accent); ?>, #8b5cf6); padding: 28px 32px; color: white; }
        .wizard-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        .wizard-header p { opacity: 0.85; font-size: 0.9rem; }
        .step-indicator { display: flex; gap: 8px; margin-top: 14px; }
        .step { width: 40px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.3); }
        .step.active { background: white; }
        .wizard-body { padding: 28px 32px 32px; }
        .section { border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .section h3 { font-size: 1rem; font-weight: 700; margin-bottom: 4px; color: <?php echo $e($t_text); ?>; display: flex; align-items: center; gap: 8px; }
        .section h3 .material-symbols-rounded { font-size: 20px; color: <?php echo $e($accent); ?>; }
        .section p { color: <?php echo $e($t_muted); ?>; font-size: 0.85rem; margin-bottom: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 6px; color: #475569; font-size: 0.88rem; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.92rem; color: #0f172a; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: <?php echo $e($accent); ?>; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        select.form-control { cursor: pointer; }
        .input-group { position: relative; }
        .input-suffix { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem; pointer-events: none; }
        .btn-primary { width: 100%; padding: 12px 28px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.95rem; background: <?php echo $e($accent); ?>; color: white; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .hint { color: #94a3b8; font-size: 0.78rem; margin-top: 4px; }
        @media (max-width: 600px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } .wizard-header, .wizard-body { padding: 20px; } }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Loan Product Setup</h1>
            <p>Configure your first loan product for <?php echo $e($tenant_name); ?>. You can add more later.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
                <div class="step"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
        </div>

        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo $e($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Basic Info -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">inventory_2</span> Product Details</h3>
                    <p>Name and type of this loan offering.</p>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Product Name <span style="color:#dc2626;">*</span></label>
                            <input type="text" class="form-control" name="product_name" value="<?php echo $e($form['product_name']); ?>" placeholder="e.g. Personal Cash Loan" required>
                        </div>
                        <div class="form-group">
                            <label>Product Type <span style="color:#dc2626;">*</span></label>
                            <select class="form-control" name="product_type">
                                <option value="Personal Loan" <?php echo $form['product_type'] === 'Personal Loan' ? 'selected' : ''; ?>>Personal Loan</option>
                                <option value="Business Loan" <?php echo $form['product_type'] === 'Business Loan' ? 'selected' : ''; ?>>Business Loan</option>
                                <option value="Emergency Loan" <?php echo $form['product_type'] === 'Emergency Loan' ? 'selected' : ''; ?>>Emergency Loan</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this loan product..."><?php echo $e($form['description']); ?></textarea>
                    </div>
                </div>

                <!-- Interest & Rates -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">percent</span> Interest &amp; Rates</h3>
                    <p>Set the interest rate and calculation method.</p>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>Interest Rate (%) <span style="color:#dc2626;">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="interest_rate" value="<?php echo $e($form['interest_rate']); ?>" step="0.01" min="0" max="100" required>
                                <span class="input-suffix">%</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Interest Type <span style="color:#dc2626;">*</span></label>
                            <select class="form-control" name="interest_type">
                                <option value="Diminishing" <?php echo $form['interest_type'] === 'Diminishing' ? 'selected' : ''; ?>>Diminishing</option>
                                <option value="Fixed" <?php echo $form['interest_type'] === 'Fixed' ? 'selected' : ''; ?>>Fixed</option>
                                <option value="Flat" <?php echo $form['interest_type'] === 'Flat' ? 'selected' : ''; ?>>Flat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Penalty Rate (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="penalty_rate" value="<?php echo $e($form['penalty_rate']); ?>" step="0.01" min="0">
                                <span class="input-suffix">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Penalty Type</label>
                            <select class="form-control" name="penalty_type">
                                <option value="Daily" <?php echo $form['penalty_type'] === 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="Monthly" <?php echo $form['penalty_type'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="Flat" <?php echo $form['penalty_type'] === 'Flat' ? 'selected' : ''; ?>>Flat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Grace Period (days)</label>
                            <input type="number" class="form-control" name="grace_period_days" value="<?php echo $e($form['grace_period_days']); ?>" min="0">
                            <span class="hint">Days after due date before penalty applies</span>
                        </div>
                    </div>
                </div>

                <!-- Loan Amounts & Terms -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">payments</span> Amounts &amp; Terms</h3>
                    <p>Set the allowed loan amount range and term length.</p>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Minimum Amount (₱) <span style="color:#dc2626;">*</span></label>
                            <input type="number" class="form-control" name="min_amount" value="<?php echo $e($form['min_amount']); ?>" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Maximum Amount (₱) <span style="color:#dc2626;">*</span></label>
                            <input type="number" class="form-control" name="max_amount" value="<?php echo $e($form['max_amount']); ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Min Term (months) <span style="color:#dc2626;">*</span></label>
                            <input type="number" class="form-control" name="min_term_months" value="<?php echo $e($form['min_term_months']); ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Max Term (months) <span style="color:#dc2626;">*</span></label>
                            <input type="number" class="form-control" name="max_term_months" value="<?php echo $e($form['max_term_months']); ?>" min="1" required>
                        </div>
                    </div>
                </div>

                <!-- Fees & Charges -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">receipt_long</span> Fees &amp; Charges</h3>
                    <p>Deductions applied at loan release. Set to 0 if not applicable.</p>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Processing Fee (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="processing_fee_percentage" value="<?php echo $e($form['processing_fee_percentage']); ?>" step="0.01" min="0">
                                <span class="input-suffix">%</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Insurance Fee (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="insurance_fee_percentage" value="<?php echo $e($form['insurance_fee_percentage']); ?>" step="0.01" min="0">
                                <span class="input-suffix">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Service Charge (₱)</label>
                            <input type="number" class="form-control" name="service_charge" value="<?php echo $e($form['service_charge']); ?>" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Documentary Stamp (₱)</label>
                            <input type="number" class="form-control" name="documentary_stamp" value="<?php echo $e($form['documentary_stamp']); ?>" step="0.01" min="0">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 8px;">
                    <button type="submit" class="btn-primary">
                        Continue to Credit Setup
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_forward</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
