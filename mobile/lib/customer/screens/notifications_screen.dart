import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/models/notification_model.dart';
import 'customer_shell.dart';
import 'notification_detail_screen.dart';
import 'order_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  String _selectedFilter = 'All';
  final List<String> _filters = ['All', 'Unread', 'Orders', 'Payments', 'Account'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!context.read<AuthProvider>().isAuthenticated) return;
      context.read<NotificationProvider>().load();
    });
  }

  List<NotificationModel> _applyFilter(List<NotificationModel> list) {
    switch (_selectedFilter) {
      case 'Unread':
        return list.where((n) => !n.isRead).toList();
      case 'Orders':
        return list.where((n) => n.isOrderRelated).toList();
      case 'Payments':
        return list.where((n) => n.isPaymentRelated).toList();
      case 'Account':
        return list.where((n) => n.isAccountRelated).toList();
      default:
        return list;
    }
  }

  void _onNotificationTap(NotificationModel n) {
    // 1. Mark as read on backend
    if (!n.isRead) {
      context.read<NotificationProvider>().markRead(n.id);
    }

    // 2. Navigate based on notification type and related entity
    if (n.entityId != null && n.entityId!.isNotEmpty && n.isOrderRelated) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => OrderDetailScreen(orderId: n.entityId!),
        ),
      );
    } else if (n.isPaymentRelated) {
      CustomerShell.showTab(context, 2);
    } else if (n.isAccountRelated) {
      CustomerShell.showTab(context, 4);
    } else {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NotificationDetailScreen(notification: n),
        ),
      );
    }
  }

  String _formatTimeAgo(String raw) {
    if (raw.isEmpty) return 'Just now';
    try {
      final dt = DateTime.parse(raw).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      if (diff.inDays == 1) return 'Yesterday';
      if (diff.inDays < 7) return '${diff.inDays}d ago';

      const months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
      ];
      return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
    } catch (_) {
      return '';
    }
  }

  IconData _getIcon(NotificationModel n) {
    if (n.isOrderRelated) {
      final lower = '${n.title} ${n.body}'.toLowerCase();
      if (lower.contains('deliver') || lower.contains('driver') || lower.contains('pickup')) {
        return Icons.local_shipping_rounded;
      }
      return Icons.water_drop_rounded;
    }
    if (n.isPaymentRelated) {
      return Icons.account_balance_wallet_rounded;
    }
    if (n.isAccountRelated) {
      return Icons.person_rounded;
    }
    return Icons.notifications_rounded;
  }

  Color _getIconBgColor(NotificationModel n, bool isRead) {
    if (isRead) return const Color(0xFFF4F9FD);
    if (n.isOrderRelated) return const Color(0xFFEAF7FF);
    if (n.isPaymentRelated) return const Color(0xFFE8F5E9);
    if (n.isAccountRelated) return const Color(0xFFFFF3E0);
    return const Color(0xFFEAF7FF);
  }

  Color _getIconColor(NotificationModel n, bool isRead) {
    if (isRead) return const Color(0xFF8FA9BF);
    if (n.isOrderRelated) return const Color(0xFF0878C9);
    if (n.isPaymentRelated) return const Color(0xFF2E7D32);
    if (n.isAccountRelated) return const Color(0xFFEF6C00);
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
                _buildFilterChips(),
                Expanded(
                  child: Consumer<NotificationProvider>(
                    builder: (context, prov, _) {
                      final isGuest = !context.watch<AuthProvider>().isAuthenticated;
                      if (prov.loading && prov.notifications.isEmpty) {
                        return _buildLoadingState();
                      }

                      if (prov.error != null && prov.notifications.isEmpty) {
                        return _buildErrorState(prov);
                      }

                      final filtered = isGuest
                          ? <NotificationModel>[]
                          : _applyFilter(prov.notifications);

                      if (filtered.isEmpty) {
                        return _buildEmptyState(prov);
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: () => context.read<AuthProvider>().isAuthenticated
                            ? prov.load()
                            : Future<void>.value(),
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
                          itemCount: filtered.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final item = filtered[index];
                            return _buildNotificationCard(item);
                          },
                        ),
                      );
                    },
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
          // Circular Back Button
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

          // Title & Subtitle
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Text(
                      'Notifications',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF12344D),
                        letterSpacing: -0.2,
                      ),
                    ),
                    Consumer<NotificationProvider>(
                      builder: (_, prov, _) {
                        final unread = context.read<AuthProvider>().isAuthenticated
                            ? prov.unreadCount
                            : 0;
                        if (unread == 0) return const SizedBox.shrink();
                        return Container(
                          margin: const EdgeInsets.only(left: 8),
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFF0878C9),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '$unread',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                        );
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                const Text(
                  'Your latest account and order updates',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF6F8BA3),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),

          // Mark All Read Action Button
          Consumer<NotificationProvider>(
            builder: (_, prov, _) {
              final hasUnread = context.read<AuthProvider>().isAuthenticated &&
                  prov.unreadCount > 0;
              return Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: hasUnread ? prov.markAllRead : null,
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: hasUnread
                          ? const Color(0xFFEAF7FF)
                          : const Color(0xFFF7FAFD),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: hasUnread
                            ? const Color(0xFFBCE3FD)
                            : const Color(0xFFE5F1FA),
                      ),
                    ),
                    child: Text(
                      'Mark all read',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: hasUnread
                            ? const Color(0xFF0878C9)
                            : const Color(0xFFB0C5D8),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // FILTER CHIPS
  // ====================================================================
  Widget _buildFilterChips() {
    return Container(
      height: 44,
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: _filters.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final filter = _filters[index];
          final isSelected = _selectedFilter == filter;

          return Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => setState(() => _selectedFilter = filter),
              borderRadius: BorderRadius.circular(18),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFF0878C9) : Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: isSelected
                        ? const Color(0xFF0878C9)
                        : const Color(0xFFD5E7F4),
                    width: 1.2,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: isSelected
                          ? const Color(0xFF0878C9).withValues(alpha: 0.22)
                          : const Color(0xFF0878C9).withValues(alpha: 0.04),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Center(
                  child: Text(
                    filter,
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight:
                          isSelected ? FontWeight.w700 : FontWeight.w600,
                      color: isSelected
                          ? Colors.white
                          : const Color(0xFF12344D),
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  // ====================================================================
  // NOTIFICATION CARD
  // ====================================================================
  Widget _buildNotificationCard(NotificationModel n) {
    final isRead = n.isRead;
    final iconData = _getIcon(n);
    final iconBgColor = _getIconBgColor(n, isRead);
    final iconColor = _getIconColor(n, isRead);
    final timeAgo = _formatTimeAgo(n.createdAt);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      decoration: BoxDecoration(
        color: isRead
            ? Colors.white
            : const Color(0xFFF7FBFE),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isRead
              ? const Color(0xFFE5F1FA)
              : const Color(0xFF0878C9).withValues(alpha: 0.35),
          width: isRead ? 1.2 : 1.6,
        ),
        boxShadow: [
          BoxShadow(
            color: isRead
                ? const Color(0xFF0878C9).withValues(alpha: 0.04)
                : const Color(0xFF0878C9).withValues(alpha: 0.10),
            blurRadius: isRead ? 8 : 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _onNotificationTap(n),
          borderRadius: BorderRadius.circular(18),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Topic Icon Avatar
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: iconBgColor,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: isRead
                          ? const Color(0xFFE5F1FA)
                          : iconColor.withValues(alpha: 0.25),
                      width: 1,
                    ),
                  ),
                  child: Icon(iconData, color: iconColor, size: 22),
                ),
                const SizedBox(width: 14),

                // Content
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              n.title,
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight:
                                    isRead ? FontWeight.w600 : FontWeight.w800,
                                color: isRead
                                    ? const Color(0xFF12344D)
                                    : const Color(0xFF0878C9),
                                letterSpacing: -0.1,
                              ),
                            ),
                          ),
                          if (!isRead) ...[
                            const SizedBox(width: 6),
                            Container(
                              width: 8,
                              height: 8,
                              margin: const EdgeInsets.only(top: 4),
                              decoration: const BoxDecoration(
                                color: Color(0xFF0878C9),
                                shape: BoxShape.circle,
                              ),
                            ),
                          ],
                        ],
                      ),
                      if (n.body.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          n.body,
                          style: TextStyle(
                            fontSize: 12.5,
                            color: isRead
                                ? const Color(0xFF6F8BA3)
                                : const Color(0xFF335C7D),
                            height: 1.4,
                          ),
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          if (timeAgo.isNotEmpty)
                            Text(
                              timeAgo,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                                color: Color(0xFF8FA9BF),
                              ),
                            ),
                          // View indicator icon
                          const Icon(
                            Icons.chevron_right_rounded,
                            size: 18,
                            color: Color(0xFFB0C5D8),
                          ),
                        ],
                      ),
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

  // ====================================================================
  // LOADING STATE
  // ====================================================================
  Widget _buildLoadingState() {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: 4,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => Container(
        height: 88,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.7),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5F1FA)),
        ),
        child: const Center(
          child: CircularProgressIndicator(
            color: Color(0xFF0878C9),
            strokeWidth: 2.5,
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // EMPTY STATE
  // ====================================================================
  Widget _buildEmptyState(NotificationProvider prov) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF7FF),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFBCE3FD)),
              ),
              child: const Icon(
                Icons.notifications_none_rounded,
                color: Color(0xFF0878C9),
                size: 38,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'No Notifications Yet',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF12344D),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              "You'll see updates about your orders,\npayments, deliveries, and account here.",
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
                height: 1.4,
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: prov.load,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text(
                'Refresh',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ====================================================================
  // ERROR STATE
  // ====================================================================
  Widget _buildErrorState(NotificationProvider prov) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: const Color(0xFFFFEBEE),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFEF9A9A)),
              ),
              child: const Icon(
                Icons.error_outline_rounded,
                color: Color(0xFFC62828),
                size: 38,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Unable to load notifications',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF12344D),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Please check your connection and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: prov.load,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text(
                'Try Again',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
