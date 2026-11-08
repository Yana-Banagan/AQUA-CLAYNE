import 'package:flutter/foundation.dart';
import '../../core/models/payment_model.dart';
import '../../core/services/realtime_service.dart';
import '../data/payment_repository.dart';

class PaymentProvider extends ChangeNotifier {
  final PaymentRepository _repo;
  final RealtimeService _realtime;

  PaymentProvider(this._repo, this._realtime);

  List<PaymentModel> _payments = [];
  PaymentSummaryModel _summary = const PaymentSummaryModel();
  PaymentModel? _detail;
  String _selectedFilter = 'ALL';
  bool _loading = false;
  String? _error;
  bool _realtimeActive = false;
  final List<VoidCallback> _removeRealtimeListeners = [];

  List<PaymentModel> get payments => _payments;
  PaymentSummaryModel get summary => _summary;
  PaymentModel? get detail => _detail;
  String get selectedFilter => _selectedFilter;
  bool get loading => _loading;
  String? get error => _error;

  int get allCount => _payments.length;
  int get paidCount => _payments.where((p) => p.isPaid).length;
  int get pendingCount => _payments.where((p) => p.isPending).length;

  List<PaymentModel> get filteredPayments {
    switch (_selectedFilter.toUpperCase()) {
      case 'PAID':
        return _payments.where((p) => p.isPaid).toList();
      case 'PENDING':
        return _payments.where((p) => p.isPending).toList();
      case 'ALL':
      default:
        return _payments;
    }
  }

  void setFilter(String filter) {
    if (_selectedFilter == filter) return;
    _selectedFilter = filter;
    notifyListeners();
  }

  /// Load payment history and summary from authenticated Laravel API.
  Future<void> loadPayments() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      // A summary is supplementary. Do not hide a successfully loaded payment
      // list just because the independent summary endpoint is unavailable.
      final results = await Future.wait<dynamic>([
        _repo.getPayments(),
        _repo.getSummary().catchError((_) => <String, dynamic>{}),
      ]);
      final rawPayments = results[0] as List<Map<String, dynamic>>;
      _payments = rawPayments.map((m) => PaymentModel.fromMap(m)).toList();
      _summary = PaymentSummaryModel.fromMap(
        results[1] as Map<String, dynamic>,
      );
    } catch (e) {
      _error = 'Unable to load payment information.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Load details of a specific payment.
  Future<PaymentModel?> loadPaymentDetail(String paymentId) async {
    try {
      final raw = await _repo.getPayment(paymentId);
      if (raw.isNotEmpty) {
        _detail = PaymentModel.fromMap(raw);
        notifyListeners();
        return _detail;
      }
    } catch (_) {}
    return null;
  }

  // ================================================================
  // REALTIME — Signal Table Subscription
  // ================================================================

  void startRealtime() {
    if (_realtimeActive) return;
    _realtimeActive = true;

    // Listen to order and delivery signal events to auto-refresh payments
    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'payment',
      onSignal: () {
        loadPayments();
        if (_detail != null) loadPaymentDetail(_detail!.id);
      },
    ));

    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'order',
      onSignal: () {
        loadPayments();
      },
    ));

    _removeRealtimeListeners.add(_realtime.listenToEntity(
      entityType: 'delivery',
      onSignal: () {
        loadPayments();
      },
    ));
  }

  void stopRealtime() {
    _realtimeActive = false;
    for (final removeListener in _removeRealtimeListeners) {
      removeListener();
    }
    _removeRealtimeListeners.clear();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearSessionData() {
    _payments = [];
    _summary = const PaymentSummaryModel();
    _detail = null;
    _loading = false;
    _error = null;
    stopRealtime();
    notifyListeners();
  }
}
