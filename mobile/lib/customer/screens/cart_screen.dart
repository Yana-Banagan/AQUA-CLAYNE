import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/providers/auth_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/guest_order_prompt.dart';
import '../../customer/providers/cart_provider.dart';
import '../../shared_widgets/gallon_quantity_stepper.dart';
import 'checkout_address_screen.dart';
import 'water_refill_services_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: const Color(0xFFF4F9FD),
        body: Container(
          decoration: const BoxDecoration(
            image: DecorationImage(
              image: AssetImage('assets/images/customer_dashboard_bg.png'),
              fit: BoxFit.cover,
            ),
          ),
          child: SafeArea(
            child: Consumer<CartProvider>(
              builder: (context, cart, _) => Column(children: [
                _Header(hasItems: !cart.isEmpty, onClear: cart.clear),
                Expanded(
                  child: cart.isEmpty
                      ? _EmptyCart(
                          onBrowse: () => Navigator.pushReplacement(
                            context,
                            MaterialPageRoute(
                                builder: (_) => const WaterRefillServicesScreen()),
                          ),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                          itemCount: cart.items.length + 1,
                          separatorBuilder: (_, _) => const SizedBox(height: 12),
                          itemBuilder: (_, index) {
                            if (index == 0) {
                              return Text(
                                '${cart.itemCount} ${cart.itemCount == 1 ? 'item' : 'items'} in your cart',
                                style: const TextStyle(
                                    color: Color(0xFF52718B),
                                    fontWeight: FontWeight.w600),
                              );
                            }
                            final item = cart.items[index - 1];
                            return _ItemCard(
                              item: item,
                              onRemove: () => cart.remove(item.product.id),
                              onQuantityChanged: (qty) =>
                                  cart.setQuantity(item.product.id, qty),
                            );
                          },
                        ),
                ),
                if (!cart.isEmpty) _Summary(cart: cart),
              ]),
            ),
          ),
        ),
      );
}

class _Header extends StatelessWidget {
  final bool hasItems;
  final VoidCallback onClear;
  const _Header({required this.hasItems, required this.onClear});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(8, 8, 12, 4),
        child: Row(children: [
          IconButton(
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0878C9)),
          ),
          const SizedBox(width: 4),
          const Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('My Cart',
                  style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800, color: Color(0xFF12344D))),
              Text('Review your refill services',
                  style: TextStyle(fontSize: 12, color: Color(0xFF52718B))),
            ]),
          ),
          if (hasItems)
            TextButton.icon(
              onPressed: onClear,
              icon: const Icon(Icons.delete_sweep_outlined, size: 18),
              label: const Text('Clear'),
              style: TextButton.styleFrom(foregroundColor: AppColors.error),
            ),
        ]),
      );
}

class _EmptyCart extends StatelessWidget {
  final VoidCallback onBrowse;
  const _EmptyCart({required this.onBrowse});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .94),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFD5EAF8)),
            ),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(
                width: 72,
                height: 72,
                decoration: const BoxDecoration(color: Color(0xFFE1F3FE), shape: BoxShape.circle),
                child: const Icon(Icons.shopping_cart_outlined, color: Color(0xFF0878C9), size: 36),
              ),
              const SizedBox(height: 18),
              const Text('Your cart is empty',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF12344D))),
              const SizedBox(height: 8),
              const Text('Browse our refill services and add what you need.',
                  textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF52718B), height: 1.4)),
              const SizedBox(height: 22),
              ElevatedButton.icon(
                onPressed: onBrowse,
                icon: const Icon(Icons.water_drop_outlined),
                label: const Text('Browse Services'),
              ),
            ]),
          ),
        ),
      );
}

class _ItemCard extends StatelessWidget {
  final CartItem item;
  final VoidCallback onRemove;
  final ValueChanged<int> onQuantityChanged;
  const _ItemCard({required this.item, required this.onRemove, required this.onQuantityChanged});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: .96),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFD9ECF8)),
          boxShadow: [BoxShadow(color: const Color(0xFF0878C9).withValues(alpha: .06), blurRadius: 10)],
        ),
        child: Row(children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(color: const Color(0xFFE1F3FE), borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.water_drop_rounded, color: Color(0xFF0878C9)),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(item.product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF12344D))),
            const SizedBox(height: 4),
            Text(
                item.product.isNewGallon
                    ? 'New gallon with water'
                    : 'Refill existing gallon',
                style: const TextStyle(fontSize: 12, color: Color(0xFF52718B))),
            Text('₱${item.subtotal.toStringAsFixed(2)}',
                style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0878C9))),
          ])),
          Column(children: [
            IconButton(
              tooltip: 'Remove item', onPressed: onRemove,
              icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFFDC2626)),
            ),
            GallonQuantityStepper(value: item.quantity, compact: true, onChanged: onQuantityChanged),
          ]),
        ]),
      );
}

class _Summary extends StatelessWidget {
  final CartProvider cart;
  const _Summary({required this.cart});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: .98),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .12), blurRadius: 16, offset: const Offset(0, -3))],
        ),
        child: SafeArea(
          top: false,
          child: Column(children: [
            const Align(alignment: Alignment.centerLeft, child: Text('Order Summary', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF12344D)))),
            const SizedBox(height: 10),
            _Row('Subtotal', '₱${cart.subtotal.toStringAsFixed(2)}'),
            if (cart.refillGallons > 0)
              _Row('Empty gallons for pickup', '${cart.refillGallons}'),
            if (cart.newGallonCount > 0)
              _Row('New gallons with water', '${cart.newGallonCount}'),
            const SizedBox(height: 7),
            _Row('Delivery fee', '₱${cart.deliveryFee.toStringAsFixed(2)}'),
            const Padding(padding: EdgeInsets.symmetric(vertical: 10), child: Divider(height: 1)),
            _Row('Total', '₱${cart.total.toStringAsFixed(2)}', total: true),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                onPressed: () async {
                  if (!context.read<AuthProvider>().isAuthenticated) {
                    await showGuestOrderPrompt(context);
                    return;
                  }
                  if (!context.mounted) return;
                  Navigator.push(context, MaterialPageRoute(builder: (_) => const CheckoutAddressScreen()));
                },
                icon: const Icon(Icons.arrow_forward_rounded),
                label: const Text('Continue to Order'),
              ),
            ),
          ]),
        ),
      );
}

class _Row extends StatelessWidget {
  final String label;
  final String value;
  final bool total;
  const _Row(this.label, this.value, {this.total = false});

  @override
  Widget build(BuildContext context) => Row(children: [
        Text(label, style: TextStyle(fontWeight: total ? FontWeight.w800 : FontWeight.w500, color: const Color(0xFF52718B))),
        const Spacer(),
        Text(value, style: TextStyle(fontSize: total ? 19 : 14, fontWeight: FontWeight.w800, color: total ? const Color(0xFF0878C9) : const Color(0xFF12344D))),
      ]);
}
