import 'dart:io';
import 'package:flutter/foundation.dart';
import '../../core/models/delivery_model.dart';
import '../../core/services/realtime_service.dart';
import '../data/delivery_repository.dart';

/// Manages driver delivery state with live Supabase Realtime updates.
///
/// Signal flow:
///   Laravel → realtime_events (entity_type: 'delivery')
///     → DriverDeliveryProvider.loadAssigned() re-fetches from Laravel
class DriverDeliveryProvider extends ChangeNotifier {
  final DeliveryRepository _repo;
  final RealtimeService _realtime;

  DriverDeliveryProvider(this._repo, this._realtime);

  List<DeliveryModel> _assigned = [];
  List<DeliveryModel> _history = [];
  List<Map<String, dynamic>> _pickupBatches = [];
  List<Map<String, dynamic>> _deliveryRoutes = [];
  Map<String, dynamic>? _currentBatch;
  Map<String, dynamic>? _currentRoute;
  DeliveryModel? _detail;
  bool _loading = false;
  bool _actionLoading = false;
  String? _error;
  bool _realtimeActive = false;
  final List<VoidCallback> _removeRealtimeListeners = [];

  List<DeliveryModel> get assigned => _assigned;
  List<DeliveryModel> get history => _history;
  List<Map<String, dynamic>> get pickupBatches => _pickupBatches;
  List<Map<String, dynamic>> get deliveryRoutes => _deliveryRoutes;
  Map<String, dynamic>? get currentBatch => _currentBatch;
  Map<String, dynamic>? get currentRoute => _currentRoute;
  int get activeBatchesCount =>
      _pickupBatches.where((b) => b['status'] == 'ready' || b['status'] == 'in_progress').length;
  int get activeRoutesCount =>
      _deliveryRoutes.where((r) => r['status'] == 'ready' || r['status'] == 'in_progress').length;
  DeliveryModel? get detail => _detail;
  bool get loading => _loading;
  bool get actionLoading => _actionLoading;
  String? get error => _error;

  List<DeliveryModel> get pickupPhase =>
      _assigned.where((d) => d.isPickupPhase).toList();

  List<DeliveryModel> get deliveryPhase =>
      _assigned.where((d) => d.isDeliveryPhase).toList();

  // ================================================================
  // REALTIME
  // ================================================================

  /// Start listening for delivery assignments and status changes.
  void startRealtime() {
    if (_realtimeActive) return;
    _realtimeActive = true;

    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'delivery',
      onSignal: () {
        debugPrint('[DriverDeliveryProvider] Realtime signal — refreshing deliveries');
        loadAssigned();
        // If we have a detail open, refresh it too
        if (_detail != null) {
          loadDetail(_detail!.id);
        }
      },
    ));

    // Also listen for order signals (refill/queue status updates)
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'order',
      onSignal: () {
        debugPrint('[DriverDeliveryProvider] Order signal — refreshing assigned');
        loadAssigned();
        if (_detail != null) {
          loadDetail(_detail!.id);
        }
      },
    ));

    // Listen for pickup batch updates
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'pickup_batch',
      onSignal: () {
        debugPrint('[DriverDeliveryProvider] Pickup batch signal — refreshing batches');
        loadPickupBatches();
        if (_currentBatch != null) {
          loadPickupBatch(_currentBatch!['id']?.toString() ?? '');
        }
      },
    ));

    // Listen for delivery route updates
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'delivery_route',
      onSignal: () {
        debugPrint('[DriverDeliveryProvider] Delivery route signal — refreshing routes');
        loadDeliveryRoutes();
        if (_currentRoute != null) {
          loadDeliveryRoute(_currentRoute!['id']?.toString() ?? '');
        }
      },
    ));

    debugPrint('[DriverDeliveryProvider] Realtime subscriptions started.');
  }

  /// Stop all subscriptions — call on logout.
  void stopRealtime() {
    for (final removeListener in _removeRealtimeListeners) {
      removeListener();
    }
    _removeRealtimeListeners.clear();
    _realtimeActive = false;
  }

  /// Removes delivery data from memory when the driver signs out.
  /// This prevents previously assigned orders appearing after logout.
  void clearSessionData() {
    _assigned = [];
    _history = [];
    _pickupBatches = [];
    _deliveryRoutes = [];
    _currentBatch = null;
    _currentRoute = null;
    _detail = null;
    _error = null;
    _loading = false;
    _actionLoading = false;
    stopRealtime();
    notifyListeners();
  }

  // ================================================================
  // LOAD ASSIGNED
  // ================================================================
  Future<void> loadAssigned() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.assigned();
      _assigned = raw.map(DeliveryModel.fromMap).toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // LOAD HISTORY
  // ================================================================
  Future<void> loadHistory() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.history();
      _history = raw.map(DeliveryModel.fromMap).toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // LOAD DETAIL
  // ================================================================
  Future<void> loadDetail(String id) async {
    _loading = true;
    _error = null;
    // Clear detail only if opening a different delivery
    final cleanId = id.trim().toLowerCase();
    if (_detail != null &&
        _detail!.id.trim().toLowerCase() != cleanId &&
        _detail!.orderId.trim().toLowerCase() != cleanId) {
      _detail = null;
    }
    notifyListeners();
    try {
      final raw = await _repo.get(id);
      if (raw.isEmpty) {
        throw StateError('The delivery details were not returned by the server.');
      }
      final detail = DeliveryModel.fromMap(raw);
      final matches = detail.id.trim().toLowerCase() == cleanId ||
          detail.orderId.trim().toLowerCase() == cleanId;
      if (detail.id.isEmpty || !matches) {
        throw StateError('The requested delivery could not be loaded.');
      }
      _detail = detail;
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // ACTION HELPER
  // ================================================================
  Future<bool> _action(Future<void> Function() fn) async {
    _actionLoading = true;
    _error = null;
    notifyListeners();
    try {
      await fn();
      _actionLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString();
      _actionLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> startPickup(String id) =>
      _action(() => _repo.startPickup(id));

  Future<bool> confirmPickup(String id, int gallonsCollected) =>
      _action(() => _repo.confirmPickup(id, gallonsCollected));

  Future<bool> reportPickupFailure(String id, String reason) =>
      _action(() => _repo.reportPickupFailure(id, reason));

  Future<bool> confirmStationArrival(String id) =>
      _action(() => _repo.confirmStationArrival(id));

  Future<bool> startDelivery(String id) =>
      _action(() => _repo.startDelivery(id));

  Future<bool> updateStatus(String id, String status, {String? note, String? eta}) =>
      _action(() => _repo.updateStatus(id, status, note: note, eta: eta));

  Future<bool> collectPayment(String id, double amount) =>
      _action(() => _repo.collectPayment(id, amount));

  Future<bool> uploadProof(String id, File photo) =>
      _action(() => _repo.uploadProof(id, photo));

  Future<bool> markDelivered(String id) =>
      _action(() => _repo.markDelivered(id));

  Future<bool> reportDeliveryFailure(String id, String reason) =>
      _action(() => _repo.reportDeliveryFailure(id, reason));

  Future<bool> reportIssue(
    String id, {
    required String issueType,
    required String description,
    bool markAsFailed = false,
  }) =>
      _action(() => _repo.reportIssue(
            id,
            issueType: issueType,
            description: description,
            markAsFailed: markAsFailed,
          ));

  // ================================================================
  // PICKUP BATCHES
  // ================================================================
  Future<void> loadPickupBatches({String? status}) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      _pickupBatches = await _repo.getPickupBatches(status: status);
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadPickupBatch(String id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      _currentBatch = await _repo.getPickupBatch(id);
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<bool> updatePickupBatchStatus(String id, String status) async {
    return _action(() async {
      final res = await _repo.updatePickupBatchStatus(id, status);
      if (_currentBatch != null && _currentBatch!['id'] == id) {
        _currentBatch = res;
      }
      await loadPickupBatches();
    });
  }

  Future<bool> collectPickupStop(String batchId, String itemId, int collectedCount, {String? notes}) async {
    return _action(() async {
      await _repo.collectPickupStop(batchId, itemId, collectedCount, notes: notes);
      await loadPickupBatch(batchId);
    });
  }

  // ================================================================
  // DELIVERY ROUTES
  // ================================================================
  Future<void> loadDeliveryRoutes({String? status}) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      _deliveryRoutes = await _repo.getDeliveryRoutes(status: status);
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadDeliveryRoute(String id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      _currentRoute = await _repo.getDeliveryRoute(id);
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<bool> updateDeliveryRouteStatus(String id, String status) async {
    return _action(() async {
      final res = await _repo.updateDeliveryRouteStatus(id, status);
      if (_currentRoute != null && _currentRoute!['id'] == id) {
        _currentRoute = res;
      }
      await loadDeliveryRoutes();
    });
  }

  Future<bool> updateDeliveryStopStatus(String routeId, String stopId, String status, {String? notes}) async {
    return _action(() async {
      await _repo.updateDeliveryStopStatus(routeId, stopId, status, notes: notes);
      await loadDeliveryRoute(routeId);
    });
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearDetail() {
    _detail = null;
    _currentBatch = null;
    _currentRoute = null;
  }
}
