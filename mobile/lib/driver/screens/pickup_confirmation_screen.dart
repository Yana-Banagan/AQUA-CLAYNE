import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/app_button.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/gallon_quantity_stepper.dart';

class PickupConfirmationScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const PickupConfirmationScreen({super.key, required this.delivery});

  @override
  State<PickupConfirmationScreen> createState() =>
      _PickupConfirmationScreenState();
}

class _PickupConfirmationScreenState extends State<PickupConfirmationScreen> {
  late int _collected;
  bool _mismatchAcknowledged = false;

  @override
  void initState() {
    super.initState();
    _collected = widget.delivery.expectedGallons;
  }

  bool get _hasMismatch => _collected != widget.delivery.expectedGallons;

  Future<void> _confirm() async {
    if (_hasMismatch && !_mismatchAcknowledged) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Quantity Mismatch'),
          content: Text(
            'Expected ${widget.delivery.expectedGallons} gallons but '
            'you are collecting $_collected. '
            'Please confirm this is correct.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Go Back'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: DriverColors.primary,
                foregroundColor: Colors.white,
              ),
              child: const Text('Confirm Anyway'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      setState(() => _mismatchAcknowledged = true);
    }

    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.confirmPickup(widget.delivery.id, _collected);
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pickup confirmed successfully.'),
          backgroundColor: DriverColors.primary,
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to confirm pickup.'),
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
          'Confirm Gallon Pickup',
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
            // Order summary
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
                  Text(
                    d.orderNumber,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.primaryDark,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    d.customerName,
                    style: const TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary,
                    ),
                  ),
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

            // Gallon counter
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
                    'Count Customer Empty Gallons',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: DriverColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.standard),

                  // Expected
                  Row(
                    children: [
                      const Text(
                        'Expected Containers:',
                        style: TextStyle(
                          fontSize: 13,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${d.expectedGallons} gallons',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.standard),

                  // Stepper
                  Row(
                    children: [
                      const Text(
                        'Actual Collected:',
                        style: TextStyle(
                          fontSize: 13,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                      const Spacer(),
                      GallonQuantityStepper(
                        value: _collected,
                        min: 0,
                        max: 50,
                        onChanged: (v) => setState(() => _collected = v),
                      ),
                    ],
                  ),

                  const SizedBox(height: AppSpacing.standard),

                  // Match / mismatch indicator
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: _hasMismatch
                          ? DriverColors.warningLight
                          : DriverColors.successLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          _hasMismatch
                              ? Icons.warning_amber_outlined
                              : Icons.check_circle_outline,
                          color: _hasMismatch
                              ? DriverColors.warning
                              : DriverColors.success,
                          size: 18,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _hasMismatch
                              ? 'Quantity mismatch — confirm before proceeding'
                              : 'Quantity matches expected count ✓',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: _hasMismatch
                                ? DriverColors.warning
                                : DriverColors.success,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const Spacer(),

            // Confirm button
            Consumer<DriverDeliveryProvider>(
              builder: (_, prov, _) => AppButton(
                label: 'Confirm Pickup & Handover to Station',
                icon: Icons.check_circle_outline,
                loading: prov.actionLoading,
                onPressed: prov.actionLoading ? null : _confirm,
              ),
            ),
            const SizedBox(height: AppSpacing.compact),
            AppButton(
              label: 'Report Pickup Failure',
              variant: AppButtonVariant.danger,
              icon: Icons.report_outlined,
              onPressed: () => _reportFailure(),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _reportFailure() async {
    final reasonCtrl = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Report Pickup Failure'),
        content: TextField(
          controller: reasonCtrl,
          decoration: const InputDecoration(labelText: 'Reason'),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: DriverColors.error,
              foregroundColor: Colors.white,
            ),
            child: const Text('Report'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.reportPickupFailure(
      widget.delivery.id,
      reasonCtrl.text.trim(),
    );
    if (!mounted) return;
    if (ok) {
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to report.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }
}
