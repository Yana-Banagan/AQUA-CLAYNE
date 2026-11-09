import '../../core/api/api_client.dart';

/// All notification endpoints require Sanctum authentication.
/// Matches backend spec section 25: /api/customer/notifications
class NotificationRepository {
  final ApiClient _api;
  NotificationRepository(this._api);

  Future<List<Map<String, dynamic>>> list() async {
    final data = await _api.get('/customer/notifications');
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<void> markRead(String id) async {
    await _api.patch('/customer/notifications/$id/read', {}, auth: true);
  }

  Future<void> markAllRead() async {
    await _api.post('/customer/notifications/read-all', {}, auth: true);
  }
}
