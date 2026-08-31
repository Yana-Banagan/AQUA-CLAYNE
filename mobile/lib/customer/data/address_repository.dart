import '../../core/api/api_client.dart';

class AddressRepository {
  final ApiClient api;

  AddressRepository(this.api);

  // ================================================================
  // GET ALL ADDRESSES
  // ================================================================

  Future<List<Map<String, dynamic>>> list() async {
    final data = await api.get('/addresses');

    return List<Map<String, dynamic>>.from(data);
  }

  // ================================================================
  // CREATE ADDRESS
  // ================================================================

  Future<Map<String, dynamic>> create(Map<String, dynamic> address) async {
    final data = await api.post('/addresses', address, auth: true);

    return Map<String, dynamic>.from(data);
  }

  // ================================================================
  // UPDATE ADDRESS
  // ================================================================

  Future<Map<String, dynamic>> update(
    String id,
    Map<String, dynamic> address,
  ) async {
    final data = await api.put('/addresses/$id', address, auth: true);

    return Map<String, dynamic>.from(data);
  }

  // ================================================================
  // DELETE ADDRESS
  // ================================================================

  Future<void> delete(String id) async {
    await api.delete('/addresses/$id', auth: true);
  }
}
