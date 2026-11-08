import 'package:flutter/material.dart';
import 'dart:math' as math;
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/services/offline_service.dart';
import '../../core/utils/url_helper.dart';
import '../../core/widgets/guest_order_prompt.dart';
import '../../customer/data/payment_repository.dart';
import '../../customer/providers/address_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../core/config/station_config.dart';
import 'checkout_step_bar.dart';
import 'order_success_screen.dart';

class CheckoutReviewScreen extends StatefulWidget {
  final String? addressId;
  final int gallons;
  final String paymentMethod;
  final String? notes;
  const CheckoutReviewScreen({super.key, this.addressId, required this.gallons, required this.paymentMethod, this.notes});

  @override
  State<CheckoutReviewScreen> createState() => _CheckoutReviewScreenState();
}

class _CheckoutReviewScreenState extends State<CheckoutReviewScreen> {
  bool _placing = false;
  final String _clientRequestId =
      '${DateTime.now().microsecondsSinceEpoch}-${math.Random.secure().nextInt(1 << 32)}';

  Future<void> _placeOrder() async {
    if (_placing) return;
    if (!context.read<AuthProvider>().isAuthenticated) {
      await showGuestOrderPrompt(context);
      return;
    }
    setState(() => _placing = true);
    final cart = context.read<CartProvider>();
    final payload = cart.toOrderPayload(
      addressId: widget.addressId,
      paymentMethod: widget.paymentMethod,
      notes: widget.notes,
      deliveryMethod: cart.deliveryMethod,
    );
    payload['expected_empty_gallons'] = cart.refillGallons;
    payload['client_request_id'] = _clientRequestId;
    final order = await context.read<OrderProvider>().placeOrder(payload);
    if (!mounted) return;
    if (order == null) {
      setState(() => _placing = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.read<OrderProvider>().error ?? 'Failed to place order.'), backgroundColor: const Color(0xFFC62828)));
      return;
    }
    if (widget.paymentMethod == 'ONLINE') {
      try {
        final checkout = await context.read<PaymentRepository>().createCheckout(order.id);
        final url = (checkout['checkout_url'] ??
                (checkout['data'] is Map ? checkout['data']['checkout_url'] : null))
            ?.toString();
        if (url != null && url.isNotEmpty) {
          final opened = await launchSafeUrl(url);
          if (!opened && mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Order created! Could not open browser automatically. Please tap Pay Online in Order Details.'),
                backgroundColor: Color(0xFFC62828),
                duration: Duration(seconds: 4),
              ),
            );
          }
        } else {
          throw Exception(checkout['message']?.toString() ?? 'Payment checkout URL unavailable.');
        }
      } catch (e) {
        if (mounted) {
          final msg = e is ApiException
              ? e.message
              : 'Your order was created, but online payment could not start (${e.toString().replaceFirst("Exception: ", "")}). Open the order to try again.';
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(msg),
              backgroundColor: const Color(0xFFC62828),
              duration: const Duration(seconds: 4),
            ),
          );
        }
      }
    }
    cart.clear();
    if (mounted) Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => OrderSuccessScreen(order: order)), (route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final isOffline = context.watch<OfflineService>().isOffline;
    final address = widget.addressId != null
        ? context.watch<AddressProvider>().addresses.where((a) => a.id == widget.addressId).firstOrNull
        : null;
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(image: DecorationImage(image: AssetImage('assets/images/customer_dashboard_bg.png'), fit: BoxFit.cover)),
        child: SafeArea(child: Column(children: [
          Padding(padding: const EdgeInsets.all(16), child: Row(children: [
            IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0878C9))),
            const SizedBox(width: 8),
            const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Review Order', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: Color(0xFF12344D))), Text('Confirm your order details', style: TextStyle(fontSize: 12, color: Color(0xFF52718B)))]),
          ])),
          const CheckoutStepBar(step: 4),
          Expanded(child: ListView(padding: const EdgeInsets.all(16), children: [if (isOffline) _offlineNotice(), _reviewContainer(cart, address)])),
          SafeArea(top: false, child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 16), child: SizedBox(width: double.infinity, height: 52, child: ElevatedButton.icon(onPressed: _placing || isOffline ? null : _placeOrder, icon: _placing ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.check_circle_rounded), label: Text(isOffline ? 'Connect to place order' : _placing ? 'Placing order...' : '${widget.paymentMethod == 'ONLINE' ? 'Pay Online' : 'Place Order'} (₱${cart.total.toStringAsFixed(2)})'))))),
        ])),
      ),
    );
  }

  Widget _offlineNotice() => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: const Color(0xFFFFF4D8), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFE8C66A))),
    child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(Icons.wifi_off_rounded, color: Color(0xFF8A5A00)),
      SizedBox(width: 10),
      Expanded(child: Text('You are offline. Your cart is still saved, but no order has been placed. Reconnect to submit it. Online payment is only started after the server confirms the order.', style: TextStyle(color: Color(0xFF654500), height: 1.35))),
    ]),
  );

  Widget _reviewContainer(CartProvider cart, dynamic address) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .97), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFFD5EAF8)), boxShadow: [BoxShadow(color: const Color(0xFF0878C9).withValues(alpha: .08), blurRadius: 14)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Order Details', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF12344D))),
      const SizedBox(height: 16),
      _heading(Icons.shopping_bag_outlined, 'Selected Products'),
      const SizedBox(height: 8),
      ...cart.items.map((item) => Padding(padding: const EdgeInsets.symmetric(vertical: 5), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.product.name, style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF12344D))), Text(item.product.isNewGallon ? 'New gallon with water' : 'Refill existing gallon', style: const TextStyle(fontSize: 11, color: Color(0xFF52718B)))])), Text('${item.quantity} × ₱${item.product.price.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, color: Color(0xFF52718B))), const SizedBox(width: 10), Text('₱${item.subtotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0878C9)))]))),
      _divider(),
      _heading(
        cart.isStationPickup ? Icons.storefront_outlined : Icons.location_on_outlined,
        cart.isStationPickup ? 'Station Pickup Location' : 'Delivery Address',
        onChange: () {
          // Review -> Payment -> Gallons -> Address
          Navigator.of(context).pop();
          Navigator.of(context).pop();
          Navigator.of(context).pop();
        },
      ),
      const SizedBox(height: 6),
      Text(
        cart.isStationPickup
            ? StationConfig.formattedStationCard
            : (address?.fullAddress ?? 'Address unavailable'),
        style: const TextStyle(color: Color(0xFF52718B), height: 1.4),
      ),
      if (cart.refillGallons > 0) ...[
        _divider(),
        _heading(Icons.water_drop_outlined, 'Empty Gallons for Refill'),
        const SizedBox(height: 6),
        Text(
          cart.isStationPickup
              ? '${cart.refillGallons} empty gallon${cart.refillGallons == 1 ? '' : 's'} will be dropped off at the station counter upon pickup.'
              : '${cart.refillGallons} empty gallon${cart.refillGallons == 1 ? '' : 's'} will be collected by the delivery driver.',
          style: const TextStyle(color: Color(0xFF52718B), height: 1.4),
        ),
      ],
      _divider(),
      _heading(
        Icons.payments_outlined,
        'Payment Method',
        onChange: () => Navigator.of(context).pop(),
      ), const SizedBox(height: 6),
      Text(
        widget.paymentMethod == 'ONLINE'
            ? 'Online Payment — PayMongo (GCash, Maya, or card)'
            : widget.paymentMethod == 'CASH'
                ? (cart.isStationPickup ? 'Cash on Pickup' : 'Cash on Delivery')
                : 'Debt',
        style: const TextStyle(color: Color(0xFF52718B)),
      ),
      if (widget.notes?.trim().isNotEmpty == true) ...[
        _divider(),
        _heading(Icons.notes_outlined, cart.isStationPickup ? 'Pickup Notes' : 'Delivery Notes'),
        const SizedBox(height: 6),
        Text(widget.notes!, style: const TextStyle(color: Color(0xFF52718B))),
      ],
      _divider(),
      _line('Subtotal', '₱${cart.subtotal.toStringAsFixed(2)}'),
      _line(
        'Delivery fee',
        cart.isStationPickup
            ? 'FREE (₱0.00)'
            : '₱${cart.deliveryFee.toStringAsFixed(2)}',
      ),
      const SizedBox(height: 8), _line('Total', '₱${cart.total.toStringAsFixed(2)}', total: true),
    ]),
  );

  Widget _heading(IconData icon, String title, {VoidCallback? onChange}) => Row(children: [
        Icon(icon, color: const Color(0xFF0878C9), size: 19),
        const SizedBox(width: 8),
        Text(title, style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF12344D))),
        const Spacer(),
        if (onChange != null)
          TextButton(
            onPressed: onChange,
            style: TextButton.styleFrom(
              minimumSize: const Size(0, 34),
              padding: const EdgeInsets.symmetric(horizontal: 10),
              foregroundColor: const Color(0xFF0878C9),
            ),
            child: const Text('Change', style: TextStyle(fontWeight: FontWeight.w800)),
          ),
      ]);
  Widget _divider() => const Padding(padding: EdgeInsets.symmetric(vertical: 15), child: Divider(height: 1));
  Widget _line(String label, String value, {bool total = false}) => Padding(padding: const EdgeInsets.symmetric(vertical: 3), child: Row(children: [Text(label, style: TextStyle(fontWeight: total ? FontWeight.w800 : FontWeight.w500, color: const Color(0xFF52718B))), const Spacer(), Text(value, style: TextStyle(fontSize: total ? 20 : 14, fontWeight: FontWeight.w800, color: total ? const Color(0xFF0878C9) : const Color(0xFF12344D)))]));
}
