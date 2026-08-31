import '../../core/api/api_client.dart';

class OrderRepository {
  final ApiClient api;

  OrderRepository(this.api);

  Future<Map<String, dynamic>> create(
    String addressId,
    List<Map<String, dynamic>> items,
  ) async {
    final data = await api.post('/orders', {
      'address_id': addressId,
      'items': items,
    }, auth: true);

    return Map<String, dynamic>.from(data);
  }

  Future<List<Map<String, dynamic>>> list() async {
    final data = await api.get('/orders', auth: true);

    return List<Map<String, dynamic>>.from(data);
  }
}
