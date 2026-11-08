import 'package:flutter/material.dart';
import '../../core/models/notification_model.dart';
import 'customer_shell.dart';
import 'order_detail_screen.dart';

class NotificationDetailScreen extends StatelessWidget {
  final NotificationModel notification;

  const NotificationDetailScreen({
    super.key,
    required this.notification,
  });

  String _formatFullDate(String raw) {
    if (raw.isEmpty) return 'Recent update';
    try {
      final dt = DateTime.parse(raw).toLocal();
      const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
      ];
      final month = months[dt.month - 1];
      final day = dt.day;
      final year = dt.year;
      final hour = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
      final minute = dt.minute.toString().padLeft(2, '0');
      final period = dt.hour >= 12 ? 'PM' : 'AM';
      return '$month $day, $year • $hour:$minute $period';
    } catch (_) {
      return raw;
    }
  }

  IconData _getIcon() {
    if (notification.isOrderRelated) {
      final lower = '${notification.title} ${notification.body}'.toLowerCase();
      if (lower.contains('deliver') || lower.contains('driver') || lower.contains('pickup')) {
        return Icons.local_shipping_rounded;
      }
      return Icons.water_drop_rounded;
    }
    if (notification.isPaymentRelated) {
      return Icons.account_balance_wallet_rounded;
    }
    if (notification.isAccountRelated) {
      return Icons.person_rounded;
    }
    return Icons.notifications_rounded;
  }

  Color _getIconBgColor() {
    if (notification.isOrderRelated) return const Color(0xFFEAF7FF);
    if (notification.isPaymentRelated) return const Color(0xFFE8F5E9);
    if (notification.isAccountRelated) return const Color(0xFFFFF3E0);
    return const Color(0xFFEAF7FF);
  }

  Color _getIconColor() {
    if (notification.isOrderRelated) return const Color(0xFF0878C9);
    if (notification.isPaymentRelated) return const Color(0xFF2E7D32);
    if (notification.isAccountRelated) return const Color(0xFFEF6C00);
    return const Color(0xFF0878C9);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen background image ────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Content ─────────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    child: Column(
                      children: [
                        _buildDetailCard(context),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => Navigator.of(context).pop(),
              borderRadius: BorderRadius.circular(21),
              child: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFD5E7F4), width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.arrow_back_rounded,
                  color: Color(0xFF0878C9),
                  size: 22,
                ),
              ),
            ),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Notification Details',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Details and related actions for this update',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF6F8BA3),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // DETAIL CARD
  // ====================================================================
  Widget _buildDetailCard(BuildContext context) {
    final iconData = _getIcon();
    final iconBgColor = _getIconBgColor();
    final iconColor = _getIconColor();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top row: Icon Avatar & Category Badge
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: iconBgColor,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: iconColor.withValues(alpha: 0.3),
                    width: 1,
                  ),
                ),
                child: Icon(iconData, color: iconColor, size: 26),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFF4F9FD),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFD5E7F4)),
                ),
                child: Text(
                  notification.type?.toUpperCase() ??
                      (notification.isOrderRelated
                          ? 'ORDER'
                          : (notification.isPaymentRelated
                              ? 'PAYMENT'
                              : 'UPDATE')),
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0878C9),
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 18),

          // Notification Title
          Text(
            notification.title,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: Color(0xFF12344D),
              letterSpacing: -0.2,
            ),
          ),

          const SizedBox(height: 6),

          // Date & Time
          Row(
            children: [
              const Icon(Icons.schedule_rounded,
                  size: 14, color: Color(0xFF8FA9BF)),
              const SizedBox(width: 6),
              Text(
                _formatFullDate(notification.createdAt),
                style: const TextStyle(
                  fontSize: 12,
                  color: Color(0xFF6F8BA3),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),

          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Divider(color: Color(0xFFF0F5FA), height: 1),
          ),

          // Full Message Body
          Text(
            notification.body,
            style: const TextStyle(
              fontSize: 14,
              color: Color(0xFF335C7D),
              height: 1.55,
              fontWeight: FontWeight.w500,
            ),
          ),

          const SizedBox(height: 24),

          // Action Button if related to order, payment, or profile
          if (notification.entityId != null &&
              notification.entityId!.isNotEmpty &&
              notification.isOrderRelated) ...[
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          OrderDetailScreen(orderId: notification.entityId!),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.track_changes_rounded, size: 19),
                    SizedBox(width: 8),
                    Text(
                      'View Order Details',
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ] else if (notification.isOrderRelated) ...[
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () => CustomerShell.showTab(context, 1),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.receipt_long_rounded, size: 19),
                    SizedBox(width: 8),
                    Text(
                      'View All Orders',
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ] else if (notification.isPaymentRelated) ...[
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () => CustomerShell.showTab(context, 2),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.account_balance_wallet_rounded, size: 19),
                    SizedBox(width: 8),
                    Text(
                      'View Payments & Credit',
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ] else if (notification.isAccountRelated) ...[
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () => CustomerShell.showTab(context, 4),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.person_rounded, size: 19),
                    SizedBox(width: 8),
                    Text(
                      'View Profile',
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
