import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../../core/models/product_model.dart';

class CartItem {
  final ProductModel product;
  int quantity;

  CartItem({required this.product, this.quantity = 1});

  double get subtotal => product.price * quantity;
}

class CartProvider extends ChangeNotifier {
  final Map<String, CartItem> _items = {};
  String _deliveryMethod = 'home_delivery';
  static const _storageKey = 'customer_cart_v1';

  CartProvider() {
    _restore();
  }

  String get deliveryMethod => _deliveryMethod;
  bool get isStationPickup => _deliveryMethod == 'station_pickup';
  bool get isHomeDelivery => !isStationPickup;

  void setDeliveryMethod(String method) {
    final normalized = method.toLowerCase();
    if (_deliveryMethod != normalized) {
      _deliveryMethod = normalized;
      notifyListeners();
    }
  }

  Future<void> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_storageKey);
    if (raw == null) return;
    try {
      final saved = jsonDecode(raw) as List;
      for (final entry in saved.cast<Map<String, dynamic>>()) {
        final product = ProductModel.fromMap(
          Map<String, dynamic>.from(entry['product'] as Map),
        );
        _items[product.id] = CartItem(product: product, quantity: entry['quantity'] as int? ?? 1);
      }
      notifyListeners();
    } catch (_) {
      // A malformed non-sensitive cache is safely ignored.
    }
  }

  Future<void> _persist() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_storageKey, jsonEncode(_items.values.map((item) => {
      'product': item.product.toMap(), 'quantity': item.quantity,
    }).toList()));
  }

  void _changed() { notifyListeners(); _persist(); }

  List<CartItem> get items => _items.values.toList();
  bool get isEmpty => _items.isEmpty;
  int get itemCount => _items.values.fold(0, (sum, i) => sum + i.quantity);

  double get subtotal =>
      _items.values.fold(0, (sum, i) => sum + i.subtotal);

  double get deliveryFee => (isEmpty || isStationPickup) ? 0.0 : 5.0;

  double get total => subtotal + deliveryFee;

  // Total number of gallons across all items
  int get totalGallons =>
      _items.values.fold(0, (sum, i) => sum + i.quantity);

  int get refillGallons => _items.values
      .where((item) => item.product.isRefill)
      .fold(0, (sum, item) => sum + item.quantity);

  int get newGallonCount => _items.values
      .where((item) => item.product.isNewGallon)
      .fold(0, (sum, item) => sum + item.quantity);

  void add(ProductModel product) {
    if (_items.containsKey(product.id)) {
      _items[product.id]!.quantity++;
    } else {
      _items[product.id] = CartItem(product: product);
    }
    _changed();
  }

  /// Set the exact quantity selected from the service catalogue. This keeps
  /// repeated taps on "Add to Cart" from accidentally duplicating an item.
  void setProductQuantity(ProductModel product, int quantity) {
    if (quantity <= 0) {
      _items.remove(product.id);
    } else if (_items.containsKey(product.id)) {
      _items[product.id]!.quantity = quantity;
    } else {
      _items[product.id] = CartItem(product: product, quantity: quantity);
    }

    _changed();
  }

  void addFromMap(Map<String, dynamic> productMap) {
    add(ProductModel.fromMap(productMap));
  }

  void remove(String productId) {
    _items.remove(productId);
    _changed();
  }

  void increment(String productId) {
    if (_items.containsKey(productId)) {
      _items[productId]!.quantity++;
      _changed();
    }
  }

  void decrement(String productId) {
    if (_items.containsKey(productId)) {
      final item = _items[productId]!;
      if (item.quantity > 1) {
        item.quantity--;
      } else {
        _items.remove(productId);
      }
      _changed();
    }
  }

  void setQuantity(String productId, int qty) {
    if (qty <= 0) {
      _items.remove(productId);
    } else if (_items.containsKey(productId)) {
      _items[productId]!.quantity = qty;
    }
    _changed();
  }

  void clear() {
    _items.clear();
    _deliveryMethod = 'home_delivery';
    _changed();
  }

  void clearCart() => clear();

  /// A cart belongs to the signed-in customer, not to the shared device.
  /// Remove it from both memory and local storage before showing the guest
  /// storefront, so a previous customer's items cannot appear in its badge.
  Future<void> clearForGuest() async {
    _items.clear();
    _deliveryMethod = 'home_delivery';
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_storageKey);
  }

  /// Build the order payload for the API.
  Map<String, dynamic> toOrderPayload({
    String? addressId,
    required String paymentMethod,
    String? notes,
    String? deliveryMethod,
  }) {
    final method = (deliveryMethod ?? _deliveryMethod).toLowerCase();
    return {
      'delivery_method': method,
      if (method != 'station_pickup' && addressId != null && addressId.isNotEmpty)
        'address_id': addressId,
      'items': items
          .map((i) => {
                'product_id': i.product.id,
                'quantity': i.quantity,
              })
          .toList(),
      // API enum values are lowercase; the checkout UI may use uppercase
      // labels for display.
      'container_ownership': refillGallons > 0 ? 'customer_owned' : 'new_gallon',
      'expected_empty_gallons': refillGallons,
      'payment_method': paymentMethod.toLowerCase(),
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    };
  }
}
