<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 2 (credit config)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 2) {
    if ($current_step === 0) {
        header('Location: force_change_password.php');
    } elseif ($current_step === 1) {
        header('Location: setup_loan_products.php');
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

// Load current credit config from system_settings if any
$load_setting = function ($key, $default) use ($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1");
    $stmt->execute([$tenant_id, $key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
};

$form = [
    'minimum_credit_score' => $load_setting('minimum_credit_score', '50'),
    'income_weight' => $load_setting('credit_weight_income', '25'),
    'employment_weight' => $load_setting('credit_weight_employment', '20'),
    'credit_history_weight' => $load_setting('credit_weight_credit_history', '20'),
    'collateral_weight' => $load_setting('credit_weight_collateral', '10'),
    'character_weight' => $load_setting('credit_weight_character', '15'),
    'business_weight' => $load_setting('credit_weight_business', '10'),
    'require_ci' => $load_setting('require_credit_investigation', '1'),
    'auto_reject_below' => $load_setting('auto_reject_below_score', '30'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? $default);
    }

    $total_weight = (int)$form['income_weight'] + (int)$form['employment_weight']
                  + (int)$form['credit_history_weight'] + (int)$form['collateral_weight']
                  + (int)$form['character_weight'] + (int)$form['business_weight'];

    if ($total_weight !== 100) {
        $error = "Scoring weights must total exactly 100%. Currently: {$total_weight}%.";
    } elseif ((int)$form['minimum_credit_score'] < 0 || (int)$form['minimum_credit_score'] > 100) {
        $error = 'Minimum credit score must be between 0 and 100.';
    } elseif ((int)$form['auto_reject_below'] < 0 || (int)$form['auto_reject_below'] > (int)$form['minimum_credit_score']) {
        $error = 'Auto-reject score must be between 0 and the minimum credit score.';
    } else {
        $settings = [
            'minimum_credit_score' => ['Credit', $form['minimum_credit_score'], 'Number'],
            'credit_weight_income' => ['Credit', $form['income_weight'], 'Number'],
            'credit_weight_employment' => ['Credit', $form['employment_weight'], 'Number'],
            'credit_weight_credit_history' => ['Credit', $form['credit_history_weight'], 'Number'],
            'credit_weight_collateral' => ['Credit', $form['collateral_weight'], 'Number'],
            'credit_weight_character' => ['Credit', $form['character_weight'], 'Number'],
            'credit_weight_business' => ['Credit', $form['business_weight'], 'Number'],
            'require_credit_investigation' => ['Credit', $form['require_ci'], 'Boolean'],
            'auto_reject_below_score' => ['Credit', $form['auto_reject_below'], 'Number'],
        ];

        $upsert = $pdo->prepare('INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP');

        foreach ($settings as $key => [$category, $value, $type]) {
            $upsert->execute([$tenant_id, $key, $value, $category, $type]);
        }

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'CREDIT_CONFIG_SETUP', 'system_settings', 'Credit scoring configured during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 3 WHERE tenant_id = ? AND setup_current_step = 2')->execute([$tenant_id]);

        header('Location: setup_website.php');
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
    <title>Credit Assessment Setup - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo $e($t_font); ?>', sans-serif; background: <?php echo $e($t_bg); ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .wizard-card { background: <?php echo $e($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 100%; max-width: 640px; overflow: hidden; }
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
        .weight-bar { display: flex; align-items: center; gap: 10px; }
        .weight-bar input { flex: 1; }
        .weight-bar .val { min-width: 32px; text-align: right; font-weight: 600; color: <?php echo $e($t_text); ?>; font-size: 0.9rem; }
        .weight-total { padding: 10px 14px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-align: center; margin-top: 8px; }
        .weight-total.ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .weight-total.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 0.9rem; color: <?php echo $e($t_text); ?>; }
        .toggle-desc { font-size: 0.78rem; color: <?php echo $e($t_muted); ?>; margin-top: 2px; }
        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 999px; transition: 0.3s; }
        .toggle-slider:before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: <?php echo $e($accent); ?>; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
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
            <h1>Credit Assessment Setup</h1>
            <p>Configure how <?php echo $e($tenant_name); ?> evaluates borrower creditworthiness.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
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

            <form method="POST" id="credit-form">
                <!-- Scoring Thresholds -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">speed</span> Score Thresholds</h3>
                    <p>Set the minimum passing score and auto-reject threshold.</p>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Minimum Credit Score (0-100) <span style="color:#dc2626;">*</span></label>
                            <input type="number" class="form-control" name="minimum_credit_score" value="<?php echo $e($form['minimum_credit_score']); ?>" min="0" max="100" required>
                            <span class="hint">Applicants below this score need manual review</span>
                        </div>
                        <div class="form-group">
                            <label>Auto-Reject Below</label>
                            <input type="number" class="form-control" name="auto_reject_below" value="<?php echo $e($form['auto_reject_below']); ?>" min="0" max="100">
                            <span class="hint">Applications below this are flagged for rejection</span>
                        </div>
                    </div>
                </div>

                <!-- Scoring Weights -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">balance</span> Scoring Weights</h3>
                    <p>Adjust how much each factor contributes to the total score. Must total 100%.</p>

                    <?php
                    $weights = [
                        ['income_weight', 'Income', 'Monthly income, other income sources'],
                        ['employment_weight', 'Employment', 'Employment status, stability, employer'],
                        ['credit_history_weight', 'Credit History', 'Past loan repayment performance'],
                        ['collateral_weight', 'Collateral', 'Assets or property offered as security'],
                        ['character_weight', 'Character', 'Reputation, references, community standing'],
                        ['business_weight', 'Business', 'Business viability (for business loans)'],
                    ];
                    foreach ($weights as [$field, $label, $desc]):
                    ?>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label style="margin-bottom:2px;"><?php echo $e($label); ?></label>
                        <span class="hint" style="margin-top:0;margin-bottom:6px;display:block;"><?php echo $e($desc); ?></span>
                        <div class="weight-bar">
                            <input type="range" name="<?php echo $e($field); ?>" class="weight-slider" value="<?php echo $e($form[$field]); ?>" min="0" max="50" step="5" oninput="updateWeights()">
                            <span class="val" id="val_<?php echo $e($field); ?>"><?php echo $e($form[$field]); ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="weight-total ok" id="weight-total">Total: 100%</div>
                </div>

                <!-- Investigation Settings -->
                <div class="section">
                    <h3><span class="material-symbols-rounded">search_insights</span> Investigation Settings</h3>
                    <p>Configure credit investigation requirements.</p>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Require Credit Investigation</div>
                            <div class="toggle-desc">All loan applications require a field CI before approval</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="require_ci" value="0">
                            <input type="checkbox" name="require_ci" value="1" <?php echo $form['require_ci'] === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 8px;">
                    <button type="submit" class="btn-primary">
                        Continue to Website Setup
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_forward</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function updateWeights() {
        var sliders = document.querySelectorAll('.weight-slider');
        var total = 0;
        sliders.forEach(function(s) {
            var v = parseInt(s.value, 10);
            total += v;
            var id = 'val_' + s.name;
            var el = document.getElementById(id);
            if (el) el.textContent = v + '%';
        });
        var totalEl = document.getElementById('weight-total');
        totalEl.textContent = 'Total: ' + total + '%';
        if (total === 100) {
            totalEl.className = 'weight-total ok';
        } else {
            totalEl.className = 'weight-total err';
        }
    }
    updateWeights();
    </script>
</body>
</html>
