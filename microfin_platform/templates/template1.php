<?php
/**
 * Template 1 — Editorial Professional (based on temp1.html design)
 * Fully dynamic — all variables passed from site.php via include scope.
 *
 * REQUIRED VARIABLES from site.php:
 *   $e, $tenant_name, $logo, $palette, $custom_css, $font_family
 *   $hero_title, $hero_subtitle, $hero_desc, $hero_cta_text, $hero_cta_url, $hero_image, $hero_badge_text, $hero_bg_path
 *   $show_partners, $partners (array of image URLs)
 *   $show_services, $services_heading, $services (array of {title, description, icon})
 *   $show_stats, $stats_heading, $stats_subheading, $stats_image, $stats (array of {value, label})
 *   $show_loan_calc, $loan_products (array from loan_products table)
 *   $show_about, $about_heading, $about_body, $about_image
 *   $show_contact, $contact_address, $contact_phone, $contact_email, $contact_hours
 *   $footer_description, $total_clients, $total_loans
 *   $meta_desc
 */

$headline_font = 'Manrope';
$body_font = $font_family ?: 'Public Sans';
$p = $palette;

// Ensure defaults
$hero_badge_text = $hero_badge_text ?? '';
$stats_heading = $stats_heading ?? '';
$stats_subheading = $stats_subheading ?? '';
$stats_image = $stats_image ?? '';
$stats = $stats ?? [];
$loan_products = $loan_products ?? [];
$partners = $partners ?? [];
$show_partners = $show_partners ?? false;
$show_stats = $show_stats ?? true;
$show_loan_calc = $show_loan_calc ?? true;
$footer_description = $footer_description ?? '';
$hero_bg_path = $hero_bg_path ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($tenant_name); ?> — Official Website</title>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:wght@400;600;700;800&family=<?php echo urlencode($body_font); ?>:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: <?php echo json_encode($p, JSON_UNESCAPED_SLASHES); ?>,
            fontFamily: {
                "headline": ["<?php echo $e($headline_font); ?>"],
                "body": ["<?php echo $e($body_font); ?>"],
                "label": ["<?php echo $e($body_font); ?>"]
            },
            borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
        },
    },
}
</script>
<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body { font-family: '<?php echo $e($body_font); ?>', sans-serif; }
    h1, h2, h3, h4 { font-family: '<?php echo $e($headline_font); ?>', sans-serif; }
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body class="bg-surface text-on-surface">

<!-- ═══════════════════════════════════════════════════════════
     TOP NAVIGATION BAR
     ═══════════════════════════════════════════════════════════ -->
<nav class="sticky top-0 w-full z-50 bg-surface/80 backdrop-blur-xl shadow-sm">
    <div class="flex justify-between items-center h-16 px-6 md:px-12 max-w-7xl mx-auto">
        <div class="text-xl font-bold text-primary font-headline tracking-tight flex items-center gap-2">
            <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-8 w-8 rounded-lg object-cover"><?php endif; ?>
            <?php echo $e($tenant_name); ?>
        </div>
        <div class="hidden md:flex items-center space-x-8 font-headline font-semibold tracking-tight">
            <?php if ($show_services && !empty($services)): ?>
            <a class="text-primary border-b-2 border-secondary pb-1" href="#services">Services</a>
            <?php endif; ?>
            <?php if ($show_about): ?>
            <a class="text-on-surface-variant hover:text-primary transition-colors" href="#about">About Us</a>
            <?php endif; ?>
            <?php if ($show_loan_calc && !empty($loan_products)): ?>
            <a class="text-on-surface-variant hover:text-primary transition-colors" href="#loan-calculator">Calculator</a>
            <?php endif; ?>
            <?php if ($show_contact): ?>
            <a class="text-on-surface-variant hover:text-primary transition-colors" href="#contact">Contact</a>
            <?php endif; ?>
        </div>
        <a href="<?php echo $e($hero_cta_url); ?>" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-5 py-2 rounded-md font-semibold active:scale-95 duration-150 transition-all no-underline">
            <?php echo $e($hero_cta_text); ?>
        </a>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════ -->
<header class="relative overflow-hidden pt-20 pb-32 px-6 md:px-12 max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7">
            <?php if ($hero_badge_text): ?>
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-secondary-fixed text-on-secondary-fixed text-xs font-bold mb-6 tracking-wider uppercase">
                <?php echo $e($hero_badge_text); ?>
            </div>
            <?php endif; ?>
            <h1 class="text-5xl md:text-6xl font-extrabold text-primary leading-[1.1] mb-8 tracking-tight">
                <?php echo nl2br($e($hero_title)); ?>
            </h1>
            <?php if ($hero_desc): ?>
            <p class="text-lg text-on-surface-variant max-w-xl mb-10 leading-relaxed">
                <?php echo $e($hero_desc); ?>
            </p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-4">
                <a href="<?php echo $e($hero_cta_url); ?>" class="bg-primary text-on-primary px-8 py-4 rounded-md font-bold text-lg shadow-md hover:shadow-lg transition-shadow no-underline">
                    <?php echo $e($hero_cta_text); ?>
                </a>
                <?php if ($show_loan_calc && !empty($loan_products)): ?>
                <a href="#loan-calculator" class="px-8 py-4 rounded-md font-bold text-lg text-primary border border-outline-variant hover:bg-surface-container-low transition-colors no-underline">
                    Calculate Interest
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="lg:col-span-5 relative">
            <div class="bg-surface-container-highest rounded-xl aspect-[4/5] overflow-hidden relative shadow-2xl">
                <?php if ($hero_image): ?>
                <img alt="<?php echo $e($tenant_name); ?>" class="w-full h-full object-cover grayscale-[20%] hover:grayscale-0 transition-all duration-700" src="<?php echo $e($hero_image); ?>"/>
                <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary/20" style="font-size: 120px;">account_balance</span>
                </div>
                <?php endif; ?>
                <div class="absolute bottom-6 left-6 right-6 bg-white/90 backdrop-blur p-6 rounded-lg shadow-xl">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-secondary flex items-center justify-center text-white">
                            <span class="material-symbols-outlined">verified_user</span>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-primary"><?php echo number_format($total_clients); ?>+ Members</div>
                            <div class="text-xs text-on-surface-variant"><?php echo number_format($total_loans); ?> Loans Funded</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<?php if ($show_partners && !empty($partners)): ?>
<!-- ═══════════════════════════════════════════════════════════
     PARTNERS STRIP
     ═══════════════════════════════════════════════════════════ -->
<section class="bg-surface-container-low py-12">
    <div class="max-w-7xl mx-auto px-6 md:px-12">
        <p class="text-center text-sm font-bold uppercase tracking-[0.2em] text-secondary mb-10">Strategic Partners</p>
        <div class="flex flex-wrap justify-center items-center gap-12 opacity-60">
            <?php foreach ($partners as $partner_url): ?>
            <img alt="Partner Logo" class="h-8 md:h-10 object-contain grayscale" src="<?php echo $e($partner_url); ?>"/>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($show_services && !empty($services)): ?>
<!-- ═══════════════════════════════════════════════════════════
     SERVICES GRID
     ═══════════════════════════════════════════════════════════ -->
<section id="services" class="py-24 px-6 md:px-12 max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
        <div class="max-w-2xl">
            <span class="text-secondary font-bold text-sm tracking-widest uppercase mb-4 block"><?php echo $e($services_heading); ?></span>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-1">
        <?php foreach ($services as $i => $svc):
            $bg_class = ($i % 2 === 0) ? 'bg-surface-container' : 'bg-surface-container-high';
        ?>
        <div class="<?php echo $bg_class; ?> p-10 group hover:bg-primary transition-colors duration-500">
            <span class="material-symbols-outlined text-4xl text-secondary group-hover:text-secondary-container mb-8"><?php echo $e($svc['icon'] ?? 'star'); ?></span>
            <h3 class="text-2xl font-bold text-primary group-hover:text-white mb-4"><?php echo $e($svc['title'] ?? ''); ?></h3>
            <p class="text-on-surface-variant group-hover:text-primary-fixed leading-relaxed mb-8">
                <?php echo $e($svc['description'] ?? ''); ?>
            </p>
            <div class="h-1 w-12 bg-secondary transition-all group-hover:w-full group-hover:bg-secondary-container"></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($show_stats && !empty($stats)): ?>
<!-- ═══════════════════════════════════════════════════════════
     TRUST STATS SECTION
     ═══════════════════════════════════════════════════════════ -->
<section id="about" class="pb-24 px-6 md:px-12 max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 items-center bg-primary rounded-xl overflow-hidden shadow-2xl">
        <div class="lg:col-span-5 aspect-square lg:aspect-auto h-full">
            <?php if ($stats_image): ?>
            <img alt="<?php echo $e($tenant_name); ?> team" class="w-full h-full object-cover" src="<?php echo $e($stats_image); ?>"/>
            <?php else: ?>
            <div class="w-full h-full min-h-[300px] bg-gradient-to-br from-primary-container to-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-on-primary/20" style="font-size: 100px;">groups</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="lg:col-span-7 p-10 md:p-16 lg:pl-0">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-8 tracking-tight"><?php echo $e($stats_heading); ?></h2>
            <?php if ($stats_subheading): ?>
            <p class="text-primary-fixed/80 mb-8"><?php echo $e($stats_subheading); ?></p>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-10">
                <?php foreach (array_slice($stats, 0, 4) as $stat): ?>
                <div>
                    <div class="text-4xl font-extrabold text-secondary-fixed mb-2"><?php echo $e($stat['value'] ?? ''); ?></div>
                    <div class="text-primary-fixed text-sm uppercase font-bold tracking-widest"><?php echo $e($stat['label'] ?? ''); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($show_loan_calc && !empty($loan_products)): ?>
<!-- ═══════════════════════════════════════════════════════════
     LOAN CALCULATOR (Real data from loan_products table)
     ═══════════════════════════════════════════════════════════ -->
<section id="loan-calculator" class="bg-surface-container-low py-24 px-6 md:px-12">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <span class="text-secondary font-bold text-sm tracking-widest uppercase mb-4 block">Loan Calculator</span>
            <h2 class="text-4xl font-extrabold text-primary tracking-tight">Estimate Your Monthly Payment</h2>
            <p class="text-on-surface-variant mt-4 max-w-lg mx-auto">Choose a loan product and adjust the amount and term to see your estimated monthly payment.</p>
        </div>
        <div class="bg-surface-container-lowest p-8 md:p-12 rounded-xl shadow-sm">
            <!-- Product Selector -->
            <div class="mb-8">
                <label class="block text-sm font-bold text-primary mb-3 uppercase tracking-wider">Select Loan Product</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" id="lc-product-list">
                    <?php foreach ($loan_products as $i => $prod): ?>
                    <button type="button"
                        class="lc-product-btn text-left p-4 rounded-lg border-2 transition-all duration-200 <?php echo $i === 0 ? 'border-primary bg-primary-fixed/30' : 'border-outline-variant/30 hover:border-primary/50'; ?>"
                        data-index="<?php echo $i; ?>"
                    >
                        <div class="flex items-center gap-3 mb-1">
                            <span class="material-symbols-outlined text-secondary text-xl">
                                <?php
                                $icon_map = ['Personal Loan' => 'person', 'Business Loan' => 'store', 'Emergency Loan' => 'emergency'];
                                echo $icon_map[$prod['product_type']] ?? 'payments';
                                ?>
                            </span>
                            <span class="font-bold text-primary text-sm"><?php echo $e($prod['product_name']); ?></span>
                        </div>
                        <div class="text-xs text-on-surface-variant"><?php echo $e($prod['interest_type']); ?> · <?php echo number_format($prod['interest_rate'], 1); ?>% / mo</div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Amount Slider -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-sm font-bold text-primary uppercase tracking-wider">Loan Amount</label>
                    <span id="lc-amount-display" class="text-2xl font-extrabold text-primary font-headline">₱0</span>
                </div>
                <input type="range" id="lc-amount-slider" class="w-full h-2 bg-surface-variant rounded-lg appearance-none cursor-pointer accent-primary" min="0" max="100000" step="1000" value="0"/>
                <div class="flex justify-between text-xs text-on-surface-variant mt-2">
                    <span id="lc-min-amount">₱0</span>
                    <span id="lc-max-amount">₱0</span>
                </div>
            </div>

            <!-- Term Slider -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-sm font-bold text-primary uppercase tracking-wider">Loan Term</label>
                    <span id="lc-term-display" class="text-2xl font-extrabold text-primary font-headline">0 months</span>
                </div>
                <input type="range" id="lc-term-slider" class="w-full h-2 bg-surface-variant rounded-lg appearance-none cursor-pointer accent-primary" min="1" max="36" step="1" value="1"/>
                <div class="flex justify-between text-xs text-on-surface-variant mt-2">
                    <span id="lc-min-term">1 mo</span>
                    <span id="lc-max-term">36 mo</span>
                </div>
            </div>

            <!-- Results -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-8 border-t border-outline-variant/20">
                <div class="bg-surface-container p-4 rounded-lg text-center">
                    <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Monthly Payment</div>
                    <div id="lc-monthly" class="text-2xl font-extrabold text-primary font-headline">₱0</div>
                </div>
                <div class="bg-surface-container p-4 rounded-lg text-center">
                    <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Total Interest</div>
                    <div id="lc-interest" class="text-2xl font-extrabold text-secondary font-headline">₱0</div>
                </div>
                <div class="bg-surface-container p-4 rounded-lg text-center">
                    <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Processing Fee</div>
                    <div id="lc-fee" class="text-2xl font-extrabold text-on-surface-variant font-headline">₱0</div>
                </div>
                <div class="bg-surface-container p-4 rounded-lg text-center">
                    <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Total Repayment</div>
                    <div id="lc-total" class="text-2xl font-extrabold text-primary font-headline">₱0</div>
                </div>
            </div>

            <p class="text-xs text-on-surface-variant mt-6 text-center">* This is an estimate only. Actual amounts may vary based on approval and applicable fees.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($show_about && $about_body): ?>
<!-- ═══════════════════════════════════════════════════════════
     ABOUT SECTION
     ═══════════════════════════════════════════════════════════ -->
<section <?php echo (!$show_stats) ? 'id="about"' : ''; ?> class="py-24 px-6 md:px-12 max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row gap-12 items-center">
        <div class="w-full md:w-1/2 relative">
            <?php if ($about_image): ?>
            <div class="relative z-10 aspect-[4/5] rounded-3xl overflow-hidden shadow-2xl">
                <img alt="About <?php echo $e($tenant_name); ?>" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
            </div>
            <?php else: ?>
            <div class="relative z-10 aspect-[4/5] rounded-3xl overflow-hidden shadow-2xl bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary/20" style="font-size: 80px;">apartment</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="w-full md:w-1/2">
            <span class="text-secondary font-bold text-sm tracking-widest uppercase mb-4 block"><?php echo $e($about_heading); ?></span>
            <p class="text-xl md:text-2xl font-headline font-bold text-primary mb-8 leading-snug">
                <?php echo nl2br($e($about_body)); ?>
            </p>
            <div class="flex items-center gap-6 mt-8">
                <div class="text-center">
                    <p class="text-3xl font-bold text-primary font-headline"><?php echo number_format($total_clients); ?>+</p>
                    <p class="text-xs font-bold text-on-surface-variant uppercase">Active Members</p>
                </div>
                <div class="h-10 w-[1px] bg-outline-variant/30"></div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-secondary font-headline"><?php echo number_format($total_loans); ?>+</p>
                    <p class="text-xs font-bold text-on-surface-variant uppercase">Loans Funded</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════════════════ -->
<footer id="contact" class="bg-surface border-t border-outline-variant/20">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 py-12 px-6 md:px-12 max-w-7xl mx-auto">
        <div>
            <div class="text-lg font-bold text-primary mb-6 flex items-center gap-2">
                <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-6 w-6 rounded object-cover"><?php endif; ?>
                <?php echo $e($tenant_name); ?>
            </div>
            <p class="text-sm text-on-surface-variant max-w-xs">
                <?php echo $e($footer_description); ?>
            </p>
        </div>

        <?php if ($show_services && !empty($services)): ?>
        <div class="flex flex-col space-y-3 text-sm">
            <span class="font-bold text-primary mb-2"><?php echo $e($services_heading); ?></span>
            <?php foreach (array_slice($services, 0, 4) as $svc): ?>
            <a class="text-on-surface-variant hover:text-primary transition-all duration-300" href="#services"><?php echo $e($svc['title'] ?? ''); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex flex-col space-y-3 text-sm">
            <span class="font-bold text-primary mb-2">Quick Links</span>
            <?php if ($show_about): ?><a class="text-on-surface-variant hover:text-primary transition-all duration-300" href="#about">About Us</a><?php endif; ?>
            <?php if ($show_services): ?><a class="text-on-surface-variant hover:text-primary transition-all duration-300" href="#services">Services</a><?php endif; ?>
            <?php if ($show_loan_calc && !empty($loan_products)): ?><a class="text-on-surface-variant hover:text-primary transition-all duration-300" href="#loan-calculator">Loan Calculator</a><?php endif; ?>
        </div>

        <?php if ($show_contact): ?>
        <div class="flex flex-col space-y-3 text-sm">
            <span class="font-bold text-primary mb-2">Contact</span>
            <?php if ($contact_address): ?><p class="text-on-surface-variant"><?php echo nl2br($e($contact_address)); ?></p><?php endif; ?>
            <?php if ($contact_phone): ?><p class="text-on-surface-variant"><?php echo $e($contact_phone); ?></p><?php endif; ?>
            <?php if ($contact_email): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="mailto:<?php echo $e($contact_email); ?>"><?php echo $e($contact_email); ?></a><?php endif; ?>
            <?php if ($contact_hours): ?><p class="text-on-surface-variant"><?php echo $e($contact_hours); ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="max-w-7xl mx-auto px-6 md:px-12 py-8 border-t border-outline-variant/10">
        <div class="text-sm text-on-surface-variant text-center">
            &copy; <?php echo date('Y'); ?> <?php echo $e($tenant_name); ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- ═══════════════════════════════════════════════════════════
     MOBILE BOTTOM NAV
     ═══════════════════════════════════════════════════════════ -->
<nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-4 pt-2 md:hidden bg-white/70 backdrop-blur-xl border-t border-outline-variant/15 shadow-[0_-4px_24px_rgba(0,0,0,0.06)]">
    <a href="#" class="flex flex-col items-center justify-center bg-secondary-container text-secondary rounded-2xl px-4 py-1 no-underline">
        <span class="material-symbols-outlined">home</span>
        <span class="text-[10px] uppercase tracking-widest font-bold mt-1">Home</span>
    </a>
    <?php if ($show_services && !empty($services)): ?>
    <a href="#services" class="flex flex-col items-center justify-center text-on-surface-variant opacity-80 hover:opacity-100 no-underline">
        <span class="material-symbols-outlined">widgets</span>
        <span class="text-[10px] uppercase tracking-widest font-bold mt-1">Services</span>
    </a>
    <?php endif; ?>
    <?php if ($show_loan_calc && !empty($loan_products)): ?>
    <a href="#loan-calculator" class="flex flex-col items-center justify-center text-on-surface-variant opacity-80 hover:opacity-100 no-underline">
        <span class="material-symbols-outlined">calculate</span>
        <span class="text-[10px] uppercase tracking-widest font-bold mt-1">Calculator</span>
    </a>
    <?php endif; ?>
    <?php if ($show_contact): ?>
    <a href="#contact" class="flex flex-col items-center justify-center text-on-surface-variant opacity-80 hover:opacity-100 no-underline">
        <span class="material-symbols-outlined">call</span>
        <span class="text-[10px] uppercase tracking-widest font-bold mt-1">Contact</span>
    </a>
    <?php endif; ?>
</nav>

<?php if ($show_loan_calc && !empty($loan_products)): ?>
<!-- ═══════════════════════════════════════════════════════════
     LOAN CALCULATOR JAVASCRIPT
     ═══════════════════════════════════════════════════════════ -->
<script>
(function() {
    var products = <?php echo json_encode(array_map(function($p) {
        return [
            'name'       => $p['product_name'],
            'min'        => (float)$p['min_amount'],
            'max'        => (float)$p['max_amount'],
            'rate'       => (float)$p['interest_rate'],
            'type'       => $p['interest_type'],
            'minTerm'    => (int)$p['min_term_months'],
            'maxTerm'    => (int)$p['max_term_months'],
            'processing' => (float)$p['processing_fee_percentage'],
        ];
    }, $loan_products), JSON_UNESCAPED_UNICODE); ?>;

    var amountSlider = document.getElementById('lc-amount-slider');
    var termSlider   = document.getElementById('lc-term-slider');
    var btns         = document.querySelectorAll('.lc-product-btn');
    var selected     = 0;
    var calcScheduled = false;
    var raf = window.requestAnimationFrame || function(cb) { return setTimeout(cb, 16); };
    var pesoFormatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

    function fmt(n) {
        return '₱' + pesoFormatter.format(Number(n));
    }

    function getAmountStep(min, max) {
        var range = Math.max(0, max - min);
        if (range <= 20000) return 50;
        if (range <= 100000) return 100;
        if (range <= 500000) return 500;
        return 1000;
    }

    function requestCalculate() {
        if (calcScheduled) return;
        calcScheduled = true;
        raf(function() {
            calcScheduled = false;
            calculate();
        });
    }

    function selectProduct(idx) {
        selected = idx;
        var p = products[idx];
        btns.forEach(function(b, i) {
            if (i === idx) {
                b.classList.add('border-primary');
                b.classList.remove('border-outline-variant/30');
            } else {
                b.classList.remove('border-primary');
                b.classList.add('border-outline-variant/30');
            }
        });

        amountSlider.min = p.min;
        amountSlider.max = p.max;
    amountSlider.step = getAmountStep(p.min, p.max);
    var mid = (p.min + p.max) / 2;
    var alignedMid = Math.round(mid / amountSlider.step) * amountSlider.step;
    amountSlider.value = Math.min(p.max, Math.max(p.min, alignedMid));

        termSlider.min = p.minTerm;
        termSlider.max = p.maxTerm;
        termSlider.value = Math.round((p.minTerm + p.maxTerm) / 2);

        document.getElementById('lc-min-amount').textContent = fmt(p.min);
        document.getElementById('lc-max-amount').textContent = fmt(p.max);
        document.getElementById('lc-min-term').textContent = p.minTerm + ' mo';
        document.getElementById('lc-max-term').textContent = p.maxTerm + ' mo';

        calculate();
    }

    function calculate() {
        var p = products[selected];
        var amount = parseFloat(amountSlider.value);
        var term   = parseInt(termSlider.value, 10);
        var rate   = p.rate / 100;

        document.getElementById('lc-amount-display').textContent = fmt(amount);
        document.getElementById('lc-term-display').textContent = term + (term === 1 ? ' month' : ' months');

        var monthly = 0, totalInterest = 0, totalRepay = 0;

        if (p.type === 'Flat' || p.type === 'Fixed') {
            totalInterest = amount * rate * term;
            totalRepay = amount + totalInterest;
            monthly = totalRepay / term;
        } else if (p.type === 'Diminishing') {
            if (rate > 0) {
                monthly = amount * (rate * Math.pow(1 + rate, term)) / (Math.pow(1 + rate, term) - 1);
            } else {
                monthly = amount / term;
            }
            totalRepay = monthly * term;
            totalInterest = totalRepay - amount;
        }

        var processingFee = amount * (p.processing / 100);

        document.getElementById('lc-monthly').textContent = fmt(Math.round(monthly));
        document.getElementById('lc-interest').textContent = fmt(Math.round(totalInterest));
        document.getElementById('lc-fee').textContent = fmt(Math.round(processingFee));
        document.getElementById('lc-total').textContent = fmt(Math.round(totalRepay));
    }

    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            selectProduct(parseInt(this.getAttribute('data-index')));
        });
    });

    amountSlider.addEventListener('input', requestCalculate);
    termSlider.addEventListener('input', requestCalculate);

    if (products.length > 0) selectProduct(0);
})();
</script>
<?php endif; ?>

</body>
</html>
