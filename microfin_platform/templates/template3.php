<?php
/**
 * Template 3 — Playful / Energetic (Bento cards, glassmorphism, bold type)
 * Adapted for MicroFin multi-tenant platform.
 * All variables are passed from site.php via include scope.
 */

$headline_font = 'Plus Jakarta Sans';
$body_font = $font_family ?: 'Be Vietnam Pro';
$p = $palette;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($tenant_name); ?> — Official Website</title>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:wght@400;500;600;700;800&family=<?php echo urlencode($body_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: <?php echo json_encode($p, JSON_UNESCAPED_SLASHES); ?>,
            fontFamily: {
                "headline": ["<?php echo $e($headline_font); ?>", "sans-serif"],
                "body": ["<?php echo $e($body_font); ?>", "sans-serif"],
                "label": ["<?php echo $e($body_font); ?>", "sans-serif"]
            },
            borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
        },
    },
}
</script>
<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
.glass-card {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
}
.gradient-mesh {
    background: radial-gradient(at 20% 80%, <?php echo $e($p['primary']); ?>20 0%, transparent 50%),
                radial-gradient(at 80% 20%, <?php echo $e($p['secondary']); ?>20 0%, transparent 50%),
                <?php echo $e($p['surface']); ?>;
}
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body class="bg-background text-on-background font-body selection:bg-secondary-container selection:text-on-secondary-container">

<!-- Top Nav -->
<header class="fixed top-0 w-full z-50 bg-background/80 backdrop-blur-2xl">
    <div class="container mx-auto flex justify-between items-center px-6 h-16">
        <div class="flex items-center gap-8">
            <span class="text-xl font-extrabold text-primary font-headline tracking-tighter flex items-center gap-2">
                <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-7 w-7 rounded-lg object-cover"><?php endif; ?>
                <?php echo $e($tenant_name); ?>
            </span>
            <nav class="hidden md:flex gap-6 text-sm font-label">
                <a class="text-primary font-bold" href="#">Home</a>
                <?php if ($show_about): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#about">About</a><?php endif; ?>
                <?php if ($show_services && !empty($services)): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#features">Features</a><?php endif; ?>
                <?php if ($show_contact): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#contact">Contact</a><?php endif; ?>
            </nav>
        </div>
        <a href="<?php echo $e($hero_cta_url); ?>" class="px-5 py-2.5 bg-primary text-on-primary text-sm font-bold rounded-xl hover:scale-105 transition-all no-underline flex items-center gap-2">
            <?php echo $e($hero_cta_text); ?>
            <span class="material-symbols-outlined text-base">bolt</span>
        </a>
    </div>
</header>

<main class="pt-16">
    <!-- Hero Section -->
    <section class="relative min-h-[650px] flex items-center px-6 py-20 overflow-hidden gradient-mesh">
        <div class="container mx-auto grid md:grid-cols-2 gap-12 items-center z-10">
            <div>
                <?php if ($hero_subtitle): ?>
                <span class="inline-flex items-center gap-2 px-4 py-2 mb-8 text-sm font-bold text-primary bg-primary/10 rounded-full font-label">
                    <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">rocket_launch</span>
                    <?php echo $e($hero_subtitle); ?>
                </span>
                <?php endif; ?>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold font-headline text-primary leading-[1.1] mb-6 tracking-tight">
                    <?php echo nl2br($e($hero_title)); ?>
                </h1>
                <?php if ($hero_desc): ?>
                <p class="text-lg text-on-surface-variant mb-10 max-w-lg leading-relaxed font-body">
                    <?php echo $e($hero_desc); ?>
                </p>
                <?php endif; ?>
                <div class="flex flex-wrap gap-4 items-center">
                    <a href="<?php echo $e($hero_cta_url); ?>" class="px-8 py-4 bg-primary text-on-primary font-bold rounded-xl hover:scale-105 transition-all flex items-center gap-2 no-underline">
                        <?php echo $e($hero_cta_text); ?>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                    <?php if ($show_about): ?>
                    <a href="#about" class="px-8 py-4 text-primary font-bold rounded-xl border border-outline-variant/30 hover:bg-surface-container transition-all no-underline">
                        Learn More
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="relative hidden md:block">
                <?php if ($hero_image): ?>
                <div class="aspect-[4/3] rounded-3xl overflow-hidden shadow-2xl ring-1 ring-outline-variant/10">
                    <img alt="<?php echo $e($tenant_name); ?>" class="w-full h-full object-cover" src="<?php echo $e($hero_image); ?>"/>
                </div>
                <?php else: ?>
                <div class="aspect-[4/3] rounded-3xl overflow-hidden shadow-2xl ring-1 ring-outline-variant/10 bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary/15" style="font-size: 120px;">speed</span>
                </div>
                <?php endif; ?>
                <!-- Momentum Stats Card -->
                <div class="absolute -bottom-6 -left-6 glass-card p-4 rounded-2xl shadow-xl max-w-[200px]">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-secondary/10 rounded-xl">
                            <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">trending_up</span>
                        </div>
                        <span class="text-xs font-bold text-on-surface-variant font-label uppercase tracking-wider">Growth</span>
                    </div>
                    <p class="text-2xl font-extrabold text-primary font-headline"><?php echo number_format($total_clients); ?>+</p>
                    <p class="text-xs text-on-surface-variant">Active members</p>
                </div>
                <!-- Secondary Floating Card -->
                <div class="absolute -top-4 -right-4 glass-card p-4 rounded-2xl shadow-xl max-w-[180px]">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-secondary text-base" style="font-variation-settings: 'FILL' 1;">verified</span>
                        <span class="text-xs font-bold text-primary font-label"><?php echo number_format($total_loans); ?>+</span>
                    </div>
                    <p class="text-xs text-on-surface-variant">Loans funded</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_services && !empty($services)): ?>
    <!-- Bento Features Grid -->
    <section id="features" class="py-24 px-6 bg-surface-container-low">
        <div class="container mx-auto">
            <div class="text-center max-w-xl mx-auto mb-16">
                <span class="text-xs font-bold text-secondary uppercase tracking-[0.2em] mb-3 block font-label"><?php echo $e($services_heading); ?></span>
                <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-primary">Everything You Need</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($services as $i => $svc): ?>
                <?php
                    $icon = $svc['icon'] ?? 'star';
                    $colors = [
                        ['bg-primary/5', 'text-primary', 'border-primary/10'],
                        ['bg-secondary/5', 'text-secondary', 'border-secondary/10'],
                        ['bg-tertiary/5', 'text-tertiary', 'border-tertiary/10'],
                    ];
                    $c = $colors[$i % 3];
                ?>
                <div class="relative group <?php echo ($i === 0) ? 'lg:col-span-2 lg:row-span-2' : ''; ?>">
                    <div class="h-full <?php echo $c[0]; ?> border <?php echo $c[2]; ?> rounded-3xl p-8 hover:shadow-lg transition-all">
                        <div class="w-12 h-12 <?php echo $c[0]; ?> <?php echo $c[1]; ?> flex items-center justify-center rounded-2xl mb-6">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;"><?php echo $e($icon); ?></span>
                        </div>
                        <h3 class="text-xl font-bold font-headline <?php echo $c[1]; ?> mb-3"><?php echo $e($svc['title'] ?? ''); ?></h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed"><?php echo $e($svc['description'] ?? ''); ?></p>
                        <?php if ($i === 0): ?>
                        <!-- Highlight first card with extra stats -->
                        <div class="flex items-center gap-6 mt-8 pt-6 border-t <?php echo $c[2]; ?>">
                            <div>
                                <p class="text-2xl font-bold font-headline <?php echo $c[1]; ?>"><?php echo number_format($total_clients); ?>+</p>
                                <p class="text-xs text-on-surface-variant">Members</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold font-headline <?php echo $c[1]; ?>"><?php echo number_format($total_loans); ?>+</p>
                                <p class="text-xs text-on-surface-variant">Loans</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 3-Step Journey -->
    <section class="py-24 px-6">
        <div class="container mx-auto max-w-4xl text-center">
            <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-primary mb-4">Your Journey Starts Here</h2>
            <p class="text-on-surface-variant mb-16 max-w-lg mx-auto">Get started in three easy steps — from application to funding, we've made it simple.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="relative p-8 bg-surface-container-low rounded-3xl group hover:bg-primary hover:text-on-primary transition-all">
                    <span class="text-6xl font-extrabold text-primary/10 group-hover:text-white/10 block mb-4 font-headline">01</span>
                    <div class="w-14 h-14 bg-primary/10 group-hover:bg-white/20 text-primary group-hover:text-white flex items-center justify-center rounded-2xl mb-6 mx-auto">
                        <span class="material-symbols-outlined">edit_note</span>
                    </div>
                    <h3 class="text-lg font-bold font-headline mb-3 text-primary group-hover:text-white">Apply Online</h3>
                    <p class="text-sm text-on-surface-variant group-hover:text-white/70 leading-relaxed">Quick and simple application from any device.</p>
                </div>
                <div class="relative p-8 bg-surface-container-low rounded-3xl group hover:bg-primary hover:text-on-primary transition-all">
                    <span class="text-6xl font-extrabold text-primary/10 group-hover:text-white/10 block mb-4 font-headline">02</span>
                    <div class="w-14 h-14 bg-primary/10 group-hover:bg-white/20 text-primary group-hover:text-white flex items-center justify-center rounded-2xl mb-6 mx-auto">
                        <span class="material-symbols-outlined">verified_user</span>
                    </div>
                    <h3 class="text-lg font-bold font-headline mb-3 text-primary group-hover:text-white">Get Approved</h3>
                    <p class="text-sm text-on-surface-variant group-hover:text-white/70 leading-relaxed">Our review focuses on your potential, not just collateral.</p>
                </div>
                <div class="relative p-8 bg-surface-container-low rounded-3xl group hover:bg-primary hover:text-on-primary transition-all">
                    <span class="text-6xl font-extrabold text-primary/10 group-hover:text-white/10 block mb-4 font-headline">03</span>
                    <div class="w-14 h-14 bg-primary/10 group-hover:bg-white/20 text-primary group-hover:text-white flex items-center justify-center rounded-2xl mb-6 mx-auto">
                        <span class="material-symbols-outlined">account_balance_wallet</span>
                    </div>
                    <h3 class="text-lg font-bold font-headline mb-3 text-primary group-hover:text-white">Receive Funds</h3>
                    <p class="text-sm text-on-surface-variant group-hover:text-white/70 leading-relaxed">Money goes straight to your account. Start growing.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_about): ?>
    <!-- About Section -->
    <section id="about" class="py-24 px-6 bg-surface-container-low">
        <div class="container mx-auto max-w-5xl">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div>
                    <?php if ($about_image): ?>
                    <div class="aspect-[4/5] rounded-3xl overflow-hidden shadow-2xl ring-1 ring-outline-variant/10">
                        <img alt="About" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
                    </div>
                    <?php else: ?>
                    <div class="aspect-[4/5] rounded-3xl overflow-hidden shadow-2xl bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary/15" style="font-size: 80px;">people</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-xs font-bold text-secondary uppercase tracking-[0.2em] mb-3 block font-label"><?php echo $e($about_heading); ?></span>
                    <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-primary mb-8 leading-tight"><?php echo $e($about_heading); ?></h2>
                    <?php if ($about_body): ?>
                    <p class="text-on-surface-variant leading-relaxed text-lg mb-8"><?php echo nl2br($e($about_body)); ?></p>
                    <?php endif; ?>
                    <div class="flex gap-8">
                        <div>
                            <p class="text-3xl font-extrabold text-primary font-headline"><?php echo number_format($total_clients); ?>+</p>
                            <p class="text-xs text-on-surface-variant font-label">Members</p>
                        </div>
                        <div>
                            <p class="text-3xl font-extrabold text-secondary font-headline"><?php echo number_format($total_loans); ?>+</p>
                            <p class="text-xs text-on-surface-variant font-label">Loans Funded</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_download_section): ?>
    <!-- CTA / Download Section -->
    <section id="download" class="py-24 px-6">
        <div class="container mx-auto max-w-4xl">
            <div class="glass-card rounded-[2rem] p-12 md:p-16 text-center relative overflow-hidden border border-primary/10">
                <div class="absolute inset-0 gradient-mesh opacity-50 -z-10"></div>
                <span class="material-symbols-outlined text-primary mb-6" style="font-size: 48px; font-variation-settings: 'FILL' 1;">download</span>
                <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-primary mb-6"><?php echo $e($download_title); ?></h2>
                <?php if ($download_description): ?>
                <p class="text-on-surface-variant text-lg mb-10 max-w-lg mx-auto"><?php echo nl2br($e($download_description)); ?></p>
                <?php endif; ?>
                <a href="<?php echo $e($download_url); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-10 py-5 bg-primary text-on-primary font-bold rounded-xl hover:scale-105 transition-all no-underline">
                    <?php echo $e($download_button_text); ?>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Footer -->
<footer id="contact" class="bg-surface-container-lowest border-t border-outline-variant/10 pt-20 pb-12 px-6">
    <div class="container mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
            <div class="col-span-2 md:col-span-1">
                <span class="text-xl font-extrabold text-primary font-headline tracking-tighter block mb-4"><?php echo $e($tenant_name); ?></span>
                <?php if ($contact_address): ?>
                <p class="text-sm text-on-surface-variant leading-relaxed"><?php echo nl2br($e($contact_address)); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($show_services && !empty($services)): ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label"><?php echo $e($services_heading); ?></h5>
                <ul class="space-y-3 text-sm text-on-surface-variant list-none p-0">
                    <?php foreach (array_slice($services, 0, 4) as $svc): ?>
                    <li><?php echo $e($svc['title'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label">Navigation</h5>
                <ul class="space-y-3 text-sm text-on-surface-variant list-none p-0">
                    <li><a class="hover:text-primary transition-colors" href="#">Home</a></li>
                    <?php if ($show_about): ?><li><a class="hover:text-primary transition-colors" href="#about">About</a></li><?php endif; ?>
                    <?php if ($show_services): ?><li><a class="hover:text-primary transition-colors" href="#features">Features</a></li><?php endif; ?>
                    <?php if ($show_contact): ?><li><a class="hover:text-primary transition-colors" href="#contact">Contact</a></li><?php endif; ?>
                </ul>
            </div>
            <?php if ($show_contact): ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label">Contact</h5>
                <ul class="space-y-3 text-sm text-on-surface-variant list-none p-0">
                    <?php if ($contact_phone): ?><li class="flex items-center gap-2"><span class="material-symbols-outlined text-base">call</span><?php echo $e($contact_phone); ?></li><?php endif; ?>
                    <?php if ($contact_email): ?><li class="flex items-center gap-2"><span class="material-symbols-outlined text-base">mail</span><a class="hover:text-primary transition-colors" href="mailto:<?php echo $e($contact_email); ?>"><?php echo $e($contact_email); ?></a></li><?php endif; ?>
                    <?php if ($contact_hours): ?><li class="flex items-center gap-2"><span class="material-symbols-outlined text-base">schedule</span><?php echo $e($contact_hours); ?></li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="pt-8 border-t border-outline-variant/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs text-on-surface-variant">&copy; <?php echo date('Y'); ?> <?php echo $e($tenant_name); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>
