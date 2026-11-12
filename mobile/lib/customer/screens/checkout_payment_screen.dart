import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../customer/data/order_repository.dart';
import '../../customer/providers/cart_provider.dart';
import 'checkout_review_screen.dart';
import 'checkout_step_bar.dart';

class CheckoutPaymentScreen extends StatefulWidget {
  final String? addressId;
  final int gallons;
  final String? notes;
  final String? initialMethod;
  const CheckoutPaymentScreen({super.key, this.addressId, required this.gallons, this.notes, this.initialMethod});

  @override
  State<CheckoutPaymentScreen> createState() => _CheckoutPaymentScreenState();
}

class _CheckoutPaymentScreenState extends State<CheckoutPaymentScreen> {
  String _method = 'ONLINE_QRPH';
  bool _loading = true;
  bool _firstOrder = true;

  @override
  void initState() {
    super.initState();
    _method = widget.initialMethod == 'ONLINE' ? 'ONLINE_QRPH' : (widget.initialMethod ?? 'ONLINE_QRPH');
    _loadRules();
  }

  Future<void> _loadRules() async {
    try {
      final rules = await context.read<OrderRepository>().paymentOptions();
      if (!mounted) return;
      setState(() {
        _firstOrder = rules['first_order'] == true;
        if (_firstOrder && !_method.startsWith('ONLINE')) _method = 'ONLINE_QRPH';
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _continue() => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CheckoutReviewScreen(
            addressId: widget.addressId,
            gallons: widget.gallons,
            paymentMethod: _method,
            notes: widget.notes,
          ),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(image: DecorationImage(image: AssetImage('assets/images/customer_dashboard_bg.png'), fit: BoxFit.cover)),
        child: SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(children: [
                IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0878C9))),
                const SizedBox(width: 8),
                const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 19, color: Color(0xFF12344D))),
                  Text('Select how you will pay', style: TextStyle(fontSize: 12, color: Color(0xFF52718B))),
                ]),
              ]),
            ),
            const CheckoutStepBar(step: 3),
            Expanded(
              child: ListView(padding: const EdgeInsets.all(16), children: [
                if (_loading)
                  const Center(child: Padding(padding: EdgeInsets.all(36), child: CircularProgressIndicator()))
                else ...[
                  if (_firstOrder) _firstOrderNotice(),
                  if (_firstOrder) const SizedBox(height: 14),
                  if (!_firstOrder) ...[
                    _methodTile(
                      cart.isStationPickup ? 'Cash on Pickup' : 'Cash on Delivery',
                      cart.isStationPickup
                          ? 'Pay at the station counter upon collecting your order.'
                          : 'Pay the driver when your order arrives.',
                      Icons.payments_rounded,
                      'CASH',
                    ),
                    const SizedBox(height: 12),
                    _methodTile('Debt', 'Record this order as payable to Aqua Clayne.', Icons.receipt_long_rounded, 'DEBT'),
                    const SizedBox(height: 12),
                  ],
                  _methodTile('QR Ph', 'Scan the QR code with a supported bank or e-wallet app.', Icons.qr_code_2_rounded, 'ONLINE_QRPH'),
                  const SizedBox(height: 10),
                  _methodTile('GCash', 'Pay securely through PayMongo using GCash.', Icons.account_balance_wallet_rounded, 'ONLINE_GCASH'),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .94), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFD5EAF8))),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Text('Order Total', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF12344D))),
                      const SizedBox(height: 8),
                      _totalRow('Subtotal', '₱${cart.subtotal.toStringAsFixed(2)}'),
                      _totalRow(
                        'Delivery fee',
                        cart.isStationPickup
                            ? 'FREE (₱0.00)'
                            : '₱${cart.deliveryFee.toStringAsFixed(2)}',
                      ),
                      const Divider(),
                      _totalRow('Total', '₱${cart.total.toStringAsFixed(2)}', bold: true),
                    ]),
                  ),
                ],
              ]),
            ),
            SafeArea(top: false, child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: SizedBox(width: double.infinity, height: 50, child: ElevatedButton(
                onPressed: _loading ? null : _continue,
                child: const Text('Continue to Review'),
              )),
            )),
          ]),
        ),
      ),
    );
  }

  Widget _firstOrderNotice() => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: const Color(0xFFEAF7FF), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFBCE3FD))),
    child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(Icons.lock_outline_rounded, color: Color(0xFF0878C9)), SizedBox(width: 10),
      Expanded(child: Text('Your first order requires secure online payment. Cash on Delivery and Debt become available after your first successful payment.', style: TextStyle(fontSize: 12, height: 1.4, color: Color(0xFF335C7D)))),
    ]),
  );

  Widget _methodTile(String title, String subtitle, IconData icon, String value) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: () => setState(() => _method = value),
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .96), borderRadius: BorderRadius.circular(18), border: Border.all(color: _method == value ? const Color(0xFF0878C9) : const Color(0xFFD5EAF8), width: _method == value ? 2 : 1)),
        child: Row(children: [
          Icon(icon, color: const Color(0xFF0878C9)), const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF12344D))), const SizedBox(height: 3), Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF52718B)))])),
          Radio<String>(value: value, groupValue: _method, onChanged: (v) => setState(() => _method = v!)),
        ]),
      ),
    ),
  );

  Widget _totalRow(String label, String value, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3), child: Row(children: [Text(label, style: TextStyle(color: const Color(0xFF52718B), fontWeight: bold ? FontWeight.w800 : FontWeight.w500)), const Spacer(), Text(value, style: TextStyle(color: bold ? const Color(0xFF0878C9) : const Color(0xFF12344D), fontSize: bold ? 18 : 14, fontWeight: FontWeight.w800))]),
  );
}
