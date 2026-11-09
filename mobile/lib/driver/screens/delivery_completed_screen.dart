import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../core/theme/driver_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../shared_widgets/driver_scaffold.dart';
import 'driver_shell.dart';

class DeliveryCompletedScreen extends StatelessWidget {
  final DeliveryModel delivery;
  const DeliveryCompletedScreen({super.key, required this.delivery});

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.section,
            vertical: AppSpacing.major,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Success badge
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  color: DriverColors.mintSoft,
                  shape: BoxShape.circle,
                  border: Border.all(color: DriverColors.primaryLight, width: 2),
                  boxShadow: const [
                    BoxShadow(
                      color: DriverColors.shadow,
                      blurRadius: 16,
                      offset: Offset(0, 6),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: DriverColors.primary,
                  size: 64,
                ),
              ),
              const SizedBox(height: AppSpacing.section),

              const Text(
                'Delivery Completed!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: DriverColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                delivery.orderNumber,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: DriverColors.primaryDark,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Successfully delivered to ${delivery.customerName}',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 14,
                  color: DriverColors.textSecondary,
                ),
              ),
              const SizedBox(height: AppSpacing.section),

              // Summary card
              Container(
                padding: const EdgeInsets.all(AppSpacing.standard),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: DriverColors.borderLight),
                  boxShadow: const [
                    BoxShadow(
                      color: DriverColors.shadow,
                      blurRadius: 10,
                      offset: Offset(0, 3),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    _SummaryRow(
                      icon: Icons.water_drop_rounded,
                      title: 'Gallons for Refill',
                      value: '${delivery.expectedGallons} gal delivered',
                    ),
                    const Divider(height: 20, color: DriverColors.borderLight),
                    _SummaryRow(
                      icon: Icons.person_rounded,
                      title: 'Customer',
                      value: delivery.customerName,
                    ),
                    const Divider(height: 20, color: DriverColors.borderLight),
                    _SummaryRow(
                      icon: Icons.payments_rounded,
                      title: 'Payment Status',
                      value: delivery.paymentMethod.toUpperCase() == 'CASH'
                          ? '₱${delivery.totalAmount.toStringAsFixed(2)} Collected'
                          : 'Credit Payment Approved',
                      isHighlight: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.section),

              // Back to Dashboard Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    context.read<DriverDeliveryProvider>().loadAssigned();
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const DriverShell(initialIndex: 0),
                      ),
                      (_) => false,
                    );
                  },
                  icon: const Icon(Icons.dashboard_rounded, color: Colors.white),
                  label: const Text(
                    'Back to Dashboard',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: DriverColors.primary,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 2,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.small),

              // View History Button
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () {
                    context.read<DriverDeliveryProvider>().loadAssigned();
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const DriverShell(initialIndex: 2),
                      ),
                      (_) => false,
                    );
                  },
                  icon: const Icon(Icons.history_rounded, color: DriverColors.primaryDark),
                  label: const Text(
                    'View Delivery History',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: DriverColors.primaryDark,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: Colors.white,
                    side: const BorderSide(color: DriverColors.border, width: 1.5),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
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

class _SummaryRow extends StatelessWidget {
  final IconData icon;
  final String title;
  final String value;
  final bool isHighlight;

  const _SummaryRow({
    required this.icon,
    required this.title,
    required this.value,
    this.isHighlight = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: DriverColors.mintSoft,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: DriverColors.primary, size: 18),
        ),
        const SizedBox(width: AppSpacing.compact),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 12,
                  color: DriverColors.textMuted,
                  fontWeight: FontWeight.w500,
                ),
              ),
              Text(
                value,
                style: TextStyle(
                  fontSize: 14,
                  color: isHighlight ? DriverColors.primaryDark : DriverColors.textPrimary,
                  fontWeight: isHighlight ? FontWeight.w700 : FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
