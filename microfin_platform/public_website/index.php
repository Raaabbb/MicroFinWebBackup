<?php
require_once '../backend/db_connect.php';

// Fetch active tenants to display in the "Trusted By" section
$stmt = $pdo->query("SELECT tenant_name FROM tenants WHERE status = 'Active' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
$active_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$tenant_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];
$powered_by_count = $tenant_count > 0 ? $tenant_count : "leading";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | The Cloud Banking Platform for Modern MFIs</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <span class="material-symbols-rounded">public</span>
                <span class="logo-text">MicroFin</span>
            </div>
            
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How it Works</a>
                <a href="#pricing">Pricing</a>
                <a href="#security">Security</a>
                <!-- Replaced by Private URL approach -->
                <a href="demo.php" class="nav-btn-link" style="color: var(--text-gray); font-weight: 500; font-size: 0.95rem; text-decoration: none; transition: color 0.2s; margin-left: 16px;">Contact Sales</a>
            </div>
            
            <div class="nav-cta" style="display: flex; gap: 12px; align-items: center;">
                <a href="../super_admin/login.php" class="btn btn-outline" style="border: 1px solid #cbd5e1; color: #475569; border-radius: 8px; font-weight: 600; padding: 10px 20px;">Platform Login</a>
                <a href="demo.php" class="btn btn-primary" style="border-radius: 8px;">Contact Us</a>       
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <div class="badge-pill">🚀 The #1 SaaS for Microfinance</div>
                <h1>Empower your institution with a true cloud core banking system.</h1>
                <p>MicroFin is a fully isolated, multi-tenant cloud banking platform designed specifically for Microfinance Institutions, SACCOs, and Cooperatives.</p>
                
                <div class="hero-actions">
                    <a href="demo.php" class="btn btn-primary btn-lg">Contact Us</a>
                    <a href="#features" class="btn btn-outline btn-lg">Explore Features</a>
                </div>
                <div class="trust-marks">
                    <span>Trusted by <?php echo $powered_by_count; ?> microfinance institutions <?php if($powered_by_count > 0) echo "including:"; ?></span>
                    <?php if (!empty($active_tenants)): ?>
                    <div class="trusted-tenants-list">
                        <?php foreach($active_tenants as $tenant): ?>
                            <span class="trusted-tenant-badge">
                                <span class="material-symbols-rounded">corporate_fare</span>
                                <?php echo htmlspecialchars($tenant['tenant_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-image">
                <div class="mockup-window">
                    <div class="mockup-header">
                        <div class="dot red"></div><div class="dot yellow"></div><div class="dot green"></div>
                    </div>
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&q=80&w=800&h=500" alt="Dashboard Preview" class="mockup-img">
                </div>
            </div>
        </div>
    </header>

    <!-- Features Grid -->
    <section id="features" class="section bg-light">
        <div class="container">
            <div class="section-header text-center">
                <h2>Built for Scale, Designed for Security</h2>
                <p>Everything your cooperative needs to operate digitally, out of the box.</p>
            </div>
            
            <div class="grid-3">
                <!-- Feature 1 -->
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">dns</span></div>
                    <h3>Multi-Tenant Architecture</h3>
                    <p>Your data is perfectly isolated. Experience enterprise-grade security where your institution's records are completely separated from others.</p>
                </div>
                <!-- Feature 2 -->
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">palette</span></div>
                    <h3>Fully Whitelabeled</h3>
                    <p>It's your brand. Upload your logo, change your color themes, and instantly transform the dashboard to look like your own proprietary software.</p>
                </div>
                <!-- Feature 3 -->
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">account_balance</span></div>
                    <h3>Core Banking Engine</h3>
                    <p>Automated loan origination, savings management, and real-time interest calculation baked directly into the platform core.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Extended Capabilities -->
    <section id="capabilities" class="section bg-white">
        <div class="container">
            <div class="section-header text-center">
                <h2>Beyond Core Banking</h2>
                <p>Advanced tools completely integrated into your ecosystem to drive growth and efficiency.</p>
            </div>
            
            <div class="grid-3">
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">monitoring</span></div>
                    <h3>Advanced Analytics</h3>
                    <p>Generate real-time PAR (Portfolio at Risk) reports, balance sheets, and income statements with one click.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">sms</span></div>
                    <h3>Automated Notifications</h3>
                    <p>Send automated SMS and email reminders to borrowers for upcoming dues, reducing default rates automatically.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">api</span></div>
                    <h3>API-Ready & Integrations</h3>
                    <p>Connect seamlessly with payment gateways, credit bureaus, and external accounting tools via secure APIs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="section bg-white text-dark">
        <div class="container">
            <div class="section-header text-center">
                <h2>Simple, Transparent Pricing</h2>
                <p>Scale your financial institution with plans designed for growth. No hidden fees.</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Starter -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Starter</h3>
                        <div class="price">₱4,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>1,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>250</strong> Max Users</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Cloud-Based Access</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Core Banking Engine</li>
                    </ul>
                </div>
                
                <!-- Growth -->
                <div class="pricing-card popular">
                    <div class="popular-badge">Most Popular</div>
                    <div class="pricing-header">
                        <h3>Growth</h3>
                        <div class="price">₱9,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>2,500</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>750</strong> Max Users</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Advanced Analytics</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Custom Branding</li>
                    </ul>
                </div>

                <!-- Pro -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Pro</h3>
                        <div class="price">₱14,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>5,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>2,000</strong> Max Users</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Automated Notifications</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Priority Support</li>
                    </ul>
                </div>

                <!-- Enterprise -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Enterprise</h3>
                        <div class="price">₱22,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>10,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>5,000</strong> Max Users</li>
                        <li><span class="material-symbols-rounded">check_circle</span> API & Integrations</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Dedicated Manager</li>
                    </ul>
                </div>

                <!-- Unlimited -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Unlimited</h3>
                        <div class="price">₱29,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Users</li>
                        <li><span class="material-symbols-rounded">check_circle</span> All Enterprise Features</li>
                        <li><span class="material-symbols-rounded">check_circle</span> White-glove Onboarding</li>
                    </ul>
                </div>
            </div>
            
            
        </div>
    </section>

    <!-- How it Works Flow -->
    <section id="how-it-works" class="section bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Go live in days, not months.</h2>
                <p>Because it's a SaaS platform, we handle the infrastructure. You just run your business.</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot">1</div>
                    <div class="timeline-content">
                        <h3>Book a Discovery Call</h3>
                        <p>We meet to understand your current loan volume, data migration needs, and compliance requirements.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">2</div>
                    <div class="timeline-content">
                        <h3>Instant Provisioning</h3>
                        <p>Once approved, our Super Admins spin up your isolated database environment (Tenant ID) in seconds.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">3</div>
                    <div class="timeline-content">
                        <h3>Your Custom Dashboard</h3>
                        <p>You receive an invite to your brand new Admin Panel. Change the colors, add your staff, and start issuing loans immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section id="security" class="section bg-white" style="border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div class="container container-flex">
            <div class="security-content">
                <span class="badge-pill" style="background: #fce7f3; color: #9333ea; margin-bottom: 16px;">Bank-Grade Security</span>
                <h2 style="font-size: 2.5rem; margin-bottom: 24px; color: var(--primary);">Your data is encrypted, isolated, and continuously backed up.</h2>
                <ul class="security-list" style="list-style: none; padding: 0; margin-bottom: 32px;">
                    <li style="margin-bottom: 16px; display: flex; align-items: flex-start; gap: 12px;">
                        <span class="material-symbols-rounded" style="color: #10b981;">check_circle</span>
                        <div>
                            <strong style="display: block; color: var(--text-dark);">Strict Tenant Isolation</strong>
                            <span style="color: var(--text-gray); font-size: 0.95rem;">Every institution has its own dedicated database schema. Commingling of records is impossible.</span>
                        </div>
                    </li>
                    <li style="margin-bottom: 16px; display: flex; align-items: flex-start; gap: 12px;">
                        <span class="material-symbols-rounded" style="color: #10b981;">check_circle</span>
                        <div>
                            <strong style="display: block; color: var(--text-dark);">End-to-End Encryption</strong>
                            <span style="color: var(--text-gray); font-size: 0.95rem;">All data in transit and at rest is secured using AES-256 and TLS 1.3 standards.</span>
                        </div>
                    </li>
                    <li style="display: flex; align-items: flex-start; gap: 12px;">
                        <span class="material-symbols-rounded" style="color: #10b981;">check_circle</span>
                        <div>
                            <strong style="display: block; color: var(--text-dark);">Automated Backups & Redundancy</strong>
                            <span style="color: var(--text-gray); font-size: 0.95rem;">Multi-region data replication ensures you never lose a single transaction record, even in hardware failure events.</span>
                        </div>
                    </li>
                </ul>
                <a href="#contact" class="btn btn-outline">Read our Security Whitepaper</a>
            </div>
            <div class="security-image" style="flex: 1; text-align: center; padding: 40px; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 24px;">
                <span class="material-symbols-rounded" style="font-size: 140px; color: #1e293b; drop-shadow: 0 10px 15px rgba(0,0,0,0.1);">gpp_good</span>
                <div style="margin-top: 24px; font-weight: 600; color: #475569; font-size: 1.1rem;">ISO 27001 & PCI-DSS Compliant Infrastructure</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="contact" class="section text-white" style="position: relative; overflow: hidden; background: linear-gradient(135deg, var(--primary) 0%, #1e1b4b 100%);">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 80% -20%, rgba(37,99,235,0.4) 0%, transparent 50%); z-index: 0;"></div>
        <div class="container" style="text-align: center; max-width: 700px; position: relative; z-index: 1;">
            <h2 style="font-size: 2.8rem; font-weight: 800; line-height: 1.1; margin-bottom: 20px; letter-spacing: -1px;">Ready to modernize your cooperative?</h2>
            <p style="font-size: 1.1rem; margin-bottom: 36px; color: var(--text-light);">Leave legacy desktop software behind. Let our team migrate your data to the cloud seamlessly.</p>
            <a href="demo.php" class="btn btn-primary btn-lg" style="padding: 16px 36px; font-size: 1.1rem;">
                <span class="material-symbols-rounded" style="font-size: 20px; margin-right: 8px; vertical-align: middle;">calendar_month</span>
                Contact Us
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <span class="material-symbols-rounded">public</span>
                    <span class="logo-text">MicroFin</span>
                </div>
                <p>The developer-first banking platform enabling financial inclusion across the globe.</p>
            </div>
            <div class="footer-links">
                <h4>Product</h4>
                <a href="#">Core Banking</a>
                <a href="#">Security</a>
                <a href="#">Pricing</a>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Contact Support</a>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; 2026 MicroFin Platform. All rights reserved.</p>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>


