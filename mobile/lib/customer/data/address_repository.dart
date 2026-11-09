import '../../core/api/api_client.dart';

/// All address endpoints require Sanctum authentication.
/// Address routes are grouped under /api/v1 for authenticated customers.
class AddressRepository {
  final ApiClient _api;
  AddressRepository(this._api);

  Future<List<Map<String, dynamic>>> list() async {
    final data = await _api.get('/customer/addresses'); // auth: true by default
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> payload) async {
    final data = await _api.post('/customer/addresses', payload, auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<dynamic> update(
      String id, Map<String, dynamic> payload) async {
    final data = await _api.patch('/customer/addresses/$id', payload, auth: true);
    return data;
  }

  Future<void> delete(String id) async {
    await _api.delete('/customer/addresses/$id', auth: true);
  }

  /// Sets an address as the default using a dedicated action endpoint.
  /// Falls back to PATCH if the server doesn't have /set-default.
  Future<dynamic> setDefault(String id) async {
    try {
      return await _api.post('/customer/addresses/$id/set-default', {}, auth: true);
    } on ApiException catch (e) {
      // If the dedicated endpoint doesn't exist (404), fall back to PATCH
      if (e.statusCode == 404) {
        return await _api.patch('/customer/addresses/$id', {'is_default': true}, auth: true);
      } else {
        rethrow;
      }
    }
  }
}
