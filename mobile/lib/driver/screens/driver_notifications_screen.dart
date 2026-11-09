import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../core/services/realtime_service.dart';
import '../../driver/data/delivery_repository.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import 'delivery_detail_screen.dart';

class DriverNotificationsScreen extends StatefulWidget {
  const DriverNotificationsScreen({super.key});

  @override
  State<DriverNotificationsScreen> createState() =>
      _DriverNotificationsScreenState();
}

class _DriverNotificationsScreenState extends State<DriverNotificationsScreen> {
  List<Map<String, dynamic>> _notifications = [];
  bool _loading = true;
  String? _error;
  VoidCallback? _removeRealtimeListener;

  @override
  void initState() {
    super.initState();
    _load();
    _removeRealtimeListener = context.read<RealtimeService>().listenToEntity(
      entityType: 'notification',
      onSignal: _load,
    );
  }

  @override
  void dispose() {
    _removeRealtimeListener?.call();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<DeliveryRepository>();
      final list = await repo.notifications();
      if (!mounted) return;
      setState(() {
        _notifications = list;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _markRead(String id) async {
    try {
      final repo = context.read<DeliveryRepository>();
      await repo.markNotificationRead(id);
      setState(() {
        final idx = _notifications.indexWhere((n) => n['id'] == id);
        if (idx != -1) {
          _notifications[idx]['is_read'] = true;
        }
      });
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Notifications',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: DriverColors.primary),
            onPressed: _loading ? null : _load,
          ),
        ],
      ),
      body: RefreshIndicator(
        color: DriverColors.primary,
        onRefresh: _load,
        child: _loading && _notifications.isEmpty
            ? const LoadingView(message: 'Loading notifications...')
            : _notifications.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.symmetric(
                      vertical: 80,
                      horizontal: 24,
                    ),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(32),
                        decoration: BoxDecoration(
                          color: DriverColors.cardTranslucent,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: DriverColors.borderLight),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              _error != null
                                  ? Icons.error_outline_rounded
                                  : Icons.notifications_none_rounded,
                              size: 48,
                              color: _error != null
                                  ? DriverColors.error
                                  : DriverColors.textMuted,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              _error != null
                                  ? 'Unable to load notifications'
                                  : 'No notifications yet',
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: DriverColors.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _error != null
                                  ? 'Please pull down to retry or check your network connection.'
                                  : 'Delivery assignments and system alerts will appear here.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 13,
                                color: DriverColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  )
                : ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(AppSpacing.standard),
                    itemCount: _notifications.length,
                    itemBuilder: (context, index) {
                      final n = _notifications[index];
                      final isRead = n['is_read'] == true || n['read_at'] != null;
                      final title = n['title'] ?? 'Notification';
                      final body = n['body'] ?? n['message'] ?? '';
                      final createdAt = n['created_at']?.toString() ?? '';
                      final data = n['data'] as Map<String, dynamic>? ?? {};
                      final entityId = data['entity_id']?.toString();

                      return Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.compact),
                        decoration: BoxDecoration(
                          color: isRead
                              ? DriverColors.cardTranslucent
                              : Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: isRead
                                ? DriverColors.borderLight
                                : DriverColors.primary.withValues(alpha: 0.4),
                            width: isRead ? 1 : 1.5,
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: DriverColors.shadow,
                              blurRadius: 6,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Material(
                          color: Colors.transparent,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () {
                              if (!isRead) {
                                _markRead(n['id']);
                              }
                              if (entityId != null && entityId.isNotEmpty) {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        DeliveryDetailScreen(id: entityId),
                                  ),
                                );
                              }
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(AppSpacing.standard),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: isRead
                                          ? DriverColors.mintSoft
                                          : DriverColors.primaryLight
                                              .withValues(alpha: 0.15),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Icon(
                                      Icons.local_shipping_outlined,
                                      size: 20,
                                      color: isRead
                                          ? DriverColors.textSecondary
                                          : DriverColors.primary,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                title,
                                                style: TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: isRead
                                                      ? FontWeight.w600
                                                      : FontWeight.bold,
                                                  color:
                                                      DriverColors.textPrimary,
                                                ),
                                              ),
                                            ),
                                            if (!isRead)
                                              Container(
                                                width: 8,
                                                height: 8,
                                                decoration: const BoxDecoration(
                                                  color: DriverColors.primary,
                                                  shape: BoxShape.circle,
                                                ),
                                              ),
                                          ],
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          body,
                                          style: const TextStyle(
                                            fontSize: 13,
                                            color: DriverColors.textSecondary,
                                          ),
                                        ),
                                        if (createdAt.isNotEmpty) ...[
                                          const SizedBox(height: 6),
                                          Text(
                                            createdAt.split('T').first,
                                            style: const TextStyle(
                                              fontSize: 11,
                                              color: DriverColors.textHint,
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
                    },
                  ),
      ),
    );
  }
}
