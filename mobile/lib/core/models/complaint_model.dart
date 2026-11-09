class ComplaintModel {
  final String id;
  final String? orderId;
  final String? orderNumber;
  final String categoryId;
  final String categoryName;
  final String description;
  final String status;
  final String? adminResponse;
  final String? photoPath;
  final String createdAt;
  final String? resolvedAt;

  const ComplaintModel({
    required this.id,
    this.orderId,
    this.orderNumber,
    required this.categoryId,
    required this.categoryName,
    required this.description,
    required this.status,
    this.adminResponse,
    this.photoPath,
    required this.createdAt,
    this.resolvedAt,
  });

  factory ComplaintModel.fromMap(Map<String, dynamic> m) {
    final cat = m['category'] as Map<String, dynamic>? ?? {};
    final order = m['order'] as Map<String, dynamic>? ?? {};
    return ComplaintModel(
      id: m['id']?.toString() ?? '',
      orderId: m['order_id']?.toString(),
      orderNumber: order['order_number']?.toString(),
      categoryId: m['complaint_category_id']?.toString() ??
          cat['id']?.toString() ?? '',
      categoryName: cat['name']?.toString() ?? 'General',
      description: m['description']?.toString() ?? '',
      status: m['status']?.toString() ?? 'OPEN',
      adminResponse: m['admin_response']?.toString(),
      photoPath: m['photo_path']?.toString(),
      createdAt: m['created_at']?.toString() ?? '',
      resolvedAt: m['resolved_at']?.toString(),
    );
  }
}

class ComplaintCategoryModel {
  final String id;
  final String name;

  const ComplaintCategoryModel({required this.id, required this.name});

  factory ComplaintCategoryModel.fromMap(Map<String, dynamic> m) =>
      ComplaintCategoryModel(
        id: m['id']?.toString() ?? '',
        name: m['name']?.toString() ?? '',
      );
}
