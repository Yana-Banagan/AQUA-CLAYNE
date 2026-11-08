import '../../core/api/api_client.dart';

/// Credit endpoint requires Sanctum authentication.
/// FAQs endpoint is public (no auth needed).
/// Matches backend spec section 25: /api/customer/credit, /api/customer/faqs
class CreditRepository {
  final ApiClient _api;
  CreditRepository(this._api);

  Future<Map<String, dynamic>> getCredit() async {
    final data = await _api.get('/customer/credit');
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  /// FAQs — public endpoint, no auth required.
  Future<List<Map<String, dynamic>>> getFaqs() async {
    final data = await _api.get('/customer/faqs', auth: false);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }
}
