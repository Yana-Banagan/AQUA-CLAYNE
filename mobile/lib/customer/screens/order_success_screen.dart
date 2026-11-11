import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/order_model.dart';
import '../../customer/data/payment_repository.dart';
import '../../customer/providers/order_provider.dart';
import 'customer_shell.dart';
import 'order_detail_screen.dart';
import 'package:qr_flutter/qr_flutter.dart';

class OrderSuccessScreen extends StatefulWidget {
  final OrderModel order;
  const OrderSuccessScreen({super.key, required this.order});

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen>
    with WidgetsBindingObserver {
  late OrderModel _order;
  Timer? _statusPollTimer;
  int _pollCount = 0;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      context.read<OrderProvider>().addListener(_syncOrder);
      _refreshOrder();
      _startPollingIfNeeded();
    });
  }

  @override
  void dispose() {
    _statusPollTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    try {
      context.read<OrderProvider>().removeListener(_syncOrder);
    } catch (_) {}
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshOrder();
      _startPollingIfNeeded();
    }
  }

  void _syncOrder() {
    if (!mounted) return;
    final provider = context.read<OrderProvider>();
    final current = provider.detail?.id == widget.order.id
        ? provider.detail
        : provider.orders.where((item) => item.id == widget.order.id).firstOrNull;
    if (current != null) {
      if (current.status != _order.status ||
          current.paymentStatus != _order.paymentStatus) {
        setState(() => _order = current);
      }
      if (current.paymentStatus.toUpperCase() == 'PAID') {
        _statusPollTimer?.cancel();
      }
    }
  }

  Future<void> _refreshOrder() async {
    if (!mounted) return;
    final orders = context.read<OrderProvider>();
    final paymentRepo = context.read<PaymentRepository>();
    try {
      await paymentRepo.getOrderPaymentStatus(widget.order.id);
    } catch (_) {}
    if (!mounted) return;
    await orders.loadDetail(widget.order.id);
    await orders.loadOrders();
    _syncOrder();
  }

  void _startPollingIfNeeded() {
    if (_order.paymentMethod.toUpperCase() != 'ONLINE') return;
    if (_order.paymentStatus.toUpperCase() == 'PAID') return;
    _statusPollTimer?.cancel();
    _pollCount = 0;
    _statusPollTimer = Timer.periodic(const Duration(seconds: 3), (timer) async {
      _pollCount++;
      if (!mounted || _order.paymentStatus.toUpperCase() == 'PAID' || _pollCount > 12) {
        timer.cancel();
        return;
      }
      await _refreshOrder();
    });
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final isCash = order.paymentMethod.toUpperCase() == 'CASH';

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        CustomerShell.showTab(context, 0);
      },
      child: Scaffold(
        body: Stack(
        children: [
          // ── 1. Full-screen background image ────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Content ─────────────────────────────────────────────
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // Celebration card
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(
                            color: const Color(0xFFD5E7F4), width: 1.2),
                        boxShadow: [
                          BoxShadow(
                            color:
                                const Color(0xFF0878C9).withValues(alpha: 0.12),
                            blurRadius: 20,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          // Green Success Halo Icon
                          Container(
                            width: 80,
                            height: 80,
                            decoration: BoxDecoration(
                              color: const Color(0xFFE8F5E9),
                              shape: BoxShape.circle,
                              border: Border.all(
                                  color: const Color(0xFFA5D6A7), width: 2),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF2E7D32)
                                      .withValues(alpha: 0.15),
                                  blurRadius: 16,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: const Center(
                              child: Icon(
                                Icons.check_circle_rounded,
                                color: Color(0xFF2E7D32),
                                size: 52,
                              ),
                            ),
                          ),
                          const SizedBox(height: 20),

                          // Heading
                          const Text(
                            'Refill Order Placed!',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF12344D),
                              letterSpacing: -0.3,
                            ),
                          ),
                          const SizedBox(height: 8),

                          // Order Number Pill
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 14, vertical: 6),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEAF7FF),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                  color: const Color(0xFFBCE3FD), width: 1),
                            ),
                            child: Text(
                              order.orderNumber,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0878C9),
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          // Description
                          const Text(
                            'Your refill order has been received by Aqua Clayne. A delivery driver will collect your empty gallons and deliver freshly refilled water.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 13,
                              color: Color(0xFF6F8BA3),
                              height: 1.45,
                            ),
                          ),

                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 18),
                            child: Divider(color: Color(0xFFF0F5FA), height: 1),
                          ),

                          // Quick details row
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Gallons to Collect',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color: Color(0xFF6F8BA3),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              Text(
                                '${order.expectedEmptyGallons} customer-owned',
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF12344D),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Payment Method',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color: Color(0xFF6F8BA3),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              Text(
                                order.paymentMethod.toUpperCase() == 'ONLINE'
                                    ? 'Pay Online via PayMongo'
                                    : isCash
                                        ? 'Cash on Delivery'
                                        : 'Debt',
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF12344D),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Payment Status',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color: Color(0xFF6F8BA3),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: order.paymentStatus.toUpperCase() == 'PAID'
                                      ? const Color(0xFFD1FAE5)
                                      : const Color(0xFFFEF3C7),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  order.paymentStatus.toUpperCase() == 'PAID'
                                      ? (order.paymentMethod.toUpperCase() == 'ONLINE' ? 'PAID ONLINE' : 'PAID')
                                      : order.paymentStatus.toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: order.paymentStatus.toUpperCase() == 'PAID'
                                        ? const Color(0xFF059669)
                                        : const Color(0xFFD97706),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Total Amount',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color: Color(0xFF6F8BA3),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              Text(
                                '₱${order.totalAmount.toStringAsFixed(2)}',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF0878C9),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    if (order.qrCode.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      const Text('Show this code to your driver at pickup', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF064B87))),
                      const SizedBox(height: 8),
                      QrImageView(data: order.qrCode, size: 180),
                    ],

                    // Primary Button: Track Order
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: () {
                          Navigator.pushReplacement(
                            context,
                            MaterialPageRoute(
                              builder: (_) =>
                                  OrderDetailScreen(orderId: _order.id),
                            ),
                          );
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0878C9),
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.track_changes_rounded, size: 20),
                            SizedBox(width: 8),
                            Text(
                              'Track Order',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Secondary Button: View Orders
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: OutlinedButton(
                        onPressed: () {
                          CustomerShell.showTab(context, 1);
                        },
                        style: OutlinedButton.styleFrom(
                          backgroundColor: Colors.white.withValues(alpha: 0.8),
                          foregroundColor: const Color(0xFF0878C9),
                          side: const BorderSide(
                              color: Color(0xFF0878C9), width: 1.4),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.receipt_long_rounded, size: 20),
                            SizedBox(width: 8),
                            Text(
                              'View Orders List',
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Ghost Text Button: Back to Home
                    TextButton(
                      onPressed: () {
                        CustomerShell.showTab(context, 0);
                      },
                      child: const Text(
                        'Back to Home',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF6F8BA3),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
}
