import 'address_model.dart';
import 'product_model.dart';

class OrderItemModel {
  final String id;
  final String productId;
  final String productName;
  final int quantity;
  final double unitPrice;
  final double subtotal;
  final ProductModel? product;

  const OrderItemModel({
    required this.id,
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.unitPrice,
    required this.subtotal,
    this.product,
  });

  factory OrderItemModel.fromMap(Map<String, dynamic> m) {
    final prod = m['product'] as Map<String, dynamic>?;
    return OrderItemModel(
      id: m['id']?.toString() ?? '',
      productId: m['product_id']?.toString() ?? '',
      productName: prod?['name']?.toString() ??
          m['product_name']?.toString() ?? 'Service',
      quantity: _toInt(m['quantity']),
      unitPrice: _toDouble(m['unit_price']),
      subtotal: _toDouble(m['subtotal']),
      product: prod != null ? ProductModel.fromMap(prod) : null,
    );
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse(v?.toString() ?? '0') ?? 0;
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  String get summary => '$quantity × $productName';
  Map<String, dynamic> toMap() => {
        'id': id,
        'product_id': productId,
        'quantity': quantity,
        'unit_price': unitPrice,
        'product_name': productName,
        if (product != null) 'product': product!.toMap(),
      };
}

class QueueInfoModel {
  final String queueNumber;
  final int position;
  final String status;

  const QueueInfoModel({
    required this.queueNumber,
    required this.position,
    required this.status,
  });

  factory QueueInfoModel.fromMap(Map<String, dynamic> m) {
    return QueueInfoModel(
      queueNumber: m['queue_number']?.toString() ?? '-',
      position: m['position'] is int
          ? m['position'] as int
          : int.tryParse(m['position']?.toString() ?? '0') ?? 0,
      status: m['status']?.toString() ?? 'WAITING',
    );
  }
}

class OrderModel {
  final String id;
  final String orderNumber;
  final String status;
  final String containerOwnership;
  final int expectedEmptyGallons;
  final int? receivedEmptyGallons;
  final int? acceptedEmptyGallons;
  final String paymentMethod;
  final String paymentStatus;
  final double totalAmount;
  final String deliveryMethod;
  final double deliveryFee;
  final String? notes;
  final List<OrderItemModel> items;
  final AddressModel? address;
  final QueueInfoModel? queue;
  final Map<String, dynamic>? delivery;
  final String createdAt;
  final String? confirmedAt;
  final String? completedAt;
  final String? cancelledAt;

  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    this.containerOwnership = 'CUSTOMER_OWNED',
    required this.expectedEmptyGallons,
    this.receivedEmptyGallons,
    this.acceptedEmptyGallons,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.totalAmount,
    this.deliveryMethod = 'home_delivery',
    this.deliveryFee = 5.0,
    this.notes,
    this.items = const [],
    this.address,
    this.queue,
    this.delivery,
    required this.createdAt,
    this.confirmedAt,
    this.completedAt,
    this.cancelledAt,
  });

  factory OrderModel.fromMap(Map<String, dynamic> m) {
    final rawItems = m['order_items'] as List? ?? m['items'] as List? ?? [];
    final addressMap = m['address'] as Map<String, dynamic>?;
    final queueMap = (m['queue'] ?? m['refill_queue_entry'])
        as Map<String, dynamic>?;
    final intakeMap = m['container_intake'] as Map<String, dynamic>?;
    final paymentMap = m['payment'] as Map<String, dynamic>?;
    final parsedDeliveryMethod =
        m['delivery_method']?.toString().toLowerCase() ?? 'home_delivery';
    final parsedDeliveryFee = m['delivery_fee'] != null
        ? _toDouble(m['delivery_fee'])
        : (parsedDeliveryMethod == 'station_pickup' ? 0.0 : 5.0);

    return OrderModel(
      id: m['id']?.toString() ?? '',
      orderNumber: m['order_number']?.toString() ?? '',
      status: m['status']?.toString() ?? 'PENDING',
      containerOwnership:
          m['container_ownership']?.toString() ?? 'CUSTOMER_OWNED',
      expectedEmptyGallons: _toInt(m['expected_empty_gallons']),
      receivedEmptyGallons:
          (m['received_empty_gallons'] ?? intakeMap?['received_count']) != null
              ? _toInt(m['received_empty_gallons'] ?? intakeMap?['received_count'])
          : null,
      acceptedEmptyGallons:
          (m['accepted_empty_gallons'] ?? intakeMap?['accepted_count']) != null
              ? _toInt(m['accepted_empty_gallons'] ?? intakeMap?['accepted_count'])
          : null,
      paymentMethod: m['payment_method']?.toString() ?? 'CASH',
      paymentStatus: m['payment_status']?.toString() ??
          paymentMap?['status']?.toString() ??
          'PENDING',
      totalAmount: _toDouble(m['total_amount']),
      deliveryMethod: parsedDeliveryMethod,
      deliveryFee: parsedDeliveryFee,
      notes: m['notes']?.toString(),
      items: rawItems
          .map((i) => OrderItemModel.fromMap(i as Map<String, dynamic>))
          .toList(),
      address: addressMap != null ? AddressModel.fromMap(addressMap) : null,
      queue: queueMap != null ? QueueInfoModel.fromMap(queueMap) : null,
      delivery: m['delivery'] as Map<String, dynamic>?,
      createdAt: m['created_at']?.toString() ?? '',
      confirmedAt: m['confirmed_at']?.toString(),
      completedAt: m['completed_at']?.toString(),
      cancelledAt: m['cancelled_at']?.toString(),
    );
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse(v?.toString() ?? '0') ?? 0;
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  bool get isStationPickup => deliveryMethod.toLowerCase() == 'station_pickup';
  bool get isHomeDelivery => !isStationPickup;
  bool get canSwitchDeliveryMethod => status.toUpperCase() == 'PENDING';

  String get deliveryMethodDisplay =>
      isStationPickup ? 'Station Pickup' : 'Home Delivery';

  bool get isActive => ![
        'COMPLETED',
        'DELIVERED',
        'REJECTED',
        'CANCELLED',
        'PICKUP_FAILED',
        'DELIVERY_FAILED',
      ].contains(status.toUpperCase());

  Map<String, dynamic> get raw => {
        'id': id,
        'order_number': orderNumber,
        'status': status,
        'delivery_method': deliveryMethod,
        'delivery_fee': deliveryFee,
        'total_amount': totalAmount,
        'created_at': createdAt,
        'payment_method': paymentMethod,
        'payment_status': paymentStatus,
        'items': items.map((item) => item.toMap()).toList(),
        if (address != null) 'address': address!.toMap(),
        if (queue != null)
          'refill_queue_entry': {
            'queue_number': queue!.queueNumber,
            'status': queue!.status,
          },
        if (delivery != null) 'delivery': delivery,
      };

  bool get isCancellable =>
      ['PENDING'].contains(status.toUpperCase());

  String get itemSummary => items.isEmpty
      ? 'Refill Order'
      : items.map((i) => i.summary).join(', ');

  OrderModel copyWith({
    String? id,
    String? orderNumber,
    String? status,
    String? containerOwnership,
    int? expectedEmptyGallons,
    int? receivedEmptyGallons,
    int? acceptedEmptyGallons,
    String? paymentMethod,
    String? paymentStatus,
    double? totalAmount,
    String? deliveryMethod,
    double? deliveryFee,
    String? notes,
    List<OrderItemModel>? items,
    AddressModel? address,
    QueueInfoModel? queue,
    Map<String, dynamic>? delivery,
    String? createdAt,
    String? confirmedAt,
    String? completedAt,
    String? cancelledAt,
  }) {
    return OrderModel(
      id: id ?? this.id,
      orderNumber: orderNumber ?? this.orderNumber,
      status: status ?? this.status,
      containerOwnership: containerOwnership ?? this.containerOwnership,
      expectedEmptyGallons: expectedEmptyGallons ?? this.expectedEmptyGallons,
      receivedEmptyGallons: receivedEmptyGallons ?? this.receivedEmptyGallons,
      acceptedEmptyGallons: acceptedEmptyGallons ?? this.acceptedEmptyGallons,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      totalAmount: totalAmount ?? this.totalAmount,
      deliveryMethod: deliveryMethod ?? this.deliveryMethod,
      deliveryFee: deliveryFee ?? this.deliveryFee,
      notes: notes ?? this.notes,
      items: items ?? this.items,
      address: address ?? this.address,
      queue: queue ?? this.queue,
      delivery: delivery ?? this.delivery,
      createdAt: createdAt ?? this.createdAt,
      confirmedAt: confirmedAt ?? this.confirmedAt,
      completedAt: completedAt ?? this.completedAt,
      cancelledAt: cancelledAt ?? this.cancelledAt,
    );
  }
}
