import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/product_repository.dart';
import '../state/cart_provider.dart';
import 'addresses_screen.dart';
import 'cart_screen.dart';
import 'order_history_screen.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();

    _future = context.read<ProductRepository>().list();
  }

  void _reloadProducts() {
    setState(() {
      _future = context.read<ProductRepository>().list();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Products'),

        actions: [
          IconButton(
            tooltip: 'Order History',
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const OrderHistoryScreen()),
              );
            },
          ),
          // --------------------------------------------------
          // ADDRESSES BUTTON
          // --------------------------------------------------
          IconButton(
            tooltip: 'My Addresses',
            icon: const Icon(Icons.location_on_outlined),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AddressesScreen()),
              );
            },
          ),

          // --------------------------------------------------
          // CART BUTTON + BADGE
          // --------------------------------------------------
          Consumer<CartProvider>(
            builder: (context, cart, child) {
              return Stack(
                alignment: Alignment.center,
                children: [
                  IconButton(
                    tooltip: 'My Cart',
                    icon: const Icon(Icons.shopping_cart_outlined, size: 28),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const CartScreen()),
                      );
                    },
                  ),

                  // Cart quantity badge
                  if (cart.itemCount > 0)
                    Positioned(
                      right: 4,
                      top: 6,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.red,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        constraints: const BoxConstraints(
                          minWidth: 20,
                          minHeight: 20,
                        ),
                        child: Text(
                          '${cart.itemCount}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                ],
              );
            },
          ),

          const SizedBox(width: 8),
        ],
      ),

      // ----------------------------------------------------
      // PRODUCT LIST
      // ----------------------------------------------------
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          // ------------------------------------------------
          // LOADING
          // ------------------------------------------------
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          // ------------------------------------------------
          // ERROR
          // ------------------------------------------------
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline, size: 60),

                    const SizedBox(height: 16),

                    const Text(
                      'Failed to load products.',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      snapshot.error.toString(),
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 20),

                    ElevatedButton(
                      onPressed: _reloadProducts,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          // ------------------------------------------------
          // PRODUCTS
          // ------------------------------------------------
          final products = snapshot.data ?? [];

          // ------------------------------------------------
          // EMPTY
          // ------------------------------------------------
          if (products.isEmpty) {
            return const Center(
              child: Text(
                'No products available.',
                style: TextStyle(fontSize: 18),
              ),
            );
          }

          // ------------------------------------------------
          // PRODUCT LIST
          // ------------------------------------------------
          return RefreshIndicator(
            onRefresh: () async {
              final future = context.read<ProductRepository>().list();

              setState(() {
                _future = future;
              });

              await future;
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: products.length,
              itemBuilder: (context, index) {
                final product = products[index];

                return _ProductCard(product: product);
              },
            ),
          );
        },
      ),
    );
  }
}

// ==========================================================
// PRODUCT CARD
// ==========================================================

class _ProductCard extends StatelessWidget {
  final Map<String, dynamic> product;

  const _ProductCard({required this.product});

  @override
  Widget build(BuildContext context) {
    final name = product['name']?.toString() ?? 'Unnamed Product';

    final description = product['description']?.toString() ?? '';

    final price = product['price'] is num
        ? (product['price'] as num).toDouble()
        : double.tryParse(product['price']?.toString() ?? '0') ?? 0;

    final inStock = product['in_stock'] == true;

    return Card(
      margin: const EdgeInsets.only(bottom: 16),

      child: Padding(
        padding: const EdgeInsets.all(16),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // ------------------------------------------------
            // PRODUCT NAME
            // ------------------------------------------------
            Text(
              name,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            // ------------------------------------------------
            // DESCRIPTION
            // ------------------------------------------------
            if (description.isNotEmpty)
              Text(description, style: TextStyle(color: Colors.grey.shade700)),

            const SizedBox(height: 12),

            // ------------------------------------------------
            // PRICE
            // ------------------------------------------------
            Text(
              '₱${price.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            // ------------------------------------------------
            // STOCK STATUS
            // ------------------------------------------------
            Row(
              children: [
                Icon(inStock ? Icons.check_circle : Icons.cancel, size: 18),

                const SizedBox(width: 6),

                Text(inStock ? 'In Stock' : 'Out of Stock'),
              ],
            ),

            const SizedBox(height: 12),

            // ------------------------------------------------
            // ADD TO CART
            // ------------------------------------------------
            SizedBox(
              width: double.infinity,

              child: ElevatedButton(
                onPressed: inStock
                    ? () {
                        context.read<CartProvider>().add(product);

                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text('$name added to cart.'),
                            duration: const Duration(seconds: 1),
                          ),
                        );
                      }
                    : null,

                child: Text(inStock ? 'Add to Cart' : 'Out of Stock'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
