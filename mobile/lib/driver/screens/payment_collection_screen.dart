import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';

class PaymentCollectionScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const PaymentCollectionScreen({super.key, required this.delivery});

  @override
  State<PaymentCollectionScreen> createState() =>
      _PaymentCollectionScreenState();
}

class _PaymentCollectionScreenState extends State<PaymentCollectionScreen> {
  late final TextEditingController _cashCtrl;
  bool _submitting = false;

  DeliveryModel get d => widget.delivery;

  @override
  void initState() {
    super.initState();
    _cashCtrl = TextEditingController(
      text: d.totalAmount > 0 ? d.totalAmount.toStringAsFixed(2) : '',
    );
  }

  @override
  void dispose() {
    _cashCtrl.dispose();
    super.dispose();
  }

  double get _cashReceived => double.tryParse(_cashCtrl.text.trim()) ?? 0;
  double get _change => (_cashReceived - d.totalAmount).clamp(0, double.infinity);
  bool get _isSufficient => _cashReceived >= d.totalAmount;

  Future<void> _recordPayment() async {
    if (!_isSufficient) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Cash received cannot be less than the order amount.'),
          backgroundColor: DriverColors.error,
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.collectPayment(d.id, _cashReceived);

    if (!mounted) return;
    setState(() => _submitting = false);

    if (ok) {
      await prov.loadDetail(d.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Cash collection recorded successfully!'),
          backgroundColor: DriverColors.primary,
        ),
      );
      Navigator.pop(context, true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to record payment collection.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final isCash = d.isCash;
    final isPaid = d.isPaid;

    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Payment Collection',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.standard),
        children: [
          // Order Summary Card
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 8,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: DriverColors.mintSoft,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        d.orderNumber,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${d.expectedGallons} Gallons Refill',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: DriverColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  d.customerName,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: DriverColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  d.fullAddress,
                  style: const TextStyle(
                    fontSize: 13,
                    color: DriverColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          if (isCash) ...[
            // Cash Collection Card
            Container(
              padding: const EdgeInsets.all(AppSpacing.standard),
              decoration: BoxDecoration(
                color: DriverColors.cardTranslucent,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: DriverColors.borderLight),
                boxShadow: const [
                  BoxShadow(
                    color: DriverColors.shadow,
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(
                        Icons.payments_outlined,
                        color: DriverColors.primary,
                        size: 20,
                      ),
                      SizedBox(width: 8),
                      Text(
                        'CASH PAYMENT',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: DriverColors.primaryDark,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Amount due
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: DriverColors.mintSoft,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: DriverColors.borderLight),
                    ),
                    child: Column(
                      children: [
                        const Text(
                          'Cash Amount to Collect',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '₱${d.totalAmount.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 28,
                            fontWeight: FontWeight.w800,
                            color: DriverColors.primaryDark,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  if (isPaid) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: DriverColors.successLight,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.check_circle, color: DriverColors.success),
                          SizedBox(width: 8),
                          Text(
                            'Cash payment has already been collected.',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.success,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ] else ...[
                    // Cash received input
                    const Text(
                      'Cash Tendered by Customer (₱)',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: DriverColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _cashCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      inputFormatters: [
                        FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d*')),
                      ],
                      onChanged: (_) => setState(() {}),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: DriverColors.primaryDark,
                      ),
                      decoration: InputDecoration(
                        prefixIcon: const Icon(
                          Icons.attach_money,
                          color: DriverColors.primary,
                        ),
                        filled: true,
                        fillColor: DriverColors.mintSurface,
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: DriverColors.border),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(
                            color: DriverColors.primary,
                            width: 1.5,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Quick change calculation
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Change to return:',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                        Text(
                          '₱${_change.toStringAsFixed(2)}',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: _isSufficient
                                ? DriverColors.primaryDark
                                : DriverColors.error,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),

                    // Record Payment Button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: (_submitting || !_isSufficient)
                            ? null
                            : _recordPayment,
                        icon: _submitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                    Colors.white,
                                  ),
                                ),
                              )
                            : const Icon(Icons.check, size: 18),
                        label: Text(
                          _submitting
                              ? 'Recording Payment...'
                              : 'Record Cash Collection',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: DriverColors.primary,
                          foregroundColor: Colors.white,
                          disabledBackgroundColor: Colors.grey.shade300,
                          disabledForegroundColor: Colors.grey.shade600,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ] else if (d.isOnline) ...[
            // Online (PayMongo) Verified Card
            Container(
              padding: const EdgeInsets.all(AppSpacing.standard),
              decoration: BoxDecoration(
                color: DriverColors.cardTranslucent,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: DriverColors.mintAccent.withValues(alpha: 0.5)),
                boxShadow: const [
                  BoxShadow(
                    color: DriverColors.shadow,
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  const Icon(
                    Icons.check_circle_rounded,
                    size: 48,
                    color: DriverColors.success,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Paid Online via PayMongo',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'This order was paid online prior to delivery. No cash collection is required from the driver.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: DriverColors.mintSoft,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: DriverColors.border),
                    ),
                    child: Text(
                      'Verified Paid: ₱${d.totalAmount.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: DriverColors.primaryDark,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ] else ...[
            // Credit Account Card
            Container(
              padding: const EdgeInsets.all(AppSpacing.standard),
              decoration: BoxDecoration(
                color: DriverColors.cardTranslucent,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: DriverColors.borderLight),
                boxShadow: const [
                  BoxShadow(
                    color: DriverColors.shadow,
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  const Icon(
                    Icons.credit_score,
                    size: 44,
                    color: DriverColors.info,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Approved Credit Transaction',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'This order is billed to the customer’s verified credit account. No cash collection is required from the driver.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: DriverColors.infoLight,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      'Billed Amount: ₱${d.totalAmount.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: DriverColors.info,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
