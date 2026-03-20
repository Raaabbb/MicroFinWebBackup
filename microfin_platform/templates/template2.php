<?php
/**
 * Template 2 — Editorial / Minimalist (Magazine-style layout)
 * Adapted for MicroFin multi-tenant platform.
 * All variables are passed from site.php via include scope.
 */

$headline_font = 'Newsreader';
$body_font = $font_family ?: 'Manrope';
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
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:ital,wght@0,400;0,700;1,400&family=<?php echo urlencode($body_font); ?>:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: <?php echo json_encode($p, JSON_UNESCAPED_SLASHES); ?>,
            fontFamily: {
                "headline": ["<?php echo $e($headline_font); ?>", "Georgia", "serif"],
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
.editorial-gradient {
    background: linear-gradient(180deg, transparent 0%, <?php echo $e($p['surface']); ?> 100%);
}
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body class="bg-background text-on-background font-body selection:bg-secondary-container selection:text-on-secondary-container">

<!-- Minimal Header -->
<header class="fixed top-0 w-full z-50 bg-background/90 backdrop-blur-xl border-b border-outline-variant/10">
    <div class="container mx-auto flex justify-between items-center px-6 h-14">
        <div class="flex items-center gap-8">
            <span class="font-headline text-lg font-bold text-primary flex items-center gap-2">
                <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-6 w-6 rounded object-cover"><?php endif; ?>
                <?php echo $e($tenant_name); ?>
            </span>
            <nav class="hidden md:flex gap-8 text-sm font-label">
                <a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Home</a>
                <?php if ($show_about): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#about">About</a><?php endif; ?>
                <?php if ($show_services && !empty($services)): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#services">Services</a><?php endif; ?>
                <?php if ($show_contact): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#contact">Contact</a><?php endif; ?>
            </nav>
        </div>
        <a href="<?php echo $e($hero_cta_url); ?>" class="hidden md:inline-flex px-4 py-2 bg-primary text-on-primary text-sm font-bold rounded-sm hover:opacity-90 transition-all no-underline">
            <?php echo $e($hero_cta_text); ?>
        </a>
    </div>
</header>

<main class="pt-14">
    <!-- Hero Statement -->
    <section class="py-24 md:py-32 px-6 border-b border-outline-variant/10">
        <div class="container mx-auto max-w-5xl">
            <div class="grid md:grid-cols-12 gap-12">
                <div class="md:col-span-8">
                    <h1 class="text-4xl md:text-6xl lg:text-7xl font-headline font-bold text-primary leading-[1.08] tracking-tight mb-8">
                        <?php echo nl2br($e($hero_title)); ?>
                    </h1>
                </div>
                <div class="md:col-span-4 flex flex-col justify-end">
                    <?php if ($hero_desc): ?>
                    <p class="text-on-surface-variant text-base leading-relaxed mb-8 font-body">
                        <?php echo $e($hero_desc); ?>
                    </p>
                    <?php endif; ?>
                    <a href="<?php echo $e($hero_cta_url); ?>" class="inline-flex items-center gap-3 text-primary font-bold text-sm group no-underline">
                        <?php echo $e($hero_cta_text); ?>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">east</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Full-Width Narrative Image -->
    <section class="relative">
        <?php if ($hero_image): ?>
        <div class="aspect-[21/9] max-h-[600px] w-full overflow-hidden">
            <img alt="<?php echo $e($tenant_name); ?>" class="w-full h-full object-cover" src="<?php echo $e($hero_image); ?>"/>
        </div>
        <?php else: ?>
        <div class="aspect-[21/9] max-h-[600px] w-full overflow-hidden bg-gradient-to-br from-surface-container-high to-surface-container flex items-center justify-center">
            <span class="material-symbols-outlined text-on-surface-variant/10" style="font-size: 160px;">landscape</span>
        </div>
        <?php endif; ?>
        <div class="absolute bottom-0 left-0 w-full h-1/3 editorial-gradient"></div>
        <?php if ($hero_subtitle): ?>
        <div class="absolute bottom-0 left-0 p-6 md:p-12">
            <div class="bg-primary text-on-primary px-6 py-3 inline-block">
                <span class="text-sm font-bold font-label uppercase tracking-widest"><?php echo $e($hero_subtitle); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($show_services && !empty($services)): ?>
    <!-- Curated Insights (Services) -->
    <section id="services" class="py-24 px-6 border-b border-outline-variant/10">
        <div class="container mx-auto max-w-5xl">
            <div class="flex items-end justify-between mb-16">
                <div>
                    <span class="text-xs font-bold text-secondary uppercase tracking-[0.2em] mb-2 block font-label"><?php echo $e($services_heading); ?></span>
                    <h2 class="text-3xl md:text-4xl font-headline font-bold text-primary">What We Offer</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <?php foreach ($services as $i => $svc): ?>
                <?php
                    // Alternate card sizes for editorial bento layout
                    $span = ($i === 0) ? 'md:col-span-7' : (($i === 1) ? 'md:col-span-5' : 'md:col-span-4');
                    if ($i > 2 && ($i % 3) === 0) $span = 'md:col-span-5';
                    elseif ($i > 2 && ($i % 3) === 1) $span = 'md:col-span-7';
                    elseif ($i > 2) $span = 'md:col-span-4';
                ?>
                <div class="<?php echo $span; ?> bg-surface-container-lowest border border-outline-variant/10 p-8 rounded-lg group hover:border-primary/20 transition-all">
                    <div>
                        <span class="text-xs font-bold font-label text-secondary uppercase tracking-widest"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <h3 class="text-xl font-headline font-bold text-primary mt-4 mb-3"><?php echo $e($svc['title'] ?? ''); ?></h3>
                    <p class="text-on-surface-variant text-sm leading-relaxed"><?php echo $e($svc['description'] ?? ''); ?></p>
                    <div class="mt-6 flex items-center gap-2 text-primary text-sm font-bold group-hover:gap-3 transition-all">
                        <span>Explore</span>
                        <span class="material-symbols-outlined text-sm">east</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_about && $about_body): ?>
    <!-- Pull Quote -->
    <section id="about" class="py-24 px-6 bg-surface-container-low">
        <div class="container mx-auto max-w-4xl text-center">
            <span class="material-symbols-outlined text-primary/10" style="font-size: 64px;">format_quote</span>
            <blockquote class="text-2xl md:text-4xl font-headline font-bold text-primary leading-snug tracking-tight mt-6 mb-8">
                <?php echo nl2br($e($about_body)); ?>
            </blockquote>
            <div class="flex items-center justify-center gap-6 mt-12">
                <?php if ($about_image): ?>
                <div class="h-12 w-12 rounded-full overflow-hidden">
                    <img alt="About" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
                </div>
                <?php endif; ?>
                <div class="text-left">
                    <p class="font-bold text-primary text-sm"><?php echo $e($about_heading); ?></p>
                    <p class="text-xs text-on-surface-variant"><?php echo $e($tenant_name); ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Impact + Stats -->
    <section class="py-24 px-6">
        <div class="container mx-auto max-w-5xl">
            <div class="grid md:grid-cols-3 gap-12 items-center">
                <div class="md:col-span-2">
                    <h2 class="text-3xl md:text-4xl font-headline font-bold text-primary mb-8">Our Impact</h2>
                    <div class="grid grid-cols-2 gap-8">
                        <div class="border-l-2 border-primary pl-6">
                            <p class="text-4xl md:text-5xl font-headline font-bold text-primary"><?php echo number_format($total_clients); ?>+</p>
                            <p class="text-sm text-on-surface-variant mt-2 font-label">Active Members Served</p>
                        </div>
                        <div class="border-l-2 border-secondary pl-6">
                            <p class="text-4xl md:text-5xl font-headline font-bold text-secondary"><?php echo number_format($total_loans); ?>+</p>
                            <p class="text-sm text-on-surface-variant mt-2 font-label">Loans Successfully Funded</p>
                        </div>
                    </div>
                </div>
                <div>
                    <?php if ($about_image): ?>
                    <div class="aspect-square rounded-lg overflow-hidden">
                        <img alt="Impact" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
                    </div>
                    <?php else: ?>
                    <div class="aspect-square rounded-lg overflow-hidden bg-gradient-to-br from-primary/5 to-secondary/5 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary/15" style="font-size: 80px;">trending_up</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_download_section): ?>
    <!-- Download / Subscribe CTA -->
    <section id="download" class="py-24 px-6">
        <div class="container mx-auto max-w-3xl text-center">
            <div class="bg-primary p-16 rounded-lg">
                <h2 class="text-3xl md:text-4xl font-headline font-bold text-on-primary mb-6"><?php echo $e($download_title); ?></h2>
                <?php if ($download_description): ?>
                <p class="text-on-primary/70 mb-10 text-base"><?php echo nl2br($e($download_description)); ?></p>
                <?php endif; ?>
                <a href="<?php echo $e($download_url); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-8 py-4 bg-on-primary text-primary font-bold text-sm rounded-sm hover:opacity-90 transition-all no-underline">
                    <?php echo $e($download_button_text); ?>
                    <span class="material-symbols-outlined text-base">east</span>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Footer -->
<footer id="contact" class="bg-surface-container-lowest border-t border-outline-variant/10 pt-20 pb-12 px-6">
    <div class="container mx-auto max-w-5xl">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
            <div class="col-span-2 md:col-span-1">
                <span class="font-headline text-lg font-bold text-primary block mb-6"><?php echo $e($tenant_name); ?></span>
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
                    <?php if ($show_services): ?><li><a class="hover:text-primary transition-colors" href="#services">Services</a></li><?php endif; ?>
                    <?php if ($show_contact): ?><li><a class="hover:text-primary transition-colors" href="#contact">Contact</a></li><?php endif; ?>
                </ul>
            </div>
            <?php if ($show_contact): ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label">Get in Touch</h5>
                <ul class="space-y-3 text-sm text-on-surface-variant list-none p-0">
                    <?php if ($contact_phone): ?><li><?php echo $e($contact_phone); ?></li><?php endif; ?>
                    <?php if ($contact_email): ?><li><a class="hover:text-primary transition-colors" href="mailto:<?php echo $e($contact_email); ?>"><?php echo $e($contact_email); ?></a></li><?php endif; ?>
                    <?php if ($contact_hours): ?><li><?php echo $e($contact_hours); ?></li><?php endif; ?>
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
