import 'package:flutter/material.dart';
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
      final dt = DateTime.parse(raw).toLocal();
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

class QueueCard extends StatelessWidget {
  final Map<String, dynamic>? queue;

  const QueueCard({super.key, this.queue});

  @override
  Widget build(BuildContext context) {
    if (queue == null) {
      return AppCard(
        color: AppColors.warningLight,
        child: const Row(
          children: [
            Icon(Icons.hourglass_empty, color: AppColors.warning),
            SizedBox(width: AppSpacing.compact),
            Text(
              'Not yet in refill queue',
              style: TextStyle(color: AppColors.warning, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      );
    }

    final position = queue!['position'] ?? queue!['queue_number'] ?? '-';
    final queueNumber = queue!['queue_number']?.toString() ?? '-';
    final status = queue!['status']?.toString() ?? 'WAITING';

    return AppCard(
      color: AppColors.infoLight,
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '#$position',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.primary,
                ),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.compact),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Queue Position',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  'Queue $queueNumber',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ),
          StatusBadge(status: status),
        ],
      ),
    );
  }
}
