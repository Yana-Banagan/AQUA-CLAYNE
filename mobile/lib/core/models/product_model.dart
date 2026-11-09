class ProductModel {
  final String id;
  final String name;
  final String? description;
  final double price;
  final String? imageUrl;
  final bool isAvailable;
  final String? status;
  final String productType;

  const ProductModel({
    required this.id,
    required this.name,
    this.description,
    required this.price,
    this.imageUrl,
    this.isAvailable = true,
    this.status,
    this.productType = 'refill',
  });

  factory ProductModel.fromMap(Map<String, dynamic> m) {
    return ProductModel(
      id: m['id']?.toString() ?? '',
      name: m['name']?.toString() ?? '',
      description: m['description']?.toString(),
      price: _toDouble(m['price']),
      imageUrl: m['image_url']?.toString(),
      isAvailable: m['in_stock'] == true ||
          m['in_stock'] == 1 ||
          m['is_available'] == true ||
          m['is_available'] == 1,
      status: m['status']?.toString(),
      productType: m['product_type']?.toString() ?? 'refill',
    );
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  Map<String, dynamic> toMap() => {
        'id': id,
        'name': name,
        'description': description,
        'price': price,
        'image_url': imageUrl,
        'is_available': isAvailable,
        'product_type': productType,
      };

  bool get isNewGallon => productType == 'new_gallon';
  bool get isRefill => !isNewGallon;
}
