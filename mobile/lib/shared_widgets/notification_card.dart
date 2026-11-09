import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_spacing.dart';

class NotificationCard extends StatelessWidget {
  final Map<String, dynamic> notification;
  final VoidCallback? onTap;

  const NotificationCard({super.key, required this.notification, this.onTap});

  @override
  Widget build(BuildContext context) {
    final title = notification['title']?.toString() ?? 'Notification';
    final body = notification['body']?.toString() ?? notification['message']?.toString() ?? '';
    final isRead = notification['is_read'] == true ||
        notification['is_read'] == 1 ||
        notification['read_at'] != null;
    final createdAt = notification['created_at']?.toString() ?? '';

    final iconData = _getIcon(title, body);
    final iconColor = isRead ? AppColors.mutedText : AppColors.aquaBlue;

    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.small),
      decoration: BoxDecoration(
        color: isRead ? AppColors.surface : const Color(0xFFF0FAFF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isRead ? AppColors.border : AppColors.aquaBlue.withValues(alpha: 0.3),
        ),
        boxShadow: [
          BoxShadow(
            color: isRead
                ? AppColors.deepBlue.withValues(alpha: 0.03)
                : AppColors.aquaBlue.withValues(alpha: 0.08),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.compact),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Icon Avatar
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: isRead
                        ? AppColors.border.withValues(alpha: 0.4)
                        : AppColors.lightAqua,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(iconData, color: iconColor, size: 20),
                ),
                const SizedBox(width: AppSpacing.compact),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              title,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: isRead ? FontWeight.w600 : FontWeight.w700,
                                color: isRead ? AppColors.darkText : AppColors.deepBlue,
                              ),
                            ),
                          ),
                          if (!isRead)
                            Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: AppColors.aquaBlue,
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      if (body.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          body,
                          style: TextStyle(
                            fontSize: 13,
                            color: isRead ? AppColors.mutedText : AppColors.darkText,
                            height: 1.35,
                          ),
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                      if (createdAt.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          _timeAgo(createdAt),
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: AppColors.mutedText,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  IconData _getIcon(String title, String body) {
    final lower = '$title $body'.toLowerCase();
    if (lower.contains('driver') || lower.contains('deliver') || lower.contains('pickup')) {
      return Icons.local_shipping_rounded;
    }
    if (lower.contains('queue') || lower.contains('refill') || lower.contains('wait')) {
      return Icons.hourglass_top_rounded;
    }
    if (lower.contains('pay') || lower.contains('credit') || lower.contains('cash') || lower.contains('balance')) {
      return Icons.account_balance_wallet_rounded;
    }
    if (lower.contains('order')) {
      return Icons.receipt_long_rounded;
    }
    return Icons.notifications_rounded;
  }

  String _timeAgo(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      if (diff.inDays == 1) return 'Yesterday';
      return '${diff.inDays}d ago';
    } catch (_) {
      return '';
    }
  }
}
