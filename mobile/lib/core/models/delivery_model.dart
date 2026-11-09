class DeliveryModel {
  final String id;
  final String orderId;
  final String orderNumber;
  final String status;
  final String customerName;
  final String customerPhone;
  final String addressLine;
  final String city;
  final double? latitude;
  final double? longitude;
  final int expectedGallons;
  final double totalAmount;
  final String paymentMethod;
  final String paymentStatus;
  final String? eta;
  final String? proofPhotoPath;
  final String? proofPhotoSignedUrl;
  final String? failureReason;
  final String? driverNotes;
  final String createdAt;
  final List<dynamic> items;
  final Map<String, dynamic>? containerIntake;
  final Map<String, dynamic> raw;

  const DeliveryModel({
    required this.id,
    required this.orderId,
    required this.orderNumber,
    required this.status,
    required this.customerName,
    required this.customerPhone,
    required this.addressLine,
    required this.city,
    this.latitude,
    this.longitude,
    required this.expectedGallons,
    required this.totalAmount,
    required this.paymentMethod,
    required this.paymentStatus,
    this.eta,
    this.proofPhotoPath,
    this.proofPhotoSignedUrl,
    this.failureReason,
    this.driverNotes,
    required this.createdAt,
    this.items = const [],
    this.containerIntake,
    required this.raw,
  });

  factory DeliveryModel.fromMap(Map<String, dynamic> m) {
    final order = _toMap(m['order']);
    final customer = _toMap(order['customer'] ?? m['customer']);
    final address = _toMap(order['address'] ?? m['address']);
    final user = _toMap(customer['user']);
    final payment = _toMap(order['payment'] ?? m['payment']);

    final rawLat = address['latitude'];
    final rawLng = address['longitude'];
    final lat = rawLat != null ? double.tryParse(rawLat.toString()) : null;
    final lng = rawLng != null ? double.tryParse(rawLng.toString()) : null;

    final photoPath = m['proof_photo_url']?.toString() ??
        m['proof_photo_path']?.toString();
    final photoSigned = m['proof_photo_signed_url']?.toString();

    final orderItems = (order['items'] as List?) ?? [];
    final intake = _toMap(order['container_intake'] ?? m['container_intake']);

    return DeliveryModel(
      id: m['id']?.toString() ?? '',
      orderId: m['order_id']?.toString() ?? order['id']?.toString() ?? '',
      orderNumber: order['order_number']?.toString() ??
          m['order_number']?.toString() ?? 'N/A',
      status: m['status']?.toString() ?? 'ASSIGNED',
      customerName: user['name']?.toString() ??
          customer['name']?.toString() ?? 'Customer',
      customerPhone: user['phone']?.toString() ??
          customer['phone']?.toString() ?? '',
      addressLine: address['address_line']?.toString() ??
          address['street']?.toString() ?? '',
      city: address['city']?.toString() ?? '',
      latitude: lat,
      longitude: lng,
      expectedGallons: _toInt(
        order['expected_empty_gallons'] ??
            order['expected_gallons'] ??
            m['expected_empty_gallons'] ??
            m['expected_gallons'] ??
            order['quantity'] ??
            m['quantity'],
      ),
      totalAmount: _toDouble(
        payment['amount'] ?? order['total_amount'] ?? m['total_amount'],
      ),
      paymentMethod: payment['method']?.toString() ??
          order['payment_method']?.toString() ??
          m['payment_method']?.toString() ??
          'CASH',
      paymentStatus: payment['status']?.toString() ??
          order['payment_status']?.toString() ??
          m['payment_status']?.toString() ??
          'PENDING',
      eta: m['eta']?.toString(),
      proofPhotoPath: photoPath,
      proofPhotoSignedUrl: photoSigned,
      failureReason: m['failure_reason']?.toString(),
      driverNotes: m['driver_notes']?.toString(),
      createdAt: m['created_at']?.toString() ?? '',
      items: orderItems,
      containerIntake: intake.isNotEmpty ? intake : null,
      raw: m,
    );
  }

  static Map<String, dynamic> _toMap(dynamic v) {
    if (v is Map<String, dynamic>) return v;
    if (v is Map) return Map<String, dynamic>.from(v);
    return {};
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString().trim()) ?? 0;
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0.0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString().trim()) ?? 0.0;
  }

  String get fullAddress =>
      city.isNotEmpty ? '$addressLine, $city' : addressLine;

  bool get hasCoordinates =>
      latitude != null && longitude != null && latitude != 0 && longitude != 0;

  bool get isPickupPhase => [
        'ASSIGNED',
        'EN_ROUTE_FOR_PICKUP',
        'PICKED_UP',
        'AT_STATION',
      ].contains(status.toUpperCase());

  bool get isDeliveryPhase => [
        'READY_FOR_DELIVERY',
        'OUT_FOR_DELIVERY',
      ].contains(status.toUpperCase());

  bool get isCompleted =>
      ['DELIVERED', 'COMPLETED'].contains(status.toUpperCase());

  bool get isFailed => status.toUpperCase() == 'FAILED';

  bool get isCash => paymentMethod.toUpperCase() == 'CASH';

  bool get isOnline => paymentMethod.toUpperCase() == 'ONLINE';

  bool get isOnlinePay => isOnline || paymentMethod.toUpperCase() == 'PAYMONGO';

  bool get isCredit => paymentMethod.toUpperCase() == 'CREDIT';

  bool get isPaid => paymentStatus.toUpperCase() == 'PAID';

  bool get hasProof =>
      (proofPhotoPath != null && proofPhotoPath!.isNotEmpty) ||
      (proofPhotoSignedUrl != null && proofPhotoSignedUrl!.isNotEmpty);
}
