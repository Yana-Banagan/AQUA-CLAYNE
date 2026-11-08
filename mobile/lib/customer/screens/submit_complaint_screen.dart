import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/complaint_provider.dart';
import '../../customer/providers/order_provider.dart';

class SubmitComplaintScreen extends StatefulWidget {
  final String? orderId;
  const SubmitComplaintScreen({super.key, this.orderId});

  @override
  State<SubmitComplaintScreen> createState() => _SubmitComplaintScreenState();
}

class _SubmitComplaintScreenState extends State<SubmitComplaintScreen> {
  final _formKey = GlobalKey<FormState>();
  final _subjectCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  String? _categoryId;
  String? _orderId;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _orderId = widget.orderId;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ComplaintProvider>().load();
      context.read<OrderProvider>().loadOrders();
    });
  }

  @override
  void dispose() {
    _subjectCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Please select a complaint category.'),
          backgroundColor: const Color(0xFFDC2626),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 3),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    final prov = context.read<ComplaintProvider>();

    final payload = {
      'complaint_category_id': _categoryId,
      if (_subjectCtrl.text.trim().isNotEmpty)
        'subject': _subjectCtrl.text.trim(),
      if (_orderId != null) 'order_id': _orderId,
      'description': _descCtrl.text.trim(),
    };

    final ok = await prov.submit(payload);

    if (!mounted) return;
    setState(() => _submitting = false);

    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
              SizedBox(width: 8),
              Text('Complaint submitted successfully.'),
            ],
          ),
          backgroundColor: const Color(0xFF16A34A),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
      Navigator.pop(context, true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to submit complaint. Please try again.'),
          backgroundColor: const Color(0xFFDC2626),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen Aqua Clayne water background ──────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Screen Content ────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildFormCard(),
                          const SizedBox(height: 18),
                          _buildSubmitButton(),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => Navigator.of(context).pop(),
              borderRadius: BorderRadius.circular(21),
              child: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFD5E7F4), width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.arrow_back_rounded,
                  color: Color(0xFF0878C9),
                  size: 22,
                ),
              ),
            ),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Submit Complaint',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Tell us about your concern',
                  style: TextStyle(
                    fontSize: 12,
                    color: Color(0xFF6F8BA3),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // FORM CARD
  // ====================================================================
  Widget _buildFormCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 1. Complaint Category
          const Text(
            'Concern Category *',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          Consumer<ComplaintProvider>(
            builder: (context, prov, _) {
              final categories = prov.categories;

              return DropdownButtonFormField<String>(
                initialValue: _categoryId,
                isExpanded: true,
                hint: const Text(
                  'Select concern category',
                  style: TextStyle(
                    fontSize: 13,
                    color: Color(0xFF9EBCD5),
                    fontWeight: FontWeight.w400,
                  ),
                ),
                decoration: _buildInputDecoration(
                  hintText: 'Select category',
                  prefixIcon: Icons.category_outlined,
                ),
                items: categories.map((c) {
                  return DropdownMenuItem<String>(
                    value: c.id,
                    child: Text(
                      c.name,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF12344D),
                      ),
                    ),
                  );
                }).toList(),
                onChanged: (val) => setState(() => _categoryId = val),
                validator: (v) => v == null ? 'Please select a category' : null,
              );
            },
          ),
          const SizedBox(height: 16),

          // 2. Subject / Topic
          const Text(
            'Subject (Optional)',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          TextFormField(
            controller: _subjectCtrl,
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
              color: Color(0xFF12344D),
            ),
            decoration: _buildInputDecoration(
              hintText: 'e.g. Delayed Refill Delivery',
              prefixIcon: Icons.title_rounded,
            ),
          ),
          const SizedBox(height: 16),

          // 3. Related Order (Optional)
          const Text(
            'Related Order (Optional)',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          Consumer<OrderProvider>(
            builder: (context, op, _) {
              return DropdownButtonFormField<String>(
                initialValue: _orderId,
                isExpanded: true,
                hint: const Text(
                  'Select order (optional)',
                  style: TextStyle(
                    fontSize: 13,
                    color: Color(0xFF9EBCD5),
                    fontWeight: FontWeight.w400,
                  ),
                ),
                decoration: _buildInputDecoration(
                  hintText: 'Select order',
                  prefixIcon: Icons.receipt_long_outlined,
                ),
                items: [
                  const DropdownMenuItem<String>(
                    value: null,
                    child: Text(
                      'None (General Concern)',
                      style: TextStyle(
                        fontSize: 13,
                        color: Color(0xFF6F8BA3),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  ...op.orders.map((o) {
                    final num = o.orderNumber.isNotEmpty
                        ? o.orderNumber
                        : o.id.substring(0, 8).toUpperCase();
                    return DropdownMenuItem<String>(
                      value: o.id,
                      child: Text(
                        'Order #$num (${o.status})',
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    );
                  }),
                ],
                onChanged: (val) => setState(() => _orderId = val),
              );
            },
          ),
          const SizedBox(height: 16),

          // 4. Description Text Area
          const Text(
            'Detailed Description *',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          TextFormField(
            controller: _descCtrl,
            maxLines: 5,
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w500,
              color: Color(0xFF12344D),
              height: 1.45,
            ),
            decoration: InputDecoration(
              hintText:
                  'Describe your issue in detail so our station team can resolve it quickly...',
              hintStyle: const TextStyle(
                fontSize: 13,
                color: Color(0xFF9EBCD5),
                fontWeight: FontWeight.w400,
              ),
              filled: true,
              fillColor: const Color(0xFFF7FAFD),
              contentPadding: const EdgeInsets.all(14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFD5E7F4), width: 1),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFD5E7F4), width: 1),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFF0878C9), width: 1.8),
              ),
              errorBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFDC2626), width: 1),
              ),
              focusedErrorBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFDC2626), width: 1.8),
              ),
            ),
            validator: (v) {
              if (v == null || v.trim().isEmpty) {
                return 'Please provide a description of your concern.';
              }
              if (v.trim().length < 10) {
                return 'Please provide at least 10 characters.';
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  InputDecoration _buildInputDecoration({
    required String hintText,
    required IconData prefixIcon,
  }) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: const TextStyle(
        fontSize: 13,
        color: Color(0xFF9EBCD5),
        fontWeight: FontWeight.w400,
      ),
      prefixIcon: Icon(prefixIcon, color: const Color(0xFF0878C9), size: 20),
      filled: true,
      fillColor: const Color(0xFFF7FAFD),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFD5E7F4), width: 1),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFD5E7F4), width: 1),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFF0878C9), width: 1.8),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFDC2626), width: 1),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFDC2626), width: 1.8),
      ),
    );
  }

  // ====================================================================
  // SUBMIT BUTTON
  // ====================================================================
  Widget _buildSubmitButton() {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: _submitting ? null : _submit,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF0878C9),
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        child: _submitting
            ? const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2.2,
                    ),
                  ),
                  SizedBox(width: 10),
                  Text(
                    'Submitting...',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                ],
              )
            : const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.send_rounded, size: 18),
                  SizedBox(width: 8),
                  Text(
                    'Submit Complaint',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
      ),
    );
  }
}
