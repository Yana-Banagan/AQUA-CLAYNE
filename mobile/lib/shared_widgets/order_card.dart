import 'package:flutter/material.dart';
import 'package:mobile/core/utils/pht_datetime.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_spacing.dart';
import 'app_card.dart';
import 'status_badge.dart';

class OrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final VoidCallback? onTap;

  const OrderCard({super.key, required this.order, this.onTap});

  @override
  Widget build(BuildContext context) {
    final orderNumber = order['order_number']?.toString() ?? 'N/A';
    final status = order['status']?.toString() ?? 'PENDING';
    final total = order['total_amount'] is num
        ? (order['total_amount'] as num).toDouble()
        : double.tryParse(order['total_amount']?.toString() ?? '0') ?? 0;
    final createdAt = order['created_at']?.toString() ?? '';
    final items = order['order_items'] as List? ?? order['items'] as List? ?? [];
    final paymentStatus = order['payment_status']?.toString() ?? '';
    final itemSummary = items.isEmpty
        ? 'Refill Order'
        : items.map((i) {
            final qty = i['quantity'] ?? '';
            final name = i['product']?['name'] ?? i['product_name'] ?? 'Service';
            return '$qty × $name';
          }).join(', ');

    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.all(AppSpacing.standard),
      child: Row(
        children: [
          // Icon
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.infoLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.water_drop,
              color: AppColors.primary,
              size: 22,
            ),
          ),
          const SizedBox(width: AppSpacing.compact),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      orderNumber,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const Spacer(),
                    StatusBadge(status: status),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  itemSummary,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text(
                      '₱${total.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primary,
                      ),
                    ),
                    if (paymentStatus.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: paymentStatus.toUpperCase() == 'PAID'
                              ? AppColors.successLight
                              : AppColors.warningLight,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          paymentStatus.toUpperCase(),
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: paymentStatus.toUpperCase() == 'PAID'
                                ? AppColors.success
                                : AppColors.warning,
                          ),
                        ),
                      ),
                    ],
                    const Spacer(),
                    if (createdAt.isNotEmpty)
                      Text(
                        _formatDate(createdAt),
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textHint,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.small),
          const Icon(Icons.chevron_right, color: AppColors.textHint, size: 20),
        ],
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = PhtDateTime.parse(raw);
      final months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
      ];
      return '${months[dt.month - 1]} ${dt.day}';
    } catch (_) {
      return raw.length > 10 ? raw.substring(0, 10) : raw;
    }
  }
}

