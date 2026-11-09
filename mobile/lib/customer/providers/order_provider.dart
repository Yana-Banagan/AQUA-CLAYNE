import 'package:flutter/foundation.dart';
import '../../core/models/order_model.dart';
import '../../core/services/realtime_service.dart';
import '../data/order_repository.dart';

/// Manages customer order state and subscribes to Supabase Realtime
/// signals so the UI updates automatically when the backend changes.
///
/// Signal flow:
///   Laravel → inserts into realtime_events (topic: 'orders')
///     → Supabase CDC fires
///       → OrderProvider.loadOrders() re-fetches from Laravel
///         → UI rebuilds
class OrderProvider extends ChangeNotifier {
  final OrderRepository _repo;
  final RealtimeService _realtime;

  OrderProvider(this._repo, this._realtime);

  List<OrderModel> _orders = [];
  OrderModel? _activeOrder;
  OrderModel? _detail;
  bool _loading = false;
  String? _error;
  bool _realtimeActive = false;
  int _ordersRequest = 0;
  int _detailRequest = 0;
  final List<VoidCallback> _removeRealtimeListeners = [];

  List<OrderModel> get orders => _orders;
  OrderModel? get activeOrder => _activeOrder;
  OrderModel? get detail => _detail;
  bool get loading => _loading;
  String? get error => _error;

  List<OrderModel> get activeOrders =>
      _orders.where((o) => o.isActive).toList();

  List<OrderModel> get completedOrders => _orders
      .where((o) => ['COMPLETED', 'DELIVERED'].contains(o.status.toUpperCase()))
      .toList();

  List<OrderModel> get cancelledOrders => _orders
      .where((o) => [
            'REJECTED',
            'CANCELLED',
            'PICKUP_FAILED',
            'DELIVERY_FAILED',
          ].contains(o.status.toUpperCase()))
      .toList();

  // ================================================================
  // REALTIME — subscribe on login, unsubscribe on logout
  // ================================================================

  /// Call after a customer logs in to start receiving live updates.
  void startRealtime() {
    if (_realtimeActive) return;
    _realtimeActive = true;

    // Listen for any order signal — then re-fetch from Laravel
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'order',
      onSignal: () {
        debugPrint('[OrderProvider] Realtime signal — refreshing orders');
        loadOrders();
        if (_detail != null) loadDetail(_detail!.id);
      },
    ));

    // Also listen for queue signals (queue position changes affect order UI)
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'refill_queue',
      onSignal: () {
        debugPrint('[OrderProvider] Realtime queue signal — refreshing orders');
        loadOrders();
        // Reload active order detail if one is open
        if (_detail != null) {
          loadDetail(_detail!.id);
        }
      },
    ));

    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'delivery',
      onSignal: () {
        loadOrders();
        if (_detail != null) loadDetail(_detail!.id);
      },
    ));

    debugPrint('[OrderProvider] Realtime subscriptions started.');
  }

  /// Call on logout to clean up subscriptions.
  void stopRealtime() {
    for (final removeListener in _removeRealtimeListeners) {
      removeListener();
    }
    _removeRealtimeListeners.clear();
    _realtimeActive = false;
    debugPrint('[OrderProvider] Realtime subscriptions stopped.');
  }

  // ================================================================
  // LOAD ORDERS
  // ================================================================
  Future<void> loadOrders() async {
    final request = ++_ordersRequest;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.list();
      if (request != _ordersRequest) return;
      _orders = raw.map(OrderModel.fromMap).toList();
      _activeOrder = _orders.where((o) => o.isActive).firstOrNull;
    } catch (e) {
      if (request != _ordersRequest) return;
      _error = e.toString();
    }
    if (request != _ordersRequest) return;
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // LOAD DETAIL
  // ================================================================
  Future<void> loadDetail(String orderId) async {
    final request = ++_detailRequest;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.get(orderId);
      if (request != _detailRequest) return;
      if (raw.isEmpty) {
        throw StateError('The server returned no details for this order.');
      }
      _detail = OrderModel.fromMap(raw);
    } catch (e) {
      if (request != _detailRequest) return;
      _error = e.toString();
      // The list endpoint already contains enough data to render this page.
      // Avoid showing a false "Order Not Found" when only detail loading fails.
      _detail = _orders.where((order) => order.id == orderId).firstOrNull;
    }
    _loading = false;
    notifyListeners();
  }

  // ================================================================
  // PLACE ORDER
  // ================================================================
  Future<OrderModel?> placeOrder(Map<String, dynamic> payload) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.create(payload);
      final order = OrderModel.fromMap(raw);
      _orders.removeWhere((existing) => existing.id == order.id);
      _orders.insert(0, order);
      _activeOrder = order;
      _loading = false;
      notifyListeners();
      return order;
    } catch (e) {
      _error = e.toString();
      _loading = false;
      notifyListeners();
      return null;
    }
  }

  // ================================================================
  // CANCEL ORDER
  // ================================================================
  Future<bool> cancelOrder(String orderId) async {
    _error = null;
    try {
      await _repo.cancel(orderId);
      await loadOrders();
      return true;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  // ================================================================
  // SWITCH DELIVERY METHOD
  // ================================================================
  Future<OrderModel?> updateDeliveryMethod(
    String orderId,
    String deliveryMethod, {
    String? addressId,
  }) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.updateDeliveryMethod(
        orderId,
        deliveryMethod,
        addressId: addressId,
      );
      final updated = OrderModel.fromMap(raw);
      final index = _orders.indexWhere((o) => o.id == orderId);
      if (index != -1) {
        _orders[index] = updated;
      }
      if (_detail?.id == orderId) {
        _detail = updated;
      }
      if (_activeOrder?.id == orderId) {
        _activeOrder = updated;
      }
      _loading = false;
      notifyListeners();
      return updated;
    } catch (e) {
      _error = e.toString();
      _loading = false;
      notifyListeners();
      return null;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearDetail() {
    _detailRequest++;
    _detail = null;
  }

  /// Removes user-specific orders before the same app instance is shown to a
  /// guest or a different customer.
  void clearSessionData() {
    stopRealtime();
    _ordersRequest++;
    _detailRequest++;
    _orders = [];
    _activeOrder = null;
    _detail = null;
    _loading = false;
    _error = null;
    notifyListeners();
  }
}
