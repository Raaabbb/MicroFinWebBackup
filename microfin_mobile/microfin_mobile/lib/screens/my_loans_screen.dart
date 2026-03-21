import 'package:flutter/material.dart';
import '../main.dart';
import '../theme.dart';
import 'loan_details_screen.dart';

class MyLoansScreen extends StatelessWidget {
  const MyLoansScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.primaryColor;
    final secondary = activeTenant.value.secondaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: primary,
            leading: Navigator.canPop(context)
              ? IconButton(icon: const Icon(Icons.arrow_back_rounded, color: Colors.white), onPressed: () => Navigator.pop(context))
              : null,
            title: const Text('My Loans', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700)),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 60),
            sliver: SliverList(delegate: SliverChildListDelegate([
              // Summary hero
              Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [primary, secondary]),
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: AppColors.elevatedShadow(primary),
                ),
                child: Row(children: [
                  Expanded(child: _stat('TOTAL OUTSTANDING', '₱17,500.00', Colors.white70, Colors.white, align: CrossAxisAlignment.start)),
                  Container(width: 1, height: 44, color: Colors.white24),
                  const SizedBox(width: 20),
                  Expanded(child: _stat('ACTIVE LOANS', '1', Colors.white70, Colors.white, align: CrossAxisAlignment.start)),
                  Container(width: 1, height: 44, color: Colors.white24),
                  const SizedBox(width: 20),
                  Expanded(child: _stat('TOTAL PAID', '₱32,500', Colors.white70, Colors.white, align: CrossAxisAlignment.start)),
                ]),
              ),
              const SizedBox(height: 28),

              // Active loans
              const Text('Active Loans', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4)),
              const SizedBox(height: 14),
              _loanCard(
                context,
                name: 'Personal Flexi Loan',
                loanNo: 'LN-2026-001',
                balance: 17500.0,
                nextDue: 2458.33,
                dueDate: 'Mar 25, 2026',
                progress: 0.65,
                status: 'Active',
                primary: primary,
              ),
              const SizedBox(height: 28),

              const Text('Past Loans', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4)),
              const SizedBox(height: 14),
              _loanCard(
                context,
                name: 'Emergency Loan',
                loanNo: 'LN-2023-088',
                balance: 0.0,
                nextDue: 0.0,
                dueDate: '—',
                progress: 1.0,
                status: 'Fully Paid',
                primary: AppColors.success,
              ),
            ])),
          ),
        ],
      ),
    );
  }

  Widget _stat(String label, String value, Color lblColor, Color valColor, {CrossAxisAlignment align = CrossAxisAlignment.start}) {
    return Column(crossAxisAlignment: align, children: [
      Text(label, style: TextStyle(color: lblColor, fontSize: 9, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
      const SizedBox(height: 6),
      Text(value, style: TextStyle(color: valColor, fontSize: 18, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
    ]);
  }

  Widget _loanCard(BuildContext context, {
    required String name, required String loanNo, required double balance, required double nextDue,
    required String dueDate, required double progress, required String status, required Color primary,
  }) {
    final isPaid = status == 'Fully Paid';
    final statusColor = isPaid ? AppColors.success : progress < 0.3 ? AppColors.danger : primary;

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoanDetailsScreen())),
      child: Container(
        decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(24), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: Row(children: [
              Container(width: 46, height: 46,
                decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(14)),
                child: Icon(Icons.account_balance_wallet_rounded, color: statusColor, size: 24)),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(name, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textMain)),
                Text(loanNo, style: const TextStyle(fontSize: 12, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
              ])),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
                child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
              ),
            ]),
          ),
          const Divider(height: 1, color: AppColors.separator),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
            child: Column(children: [
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                _loanStat('BALANCE', '₱${balance.toStringAsFixed(2)}', isPaid ? AppColors.textMuted : primary),
                _loanStat('NEXT DUE', '₱${nextDue.toStringAsFixed(2)}', AppColors.textMain),
              ]),
              const SizedBox(height: 16),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('REPAYMENT PROGRESS', style: TextStyle(fontSize: 10, color: AppColors.textMuted, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
                Text('${(progress * 100).toInt()}%', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: statusColor)),
              ]),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(value: progress, minHeight: 8, backgroundColor: AppColors.separator, valueColor: AlwaysStoppedAnimation<Color>(statusColor)),
              ),
              const SizedBox(height: 12),
              Row(children: [
                const Icon(Icons.event_outlined, size: 14, color: AppColors.textMuted),
                const SizedBox(width: 6),
                Text('Next Due: $dueDate', style: const TextStyle(fontSize: 12, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
              ]),
            ]),
          ),
          if (!isPaid) ...[
            const Divider(height: 1, color: AppColors.separator),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {},
                  style: ElevatedButton.styleFrom(backgroundColor: primary, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), padding: const EdgeInsets.symmetric(vertical: 13), elevation: 0),
                  child: const Text('PAY NOW', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 0.5)),
                ),
              ),
            ),
          ],
        ]),
      ),
    );
  }

  Widget _loanStat(String label, String value, Color valColor) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 10, color: AppColors.textMuted, fontWeight: FontWeight.w700, letterSpacing: 0.8)),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900, color: valColor)),
    ]);
  }
}
