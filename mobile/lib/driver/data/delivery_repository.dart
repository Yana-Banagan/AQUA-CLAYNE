import 'dart:io';
import '../../core/api/api_client.dart';

class DeliveryRepository {
  final ApiClient _api;
  DeliveryRepository(this._api);

  // ================================================================
  // ASSIGNED DELIVERIES (active)
  // ================================================================
  Future<List<Map<String, dynamic>>> assigned() async {
    final data = await _api.get('/driver/deliveries');
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  // ================================================================
  // HISTORY
  // ================================================================
  Future<List<Map<String, dynamic>>> history() async {
    final data = await _api.get('/driver/deliveries/history');
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  // ================================================================
  // GET SINGLE DELIVERY
  // ================================================================
  Future<Map<String, dynamic>> get(String id) async {
    final data = await _api.get('/driver/deliveries/$id');
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> details(String id) => get(id);

  Future<void> updateLocation(String id, double latitude, double longitude) async {
    await _api.post('/driver/deliveries/$id/location', {
      'latitude': latitude,
      'longitude': longitude,
    }, auth: true);
  }

  Future<Map<String, dynamic>> bulkOutForDelivery() async {
    final result = await _api.post('/driver/deliveries/out-for-delivery/bulk', const {}, auth: true);
    if (result is Map && result['data'] is Map) return Map<String, dynamic>.from(result['data'] as Map);
    return result is Map ? Map<String, dynamic>.from(result) : <String, dynamic>{};
  }

  // ================================================================
  // STATUS TRANSITIONS (Laravel API PATCH /driver/deliveries/{id}/status)
  // ================================================================
  Future<Map<String, dynamic>> updateStatus(
    String id,
    String newStatus, {
    String? note,
    String? eta,
  }) async {
    final body = <String, dynamic>{
      'status': newStatus.toLowerCase(),
      if (note != null && note.isNotEmpty) 'note': note,
      if (eta != null && eta.isNotEmpty) 'eta': eta,
    };

    final data = await _api.patch(
      '/driver/deliveries/$id/status',
      body,
      auth: true,
    );

    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // Pickup phase transitions
  Future<void> startPickup(String id) async {
    await updateStatus(id, 'en_route_for_pickup');
  }

  Future<void> confirmPickup(String id, int gallonsCollected) async {
    await updateStatus(
      id,
      'picked_up',
      note: 'Collected $gallonsCollected customer-owned gallon(s) for refill.',
    );
  }

  Future<void> reportPickupFailure(String id, String reason) async {
    await updateStatus(id, 'failed', note: reason);
  }

  // Station arrival
  Future<void> confirmStationArrival(String id) async {
    await updateStatus(
      id,
      'at_station',
      note: 'Customer gallons arrived at station.',
    );
  }

  // Delivery phase transitions
  Future<void> startDelivery(String id) async {
    await updateStatus(id, 'out_for_delivery');
  }

  Future<void> markDelivered(String id) async {
    await updateStatus(id, 'delivered');
  }

  Future<void> reportDeliveryFailure(String id, String reason) async {
    await updateStatus(id, 'failed', note: reason);
  }

  // ================================================================
  // PAYMENT COLLECTION (PATCH /driver/deliveries/{id}/collect-payment)
  // ================================================================
  Future<Map<String, dynamic>> collectPayment(String id, double amount) async {
    final data = await _api.patch(
      '/driver/deliveries/$id/collect-payment',
      {'amount': amount},
      auth: true,
    );
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // PROOF OF DELIVERY (POST /driver/deliveries/{id}/proof)
  // ================================================================
  Future<Map<String, dynamic>> uploadProof(String id, File photo) async {
    final data = await _api.postMultipart(
      '/driver/deliveries/$id/proof',
      {'photo': photo},
    );
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // REPORT DELIVERY ISSUE (POST /driver/deliveries/{id}/issue)
  // ================================================================
  Future<Map<String, dynamic>> reportIssue(
    String id, {
    required String issueType,
    required String description,
    bool markAsFailed = false,
  }) async {
    final data = await _api.post(
      '/driver/deliveries/$id/issue',
      {
        'issue_type': issueType,
        'description': description,
        'mark_failed': markAsFailed,
      },
      auth: true,
    );
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // NOTIFICATIONS (GET /driver/notifications)
  // ================================================================
  Future<List<Map<String, dynamic>>> notifications() async {
    final data = await _api.get('/driver/notifications');
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<void> markNotificationRead(String id) async {
    await _api.patch('/driver/notifications/$id/read', {}, auth: true);
  }

  // ================================================================
  // PICKUP BATCHES (Multi-stop gallon collection)
  // ================================================================
  Future<List<Map<String, dynamic>>> getPickupBatches({String? status}) async {
    final path = status != null && status.isNotEmpty
        ? '/driver/pickups?status=$status'
        : '/driver/pickups';
    final data = await _api.get(path);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> getPickupBatch(String id) async {
    final data = await _api.get('/driver/pickups/$id');
    if (data is Map<String, dynamic>) {
      if (data.containsKey('data') && data['data'] is Map<String, dynamic>) {
        return data['data'] as Map<String, dynamic>;
      }
      return data;
    }
    return {};
  }

  Future<Map<String, dynamic>> updatePickupBatchStatus(String id, String status) async {
    final data = await _api.patch('/driver/pickups/$id/status', {'status': status}, auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> collectPickupStop(String batchId, String itemId, int collectedCount, {String? notes}) async {
    final data = await _api.post(
      '/driver/pickups/$batchId/stops/$itemId/collect',
      {
        'collected_count': collectedCount,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      auth: true,
    );
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // DELIVERY ROUTES (Multi-stop refilled gallon drop-offs)
  // ================================================================
  Future<List<Map<String, dynamic>>> getDeliveryRoutes({String? status}) async {
    final path = status != null && status.isNotEmpty
        ? '/driver/routes?status=$status'
        : '/driver/routes';
    final data = await _api.get(path);
    if (data is List) return data.cast<Map<String, dynamic>>();
    if (data is Map && data['data'] is List) {
      return (data['data'] as List).cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<Map<String, dynamic>> getDeliveryRoute(String id) async {
    final data = await _api.get('/driver/routes/$id');
    if (data is Map<String, dynamic>) {
      if (data.containsKey('data') && data['data'] is Map<String, dynamic>) {
        return data['data'] as Map<String, dynamic>;
      }
      return data;
    }
    return {};
  }

  Future<Map<String, dynamic>> updateDeliveryRouteStatus(String id, String status) async {
    final data = await _api.patch('/driver/routes/$id/status', {'status': status}, auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> updateDeliveryStopStatus(String routeId, String stopId, String status, {String? notes}) async {
    final data = await _api.patch(
      '/driver/routes/$routeId/stops/$stopId/status',
      {
        'status': status,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      auth: true,
    );
    if (data is Map<String, dynamic>) return data;
    return {};
  }
}
