import 'package:flutter/material.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../services/navigation_launcher.dart';
import 'delivery_detail_screen.dart';
import 'proof_of_delivery_screen.dart';

class OutForDeliveryScreen extends StatelessWidget {
  final DeliveryModel delivery;
  const OutForDeliveryScreen({super.key, required this.delivery});

  @override
  Widget build(BuildContext context) {
    final d = delivery;

    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Out for Delivery',
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
          // Status banner
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.mintSoft,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.border),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.delivery_dining,
                  color: DriverColors.primary,
                  size: 36,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'On the Way to Customer!',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${d.expectedGallons} refilled gallons for ${d.customerName}',
                        style: const TextStyle(
                          fontSize: 13,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Destination card
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
                      Icons.location_on_outlined,
                      size: 16,
                      color: DriverColors.primary,
                    ),
                    SizedBox(width: 6),
                    Text(
                      'DELIVERY DESTINATION',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: DriverColors.primaryDark,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  d.customerName,
                  style: const TextStyle(
                    fontSize: 16,
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
                if (d.customerPhone.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(
                        Icons.phone_outlined,
                        size: 15,
                        color: DriverColors.primary,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        d.customerPhone,
                        style: const TextStyle(
                          fontSize: 13,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                      const Spacer(),
                      ElevatedButton.icon(
                        onPressed: () => NavigationLauncher.launchCall(
                          context,
                          d.customerPhone,
                        ),
                        icon: const Icon(Icons.call, size: 14),
                        label: const Text('Call'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: DriverColors.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                          elevation: 0,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

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
              children: [
                _SummaryRow('Order Number', d.orderNumber),
                const Divider(color: DriverColors.divider, height: 16),
                _SummaryRow(
                  'Gallons for Refill',
                  '${d.expectedGallons} customer-owned container${d.expectedGallons != 1 ? 's' : ''}',
                ),
                const Divider(color: DriverColors.divider, height: 16),
                _SummaryRow(
                  'Payment',
                  d.paymentMethod == 'CASH'
                      ? 'Cash — ₱${d.totalAmount.toStringAsFixed(2)}'
                      : 'Approved Credit (Paid)',
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Navigation action
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => NavigationLauncher.launchNavigation(
                context: context,
                latitude: d.latitude,
                longitude: d.longitude,
                address: d.fullAddress,
                customerName: d.customerName,
                deliveryId: d.id,
                deliveryStatus: d.status,
              ),
              icon: const Icon(Icons.navigation_outlined, size: 18),
              label: const Text('Open Navigation in Google Maps'),
              style: OutlinedButton.styleFrom(
                foregroundColor: DriverColors.primary,
                side: const BorderSide(color: DriverColors.primary, width: 1.5),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // Optional proof can be skipped; delivery completion is available
          // from the delivery detail workflow either way.
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ProofOfDeliveryScreen(delivery: d),
                ),
              ),
              icon: const Icon(Icons.camera_alt_outlined, size: 18),
              label: const Text('Optional Proof of Delivery'),
              style: ElevatedButton.styleFrom(
                backgroundColor: DriverColors.primaryDark,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => Navigator.pushReplacement(
                context,
                MaterialPageRoute(
                  builder: (_) => DeliveryDetailScreen(id: d.id),
                ),
              ),
              icon: const Icon(Icons.skip_next_rounded),
              label: const Text('Skip Proof / Continue to Complete Delivery'),
              style: OutlinedButton.styleFrom(
                foregroundColor: DriverColors.primaryDark,
                side: const BorderSide(color: DriverColors.primary),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  const _SummaryRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Row(
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
    );
  }
}
