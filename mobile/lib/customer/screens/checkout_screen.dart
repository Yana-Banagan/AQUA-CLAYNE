import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../data/address_repository.dart';
import '../data/order_repository.dart';
import '../state/cart_provider.dart';
import 'order_confirmation_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  late Future<List<Map<String, dynamic>>> _futureAddresses;

  String? _selectedAddressId;
  bool _placingOrder = false;

  @override
  void initState() {
    super.initState();

    _futureAddresses = context.read<AddressRepository>().list();
  }

  void _reloadAddresses() {
    setState(() {
      _futureAddresses = context.read<AddressRepository>().list();
    });
  }

  Future<void> _placeOrder(String addressId) async {
    if (_placingOrder) return;

    final cart = context.read<CartProvider>();
    final orderRepository = context.read<OrderRepository>();

    if (cart.items.isEmpty) {
      if (!mounted) return;

      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Your cart is empty.')));

      return;
    }

    final items = cart.items.map((item) {
      return {'product_id': item.product['id'], 'quantity': item.quantity};
    }).toList();

    setState(() {
      _placingOrder = true;
    });

    try {
      final order = await orderRepository.create(addressId, items);

      if (!mounted) return;

      cart.clear();

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => OrderConfirmationScreen(order: order),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        _placingOrder = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _placingOrder = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('Failed to place order: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _futureAddresses,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return _ErrorView(
              message: snapshot.error.toString(),
              onRetry: _reloadAddresses,
            );
          }

          final addresses = snapshot.data ?? [];

          if (addresses.isEmpty) {
            return _NoAddressView(onRefresh: _reloadAddresses);
          }

          if (_selectedAddressId == null) {
            final defaultAddress = addresses
                .cast<Map<String, dynamic>?>()
                .firstWhere(
                  (address) => address?['is_default'] == true,
                  orElse: () => null,
                );

            if (defaultAddress != null) {
              _selectedAddressId = defaultAddress['id']?.toString();
            } else {
              _selectedAddressId = addresses.first['id']?.toString();
            }
          }

          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _buildAddressSection(addresses),

                    const SizedBox(height: 20),

                    _buildCartSection(cart),

                    const SizedBox(height: 20),

                    _buildTotalSection(cart),

                    const SizedBox(height: 20),

                    _buildPaymentSection(),
                  ],
                ),
              ),

              _buildPlaceOrderButton(cart),
            ],
          );
        },
      ),
    );
  }

  Widget _buildAddressSection(List<Map<String, dynamic>> addresses) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.location_on_outlined),
                SizedBox(width: 8),
                Text(
                  'Delivery Address',
                  style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold),
                ),
              ],
            ),

            const SizedBox(height: 12),

            RadioGroup<String>(
              groupValue: _selectedAddressId,
              onChanged: (value) {
                if (_placingOrder) return;

                setState(() {
                  _selectedAddressId = value;
                });
              },
              child: Column(
                children: addresses.map((address) {
                  final id = address['id']?.toString();

                  final label = address['label']?.toString().trim() ?? '';

                  final addressLine = address['address_line']?.toString() ?? '';

                  final isDefault = address['is_default'] == true;

                  return RadioListTile<String>(
                    value: id ?? '',
                    title: Row(
                      children: [
                        Expanded(
                          child: Text(
                            label.isEmpty ? 'Address' : label,
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ),

                        if (isDefault) const Chip(label: Text('Default')),
                      ],
                    ),
                    subtitle: Text(addressLine),
                    contentPadding: EdgeInsets.zero,
                  );
                }).toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCartSection(CartProvider cart) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.shopping_cart_outlined),
                SizedBox(width: 8),
                Text(
                  'Order Summary',
                  style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold),
                ),
              ],
            ),

            const SizedBox(height: 12),

            ...cart.items.map((item) {
              final product = item.product;

              final name = product['name']?.toString() ?? 'Unnamed Product';

              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '$name × ${item.quantity}',
                        style: const TextStyle(fontSize: 16),
                      ),
                    ),

                    Text(
                      '₱${item.lineTotal.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  Widget _buildTotalSection(CartProvider cart) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Total',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),

            Text(
              '₱${cart.total.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentSection() {
    return Card(
      child: ListTile(
        leading: const Icon(Icons.payments_outlined),
        title: const Text(
          'Payment Method',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: const Text('Cash on Delivery'),
      ),
    );
  }

  Widget _buildPlaceOrderButton(CartProvider cart) {
    final canPlaceOrder =
        _selectedAddressId != null &&
        _selectedAddressId!.isNotEmpty &&
        cart.items.isNotEmpty &&
        !_placingOrder;

    return SafeArea(
      top: false,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
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
        child: SizedBox(
          height: 52,
          child: ElevatedButton.icon(
            onPressed: canPlaceOrder
                ? () => _placeOrder(_selectedAddressId!)
                : null,
            icon: _placingOrder
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.check_circle_outline),
            label: Text(
              _placingOrder ? 'Placing Order...' : 'Place Order',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 60),

            const SizedBox(height: 16),

            const Text(
              'Failed to load addresses.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            Text(message, textAlign: TextAlign.center),

            const SizedBox(height: 20),

            ElevatedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _NoAddressView extends StatelessWidget {
  final VoidCallback onRefresh;

  const _NoAddressView({required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.location_off_outlined, size: 70),

            const SizedBox(height: 16),

            const Text(
              'No delivery address.',
              style: TextStyle(fontSize: 21, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            const Text(
              'Please add at least one address before placing your order.',
              textAlign: TextAlign.center,
            ),

            const SizedBox(height: 20),

            ElevatedButton.icon(
              onPressed: onRefresh,
              icon: const Icon(Icons.refresh),
              label: const Text('Refresh'),
            ),
          ],
        ),
      ),
    );
  }
}
