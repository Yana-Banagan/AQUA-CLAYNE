import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/app_button.dart';
import '../../shared_widgets/driver_scaffold.dart';

class StationIntakeScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const StationIntakeScreen({super.key, required this.delivery});

  @override
  State<StationIntakeScreen> createState() => _StationIntakeScreenState();
}

class _StationIntakeScreenState extends State<StationIntakeScreen> {
  Future<void> _confirm() async {
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.confirmStationArrival(widget.delivery.id);
    if (!mounted) return;
    if (ok) {
      await prov.loadDetail(widget.delivery.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Station arrival confirmed.'),
          backgroundColor: DriverColors.primary,
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to confirm arrival.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.delivery;

    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Station Arrival / Intake',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
      ),
      body: Padding(
        padding: const EdgeInsets.all(AppSpacing.standard),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Station arrival banner
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(AppSpacing.standard),
              decoration: BoxDecoration(
                color: DriverColors.mintSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: DriverColors.border),
              ),
              child: const Column(
                children: [
                  Icon(Icons.store, color: DriverColors.primary, size: 40),
                  SizedBox(height: AppSpacing.small),
                  Text(
                    'Arrived at Station',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.primaryDark,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Please hand over the collected customer gallons\nto station staff for inspection and refill queueing.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.standard),

            // Intake summary
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
                  const Text(
                    'Intake Summary',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.primaryDark,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.compact),
                  _IntakeRow('Order Number', d.orderNumber),
                  _IntakeRow('Customer', d.customerName),
                  _IntakeRow(
                    'Expected Gallons',
                    '${d.expectedGallons} customer container${d.expectedGallons != 1 ? 's' : ''}',
                  ),
                  _IntakeRow('Status', d.status),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.standard),

            // Note about queue eligibility
            Container(
              padding: const EdgeInsets.all(AppSpacing.compact),
              decoration: BoxDecoration(
                color: DriverColors.mintSurface,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: DriverColors.border),
              ),
              child: const Row(
                children: [
                  Icon(
                    Icons.info_outline,
                    color: DriverColors.primary,
                    size: 16,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Refill queueing begins only after station staff physically receives and reconciles the customer containers.',
                      style: TextStyle(
                        fontSize: 12,
                        color: DriverColors.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const Spacer(),

            Consumer<DriverDeliveryProvider>(
              builder: (_, prov, _) => AppButton(
                label: 'Confirm Station Arrival',
                icon: Icons.store_outlined,
                loading: prov.actionLoading,
                onPressed: prov.actionLoading ? null : _confirm,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _IntakeRow extends StatelessWidget {
  final String label;
  final String value;
  const _IntakeRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 13,
              color: DriverColors.textSecondary,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: DriverColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
