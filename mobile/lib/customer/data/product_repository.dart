import '../../core/api/api_client.dart';

/// Products/refill services are a public catalog — no auth required for listing.
/// Matches backend route: GET /api/products
class ProductRepository {
  final ApiClient _api;
  ProductRepository(this._api);

  Future<List<Map<String, dynamic>>> list() async {
    // Backend spec section 25: GET /api/products (public, no auth needed)
    final data = await _api.get('/products', auth: false);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> get(String id) async {
    // Backend spec section 25: GET /api/products/{product} (public)
    final data = await _api.get('/products/$id', auth: false);
    if (data is Map<String, dynamic>) return data;
    return {};
  }
}
