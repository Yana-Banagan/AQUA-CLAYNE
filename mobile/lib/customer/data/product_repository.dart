import '../../core/api/api_client.dart';

class ProductRepository {
  final ApiClient api;

  ProductRepository(this.api);

  Future<List<Map<String, dynamic>>> list() async {
    final data = await api.get('/products');

    return List<Map<String, dynamic>>.from(data);
  }
}
