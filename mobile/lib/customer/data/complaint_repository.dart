import '../../core/api/api_client.dart';

/// All complaint endpoints require Sanctum authentication.
/// Categories endpoint is public (no auth needed).
/// Matches backend spec section 25: /api/customer/complaints
class ComplaintRepository {
  final ApiClient _api;
  ComplaintRepository(this._api);

  Future<List<Map<String, dynamic>>> list() async {
    final data = await _api.get('/customer/complaints');
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> get(String id) async {
    final data = await _api.get('/customer/complaints/$id');
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> payload) async {
    final data =
        await _api.post('/customer/complaints', payload, auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  /// Complaint categories — public endpoint, no auth required.
  Future<List<Map<String, dynamic>>> categories() async {
    final data = await _api.get('/customer/complaint-categories', auth: false);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }
}
