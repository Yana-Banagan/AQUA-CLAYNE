import 'package:flutter/foundation.dart';
import '../../core/models/notification_model.dart';
import '../../core/services/realtime_service.dart';
import '../data/notification_repository.dart';

/// Manages customer notification state with live Supabase Realtime updates.
class NotificationProvider extends ChangeNotifier {
  final NotificationRepository _repo;
  final RealtimeService _realtime;

  NotificationProvider(this._repo, this._realtime);

  List<NotificationModel> _notifications = [];
  bool _loading = false;
  String? _error;
  bool _realtimeActive = false;
  VoidCallback? _removeRealtimeListener;

  List<NotificationModel> get notifications => _notifications;
  bool get loading => _loading;
  String? get error => _error;
  int get unreadCount => _notifications.where((n) => !n.isRead).length;

  // ================================================================
  // REALTIME
  // ================================================================

  void startRealtime() {
    if (_realtimeActive) return;
    _realtimeActive = true;

    _removeRealtimeListener = _realtime.listenToEntity(
      entityType: 'notification',
      onSignal: () {
        debugPrint('[NotificationProvider] Realtime signal received — refreshing notifications');
        load();
      },
    );
  }

  void stopRealtime() {
    _removeRealtimeListener?.call();
    _removeRealtimeListener = null;
    _realtimeActive = false;
  }

  /// Wipes all cached notifications on logout / guest transition.
  void clearSessionData() {
    _notifications = [];
    _loading = false;
    _error = null;
    stopRealtime();
    notifyListeners();
  }

  // ================================================================
  // LOAD
  // ================================================================
  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.list();
      _notifications = raw.map(NotificationModel.fromMap).toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // MARK READ
  // ================================================================
  Future<void> markRead(String id) async {
    try {
      await _repo.markRead(id);
      final idx = _notifications.indexWhere((n) => n.id == id);
      if (idx != -1) {
        _notifications[idx] = _notifications[idx].copyWith(isRead: true);
        notifyListeners();
      }
    } catch (_) {}
  }

  // ================================================================
  // MARK ALL READ
  // ================================================================
  Future<void> markAllRead() async {
    try {
      await _repo.markAllRead();
      _notifications = _notifications
          .map((n) => n.copyWith(isRead: true))
          .toList();
      notifyListeners();
    } catch (_) {}
  }
}
