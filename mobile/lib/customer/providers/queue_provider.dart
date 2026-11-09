import 'package:flutter/foundation.dart';
import '../../core/services/realtime_service.dart';
import '../data/queue_repository.dart';

class QueueProvider extends ChangeNotifier {
  final QueueRepository _repo;
  final RealtimeService _realtime;

  QueueProvider(this._repo, this._realtime);

  Map<String, dynamic>? _queueInfo;
  bool _loading = false;
  String? _error;
  String? _trackedOrderId;
  bool _activeQueueView = false;
  bool _realtimeActive = false;
  int _requestVersion = 0;
  final List<VoidCallback> _removeRealtimeListeners = [];

  Map<String, dynamic>? get queueInfo => _queueInfo;
  bool get loading => _loading;
  String? get error => _error;

  int get position => _queueInfo?['position'] as int? ?? 0;
  String get status => _queueInfo?['status']?.toString() ?? '';
  String get queueNumber => _queueInfo?['queue_number']?.toString() ?? '';
  bool get inQueue => _queueInfo?['in_queue'] == true || (position > 0 && ['WAITING', 'IN_PROGRESS', 'WAITING_IN_QUEUE', 'REFILLING'].contains(status.toUpperCase()));
  int get estimatedWaitMinutes => _queueInfo?['estimated_wait_minutes'] as int? ?? (position > 0 ? (position == 1 ? 5 : (position - 1) * 15 + 10) : 0);
  String get orderNumber => _queueInfo?['order_number']?.toString() ?? '';
  String get orderStatus => _queueInfo?['order_status']?.toString() ?? '';
  int get gallons => (_queueInfo?['accepted_empty_gallons'] ?? _queueInfo?['expected_empty_gallons']) as int? ?? 0;
  int get receivedGallons => _queueInfo?['received_empty_gallons'] as int? ?? 0;

  void startRealtime() {
    if (_realtimeActive) return;
    _realtimeActive = true;
    void refresh() {
      if (_trackedOrderId != null) {
        loadForOrder(_trackedOrderId!);
      } else if (_activeQueueView) {
        loadActiveQueue();
      }
    }

    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'order',
      onSignal: refresh,
    ));
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'refill_queue',
      onSignal: refresh,
    ));
  }

  void stopRealtime() {
    for (final removeListener in _removeRealtimeListeners) {
      removeListener();
    }
    _removeRealtimeListeners.clear();
    _realtimeActive = false;
  }

  void clearSessionData() {
    _queueInfo = null;
    _loading = false;
    _error = null;
    _trackedOrderId = null;
    _activeQueueView = false;
    stopRealtime();
    notifyListeners();
  }

  Future<void> loadForOrder(String orderId) async {
    final request = ++_requestVersion;
    _trackedOrderId = orderId;
    _activeQueueView = false;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      _queueInfo = await _repo.getForOrder(orderId);
      if (request != _requestVersion) return;
    } catch (e) {
      if (request != _requestVersion) return;
      _error = e.toString();
      _queueInfo = null;
    }
    if (request != _requestVersion) return;
    _loading = false;
    notifyListeners();
  }

  Future<void> loadActiveQueue() async {
    final request = ++_requestVersion;
    _trackedOrderId = null;
    _activeQueueView = true;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final res = await _repo.getActiveQueue();
      if (request != _requestVersion) return;
      _queueInfo = res.isNotEmpty ? res : null;
    } catch (e) {
      if (request != _requestVersion) return;
      _error = e.toString();
      _queueInfo = null;
    }
    if (request != _requestVersion) return;
    _loading = false;
    notifyListeners();
  }

  void clear() {
    _requestVersion++;
    _queueInfo = null;
    _error = null;
    _trackedOrderId = null;
    _activeQueueView = false;
    _loading = false;
    notifyListeners();
  }
}
