import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../main.dart';
import '../theme.dart';

class LoanApplicationScreen extends StatefulWidget {
  const LoanApplicationScreen({super.key});
  @override
  State<LoanApplicationScreen> createState() => _LoanApplicationScreenState();
}

class _LoanApplicationScreenState extends State<LoanApplicationScreen> {
  int _selectedProduct = 0;
  double _loanAmount = 25000;
  int _selectedTerm = 12;
  final _purposeController = TextEditingController();
  bool _isSubmitting = false;

  static const _products = [
    {'name': 'Personal Loan', 'rate': 2.0, 'min': 5000.0, 'max': 100000.0, 'icon': Icons.person_outline_rounded},
    {'name': 'Business Loan', 'rate': 2.5, 'min': 10000.0, 'max': 500000.0, 'icon': Icons.business_center_outlined},
    {'name': 'Emergency Loan', 'rate': 1.5, 'min': 1000.0, 'max': 30000.0, 'icon': Icons.local_hospital_outlined},
  ];
  final _terms = [3, 6, 12, 18, 24];

  Map<String, dynamic> get _product => _products[_selectedProduct];
  double get _rate => (_product['rate'] as double) / 100;
  double get _monthly => (_loanAmount + (_loanAmount * _rate * _selectedTerm)) / _selectedTerm;
  double get _totalInterest => _monthly * _selectedTerm - _loanAmount;

  @override
  void dispose() { _purposeController.dispose(); super.dispose(); }

  void _submit() async {
    HapticFeedback.mediumImpact();
    setState(() => _isSubmitting = true);
    await Future.delayed(const Duration(milliseconds: 1800));
    if (!mounted) return;
    setState(() => _isSubmitting = false);
    final primary = activeTenant.value.primaryColor;
    showDialog(context: context, barrierDismissible: false, builder: (_) =>
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        contentPadding: const EdgeInsets.all(28),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 72, height: 72,
            decoration: const BoxDecoration(color: AppColors.successLight, shape: BoxShape.circle),
            child: const Icon(Icons.check_rounded, color: AppColors.success, size: 36)),
          const SizedBox(height: 20),
          const Text('Application Submitted!', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4)),
          const SizedBox(height: 10),
          const Text("Your application has been submitted. We'll review it within 24–48 hours.", textAlign: TextAlign.center, style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.5)),
          const SizedBox(height: 24),
          SizedBox(width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.pop(context),
              style: ElevatedButton.styleFrom(backgroundColor: primary, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), padding: const EdgeInsets.symmetric(vertical: 14)),
              child: const Text('Done'))),
        ]),
      ));
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.primaryColor;
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
            title: const Text('Apply for a Loan', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700)),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 60),
            sliver: SliverList(delegate: SliverChildListDelegate([
              _sectionLabel('Select Loan Product'),
              const SizedBox(height: 12),
              ..._products.asMap().entries.map((e) => _productCard(e.key, e.value, primary)),
              const SizedBox(height: 24),
              _sectionLabel('Loan Amount'),
              const SizedBox(height: 12),
              _amountDisplay(primary),
              const SizedBox(height: 6),
              SliderTheme(
                data: SliderTheme.of(context).copyWith(activeTrackColor: primary, inactiveTrackColor: AppColors.surfaceVariant, thumbColor: primary, overlayColor: primary.withOpacity(0.15), trackHeight: 5),
                child: Slider(value: _loanAmount, min: _product['min'] as double, max: _product['max'] as double, onChanged: (v) => setState(() => _loanAmount = v)),
              ),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Min: ₱${(_product['min'] as double).toInt()}', style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
                Text('Max: ₱${(_product['max'] as double).toInt()}', style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
              ]),
              const SizedBox(height: 24),
              _sectionLabel('Loan Term (months)'),
              const SizedBox(height: 12),
              _termSelector(primary),
              const SizedBox(height: 24),
              _sectionLabel('Loan Purpose (Optional)'),
              const SizedBox(height: 12),
              TextFormField(
                controller: _purposeController,
                maxLines: 3,
                style: const TextStyle(fontSize: 14, color: AppColors.textMain),
                decoration: const InputDecoration(hintText: 'Briefly describe your loan purpose...'),
              ),
              const SizedBox(height: 28),
              _summaryCard(primary),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submit,
                  style: ElevatedButton.styleFrom(backgroundColor: primary, disabledBackgroundColor: primary.withOpacity(0.6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
                  child: _isSubmitting
                    ? const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                        SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5)),
                        SizedBox(width: 12),
                        Text('Submitting...', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                      ])
                    : const Text('Submit Application', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                ),
              ),
              const SizedBox(height: 12),
              const Center(child: Text('🔒  Your data is secured and encrypted', style: TextStyle(fontSize: 12, color: AppColors.textMuted))),
            ])),
          ),
        ],
      ),
    );
  }

  Widget _sectionLabel(String t) => Text(t, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textMain));

  Widget _productCard(int i, Map<String, dynamic> p, Color primary) {
    final sel = _selectedProduct == i;
    return GestureDetector(
      onTap: () => setState(() { _selectedProduct = i; _loanAmount = ((p['min'] as double) + (p['max'] as double)) / 2; }),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: sel ? primary.withOpacity(0.06) : AppColors.card,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: sel ? primary : AppColors.separator, width: sel ? 2 : 1),
          boxShadow: AppColors.cardShadow,
        ),
        child: Row(children: [
          Container(width: 44, height: 44,
            decoration: BoxDecoration(color: sel ? primary.withOpacity(0.12) : AppColors.surfaceVariant, borderRadius: BorderRadius.circular(12)),
            child: Icon(p['icon'] as IconData, color: sel ? primary : AppColors.textMuted, size: 22)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(p['name'] as String, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: sel ? primary : AppColors.textMain)),
            Text('${p['rate']}% / month  •  Up to ₱${((p['max'] as double) / 1000).toInt()}K', style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ])),
          Container(width: 22, height: 22,
            decoration: BoxDecoration(color: sel ? primary : Colors.transparent, shape: BoxShape.circle, border: Border.all(color: sel ? primary : AppColors.separator, width: 2)),
            child: sel ? const Icon(Icons.check, color: Colors.white, size: 14) : null),
        ]),
      ),
    );
  }

  Widget _amountDisplay(Color primary) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
    decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: primary, width: 2), boxShadow: AppColors.cardShadow),
    child: Row(children: [
      Text('₱', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: primary)),
      const SizedBox(width: 8),
      Expanded(child: Text(_loanAmount.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},'),
        style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: primary, letterSpacing: -0.5))),
      Icon(Icons.edit_outlined, color: primary, size: 18),
    ]),
  );

  Widget _termSelector(Color primary) => Row(
    children: _terms.asMap().entries.map((e) {
      final sel = _selectedTerm == e.value;
      return Expanded(child: GestureDetector(
        onTap: () => setState(() => _selectedTerm = e.value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          margin: EdgeInsets.only(right: e.key < _terms.length - 1 ? 8 : 0),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(color: sel ? primary : AppColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: sel ? primary : AppColors.separator, width: sel ? 2 : 1)),
          child: Column(children: [
            Text('${e.value}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: sel ? Colors.white : AppColors.textMain)),
            Text('mo', style: TextStyle(fontSize: 10, color: sel ? Colors.white70 : AppColors.textMuted)),
          ]),
        ),
      ));
    }).toList(),
  );

  Widget _summaryCard(Color primary) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: primary.withOpacity(0.05),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: primary.withOpacity(0.2), width: 1.5),
    ),
    child: Column(children: [
      Row(children: [Icon(Icons.receipt_long_outlined, color: primary, size: 18), const SizedBox(width: 8), Text('Loan Summary', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: primary))]),
      const SizedBox(height: 16),
      _summaryRow('Principal Amount', '₱${_loanAmount.toStringAsFixed(2)}', false, primary),
      const SizedBox(height: 10),
      _summaryRow('Interest Rate', '${_product['rate']}% / month', false, primary),
      const SizedBox(height: 10),
      _summaryRow('Loan Term', '$_selectedTerm months', false, primary),
      const SizedBox(height: 10),
      _summaryRow('Total Interest', '₱${_totalInterest.toStringAsFixed(2)}', false, primary),
      Divider(height: 24, color: primary.withOpacity(0.2)),
      _summaryRow('Monthly Payment', '₱${_monthly.toStringAsFixed(2)}', true, primary),
      const SizedBox(height: 8),
      _summaryRow('Total Amount', '₱${(_monthly * _selectedTerm).toStringAsFixed(2)}', true, primary),
    ]),
  );

  Widget _summaryRow(String label, String val, bool highlight, Color primary) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      Text(label, style: TextStyle(fontSize: highlight ? 14 : 13, fontWeight: highlight ? FontWeight.w700 : FontWeight.w500, color: highlight ? AppColors.textMain : AppColors.textMuted)),
      Text(val, style: TextStyle(fontSize: highlight ? 16 : 14, fontWeight: FontWeight.w800, color: highlight ? primary : AppColors.textMain)),
    ],
  );
}
