import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/utils/url_helper.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/address_provider.dart';
import '../../core/models/order_model.dart';
import '../../core/theme/app_colors.dart';
import '../../shared_widgets/status_badge.dart';
import '../../shared_widgets/order_timeline.dart';
import '../../shared_widgets/confirmation_dialog.dart';
import 'customer_shell.dart';
import 'notifications_screen.dart';
import 'submit_complaint_screen.dart';
import '../../core/config/station_config.dart';
import '../data/payment_repository.dart';
import '../widgets/order_driver_tracking_map.dart';
import 'package:qr_flutter/qr_flutter.dart';

class OrderDetailScreen extends StatefulWidget {
  final String orderId;
  /// The customer shell owns the shared navigation bar for normal app routes.
  /// Deep-linked order details can opt in when opened above the shell.
  final bool showBottomNavigation;

  const OrderDetailScreen({
    super.key,
    required this.orderId,
    this.showBottomNavigation = false,
  });

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen>
    with WidgetsBindingObserver {
  bool _paying = false;
  Timer? _statusPollTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
    });
  }

  @override
  void dispose() {
    _statusPollTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _load();
      _startPollingIfNeeded();
    }
  }

  void _startPollingIfNeeded() {
    final order = context.read<OrderProvider>().detail;
    if (order != null &&
        order.paymentMethod.toUpperCase() == 'ONLINE' &&
        order.paymentStatus.toUpperCase() != 'PAID') {
      _statusPollTimer?.cancel();
      int count = 0;
      _statusPollTimer = Timer.periodic(const Duration(seconds: 3), (timer) async {
        count++;
        if (!mounted || count > 12) {
          timer.cancel();
          return;
        }
        await _load();
        if (mounted && context.read<OrderProvider>().detail?.paymentStatus.toUpperCase() == 'PAID') {
          timer.cancel();
        }
      });
    }
  }

  Future<void> _payOnline(OrderModel order) async {
    setState(() => _paying = true);
    try {
      final checkout = await context.read<PaymentRepository>().createCheckout(order.id);
      final url = (checkout['checkout_url'] ??
              (checkout['data'] is Map ? checkout['data']['checkout_url'] : null))
          ?.toString();
      if (url == null || url.isEmpty) {
        throw Exception(checkout['message']?.toString() ?? 'PayMongo checkout link not available.');
      }

      final opened = await launchSafeUrl(url);
      if (opened) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Opening secure PayMongo checkout. Returning will refresh your payment status.'),
            backgroundColor: Color(0xFF0878C9),
            duration: Duration(seconds: 4),
          ),
        );
        _startPollingIfNeeded();
      } else {
        throw Exception('Could not open browser for payment. Please check your default browser.');
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message.isNotEmpty ? e.message : 'Payment could not be completed. Please try again.'),
          backgroundColor: const Color(0xFFC62828),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().replaceFirst('Exception: ', '');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msg.isNotEmpty ? msg : 'Payment could not be completed. Please try again.'),
          backgroundColor: const Color(0xFFC62828),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _paying = false);
      }
    }
  }

  Future<void> _load() async {
    if (!mounted) return;
    try {
      // Sync latest payment status from gateway
      await context.read<PaymentRepository>().getOrderPaymentStatus(widget.orderId);
    } catch (_) {}
    if (!mounted) return;
    await Future.wait([
      context.read<OrderProvider>().loadDetail(widget.orderId),
      context.read<NotificationProvider>().load(),
    ]);
    if (mounted && context.read<OrderProvider>().detail?.paymentStatus.toUpperCase() == 'PAID') {
      _statusPollTimer?.cancel();
    }
  }

  Future<void> _cancelOrder(OrderModel order) async {
    final confirm = await showConfirmationDialog(
      context,
      title: 'Cancel Order',
      message:
          'Are you sure you want to cancel order ${order.orderNumber}? This action cannot be undone.',
      confirmLabel: 'Yes, Cancel',
      isDanger: true,
    );
    if (!confirm || !mounted) return;

    final prov = context.read<OrderProvider>();
    final ok = await prov.cancelOrder(order.id);
    if (!mounted) return;

    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order cancelled successfully.'),
          backgroundColor: AppColors.success,
          duration: Duration(seconds: 3),
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to cancel order.'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  String _formatDate(String raw) {
    if (raw.isEmpty) return 'Recent';
    try {
      final dt = DateTime.parse(raw).toLocal();
      const months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
      ];
      final month = months[dt.month - 1];
      final day = dt.day;
      final year = dt.year;
      final hour = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
      final minute = dt.minute.toString().padLeft(2, '0');
      final period = dt.hour >= 12 ? 'PM' : 'AM';
      return '$month $day, $year • $hour:$minute $period';
    } catch (_) {
      return raw.length > 10 ? raw.substring(0, 10) : raw;
    }
  }

  String _getStatusDescription(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return 'Your refill order has been submitted.';
      case 'CONFIRMED':
        return 'Your refill order has been confirmed.';
      case 'ASSIGNED':
        return 'A driver has been assigned to your order.';
      case 'PICKED_UP':
        return 'Your empty gallons have been picked up.';
      case 'RECEIVED_AT_STATION':
        return 'Your gallons have arrived at the station.';
      case 'REFILLING':
        return 'Your gallons are currently being refilled.';
      case 'READY_FOR_PICKUP':
        return 'Your clean refilled gallons are ready for station pickup!';
      case 'READY_FOR_DELIVERY':
        return 'Your refill is ready for delivery.';
      case 'OUT_FOR_DELIVERY':
        return 'Your refill is on the way to you.';
      case 'DELIVERED':
        return 'Your filled gallons have been delivered.';
      case 'COMPLETED':
        return 'Your order has been completed.';
      case 'REJECTED':
        return 'Your order was rejected.';
      case 'CANCELLED':
        return 'Your order has been cancelled.';
      case 'PICKUP_FAILED':
        return 'The gallon pickup could not be completed.';
      case 'DELIVERY_FAILED':
        return 'The delivery could not be completed.';
      default:
        return 'Your refill order is in progress.';
    }
  }

  void _showProofDialog(BuildContext context, String imageUrl) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        clipBehavior: Clip.antiAlias,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              color: const Color(0xFFEAF7FF),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Proof of Delivery',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                      color: Color(0xFF064B87),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, size: 20),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 350),
              child: Image.network(
                imageUrl,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('Unable to load proof of delivery photo.'),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Close'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F9FD),
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          image: DecorationImage(
            image: AssetImage('assets/images/customer_dashboard_bg.png'),
            fit: BoxFit.cover,
            alignment: Alignment.center,
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              // 1. Top Header
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                child: _buildHeader(context),
              ),

              // 2. Scrollable Body
              Expanded(
                child: Consumer<OrderProvider>(
                  builder: (context, orderProv, _) {
                    if (orderProv.loading && orderProv.detail == null) {
                      return _buildLoadingSkeleton();
                    }

                    final order = orderProv.detail;
                    if (order == null) {
                      return _buildNotFoundState(orderProv);
                    }

                    return RefreshIndicator(
                      color: const Color(0xFF0878C9),
                      onRefresh: _load,
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Order Title Header
                            _buildOrderTitle(order),

                            const SizedBox(height: 14),

                            // Current Status Banner Card
                            _buildCurrentStatusCard(order),

                            const SizedBox(height: 14),

                            // Refilling Highlight Banner (when active refilling)
                            if (order.status.toUpperCase() == 'REFILLING') ...[
                              _buildRefillingNowBanner(),
                              const SizedBox(height: 14),
                            ],

                            // Ready for Pickup Announcement Banner
                            if (order.status.toUpperCase() == 'READY_FOR_PICKUP') ...[
                              _buildReadyForPickupBanner(order),
                              const SizedBox(height: 14),
                            ],

                            // Order Progress Timeline Card
                            _buildTimelineCard(order),

                            const SizedBox(height: 14),

                            _buildCombinedOrderSummary(context, order),
                            const SizedBox(height: 14),

                            // Completed Order Banner
                            if (order.status.toUpperCase() == 'COMPLETED') ...[
                              _buildCompletedBanner(order),
                              const SizedBox(height: 14),
                            ],

                            // Exception / Failure Card
                            if (_isExceptionStatus(order.status)) ...[
                              _buildExceptionCard(order),
                              const SizedBox(height: 14),
                            ],

                            // Action Buttons (Cancel / Report Concern)
                            _buildActionButtons(context, order),

                            const SizedBox(height: 28),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),

              // The shell already provides the customer menu for normal routes.
              // Keep a local menu only for order details opened as root overlays.
              if (widget.showBottomNavigation) _buildFixedBottomNav(context),
            ],
          ),
        ),
      ),
    );
  }

  // ── 1. Top Header ──────────────────────────────────────────────────────────
  Widget _buildHeader(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Back Button
        GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white,
              border: Border.all(color: const Color(0xFFD5E7F4)),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: const Center(
              child: Icon(
                Icons.arrow_back_ios_new_rounded,
                size: 18,
                color: Color(0xFF0878C9),
              ),
            ),
          ),
        ),

        // Title
        const Text(
          'Order Details',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: Color(0xFF064B87),
          ),
        ),

        // Notification Bell
        Consumer<NotificationProvider>(
          builder: (context, notifProv, _) {
            final unreadCount = context.watch<AuthProvider>().isAuthenticated
                ? notifProv.unreadCount
                : 0;
            return GestureDetector(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const NotificationsScreen(),
                  ),
                );
              },
              child: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white,
                  border: Border.all(color: const Color(0xFFD5E7F4)),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    const Center(
                      child: Icon(
                        Icons.notifications_none_rounded,
                        color: Color(0xFF0878C9),
                        size: 22,
                      ),
                    ),
                    if (unreadCount > 0)
                      Positioned(
                        top: 6,
                        right: 6,
                        child: Container(
                          padding: const EdgeInsets.all(3),
                          decoration: const BoxDecoration(
                            color: Color(0xFFEF4444),
                            shape: BoxShape.circle,
                          ),
                          constraints: const BoxConstraints(
                            minWidth: 16,
                            minHeight: 16,
                          ),
                          child: Text(
                            unreadCount > 9 ? '9+' : '$unreadCount',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 8.5,
                              fontWeight: FontWeight.bold,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  // ── 2. Order Title ─────────────────────────────────────────────────────────
  Widget _buildOrderTitle(OrderModel order) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Refill Order',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF0878C9),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: order.isStationPickup ? const Color(0xFFE0F2FE) : const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: order.isStationPickup ? const Color(0xFFBAE6FD) : const Color(0xFFBBF7D0),
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    order.isStationPickup ? Icons.storefront_rounded : Icons.delivery_dining_rounded,
                    size: 13,
                    color: order.isStationPickup ? const Color(0xFF0284C7) : const Color(0xFF16A34A),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    order.isStationPickup ? 'Station Pickup' : 'Home Delivery',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: order.isStationPickup ? const Color(0xFF0369A1) : const Color(0xFF15803D),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Text(
                order.orderNumber,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                softWrap: false,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
            ),
            const SizedBox(width: 8),
            StatusBadge(status: order.status, small: true),
          ],
        ),
      ],
    );
  }

  // ── 3. Current Status Card ─────────────────────────────────────────────────
  Widget _buildCurrentStatusCard(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2F1FC)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'CURRENT STATUS',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: Color(0xFF6F8BA3),
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF0878C9),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                _canonicalStatusLabel(order.status),
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF064B87),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            _getStatusDescription(order.status),
            style: const TextStyle(
              fontSize: 13,
              color: Color(0xFF4A6B82),
              height: 1.3,
            ),
          ),
          if (order.isStationPickup) ...[
            const SizedBox(height: 12),
            _buildStationPickupCard(order),
          ] else if (order.address != null || order.delivery != null)
            OrderDriverTrackingMap(
              order: order,
              trackDriver: order.delivery != null &&
                  !['DELIVERED', 'COMPLETED', 'CANCELLED', 'REJECTED']
                      .contains(order.status.toUpperCase()),
            ),
        ],
      ),
    );
  }

  Widget _buildStationPickupCard(OrderModel order) {
    final isReady = order.status.toUpperCase() == 'READY_FOR_PICKUP';
    final isCompleted = order.status.toUpperCase() == 'COMPLETED';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isReady ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isReady ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: isReady ? const Color(0xFFDCFCE7) : const Color(0xFFE0F2FE),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.storefront_rounded,
                  color: isReady ? const Color(0xFF16A34A) : const Color(0xFF0284C7),
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isReady
                          ? 'Ready for Counter Pickup'
                          : isCompleted
                              ? 'Station Pickup Completed'
                              : 'Self-Pickup at Station',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: isReady ? const Color(0xFF166534) : const Color(0xFF0F172A),
                      ),
                    ),
                    const Text(
                      'Aqua Clayne Water Refilling Station',
                      style: TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),
          const SizedBox(height: 10),
          _buildStationMiniRow(Icons.place_outlined, 'Batisan, Gatid, Santa Cruz, Laguna'),
          const SizedBox(height: 4),
          _buildStationMiniRow(Icons.access_time_rounded, 'Mon - Sat: 7:00 AM - 6:00 PM'),
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Text(
              isReady
                  ? '🔔 Please present Order #${order.orderNumber} to the station cashier to claim.'
                  : isCompleted
                      ? '✓ Gallons claimed and transaction concluded.'
                      : 'ℹ️ Drop off empty customer-owned gallons at the counter for sanitizing and refilling.',
              style: TextStyle(
                fontSize: 11,
                fontWeight: isReady ? FontWeight.w600 : FontWeight.normal,
                color: isReady ? const Color(0xFF15803D) : const Color(0xFF475569),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStationMiniRow(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 14, color: const Color(0xFF0878C9)),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 11.5, color: Color(0xFF475569)),
          ),
        ),
      ],
    );
  }

  Widget _buildReadyForPickupBanner(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFDCFCE7), Color(0xFFBBF7D0)],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFF86EFAC)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF16A34A).withValues(alpha: 0.12),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            child: const Center(
              child: Icon(
                Icons.check_circle_rounded,
                color: Color(0xFF16A34A),
                size: 28,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'READY FOR PICKUP',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF14532D),
                    letterSpacing: 0.5,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Your clean refilled water gallons are ready! Head over to Aqua Clayne station in Batisan, Gatid to claim your order.',
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF166534),
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 4. Refilling Highlight Banner ──────────────────────────────────────────
  Widget _buildRefillingNowBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFE0F3FE), Color(0xFFC3E7FD)],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFBCE1F9)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            child: const Center(
              child: Icon(
                Icons.water_drop_rounded,
                color: Color(0xFF0878C9),
                size: 26,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'REFILLING IN PROGRESS',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF064B87),
                    letterSpacing: 0.5,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Your customer-owned gallons are currently being sanitized and refilled with purified drinking water.',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF4A6B82),
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 5. Order Progress Timeline Card ────────────────────────────────────────
  Widget _buildTimelineCard(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2F1FC)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Progress',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 14),
          OrderTimeline(
            currentStatus: order.status,
            isStationPickup: order.isStationPickup,
          ),
        ],
      ),
    );
  }

  // ── 6. Customer-Owned Gallon Details Card ──────────────────────────────────
  Widget _buildCombinedOrderSummary(BuildContext context, OrderModel order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2F1FC)),
        boxShadow: [
          BoxShadow(color: const Color(0xFF0878C9).withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, 4)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: const [
            Icon(Icons.receipt_long_outlined, color: Color(0xFF0878C9), size: 18),
            SizedBox(width: 8),
            Text('Order Summary', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF12344D))),
          ]),
          const SizedBox(height: 12),
          _buildGallonDetailsCard(order),
          const Divider(height: 24, color: Color(0xFFEAF4FC)),
          if (order.isStationPickup) ...[
            _buildStationPickupInfoCard(order),
            const Divider(height: 24, color: Color(0xFFEAF4FC)),
          ] else if (order.delivery != null || order.address != null) ...[
            _buildDeliveryInfoCard(order),
            const Divider(height: 24, color: Color(0xFFEAF4FC)),
          ],
          _buildOrderSummaryCard(order),
          const Divider(height: 24, color: Color(0xFFEAF4FC)),
          _buildPaymentCard(order),
          if (order.qrCode.isNotEmpty) ...[
            const Divider(height: 24, color: Color(0xFFEAF4FC)),
            Center(child: Column(children: [
              const Text('PRESENT THIS QR CODE AT PICKUP', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF064B87))),
              const SizedBox(height: 10),
              GestureDetector(
                onTap: () => showDialog(context: context, builder: (_) => Dialog(child: Padding(padding: const EdgeInsets.all(24), child: QrImageView(data: order.qrCode, size: 280)))),
                child: QrImageView(data: order.qrCode, size: 170),
              ),
              const Text('Tap to enlarge • Available in Order Details'),
            ])),
          ],
          if (!order.isStationPickup && _hasProofOfDelivery(order)) ...[
            const Divider(height: 24, color: Color(0xFFEAF4FC)),
            _buildProofOfDeliveryCard(context, order),
          ],
        ],
      ),
    );
  }

  Widget _buildGallonDetailsCard(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.inventory_2_outlined, color: Color(0xFF0878C9), size: 18),
              SizedBox(width: 8),
              Text(
                'Your Gallons',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildInfoRow('Container Ownership', 'Customer-Owned'),
          _buildInfoRow('Quantity for Refill', '${order.expectedEmptyGallons} Gallons'),
          _buildInfoRow('Expected Count', '${order.expectedEmptyGallons}'),
          _buildInfoRow(
            'Received Count',
            order.receivedEmptyGallons != null
                ? '${order.receivedEmptyGallons}'
                : 'Awaiting station intake',
          ),
          _buildInfoRow(
            'Accepted Count',
            order.acceptedEmptyGallons != null
                ? '${order.acceptedEmptyGallons}'
                : 'Pending inspection',
          ),
        ],
      ),
    );
  }

  // ── 8. Delivery Information Card ───────────────────────────────────────────
  Widget _buildDeliveryInfoCard(OrderModel order) {
    final delivery = order.delivery;
    final driverName = delivery?['driver']?['name']?.toString() ??
        delivery?['driver_name']?.toString() ??
        'Assigned Station Driver';
    final deliveryStatus = delivery?['status']?.toString() ?? order.status;

    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.local_shipping_outlined, color: Color(0xFF0878C9), size: 18),
              SizedBox(width: 8),
              Text(
                'Delivery Information',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (delivery != null) ...[
            _buildInfoRow('Driver', driverName),
            _buildInfoRow('Delivery Status', deliveryStatus),
          ],
          if (order.address != null) ...[
            _buildInfoRow('Delivery Address', order.address!.fullAddress),
          ],
        ],
      ),
    );
  }

  // ── 8b. Station Pickup Details Card ────────────────────────────────────────
  Widget _buildStationPickupInfoCard(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.storefront_outlined, color: Color(0xFF0878C9), size: 18),
              SizedBox(width: 8),
              Text(
                'Station Pickup Information',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildInfoRow('Pickup Location', StationConfig.name),
          _buildInfoRow('Address', StationConfig.shortAddress),
          _buildInfoRow('Station Coordinates', StationConfig.formattedCoordinates),
          _buildInfoRow('Operating Hours', 'Mon - Sat: 7:00 AM - 6:00 PM'),
          _buildInfoRow('Delivery Fee', 'FREE (₱0.00)'),
          _buildInfoRow(
            'Pickup Status',
            order.status.toUpperCase() == 'READY_FOR_PICKUP'
                ? 'Ready for pickup'
                : order.status.toUpperCase() == 'COMPLETED'
                    ? 'Completed'
                    : 'Preparing at station',
          ),
        ],
      ),
    );
  }

  // ── 9. Order Summary Card ──────────────────────────────────────────────────
  Widget _buildOrderSummaryCard(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (order.items.isNotEmpty)
            ...order.items.map((item) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.productName.isNotEmpty
                            ? item.productName
                            : '5-Gallon Purified Water Refill',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ),
                    Text(
                      '${item.quantity} × ₱${item.unitPrice.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 12, color: Color(0xFF6F8BA3)),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      '₱${item.subtotal.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF0878C9),
                      ),
                    ),
                  ],
                ),
              );
            })
          else
            _buildInfoRow(
              '5-Gallon Purified Water Refill',
              '${order.expectedEmptyGallons} × Refill',
            ),
          const Divider(height: 18, color: Color(0xFFEAF4FC)),
          _buildInfoRow('Delivery Method', order.deliveryMethodDisplay),
          _buildInfoRow(
            'Delivery Fee',
            order.isStationPickup ? 'FREE (₱0.00)' : '₱${order.deliveryFee.toStringAsFixed(2)}',
          ),
          const Divider(height: 18, color: Color(0xFFEAF4FC)),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Total Amount',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
              Text(
                '₱${order.totalAmount.toStringAsFixed(2)}',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0878C9),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ── 10. Payment Card ───────────────────────────────────────────────────────
  Widget _buildPaymentCard(OrderModel order) {
    final methodUpper = order.paymentMethod.toUpperCase();
    final isOnline = methodUpper == 'ONLINE';
    final isCash = methodUpper == 'CASH';
    final isDebt = methodUpper == 'DEBT';
    final isPaid = order.paymentStatus.toUpperCase() == 'PAID';
    final isFailed = order.paymentStatus.toUpperCase() == 'FAILED';

    String methodLabel = order.isStationPickup ? 'Cash on Pickup (COP)' : 'Cash on Delivery (COD)';
    if (isOnline) {
      methodLabel = 'Pay Online via PayMongo';
    } else if (isDebt) {
      methodLabel = 'Debt';
    }

    String statusLabel = 'PENDING';
    Color statusBg = const Color(0xFFFEF3C7);
    Color statusFg = const Color(0xFFD97706);

    if (isPaid) {
      statusLabel = isOnline ? 'PAID ONLINE' : 'PAID';
      statusBg = const Color(0xFFD1FAE5);
      statusFg = const Color(0xFF059669);
    } else if (isFailed) {
      statusLabel = 'FAILED';
      statusBg = const Color(0xFFFEE2E2);
      statusFg = const Color(0xFFDC2626);
    } else if (isCash) {
      statusLabel = order.isStationPickup ? 'PAY AT STATION UPON PICKUP' : 'COLLECT UPON DELIVERY';
      statusBg = const Color(0xFFE0F2FE);
      statusFg = const Color(0xFF0284C7);
    } else if (isDebt) {
      statusLabel = 'UNPAID DEBT';
      statusBg = const Color(0xFFEDE9FE);
      statusFg = const Color(0xFF7C3AED);
    }

    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: const [
                  Icon(Icons.account_balance_wallet_outlined,
                      color: Color(0xFF0878C9), size: 18),
                  SizedBox(width: 8),
                  Text(
                    'Payment Information',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF12344D),
                    ),
                  ),
                ],
              ),
              IconButton(
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded, size: 18, color: Color(0xFF0878C9)),
                tooltip: 'Refresh payment status',
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildInfoRow('Payment Method', methodLabel),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Payment Status',
                style: TextStyle(fontSize: 12.5, color: Color(0xFF6F8BA3)),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                decoration: BoxDecoration(
                  color: statusBg,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  statusLabel,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.bold,
                    color: statusFg,
                  ),
                ),
              ),
            ],
          ),
          _buildInfoRow('Order Total', '₱${order.totalAmount.toStringAsFixed(2)}'),

          // If Online and not yet paid: Display Pay Online / Retry button!
          if (isOnline && !isPaid) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _paying ? null : () => _payOnline(order),
                icon: _paying
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.payment_rounded, size: 18),
                label: Text(_paying
                    ? 'Opening PayMongo...'
                    : (isFailed
                        ? 'Retry Online Payment'
                        : 'Pay Online (₱${order.totalAmount.toStringAsFixed(2)})')),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 0,
                ),
              ),
            ),
            const SizedBox(height: 6),
            const Center(
              child: Text(
                'Supports GCash, Maya, GrabPay & Credit/Debit Cards via PayMongo',
                style: TextStyle(fontSize: 10.5, color: Color(0xFF88A0B8)),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ── 11. Proof of Delivery Card ─────────────────────────────────────────────
  bool _hasProofOfDelivery(OrderModel order) {
    final delivery = order.delivery;
    return delivery != null &&
        (delivery['proof_photo_url'] != null || delivery['photo_url'] != null);
  }

  Widget _buildProofOfDeliveryCard(BuildContext context, OrderModel order) {
    final proofUrl = (order.delivery?['proof_photo_url'] ??
            order.delivery?['photo_url'])
        ?.toString() ??
        '';

    return Container(
      width: double.infinity,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.verified_outlined, color: Color(0xFF059669), size: 18),
              SizedBox(width: 8),
              Text(
                'Proof of Delivery',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'The delivery was confirmed with a photo confirmation by station driver.',
            style: TextStyle(fontSize: 12, color: Color(0xFF6F8BA3)),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => _showProofDialog(context, proofUrl),
              icon: const Icon(Icons.photo_outlined, size: 16),
              label: const Text('View Proof Photo'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF0878C9),
                side: const BorderSide(color: Color(0xFF0878C9)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── 12. Completed Banner ───────────────────────────────────────────────────
  Widget _buildCompletedBanner(OrderModel order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFD1FAE5),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFA7F3D0)),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0xFF059669),
            ),
            child: const Icon(Icons.check, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Order Completed',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF065F46),
                  ),
                ),
                Text(
                  'Completed on ${_formatDate(order.completedAt ?? order.createdAt)}',
                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF047857)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 13. Exception Card ─────────────────────────────────────────────────────
  bool _isExceptionStatus(String status) {
    return [
      'REJECTED',
      'CANCELLED',
      'PICKUP_FAILED',
      'DELIVERY_FAILED',
    ].contains(status.toUpperCase());
  }

  Widget _buildExceptionCard(OrderModel order) {
    final status = order.status.toUpperCase();
    final isCancelled = status == 'CANCELLED';
    final isRejected = status == 'REJECTED';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFEE2E2),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFFECACA)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.error_outline_rounded, color: Color(0xFFDC2626), size: 20),
              const SizedBox(width: 8),
              Text(
                isCancelled
                    ? 'Order Cancelled'
                    : isRejected
                        ? 'Order Rejected'
                        : 'Delivery Exception',
                style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF991B1B),
                ),
              ),
            ],
          ),
          if (order.notes != null && order.notes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              'Reason: ${order.notes}',
              style: const TextStyle(fontSize: 12, color: Color(0xFFB91C1C)),
            ),
          ],
        ],
      ),
    );
  }

  // ── 14. Action Buttons ─────────────────────────────────────────────────────
  Widget _buildActionButtons(BuildContext context, OrderModel order) {
    return Column(
      children: [
        // Switch Delivery Method (if pending)
        if (order.canSwitchDeliveryMethod) ...[
          SizedBox(
            width: double.infinity,
            height: 46,
            child: OutlinedButton.icon(
              onPressed: () => _showSwitchDeliveryMethodModal(order),
              icon: Icon(
                order.isStationPickup ? Icons.delivery_dining_rounded : Icons.storefront_rounded,
                size: 18,
              ),
              label: Text(
                order.isStationPickup ? 'Switch to Home Delivery (+₱5.00)' : 'Switch to Station Pickup (FREE)',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF0878C9),
                side: const BorderSide(color: Color(0xFF0878C9), width: 1.5),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],

        // Cancel button (if pending)
        if (order.isCancellable) ...[
          SizedBox(
            width: double.infinity,
            height: 46,
            child: OutlinedButton.icon(
              onPressed: () => _cancelOrder(order),
              icon: const Icon(Icons.cancel_outlined, size: 18),
              label: const Text('Cancel Order'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFFDC2626),
                side: const BorderSide(color: Color(0xFFDC2626)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],

        // Report Concern Button
        SizedBox(
          width: double.infinity,
          height: 46,
          child: OutlinedButton.icon(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => SubmitComplaintScreen(orderId: order.id),
                ),
              );
            },
            icon: const Icon(Icons.report_outlined, size: 18),
            label: const Text('Have a problem? Report a Concern'),
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFF0878C9),
              side: const BorderSide(color: Color(0xFF0878C9)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _showSwitchDeliveryMethodModal(OrderModel order) async {
    final messenger = ScaffoldMessenger.of(context);
    final orderProv = context.read<OrderProvider>();
    final isCurrentlyPickup = order.isStationPickup;
    final targetMethod = isCurrentlyPickup ? 'home_delivery' : 'station_pickup';
    String? selectedAddressId = order.address?.id;

    if (!isCurrentlyPickup) {
      // Switching from Delivery to Pickup
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Row(
            children: const [
              Icon(Icons.storefront_rounded, color: Color(0xFF0878C9)),
              SizedBox(width: 8),
              Text('Switch to Station Pickup', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Collect your water directly at the Aqua Clayne Station in Batisan, Gatid, Santa Cruz, Laguna.',
                style: TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.4),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('Current Delivery Fee:', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                        Text('₱5.00', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('New Delivery Fee:', style: TextStyle(fontSize: 12, color: Color(0xFF16A34A), fontWeight: FontWeight.bold)),
                        Text('FREE (₱0.00)', style: TextStyle(fontSize: 12, color: Color(0xFF16A34A), fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const Divider(height: 14),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Updated Total:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF12344D))),
                        Text(
                          '₱${(order.totalAmount - order.deliveryFee).clamp(0.0, double.infinity).toStringAsFixed(2)}',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0878C9)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel', style: TextStyle(color: Color(0xFF64748B))),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Confirm Switch'),
            ),
          ],
        ),
      );

      if (confirmed != true || !mounted) return;

      try {
        final updated = await orderProv.updateDeliveryMethod(order.id, targetMethod);
        if (!mounted) return;
        if (updated != null) {
          messenger.showSnackBar(
            const SnackBar(
              content: Text('Order switched to Station Pickup (Delivery fee removed).'),
              backgroundColor: AppColors.success,
            ),
          );
          _load();
        } else {
          messenger.showSnackBar(
            SnackBar(
              content: Text(orderProv.error ?? 'Failed to switch delivery method.'),
              backgroundColor: AppColors.error,
            ),
          );
        }
      } catch (e) {
        if (!mounted) return;
        messenger.showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } else {
      // Switching from Station Pickup to Home Delivery:
      // Load addresses
      final addressProv = context.read<AddressProvider>();
      if (addressProv.addresses.isEmpty) {
        await addressProv.load();
      }
      final addresses = addressProv.addresses;
      if (addresses.isEmpty) {
        if (!mounted) return;
        messenger.showSnackBar(
          const SnackBar(
            content: Text('Please add a delivery address first in your profile.'),
            backgroundColor: Color(0xFFDC2626),
          ),
        );
        return;
      }

      selectedAddressId ??= addressProv.defaultAddress?.id ?? addresses.first.id;

      if (!mounted) return;
      final result = await showModalBottomSheet<String>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        builder: (ctx) {
          String tempAddressId = selectedAddressId!;
          return StatefulBuilder(
            builder: (ctx, setModalState) {
              return Padding(
                padding: EdgeInsets.only(
                  left: 20,
                  right: 20,
                  top: 20,
                  bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: const [
                            Icon(Icons.delivery_dining_rounded, color: Color(0xFF0878C9)),
                            SizedBox(width: 8),
                            Text(
                              'Switch to Home Delivery',
                              style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF12344D)),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: const Icon(Icons.close, size: 20),
                          onPressed: () => Navigator.pop(ctx),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Select the destination address in Gatid, Duhat, or Bubukal for driver delivery (+₱5.00 fee):',
                      style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 12),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 220),
                      child: ListView.builder(
                        shrinkWrap: true,
                        itemCount: addresses.length,
                        itemBuilder: (ctx, idx) {
                          final addr = addresses[idx];
                          final isSelected = addr.id == tempAddressId;
                          return InkWell(
                            onTap: () {
                              setModalState(() {
                                tempAddressId = addr.id;
                              });
                            },
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: isSelected ? const Color(0xFFF0F9FF) : Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: isSelected ? const Color(0xFF0878C9) : const Color(0xFFE2E8F0),
                                  width: isSelected ? 1.5 : 1,
                                ),
                              ),
                              child: Row(
                                children: [
                                  Icon(
                                    isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                                    color: isSelected ? const Color(0xFF0878C9) : const Color(0xFF94A3B8),
                                    size: 20,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          addr.label.isNotEmpty ? addr.label : 'Delivery Address',
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                        ),
                                        Text(
                                          addr.fullAddress,
                                          style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Delivery Fee Addition:', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                          const Text('+₱5.00', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0878C9))),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton(
                        onPressed: () => Navigator.pop(ctx, tempAddressId),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0878C9),
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Confirm Switch to Delivery', style: TextStyle(fontWeight: FontWeight.bold)),
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      );

      if (result == null || !mounted) return;

      try {
        final updated = await orderProv.updateDeliveryMethod(order.id, targetMethod, addressId: result);
        if (!mounted) return;
        if (updated != null) {
          messenger.showSnackBar(
            const SnackBar(
              content: Text('Order switched to Home Delivery (+₱5.00 fee).'),
              backgroundColor: AppColors.success,
            ),
          );
          _load();
        } else {
          messenger.showSnackBar(
            SnackBar(
              content: Text(orderProv.error ?? 'Failed to switch delivery method.'),
              backgroundColor: AppColors.error,
            ),
          );
        }
      } catch (e) {
        if (!mounted) return;
        messenger.showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  // ── 15. Fixed Bottom Navigation Bar (Orders Active) ────────────────────────
  Widget _buildFixedBottomNav(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFE5F1FA), width: 1),
        ),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 62,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildNavItem(
                label: 'Home',
                icon: Icons.home_outlined,
                isActive: false,
                onTap: () {
                  _openCustomerTab(context, 0);
                },
              ),
              _buildNavItem(
                label: 'Orders',
                icon: Icons.article_rounded,
                isActive: true, // ACTIVE
                onTap: () => _openCustomerTab(context, 1),
              ),
              _buildNavItem(
                label: 'Payments',
                icon: Icons.account_balance_wallet_outlined,
                isActive: false,
                onTap: () {
                  _openCustomerTab(context, 2);
                },
              ),
              _buildNavItem(
                label: 'Chat',
                icon: Icons.chat_bubble_outline_rounded,
                isActive: false,
                onTap: () => _openCustomerTab(context, 3),
              ),
              _buildNavItem(
                label: 'Profile',
                icon: Icons.person_outline_rounded,
                isActive: false,
                onTap: () {
                  _openCustomerTab(context, 4);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    required String label,
    required IconData icon,
    required bool isActive,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          splashColor: const Color(0xFF0878C9).withValues(alpha: 0.1),
          highlightColor: Colors.transparent,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                color: isActive
                    ? const Color(0xFF0878C9)
                    : const Color(0xFF6F8BA3),
                size: 24,
              ),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
                  color: isActive
                      ? const Color(0xFF0878C9)
                      : const Color(0xFF6F8BA3),
                ),
              ),
              const SizedBox(height: 3),
              Container(
                width: 28,
                height: 2.5,
                decoration: BoxDecoration(
                  color: isActive
                      ? const Color(0xFF0878C9)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _openCustomerTab(BuildContext context, int index) {
    CustomerShell.showTab(context, index);
  }

  // ── Helper UI Widgets ──────────────────────────────────────────────────────
  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3.5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 12.5, color: Color(0xFF6F8BA3)),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: Color(0xFF12344D),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _canonicalStatusLabel(String s) {
    switch (s.toUpperCase()) {
      case 'PENDING':
        return 'Pending Confirmation';
      case 'CONFIRMED':
        return 'Confirmed';
      case 'ASSIGNED':
        return 'Driver Assigned';
      case 'PICKED_UP':
        return 'Empty Gallons Collected';
      case 'RECEIVED_AT_STATION':
        return 'Received at Station';
      case 'REFILLING':
        return 'Refilling Now';
      case 'READY_FOR_PICKUP':
        return 'Ready for Pickup';
      case 'READY_FOR_DELIVERY':
        return 'Ready for Delivery';
      case 'OUT_FOR_DELIVERY':
        return 'Out for Delivery';
      case 'DELIVERED':
        return 'Delivered';
      case 'COMPLETED':
        return 'Completed';
      case 'REJECTED':
        return 'Rejected';
      case 'CANCELLED':
        return 'Cancelled';
      case 'PICKUP_FAILED':
        return 'Pickup Failed';
      case 'DELIVERY_FAILED':
        return 'Delivery Failed';
      default:
        return s;
    }
  }

  Widget _buildLoadingSkeleton() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: List.generate(4, (index) {
          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE2F1FC)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 140,
                  height: 16,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEAF7FF),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  height: 12,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEAF7FF),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  width: 180,
                  height: 12,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEAF7FF),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _buildNotFoundState(OrderProvider prov) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFE2F1FC)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.receipt_long_outlined, size: 48, color: Color(0xFF6F8BA3)),
              const SizedBox(height: 14),
              const Text(
                'Order Not Found',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'This order could not be located or may have been removed.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12.5, color: Color(0xFF6F8BA3)),
              ),
              const SizedBox(height: 18),
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: const Text('Back to Orders', style: TextStyle(color: Colors.white)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
