import 'package:flutter/foundation.dart';

class CartItem {
  final Map<String, dynamic> product;
  int quantity;

  CartItem({required this.product, required this.quantity});

  double get unitPrice {
    final value = product['price'];

    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString() ?? '0') ?? 0.0;
  }

  double get lineTotal {
    return unitPrice * quantity;
  }
}

class CartProvider extends ChangeNotifier {
  final List<CartItem> _items = [];

  List<CartItem> get items => List.unmodifiable(_items);

  int get itemCount {
    return _items.fold(0, (total, item) => total + item.quantity);
  }

  double get total {
    return _items.fold(0.0, (total, item) => total + item.lineTotal);
  }

  void add(Map<String, dynamic> product) {
    final productId = product['id']?.toString();

    if (productId == null || productId.isEmpty) {
      return;
    }

    final existingIndex = _items.indexWhere(
      (item) => item.product['id']?.toString() == productId,
    );

    if (existingIndex >= 0) {
      _items[existingIndex].quantity++;
    } else {
      _items.add(CartItem(product: product, quantity: 1));
    }

    notifyListeners();
  }

  void updateQuantity(String productId, int newQuantity) {
    final index = _items.indexWhere(
      (item) => item.product['id']?.toString() == productId,
    );

    if (index == -1) {
      return;
    }

    if (newQuantity <= 0) {
      _items.removeAt(index);
    } else {
      _items[index].quantity = newQuantity;
    }

    notifyListeners();
  }

  void remove(String productId) {
    _items.removeWhere((item) => item.product['id']?.toString() == productId);

    notifyListeners();
  }

  void clear() {
    _items.clear();
    notifyListeners();
  }
}
