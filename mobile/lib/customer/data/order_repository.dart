import '../../core/api/api_client.dart';

/// All order endpoints require Sanctum authentication.
/// Matches backend spec section 25: /api/customer/orders
class OrderRepository {
  final ApiClient _api;
  OrderRepository(this._api);

  Future<List<Map<String, dynamic>>> list() async {
    final data = await _api.get('/customer/orders', auth: true);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> get(String id) async {
    final data = await _api.get('/customer/orders/$id', auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> tracking(String id) async {
    final data = await _api.get('/customer/orders/$id/tracking');
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> payload) async {
    final data = await _api.post('/customer/orders', payload, auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<void> cancel(String id) async {
    await _api.post('/customer/orders/$id/cancel', {}, auth: true);
  }

  Future<Map<String, dynamic>> updateDeliveryMethod(
    String id,
    String deliveryMethod, {
    String? addressId,
  }) async {
    final payload = <String, dynamic>{
      'delivery_method': deliveryMethod,
      if (addressId != null && addressId.isNotEmpty) 'address_id': addressId,
    };
    final data = await _api.patch('/customer/orders/$id/delivery-method', payload, auth: true);
    if (data is Map<String, dynamic>) {
      if (data['order'] is Map<String, dynamic>) {
        return data['order'] as Map<String, dynamic>;
      }
      return data;
    }
    return {};
  }

  Future<Map<String, dynamic>> paymentOptions() async {
    final data = await _api.get('/customer/payment-options', auth: true);
    return data is Map<String, dynamic> ? data : {};
  }
}
