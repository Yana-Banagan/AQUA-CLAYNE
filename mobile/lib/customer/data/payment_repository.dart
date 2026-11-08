import '../../core/api/api_client.dart';

class PaymentRepository {
  final ApiClient _api;

  PaymentRepository(this._api);

  /// Starts a Hosted Checkout for an existing order. Laravel calculates the
  /// amount from the stored order; the mobile app never supplies an amount.
  Future<Map<String, dynamic>> createCheckout(String orderId) async {
    final data = await _api.post(
      '/customer/orders/$orderId/payment/checkout',
      {},
      auth: true,
    );
    if (data is Map<String, dynamic>) {
      if (data['data'] is Map<String, dynamic>) {
        return {...data, ...data['data'] as Map<String, dynamic>};
      }
      return data;
    }
    return {};
  }

  /// Check payment status for a specific order.
  Future<Map<String, dynamic>> getOrderPaymentStatus(String orderId) async {
    final data = await _api.get(
      '/customer/orders/$orderId/payment',
      auth: true,
    );
    return data is Map<String, dynamic> ? data : {};
  }

  /// Fetch payment transactions for the authenticated customer.
  Future<List<Map<String, dynamic>>> getPayments({
    String? status,
    String? method,
  }) async {
    final query = <String, String>{};
    if (status != null && status.isNotEmpty && status.toLowerCase() != 'all') {
      query['status'] = status.toLowerCase();
    }
    if (method != null && method.isNotEmpty && method.toLowerCase() != 'all') {
      query['method'] = method.toLowerCase();
    }

    final path = query.isEmpty
        ? '/customer/payments'
        : '/customer/payments?${Uri(queryParameters: query).query}';

    final data = await _api.get(path, auth: true);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  /// Fetch payment and balance summary for the authenticated customer.
  Future<Map<String, dynamic>> getSummary() async {
    final data = await _api.get('/customer/payments/summary', auth: true);
    if (data is Map<String, dynamic>) {
      if (data.containsKey('data') && data['data'] is Map<String, dynamic>) {
        return data['data'] as Map<String, dynamic>;
      }
      return data;
    }
    return {};
  }

  /// Fetch a single payment detail.
  Future<Map<String, dynamic>> getPayment(String id) async {
    final data = await _api.get('/customer/payments/$id', auth: true);
    if (data is Map<String, dynamic>) {
      if (data.containsKey('data') && data['data'] is Map<String, dynamic>) {
        return data['data'] as Map<String, dynamic>;
      }
      return data;
    }
    return {};
  }
}
