import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/app_button.dart';
import '../../shared_widgets/app_card.dart';
import 'out_for_delivery_screen.dart';

class ReadyForDeliveryScreen extends StatelessWidget {
  final DeliveryModel delivery;
  const ReadyForDeliveryScreen({super.key, required this.delivery});

  Future<void> _startDelivery(BuildContext context) async {
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.startDelivery(delivery.id);
    if (!context.mounted) return;
    if (ok) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => OutForDeliveryScreen(delivery: delivery),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to start delivery.'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DriverColors.background,
      appBar: AppBar(title: const Text('Ready for Delivery')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.standard),
          child: Column(
            children: [
              // Ready banner
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.section),
                decoration: BoxDecoration(
                  color: AppColors.successLight,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                      color: AppColors.success.withValues(alpha: 0.3)),
                ),
                child: Column(children: [
                  const Icon(Icons.check_circle_rounded,
                      color: AppColors.success, size: 56),
                  const SizedBox(height: AppSpacing.compact),
                  const Text('Order Ready!',
                      style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: AppColors.success)),
                  const SizedBox(height: 4),
                  Text(
                    '${delivery.expectedGallons} gallons are refilled and ready.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                        fontSize: 14, color: DriverColors.textSecondary),
                  ),
                ]),
              ),

              const SizedBox(height: AppSpacing.standard),

              // Order info
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(delivery.orderNumber,
                        style: const TextStyle(
                            fontSize: 15, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    _InfoRow(Icons.person_outline, delivery.customerName),
                    const SizedBox(height: 4),
                    _InfoRow(Icons.location_on_outlined, delivery.fullAddress),
                    const SizedBox(height: 4),
                    _InfoRow(Icons.water_drop_outlined,
                        '${delivery.expectedGallons} filled gallons'),
                    const SizedBox(height: 4),
                    _InfoRow(Icons.payments_outlined,
                        '₱${delivery.totalAmount.toStringAsFixed(2)} — ${delivery.paymentMethod == 'CASH' ? 'Cash' : 'Credit'}'),
                  ],
                ),
              ),

              // Instruction
              Container(
                padding: const EdgeInsets.all(AppSpacing.compact),
                decoration: BoxDecoration(
                  color: AppColors.warningLight,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Row(children: [
                  Icon(Icons.info_outline,
                      color: AppColors.warning, size: 16),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Collect the filled gallons from station staff before tapping Start Delivery.',
                      style: TextStyle(
                          fontSize: 12, color: DriverColors.textSecondary),
                    ),
                  ),
                ]),
              ),

              const Spacer(),

              Consumer<DriverDeliveryProvider>(
                builder: (_, prov, _) => AppButton(
                  label: 'Collect Filled Gallons & Start Delivery',
                  icon: Icons.local_shipping_outlined,
                  loading: prov.actionLoading,
                  onPressed: prov.actionLoading
                      ? null
                      : () => _startDelivery(context),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String text;
  const _InfoRow(this.icon, this.text);

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 15, color: DriverColors.textSecondary),
        const SizedBox(width: 6),
        Expanded(
          child: Text(text,
              style: const TextStyle(
                  fontSize: 13, color: DriverColors.textSecondary)),
        ),
      ],
    );
  }
}
