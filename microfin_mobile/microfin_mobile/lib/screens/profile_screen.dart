import 'package:flutter/material.dart';
import '../main.dart';
import '../theme.dart';
import 'splash_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _notificationsOn = true;
  bool _twoFaOn = false;

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.primaryColor;
    final secondary = tenant.secondaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          // ── Profile Header ─────────────────────────────────────────────────
          SliverToBoxAdapter(child: _buildHeader(context, tenant, primary, secondary)),

          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 60),
            sliver: SliverList(delegate: SliverChildListDelegate([
              // Personal info
              _cardSection('Personal Information', [
                _infoRow(Icons.person_outline_rounded, 'Full Name', 'Juan Dela Cruz', primary),
                _divider(),
                _infoRow(Icons.mail_outline_rounded, 'Email', 'juan.delacruz@email.com', primary),
                _divider(),
                _infoRow(Icons.phone_outlined, 'Phone', '+63 912 345 6789', primary),
                _divider(),
                _infoRow(Icons.cake_outlined, 'Date of Birth', 'Jan 1, 1995', primary),
              ], primary),
              const SizedBox(height: 20),

              // Documents
              _cardSection('My Documents', [
                _documentRow(Icons.badge_outlined, 'Valid ID', 'Verified', AppColors.success, AppColors.successLight, primary),
                _divider(),
                _documentRow(Icons.receipt_long_outlined, 'Proof of Income', 'Verified', AppColors.success, AppColors.successLight, primary),
                _divider(),
                _documentRow(Icons.home_outlined, 'Proof of Billing', 'Pending', AppColors.warning, AppColors.warningLight, primary),
                _divider(),
                _uploadDocRow(primary),
              ], primary),
              const SizedBox(height: 20),

              // Loan Summary
              _cardSection('Account Summary', [
                _infoRow(Icons.account_balance_wallet_outlined, 'Active Loans', '1 Active', primary),
                _divider(),
                _infoRow(Icons.history_rounded, 'Total Applications', '3 Applications', primary),
                _divider(),
                _infoRow(Icons.star_outline_rounded, 'Credit Score', 'Excellent (820)', primary),
                _divider(),
                _infoRow(Icons.verified_outlined, 'Member Since', 'January 2026', primary),
              ], primary),
              const SizedBox(height: 20),

              // Settings
              _cardSection('Settings & Security', [
                _switchRow(Icons.notifications_outlined, 'Notifications', _notificationsOn, (v) => setState(() => _notificationsOn = v), primary),
                _divider(),
                _switchRow(Icons.security_outlined, '2FA Authentication', _twoFaOn, (v) => setState(() => _twoFaOn = v), primary),
                _divider(),
                _navRow(Icons.lock_outline_rounded, 'Change Password', primary, () {}),
                _divider(),
                _navRow(Icons.language_outlined, 'Language', primary, () {}),
              ], primary),
              const SizedBox(height: 20),

              // Help & Support
              _cardSection('Help & Support', [
                _navRow(Icons.help_outline_rounded, 'FAQ & Help Center', primary, () {}),
                _divider(),
                _navRow(Icons.headset_mic_outlined, 'Contact Support', primary, () {}),
                _divider(),
                _navRow(Icons.policy_outlined, 'Terms & Privacy Policy', primary, () {}),
              ], primary),
              const SizedBox(height: 20),

              // Tenant switcher
              _cardSection('App Settings', [
                _tenantSwitcherRow(context, tenant, primary),
              ], primary),
              const SizedBox(height: 20),

              // Log out
              GestureDetector(
                onTap: () => _confirmLogout(context, primary),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  decoration: BoxDecoration(
                    color: AppColors.dangerLight,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.danger.withOpacity(0.3)),
                  ),
                  child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.logout_rounded, color: AppColors.danger, size: 20),
                    SizedBox(width: 10),
                    Text('Log Out', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.danger)),
                  ]),
                ),
              ),
            ])),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, tenant, Color primary, Color secondary) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(24, MediaQuery.of(context).padding.top + 20, 24, 32),
      decoration: BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [primary, secondary]),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(32)),
        boxShadow: [BoxShadow(color: primary.withOpacity(0.25), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Column(children: [
        // Avatar
        Stack(alignment: Alignment.bottomRight, children: [
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withOpacity(0.2),
              border: Border.all(color: Colors.white.withOpacity(0.4), width: 3),
            ),
            child: const Center(child: Text('👤', style: TextStyle(fontSize: 42))),
          ),
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 4)]),
            child: Icon(Icons.camera_alt_rounded, color: primary, size: 15),
          ),
        ]),
        const SizedBox(height: 14),
        const Text('Juan Dela Cruz', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -0.4)),
        const SizedBox(height: 4),
        Text('CLT-00123  •  Active Member', style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 13)),
        const SizedBox(height: 12),
        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          _headerBadge(Icons.verified_rounded, 'Fully Verified', Colors.white.withOpacity(0.2)),
          const SizedBox(width: 10),
          _headerBadge(Icons.star_rounded, 'Excellent Score', Colors.white.withOpacity(0.2)),
        ]),
      ]),
    );
  }

  Widget _headerBadge(IconData icon, String label, Color bg) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: Colors.white, size: 14),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
      ]),
    );
  }

  Widget _cardSection(String title, List<Widget> children, Color primary) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(
        padding: const EdgeInsets.only(left: 4, bottom: 10),
        child: Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textMain)),
      ),
      Container(
        decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
        child: Column(children: children),
      ),
    ]);
  }

  Widget _divider() => const Divider(height: 1, indent: 64, endIndent: 20, color: AppColors.separator);

  Widget _infoRow(IconData icon, String label, String value, Color primary) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: primary, size: 18)),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textMain)),
        ])),
        const Icon(Icons.chevron_right_rounded, color: AppColors.textLight, size: 20),
      ]),
    );
  }

  Widget _documentRow(IconData icon, String label, String status, Color statusColor, Color statusBg, Color primary) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: primary, size: 18)),
        const SizedBox(width: 14),
        Expanded(child: Text(label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textMain))),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(color: statusBg, borderRadius: BorderRadius.circular(8)),
          child: Text(status, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor)),
        ),
      ]),
    );
  }

  Widget _uploadDocRow(Color primary) {
    return GestureDetector(
      onTap: () {},
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(children: [
          Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(Icons.add_rounded, color: primary, size: 20)),
          const SizedBox(width: 14),
          Text('Upload a Document', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: primary)),
        ]),
      ),
    );
  }

  Widget _navRow(IconData icon, String label, Color primary, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(children: [
          Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: primary, size: 18)),
          const SizedBox(width: 14),
          Expanded(child: Text(label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textMain))),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textLight, size: 20),
        ]),
      ),
    );
  }

  Widget _switchRow(IconData icon, String label, bool value, ValueChanged<bool> onChanged, Color primary) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: primary, size: 18)),
        const SizedBox(width: 14),
        Expanded(child: Text(label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textMain))),
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: primary),
      ]),
    );
  }

  Widget _tenantSwitcherRow(BuildContext context, tenant, Color primary) {
    return GestureDetector(
      onTap: () => _showTenantPicker(context, primary),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(children: [
          Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Text(tenant.emoji, style: const TextStyle(fontSize: 18), textAlign: TextAlign.center)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Current App / Tenant', style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
            Text(tenant.appName, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textMain)),
          ])),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
            child: Text('Switch', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: primary)),
          ),
        ]),
      ),
    );
  }

  void _showTenantPicker(BuildContext context, Color primary) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        decoration: const BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
        padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.separator, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          const Text('Switch Tenant', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain)),
          const SizedBox(height: 16),
          ...activeTenant.value == null ? [] : [1, 2, 3].map((_) => const SizedBox()),
          ...['💳 Fundline Mobile', '🏦 PlaridelMFB', '🌿 Sacred Heart Coop'].map((t) {
            return ListTile(
              title: Text(t, style: const TextStyle(fontWeight: FontWeight.w600)),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () { Navigator.pop(context); },
            );
          }),
        ]),
      ),
    );
  }

  void _confirmLogout(BuildContext context, Color primary) {
    showDialog(context: context, builder: (_) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Text('Log Out', style: TextStyle(fontWeight: FontWeight.w800)),
      content: const Text('Are you sure you want to log out of your account?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        ElevatedButton(
          onPressed: () {
            Navigator.pop(context);
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const SplashScreen()),
              (_) => false,
            );
          },
          style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
          child: const Text('Log Out'),
        ),
      ],
    ));
  }
}
