import 'package:flutter/material.dart';
import '../main.dart';
import '../theme.dart';
import 'loan_application_screen.dart';

class LoanDetailsScreen extends StatelessWidget {
  const LoanDetailsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.primaryColor;
    final secondary = activeTenant.value.secondaryColor;

    final schedule = List.generate(24, (i) => _ScheduleItem(
      no: i + 1,
      dueDate: _dueDate(i + 1),
      principal: 1458.33,
      interest: 1000.0,
      total: 2458.33,
      status: i < 16 ? 'Paid' : i == 16 ? 'Due' : 'Pending',
    ));

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          // ── App Bar ──────────────────────────────────────────────────────
          SliverAppBar(
            pinned: true,
            backgroundColor: primary,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
              onPressed: () => Navigator.pop(context),
            ),
            title: const Text('Loan Details', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700)),
            actions: [
              IconButton(
                icon: const Icon(Icons.share_outlined, color: Colors.white),
                onPressed: () {},
              ),
            ],
          ),

          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 60),
            sliver: SliverList(delegate: SliverChildListDelegate([

              // ── Hero Card ──────────────────────────────────────────────────
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [primary, secondary]),
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: AppColors.elevatedShadow(primary),
                ),
                child: Stack(children: [
                  Positioned(right: -10, top: -10,
                    child: Icon(Icons.account_balance_rounded, size: 120, color: Colors.white.withOpacity(0.06))),
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      const Text('Personal Loan', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500)),
                      _statusBadge('Active', AppColors.success),
                    ]),
                    const SizedBox(height: 6),
                    const Text('LN-2026-001', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.3)),
                    const SizedBox(height: 16),
                    const Text('₱50,000.00', style: TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w900, letterSpacing: -1.5, height: 1.0)),
                    const SizedBox(height: 4),
                    Text('Released Jan 15, 2026', style: TextStyle(color: Colors.white.withOpacity(0.65), fontSize: 13)),
                    const SizedBox(height: 20),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(value: 0.65, minHeight: 8, backgroundColor: Colors.white.withOpacity(0.2), valueColor: const AlwaysStoppedAnimation<Color>(Colors.white)),
                    ),
                    const SizedBox(height: 10),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text('65% Paid  •  16 of 24 payments', style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 12)),
                      const Text('₱17,500 left', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ]),
                  ]),
                ]),
              ),
              const SizedBox(height: 24),

              // ── Loan Breakdown ─────────────────────────────────────────────
              _sectionTitle('Loan Breakdown'),
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
                child: Column(children: [
                  _detailRow('Principal Amount', '₱50,000.00', primary, isFirst: true),
                  _divider(),
                  _detailRow('Interest Rate', '2.00% / month', primary),
                  _divider(),
                  _detailRow('Loan Term', '24 months', primary),
                  _divider(),
                  _detailRow('Monthly Payment', '₱2,458.33', primary, highlight: true),
                  _divider(),
                  _detailRow('Total Interest', '₱9,000.00', primary),
                  _divider(),
                  _detailRow('Total Amount', '₱59,000.00', primary),
                  _divider(),
                  _detailRow('Remaining Balance', '₱17,500.00', primary, highlight: true),
                  _divider(),
                  _detailRow('Next Due Date', 'Mar 25, 2026', AppColors.warning),
                  _divider(),
                  _detailRow('Release Date', 'Jan 15, 2026', primary, isLast: true),
                ]),
              ),
              const SizedBox(height: 24),

              // ── Stats Row ──────────────────────────────────────────────────
              Row(children: [
                Expanded(child: _miniStatCard('On-Time\nPayments', '16/16', AppColors.success, AppColors.successLight)),
                const SizedBox(width: 12),
                Expanded(child: _miniStatCard('Days\nOverdue', '0 days', AppColors.info, AppColors.infoLight)),
                const SizedBox(width: 12),
                Expanded(child: _miniStatCard('Credit\nRating', 'Excellent', const Color(0xFF6366F1), const Color(0xFFE0E7FF))),
              ]),
              const SizedBox(height: 24),

              // ── Payment Schedule ───────────────────────────────────────────
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                _sectionTitle('Payment Schedule'),
                Text('24 payments', style: TextStyle(fontSize: 13, color: primary, fontWeight: FontWeight.w600)),
              ]),
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.separator), boxShadow: AppColors.cardShadow),
                child: Column(children: [
                  // Header
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(color: AppColors.surfaceVariant, borderRadius: const BorderRadius.vertical(top: Radius.circular(20))),
                    child: const Row(children: [
                      SizedBox(width: 32, child: Text('#', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textMuted))),
                      Expanded(child: Text('DUE DATE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textMuted))),
                      SizedBox(width: 85, child: Text('AMOUNT', textAlign: TextAlign.right, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textMuted))),
                      SizedBox(width: 70, child: Text('STATUS', textAlign: TextAlign.right, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textMuted))),
                    ]),
                  ),
                  // Only show first 6 rows + see all
                  ...schedule.take(6).map((s) => _scheduleRow(s, primary)),
                  if (schedule.length > 6)
                    GestureDetector(
                      onTap: () => _showFullSchedule(context, schedule, primary),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        decoration: const BoxDecoration(borderRadius: BorderRadius.vertical(bottom: Radius.circular(20))),
                        child: Center(child: Text('View all ${schedule.length} payments →', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: primary))),
                      ),
                    ),
                ]),
              ),
              const SizedBox(height: 28),

              // ── Action Buttons ─────────────────────────────────────────────
              SizedBox(
                width: double.infinity,
                height: 54,
                child: ElevatedButton.icon(
                  onPressed: () {},
                  icon: const Icon(Icons.payments_rounded),
                  label: const Text('Make a Payment', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 54,
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoanApplicationScreen())),
                  icon: Icon(Icons.add_circle_outline, color: primary),
                  label: Text('Apply Another Loan', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: primary)),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: primary, width: 1.5),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                ),
              ),
            ])),
          ),
        ],
      ),
    );
  }

  String _dueDate(int n) {
    final base = DateTime(2026, 1, 15);
    final d = DateTime(base.year, base.month + n, base.day);
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return '${months[d.month - 1]} ${d.day}, ${d.year}';
  }

  Widget _sectionTitle(String t) => Text(t, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4));

  Widget _statusBadge(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.circle, color: Colors.white, size: 6),
      const SizedBox(width: 4),
      Text(label.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
    ]),
  );

  Widget _detailRow(String label, String value, Color primary, {bool highlight = false, bool isFirst = false, bool isLast = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: const TextStyle(fontSize: 14, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
        Text(value, style: TextStyle(fontSize: highlight ? 16 : 14, fontWeight: FontWeight.w700, color: highlight ? primary : AppColors.textMain)),
      ]),
    );
  }

  Widget _divider() => const Divider(height: 1, indent: 18, endIndent: 18, color: AppColors.separator);

  Widget _miniStatCard(String label, String value, Color color, Color bg) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(16), border: Border.all(color: color.withOpacity(0.2))),
      child: Column(children: [
        Text(value, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: color), textAlign: TextAlign.center),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 10, color: AppColors.textMuted, fontWeight: FontWeight.w600, height: 1.3), textAlign: TextAlign.center),
      ]),
    );
  }

  Widget _scheduleRow(_ScheduleItem s, Color primary) {
    Color statusColor;
    Color statusBg;
    String statusLabel;
    switch (s.status) {
      case 'Paid': statusColor = AppColors.success; statusBg = AppColors.successLight; statusLabel = '✓ Paid'; break;
      case 'Due': statusColor = AppColors.warning; statusBg = AppColors.warningLight; statusLabel = '⏰ Due'; break;
      default: statusColor = AppColors.textLight; statusBg = AppColors.surfaceVariant; statusLabel = 'Pending';
    }
    return Container(
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.separator, width: 0.5))),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(children: [
        SizedBox(width: 32, child: Text('${s.no}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textMuted))),
        Expanded(child: Text(s.dueDate, style: const TextStyle(fontSize: 13, color: AppColors.textMain, fontWeight: FontWeight.w500))),
        SizedBox(width: 85, child: Text('₱${s.total.toStringAsFixed(2)}', textAlign: TextAlign.right, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textMain))),
        const SizedBox(width: 8),
        SizedBox(width: 62, child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
          decoration: BoxDecoration(color: statusBg, borderRadius: BorderRadius.circular(6)),
          child: Text(statusLabel, textAlign: TextAlign.center, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: statusColor)),
        )),
      ]),
    );
  }

  void _showFullSchedule(BuildContext context, List<_ScheduleItem> schedule, Color primary) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        initialChildSize: 0.85,
        maxChildSize: 0.95,
        minChildSize: 0.5,
        builder: (_, ctrl) => Container(
          decoration: const BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
          child: Column(children: [
            const SizedBox(height: 12),
            Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.separator, borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Full Payment Schedule', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textMain)),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
              ]),
            ),
            const SizedBox(height: 8),
            Expanded(child: ListView.builder(
              controller: ctrl,
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              itemCount: schedule.length,
              itemBuilder: (_, i) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Container(
                  decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.separator)),
                  child: _scheduleRow(schedule[i], primary),
                ),
              ),
            )),
          ]),
        ),
      ),
    );
  }
}

class _ScheduleItem {
  final int no;
  final String dueDate;
  final double principal, interest, total;
  final String status;
  const _ScheduleItem({required this.no, required this.dueDate, required this.principal, required this.interest, required this.total, required this.status});
}
