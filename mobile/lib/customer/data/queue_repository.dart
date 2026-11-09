import '../../core/api/api_client.dart';

/// Queue info — requires Sanctum auth.
/// Matches backend spec: GET /api/customer/orders/{id}/queue
class QueueRepository {
  final ApiClient _api;
  QueueRepository(this._api);

  Future<Map<String, dynamic>> getForOrder(String orderId) async {
    final data = await _api.get('/orders/$orderId/queue');
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> getActiveQueue() async {
    final data = await _api.get('/queue');
    if (data is Map<String, dynamic>) return data;
    return {};
  }
}
