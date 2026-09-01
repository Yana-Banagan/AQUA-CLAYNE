import '../../core/api/api_client.dart';

class DeliveryRepository {
  final ApiClient api;

  DeliveryRepository(this.api);

  // ================================================================
  // ASSIGNED DELIVERIES
  // ================================================================

  Future<List<Map<String, dynamic>>> assigned() async {
    final response = await api.get('/driver/deliveries');

    return List<Map<String, dynamic>>.from(response);
  }

  // ================================================================
  // DELIVERY HISTORY
  // ================================================================

  Future<List<Map<String, dynamic>>> history() async {
    final response = await api.get('/driver/deliveries/history');

    return List<Map<String, dynamic>>.from(response);
  }

  // ================================================================
  // DELIVERY DETAILS
  // ================================================================

  Future<Map<String, dynamic>> details(String id) async {
    final response = await api.get('/driver/deliveries/$id');

    return Map<String, dynamic>.from(response);
  }

  // ================================================================
  // UPDATE DELIVERY STATUS
  // ================================================================

  Future<Map<String, dynamic>> updateStatus(
    String id,
    String status, {
    String? note,
    String? eta,
  }) async {
    return await api.patch('/driver/deliveries/$id/status', {
      'status': status,
      'note': ?note,
      'eta': ?eta,
    });
  }
}
