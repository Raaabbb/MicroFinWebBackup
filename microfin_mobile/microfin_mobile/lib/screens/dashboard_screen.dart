import 'package:flutter/material.dart';
import '../main.dart';
import '../theme.dart';
import 'loan_application_screen.dart';
import 'loan_details_screen.dart';
import 'my_loans_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.primaryColor;
    final secondary = tenant.secondaryColor;
    final hour = DateTime.now().hour;
    final greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(child: _header(context, tenant, primary, secondary, greeting)),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 110),
            sliver: SliverList(delegate: SliverChildListDelegate([
              _loanHeroCard(context, primary, secondary),
              const SizedBox(height: 28),
              _sectionTitle('Quick Actions'),
              const SizedBox(height: 14),
              _quickActions(context, primary),
              const SizedBox(height: 28),
              _sectionTitle('Repayment Progress'),
              const SizedBox(height: 14),
              _progressCard(primary),
              const SizedBox(height: 28),
              _sectionTitle('Notifications'),
              const SizedBox(height: 14),
              _notification(Icons.notifications_active_rounded, AppColors.warning, AppColors.warningLight, 'Payment Due Tomorrow', 'Your payment of ₱2,500.00 is due on Mar 25, 2026', '2h ago'),
              const SizedBox(height: 12),
              _notification(Icons.check_circle_rounded, AppColors.success, AppColors.successLight, 'Loan Application Approved', 'Your Personal Loan #LN-2026-001 has been approved!', '1d ago'),
              const SizedBox(height: 12),
              _notification(Icons.account_balance_rounded, AppColors.info, AppColors.infoLight, 'Funds Disbursed', '₱50,000.00 has been credited to your account', '3d ago'),
            ])),
          ),
        ],
      ),
    );
  }

  Widget _header(BuildContext context, tenant, Color primary, Color secondary, String greeting) {
    return Container(
      padding: EdgeInsets.fromLTRB(24, MediaQuery.of(context).padding.top + 16, 24, 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [primary, secondary]),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
        boxShadow: [BoxShadow(color: primary.withOpacity(0.25), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Row(children: [
            Text(tenant.emoji, style: const TextStyle(fontSize: 22)),
            const SizedBox(width: 8),
            Text(tenant.appName, style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700, letterSpacing: -0.2)),
          ]),
          Stack(children: [
            Container(width: 40, height: 40, decoration: BoxDecoration(color: Colors.white.withOpacity(0.15), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.notifications_outlined, color: Colors.white, size: 22)),
            Positioned(top: 8, right: 8, child: Container(width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFFFBBF24), shape: BoxShape.circle))),
          ]),
        ]),
        const SizedBox(height: 20),
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('$greeting 👋', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 14)),
            const SizedBox(height: 2),
            const Text('Juan Dela Cruz', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: Colors.white.withOpacity(0.15), borderRadius: BorderRadius.circular(8)),
              child: const Text('CLT-00123 • Active Member', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
            ),
          ])),
          Container(width: 56, height: 56,
            decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(0.2), border: Border.all(color: Colors.white.withOpacity(0.3), width: 2)),
            child: const Center(child: Text('👤', style: TextStyle(fontSize: 26)))),
        ]),
      ]),
    );
  }

  Widget _loanHeroCard(BuildContext context, Color primary, Color secondary) {
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoanDetailsScreen())),
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [primary, secondary]),
          borderRadius: BorderRadius.circular(24),
          boxShadow: AppColors.elevatedShadow(primary),
        ),
        child: Stack(children: [
          Positioned(right: -10, top: -10, child: Icon(Icons.account_balance_rounded, size: 130, color: Colors.white.withOpacity(0.06))),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Active Loan', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13, fontWeight: FontWeight.w500)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(color: AppColors.success, borderRadius: BorderRadius.circular(8)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.circle, color: Colors.white, size: 6),
                  SizedBox(width: 4),
                  Text('ACTIVE', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
                ]),
              ),
            ]),
            const SizedBox(height: 6),
            const Text('₱50,000.00', style: TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w900, letterSpacing: -1.0, height: 1)),
            const SizedBox(height: 4),
            Text('Personal Loan  •  LN-2026-001', style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12)),
            const SizedBox(height: 20),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Remaining Balance', style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 11)),
              const Text('65% Paid', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
            ]),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(value: 0.65, minHeight: 8, backgroundColor: Colors.white.withOpacity(0.2), valueColor: const AlwaysStoppedAnimation<Color>(Colors.white)),
            ),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: _loanStat('REMAINING', '₱17,500')),
              Container(width: 1, height: 28, color: Colors.white.withOpacity(0.2)),
              Expanded(child: Padding(padding: const EdgeInsets.only(left: 16), child: _loanStat('NEXT DUE', 'Mar 25, 2026'))),
              Container(width: 1, height: 28, color: Colors.white.withOpacity(0.2)),
              Expanded(child: Padding(padding: const EdgeInsets.only(left: 16), child: _loanStat('MONTHLY', '₱2,458'))),
            ]),
          ]),
        ]),
      ),
    );
  }

  Widget _loanStat(String label, String value) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(color: Colors.white.withOpacity(0.65), fontSize: 9, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
      const SizedBox(height: 3),
      Text(value, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
    ]);
  }

  Widget _sectionTitle(String t) => Text(t, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4));

  Widget _quickActions(BuildContext context, Color primary) {
    final items = [
      {'icon': Icons.add_circle_outline_rounded, 'label': 'Apply\nLoan', 'screen': const LoanApplicationScreen()},
      {'icon': Icons.payments_outlined, 'label': 'Pay\nLoan', 'screen': const LoanDetailsScreen()},
      {'icon': Icons.calendar_today_outlined, 'label': 'Schedule', 'screen': const LoanDetailsScreen()},
      {'icon': Icons.account_balance_wallet_outlined, 'label': 'My\nLoans', 'screen': const MyLoansScreen()},
    ];
    return Row(
      children: List.generate(items.length, (i) {
        final item = items[i];
        return Expanded(child: GestureDetector(
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => item['screen'] as Widget)),
          child: Container(
            margin: EdgeInsets.only(right: i < items.length - 1 ? 10 : 0),
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.separator),
              boxShadow: AppColors.cardShadow,
            ),
            child: Column(children: [
              Container(width: 44, height: 44, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(14)), child: Icon(item['icon'] as IconData, color: primary, size: 22)),
              const SizedBox(height: 8),
              Text(item['label'] as String, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.textMain, height: 1.2)),
            ]),
          ),
        ));
      }),
    );
  }

  Widget _progressCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Text('Personal Loan — LN-2026-001', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.textMain)),
          Text('16 / 24 months', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: primary)),
        ]),
        const SizedBox(height: 14),
        ClipRRect(borderRadius: BorderRadius.circular(8), child: LinearProgressIndicator(value: 0.65, minHeight: 10, backgroundColor: AppColors.surfaceVariant, valueColor: AlwaysStoppedAnimation<Color>(primary))),
        const SizedBox(height: 14),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          _miniStat('Paid', '₱32,500', AppColors.success),
          _miniStat('Remaining', '₱17,500', primary),
          _miniStat('On-Time', '16/16', const Color(0xFF6366F1)),
        ]),
      ]),
    );
  }

  Widget _miniStat(String label, String value, Color color) {
    return Column(children: [
      Text(value, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: color)),
      const SizedBox(height: 2),
      Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
    ]);
  }

  Widget _notification(IconData icon, Color color, Color bg, String title, String sub, String time) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(width: 40, height: 40, decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)), child: Icon(icon, color: color, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Expanded(child: Text(title, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textMain))),
            Text(time, style: const TextStyle(fontSize: 11, color: AppColors.textLight)),
          ]),
          const SizedBox(height: 4),
          Text(sub, style: const TextStyle(fontSize: 12, color: AppColors.textMuted, height: 1.4)),
        ])),
      ]),
    );
  }
}
