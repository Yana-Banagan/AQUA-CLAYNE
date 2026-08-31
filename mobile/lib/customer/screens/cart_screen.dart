import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/cart_provider.dart';
import 'checkout_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('My Cart')),

      body: cart.items.isEmpty
          ? const _EmptyCart()
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: cart.items.length,
                    itemBuilder: (context, index) {
                      final item = cart.items[index];

                      return _CartItemCard(item: item);
                    },
                  ),
                ),

                _CartSummary(cart: cart),
              ],
            ),
    );
  }
}

// ==========================================================
// EMPTY CART
// ==========================================================

class _EmptyCart extends StatelessWidget {
  const _EmptyCart();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            Icon(
              Icons.shopping_cart_outlined,
              size: 80,
              color: Colors.grey.shade500,
            ),

            const SizedBox(height: 20),

            const Text(
              'Your cart is empty.',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            Text(
              'Add products from the catalog to continue.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, color: Colors.grey.shade700),
            ),

            const SizedBox(height: 24),

            ElevatedButton.icon(
              onPressed: () {
                Navigator.of(context).pop();
              },

              icon: const Icon(Icons.shopping_bag_outlined),

              label: const Text('Continue Shopping'),
            ),
          ],
        ),
      ),
    );
  }
}

// ==========================================================
// CART ITEM
// ==========================================================

class _CartItemCard extends StatelessWidget {
  final CartItem item;

  const _CartItemCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final product = item.product;

    final name = product['name']?.toString() ?? 'Unnamed Product';

    final price = product['price'] is num
        ? (product['price'] as num).toDouble()
        : double.tryParse(product['price']?.toString() ?? '0') ?? 0;

    final lineTotal = item.lineTotal;

    final productId = product['id']?.toString();

    return Card(
      margin: const EdgeInsets.only(bottom: 16),

      child: Padding(
        padding: const EdgeInsets.all(16),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // Product name
            Text(
              name,
              style: const TextStyle(fontSize: 19, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            // Unit price
            Text(
              '₱${price.toStringAsFixed(2)} each',
              style: TextStyle(fontSize: 15, color: Colors.grey.shade700),
            ),

            const SizedBox(height: 14),

            Row(
              children: [
                // Quantity controls
                Row(
                  children: [
                    IconButton(
                      onPressed: productId == null
                          ? null
                          : () {
                              context.read<CartProvider>().updateQuantity(
                                productId,
                                item.quantity - 1,
                              );
                            },

                      icon: const Icon(Icons.remove),

                      tooltip: 'Decrease quantity',
                    ),

                    Container(
                      width: 45,

                      alignment: Alignment.center,

                      child: Text(
                        '${item.quantity}',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),

                    IconButton(
                      onPressed: productId == null
                          ? null
                          : () {
                              context.read<CartProvider>().updateQuantity(
                                productId,
                                item.quantity + 1,
                              );
                            },

                      icon: const Icon(Icons.add),

                      tooltip: 'Increase quantity',
                    ),
                  ],
                ),

                const Spacer(),

                // Line total
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,

                  children: [
                    const Text('Line Total', style: TextStyle(fontSize: 13)),

                    const SizedBox(height: 4),

                    Text(
                      '₱${lineTotal.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ==========================================================
// CART SUMMARY
// ==========================================================

class _CartSummary extends StatelessWidget {
  final CartProvider cart;

  const _CartSummary({required this.cart});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,

      child: Container(
        width: double.infinity,

        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),

        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,

          boxShadow: [
            BoxShadow(
              blurRadius: 8,

              color: Colors.black.withValues(alpha: 0.10),

              offset: const Offset(0, -2),
            ),
          ],
        ),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,

          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: [
                const Text(
                  'Total',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                ),

                Text(
                  '₱${cart.total.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 14),

            SizedBox(
              height: 52,

              child: ElevatedButton.icon(
                onPressed: cart.items.isEmpty
                    ? null
                    : () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const CheckoutScreen(),
                          ),
                        );
                      },

                icon: const Icon(Icons.shopping_cart_checkout),

                label: const Text(
                  'Checkout',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
