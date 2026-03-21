import 'package:flutter/material.dart';
import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import '../widgets/microfin_logo.dart';

import 'login_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  late AnimationController _logoController;
  late AnimationController _panelController;
  late Animation<double> _fadeLogo;
  late Animation<double> _scaleLogo;
  late Animation<double> _slidePanel;
  late Animation<double> _fadePanel;
  bool _showPanel = false;

  @override
  void initState() {
    super.initState();
    _logoController = AnimationController(
      duration: const Duration(milliseconds: 1200),
      vsync: this,
    );
    _panelController = AnimationController(
      duration: const Duration(milliseconds: 550),
      vsync: this,
    );

    _fadeLogo = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(
          parent: _logoController,
          curve: const Interval(0.0, 0.6, curve: Curves.easeOut)),
    );
    _scaleLogo = Tween<double>(begin: 0.65, end: 1.0).animate(
      CurvedAnimation(
          parent: _logoController,
          curve: const Interval(0.0, 0.7, curve: Curves.easeOutBack)),
    );
    _slidePanel = Tween<double>(begin: 1.0, end: 0.0).animate(
      CurvedAnimation(parent: _panelController, curve: Curves.easeOutCubic),
    );
    _fadePanel = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _panelController, curve: Curves.easeOut),
    );

    _startSequence();
  }

  void _startSequence() async {
    await _logoController.forward();
    await Future.delayed(const Duration(milliseconds: 600));
    if (mounted) {
      setState(() => _showPanel = true);
      _panelController.forward();
    }
  }

  @override
  void dispose() {
    _logoController.dispose();
    _panelController.dispose();
    super.dispose();
  }

  void _selectTenant(TenantBranding tenant) {
    // Simulated behavior: 
    // 1. App scans website QR / clicks deep link: `https://app.microfin.com/?tenant_id=fundline`
    // 2. Fetch branding: `TenantBranding.fromTenantId('fundline')`
    final mappedBranding = TenantBranding.fromTenantId(tenant.slug) ?? tenant;
    activeTenant.value = mappedBranding;
    
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 500),
        pageBuilder: (_, __, ___) => const LoginScreen(),
        transitionsBuilder: (_, animation, __, child) => FadeTransition(
          opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
          child: child,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    const panelHeight = 0.52;

    return Scaffold(
      backgroundColor: const Color(0xFF0A0F1E),
      body: Stack(
        children: [
          // ── Dark gradient background ────────────────────────────────────────
          Positioned.fill(
            child: Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                  colors: [Color(0xFF0F172A), Color(0xFF1A0A0A)],
                ),
              ),
            ),
          ),

          // ── Decorative glow blobs ───────────────────────────────────────────
          Positioned(
            top: -80,
            right: -80,
            child: Container(
              width: 260,
              height: 260,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFFDC2626).withOpacity(0.1),
              ),
            ),
          ),
          Positioned(
            top: 120,
            left: -60,
            child: Container(
              width: 160,
              height: 160,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF1D4ED8).withOpacity(0.07),
              ),
            ),
          ),
          Positioned(
            bottom: size.height * panelHeight + 60,
            right: 30,
            child: Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF059669).withOpacity(0.08),
              ),
            ),
          ),

          // ── Logo section ──────────────────────────────────────────────────
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            bottom: _showPanel ? size.height * panelHeight : 0,
            child: AnimatedBuilder(
              animation: _logoController,
              builder: (context, _) {
                return Center(
                  child: FadeTransition(
                    opacity: _fadeLogo,
                    child: ScaleTransition(
                      scale: _scaleLogo,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          // App icon container
                          const MicroFinLogo(size: 100),
                          const SizedBox(height: 28),
                          // App name
                          const Text(
                            'MicroFin',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 42,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -2.0,
                              height: 1.0,
                            ),
                          ),
                          const SizedBox(height: 12),
                          // Tagline pill
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 18, vertical: 8),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.07),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.1),
                              ),
                            ),
                            child: const Text(
                              'Your Unified Lending Hub',
                              style: TextStyle(
                                color: Colors.white70,
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),

          // ── Tenant picker panel ────────────────────────────────────────────
          if (_showPanel)
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              height: size.height * (panelHeight + 0.05),
              child: AnimatedBuilder(
                animation: _panelController,
                builder: (context, _) {
                  return Transform.translate(
                    offset: Offset(
                        0, size.height * 0.35 * _slidePanel.value),
                    child: Opacity(
                      opacity: _fadePanel.value,
                      child: Container(
                        decoration: const BoxDecoration(
                          color: AppColors.bg,
                          borderRadius: BorderRadius.vertical(
                              top: Radius.circular(32)),
                        ),
                        padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Handle bar
                            Center(
                              child: Container(
                                width: 40,
                                height: 4,
                                decoration: BoxDecoration(
                                  color: AppColors.separator,
                                  borderRadius: BorderRadius.circular(2),
                                ),
                              ),
                            ),
                            const SizedBox(height: 20),
                            const Text(
                              '[Debug] URL/QR Scanner',
                              style: TextStyle(
                                color: AppColors.textMain,
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.6,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Simulate mapping a website Tenant ID to Branding',
                              style: TextStyle(
                                color: Color(0xFF3B82F6), // Using a distinct color to highlight debug mode
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 20),
                            Expanded(
                              child: ListView.separated(
                                padding: EdgeInsets.zero,
                                itemCount: TenantBranding.tenants.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(height: 12),
                                itemBuilder: (context, index) {
                                  return _TenantCard(
                                    tenant: TenantBranding.tenants[index],
                                    onTap: () => _selectTenant(
                                        TenantBranding.tenants[index]),
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

// ─── Tenant Card Widget ────────────────────────────────────────────────────────
class _TenantCard extends StatefulWidget {
  final TenantBranding tenant;
  final VoidCallback onTap;

  const _TenantCard({required this.tenant, required this.onTap});

  @override
  State<_TenantCard> createState() => _TenantCardState();
}

class _TenantCardState extends State<_TenantCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _pressController;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _pressController = AnimationController(
      duration: const Duration(milliseconds: 120),
      vsync: this,
    );
    _scaleAnim = Tween<double>(begin: 1.0, end: 0.96).animate(
      CurvedAnimation(parent: _pressController, curve: Curves.easeOut),
    );
  }

  @override
  void dispose() {
    _pressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.tenant;
    return GestureDetector(
      onTapDown: (_) => _pressController.forward(),
      onTapUp: (_) {
        _pressController.reverse();
        widget.onTap();
      },
      onTapCancel: () => _pressController.reverse(),
      child: ScaleTransition(
        scale: _scaleAnim,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.separator),
            boxShadow: AppColors.cardShadow,
          ),
          child: Row(
            children: [
              // Emoji icon
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: t.primaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Center(
                  child: Text(
                    t.emoji,
                    style: const TextStyle(fontSize: 28),
                  ),
                ),
              ),
              const SizedBox(width: 16),
              // Info
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t.appName,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textMain,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      t.description,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w400,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              // Arrow button
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: t.primaryColor,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: AppColors.elevatedShadow(t.primaryColor),
                ),
                child: const Center(
                  child: Icon(
                    Icons.arrow_forward_rounded,
                    color: Colors.white,
                    size: 18,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
