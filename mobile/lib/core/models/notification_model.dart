class NotificationModel {
  final String id;
  final String title;
  final String body;
  final String? type;
  final String? entityId;
  final bool isRead;
  final String createdAt;
  final Map<String, dynamic>? data;

  const NotificationModel({
    required this.id,
    required this.title,
    required this.body,
    this.type,
    this.entityId,
    required this.isRead,
    required this.createdAt,
    this.data,
  });

  factory NotificationModel.fromMap(Map<String, dynamic> m) {
    Map<String, dynamic>? parsedData;
    if (m['data'] is Map<String, dynamic>) {
      parsedData = m['data'] as Map<String, dynamic>;
    } else if (m['data'] is Map) {
      parsedData = Map<String, dynamic>.from(m['data'] as Map);
    }

    final entityId = m['entity_id']?.toString() ??
        m['order_id']?.toString() ??
        parsedData?['entity_id']?.toString() ??
        parsedData?['order_id']?.toString();

    final type = m['type']?.toString() ??
        parsedData?['entity_type']?.toString() ??
        parsedData?['type']?.toString();

    return NotificationModel(
      id: m['id']?.toString() ?? '',
      title: m['title']?.toString() ?? 'Notification',
      body: m['body']?.toString() ?? m['message']?.toString() ?? '',
      type: type,
      entityId: entityId,
      isRead: m['is_read'] == true || m['is_read'] == 1 || m['read_at'] != null,
      createdAt: m['created_at']?.toString() ?? '',
      data: parsedData,
    );
  }

  NotificationModel copyWith({
    String? id,
    String? title,
    String? body,
    String? type,
    String? entityId,
    bool? isRead,
    String? createdAt,
    Map<String, dynamic>? data,
  }) {
    return NotificationModel(
      id: id ?? this.id,
      title: title ?? this.title,
      body: body ?? this.body,
      type: type ?? this.type,
      entityId: entityId ?? this.entityId,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt ?? this.createdAt,
      data: data ?? this.data,
    );
  }

  bool get isOrderRelated {
    final lowerTitle = title.toLowerCase();
    final lowerBody = body.toLowerCase();
    final lowerType = (type ?? '').toLowerCase();
    return lowerType.contains('order') ||
        lowerType.contains('delivery') ||
        lowerType.contains('pickup') ||
        lowerTitle.contains('order') ||
        lowerTitle.contains('delivery') ||
        lowerTitle.contains('pickup') ||
        lowerTitle.contains('refill') ||
        lowerBody.contains('order');
  }

  bool get isPaymentRelated {
    final lowerTitle = title.toLowerCase();
    final lowerBody = body.toLowerCase();
    final lowerType = (type ?? '').toLowerCase();
    return lowerType.contains('pay') ||
        lowerType.contains('credit') ||
        lowerTitle.contains('pay') ||
        lowerTitle.contains('credit') ||
        lowerTitle.contains('balance') ||
        lowerBody.contains('payment') ||
        lowerBody.contains('paid');
  }

  bool get isAccountRelated {
    final lowerTitle = title.toLowerCase();
    final lowerBody = body.toLowerCase();
    final lowerType = (type ?? '').toLowerCase();
    return lowerType.contains('account') ||
        lowerType.contains('user') ||
        lowerType.contains('complaint') ||
        lowerTitle.contains('profile') ||
        lowerTitle.contains('password') ||
        lowerTitle.contains('complaint') ||
        lowerTitle.contains('account') ||
        lowerBody.contains('account');
  }
}
