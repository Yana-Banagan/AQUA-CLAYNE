import 'package:flutter/material.dart';
import '../core/models/delivery_model.dart';
import '../core/theme/driver_colors.dart';
import '../core/theme/app_spacing.dart';
import '../driver/services/navigation_launcher.dart';
import 'status_badge.dart';

class DriverDeliveryCard extends StatelessWidget {
  final Map<String, dynamic>? delivery;
  final DeliveryModel? deliveryModel;
  final VoidCallback? onTap;
  final bool showNavigateButton;

  const DriverDeliveryCard({
    super.key,
    this.delivery,
    this.deliveryModel,
    this.onTap,
    this.showNavigateButton = true,
  });

  @override
  Widget build(BuildContext context) {
    final d = deliveryModel ?? DeliveryModel.fromMap(delivery ?? {});

    final orderNumber = d.orderNumber;
    final customerName = d.customerName;
    final fullAddress =
        d.fullAddress.isNotEmpty ? d.fullAddress : 'Address unavailable';
    final status = d.status;
    final expectedGallons = d.expectedGallons;
    final double totalAmount = d.totalAmount;
    final paymentMethod = d.paymentMethod.toUpperCase();
    final paymentStatus = d.paymentStatus.toUpperCase();
    final lat = d.latitude;
    final lng = d.longitude;

    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.compact),
      decoration: BoxDecoration(
        color: DriverColors.cardTranslucent,
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
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.standard),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top row: Order Number + Status Badge
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: DriverColors.mintSoft,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        orderNumber,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                    ),
                    const Spacer(),
                    StatusBadge(status: status, driverStyle: true),
                  ],
                ),
                const SizedBox(height: 10),

                // Customer Name + Refill quantity
                Row(
                  children: [
                    const Icon(
                      Icons.person_outline_rounded,
                      size: 16,
                      color: DriverColors.primary,
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        customerName,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: DriverColors.textPrimary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: DriverColors.mintSoft,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.water_drop_outlined,
                            size: 13,
                            color: DriverColors.mintAccent,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            '$expectedGallons Refill',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: DriverColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Delivery Address
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(top: 2),
                      child: Icon(
                        Icons.location_on_outlined,
                        size: 16,
                        color: DriverColors.textSecondary,
                      ),
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        fullAddress,
                        style: const TextStyle(
                          fontSize: 13,
                          color: DriverColors.textSecondary,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                const Divider(color: DriverColors.divider, height: 1),
                const SizedBox(height: 8),

                // Bottom row: Payment Info + Actions
                // Payment row
                Row(
                  children: [
                    if (paymentMethod == 'ONLINE' || paymentStatus == 'PAID' && paymentMethod != 'CASH') ...[
                      const Icon(
                        Icons.check_circle_outline_rounded,
                        size: 15,
                        color: DriverColors.success,
                      ),
                      const SizedBox(width: 4),
                      const Text(
                        'Paid Online (PayMongo)',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: DriverColors.success,
                        ),
                      ),
                    ] else if (paymentMethod == 'CASH') ...[
                      const Icon(
                        Icons.payments_outlined,
                        size: 15,
                        color: DriverColors.primary,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        paymentStatus == 'PAID'
                            ? 'Cash (Paid)'
                            : 'Cash to Collect: ₱${totalAmount.toStringAsFixed(2)}',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: paymentStatus == 'PAID'
                              ? DriverColors.success
                              : DriverColors.warning,
                        ),
                      ),
                    ] else ...[
                      const Icon(
                        Icons.credit_card_outlined,
                        size: 15,
                        color: DriverColors.info,
                      ),
                      const SizedBox(width: 4),
                      const Text(
                        'Approved Credit',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: DriverColors.info,
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 12),

                // Actions row: Navigate + View Delivery
                Row(
                  children: [
                    if (showNavigateButton) ...[
                      Expanded(
                        child: OutlinedButton.icon(
                          icon: const Icon(Icons.navigation_outlined, size: 16),
                          label: const Text('Navigate'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: DriverColors.primary,
                            side: const BorderSide(color: DriverColors.primary, width: 1.2),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            visualDensity: VisualDensity.compact,
                          ),
                          onPressed: () => NavigationLauncher.launchNavigation(
                            context: context,
                            latitude: lat,
                            longitude: lng,
                            address: fullAddress,
                            customerName: customerName,
                            deliveryId: d.id,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    Expanded(
                      flex: showNavigateButton ? 1 : 2,
                      child: ElevatedButton.icon(
                        icon: const Icon(Icons.visibility_outlined, size: 16),
                        label: const Text('View Delivery'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: DriverColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: onTap,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
