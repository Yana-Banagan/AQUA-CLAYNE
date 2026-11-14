import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:mobile/core/utils/pht_datetime.dart';

import '../../customer/providers/order_provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../core/models/order_model.dart';
import '../../core/theme/app_colors.dart';
import '../../shared_widgets/status_badge.dart';
import 'order_detail_screen.dart';
import 'catalog_screen.dart';
import 'notifications_screen.dart';

class OrdersListScreen extends StatefulWidget {
  const OrdersListScreen({super.key});

  @override
  State<OrdersListScreen> createState() => _OrdersListScreenState();
}

class _OrdersListScreenState extends State<OrdersListScreen> {
  String _selectedFilter = 'All'; // 'All', 'Active', 'Completed', 'Cancelled'

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!context.read<AuthProvider>().isAuthenticated) return;
      context.read<OrderProvider>().loadOrders();
      context.read<NotificationProvider>().load();
    });
  }

  String _formatDate(String raw) {
    if (raw.isEmpty) return 'Recent';
    try {
      final dt = PhtDateTime.parse(raw);
      const months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
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

  List<OrderModel> _getFilteredOrders(OrderProvider prov) {
    switch (_selectedFilter) {
      case 'Active':
        return prov.activeOrders;
      case 'Completed':
        return prov.completedOrders;
      case 'Cancelled':
        return prov.cancelledOrders;
      case 'All':
      default:
        return prov.orders;
    }
  }

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.canPop(context);

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
          child: Consumer<OrderProvider>(
            builder: (context, orderProv, _) {
              final isGuest = !context.watch<AuthProvider>().isAuthenticated;
              final filteredOrders = isGuest ? <OrderModel>[] : _getFilteredOrders(orderProv);
              final activeOrder = isGuest ? null : orderProv.activeOrder;
              final showActiveHero =
                  (_selectedFilter == 'All' || _selectedFilter == 'Active') &&
                  activeOrder != null;

              return Column(
                children: [
                  // Keep the order actions visible while the order list scrolls.
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 10,
                    ),
                    child: _buildHeader(context, canPop),
                  ),
                  Expanded(
                    child: RefreshIndicator(
                      color: AppColors.primary,
                      onRefresh: () => context.read<AuthProvider>().isAuthenticated
                          ? orderProv.loadOrders()
                          : Future<void>.value(),
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const SizedBox(height: 10),

                            // 2. Page Title & Subtitle
                            _buildPageTitle(),

                            const SizedBox(height: 16),

                            // 3. Horizontal Filter Tabs
                            _buildFilterTabs(orderProv),

                            const SizedBox(height: 18),

                            // 4. Prominent Active / Current Order Card (When on All or Active)
                            if (showActiveHero) ...[
                              _buildActiveOrderHeroCard(context, activeOrder),
                              const SizedBox(height: 20),
                              if (filteredOrders
                                  .where((o) => o.id != activeOrder.id)
                                  .isNotEmpty)
                                const Padding(
                                  padding: EdgeInsets.only(bottom: 12),
                                  child: Text(
                                    'ORDER HISTORY',
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF6F8BA3),
                                      letterSpacing: 0.8,
                                    ),
                                  ),
                                ),
                            ],

                            // 5. Orders List / Loading / Empty / Error
                            if (!isGuest && orderProv.loading && orderProv.orders.isEmpty)
                              _buildLoadingSkeleton()
                            else if (!isGuest && orderProv.error != null &&
                                orderProv.orders.isEmpty)
                              _buildErrorState(orderProv)
                            else if (filteredOrders.isEmpty)
                              _buildEmptyState()
                            else
                              ...filteredOrders
                                  .where(
                                    (o) => showActiveHero
                                        ? o.id != activeOrder.id
                                        : true,
                                  )
                                  .map(
                                    (order) => Padding(
                                      padding: const EdgeInsets.only(
                                        bottom: 12,
                                      ),
                                      child: _buildOrderCard(context, order),
                                    ),
                                  ),

                            const SizedBox(height: 28),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  // ── 1. Top Header ──────────────────────────────────────────────────────────
  Widget _buildHeader(BuildContext context, bool canPop) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Left: Back button or Logo
        if (canPop)
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
          )
        else
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [Color(0xFFEAF7FF), Color(0xFFC7E8FD)],
                  ),
                ),
                child: ClipOval(
                  child: Image.asset(
                    'assets/images/aqua_clayne_logo.png',
                    fit: BoxFit.cover,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              const Text(
                'AQUA CLAYNE',
                style: TextStyle(
                  fontFamily: 'serif',
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF064B87),
                  letterSpacing: 1.0,
                ),
              ),
            ],
          ),

        // Center: Page Header Title (when popped)
        if (canPop)
          const Text(
            'Orders',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Color(0xFF064B87),
            ),
          ),

        // Right: Notification Bell Button
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

  // ── 2. Page Title & Subtitle ───────────────────────────────────────────────
  Widget _buildPageTitle() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: const [
        Text(
          'My Orders',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: Color(0xFF12344D),
            height: 1.15,
          ),
        ),
        SizedBox(height: 2),
        Text(
          'Track your water refill orders in real time',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: Color(0xFF6F8BA3),
          ),
        ),
      ],
    );
  }

  // ── 3. Horizontal Filter Tabs ──────────────────────────────────────────────
  Widget _buildFilterTabs(OrderProvider prov) {
    final filters = [
      {'label': 'All', 'count': prov.orders.length},
      {'label': 'Active', 'count': prov.activeOrders.length},
      {'label': 'Completed', 'count': prov.completedOrders.length},
      {'label': 'Cancelled', 'count': prov.cancelledOrders.length},
    ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: filters.map((f) {
          final label = f['label'] as String;
          final count = f['count'] as int;
          final isSelected = _selectedFilter == label;

          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => setState(() => _selectedFilter = label),
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    gradient: isSelected
                        ? const LinearGradient(
                            colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                          )
                        : null,
                    color: isSelected ? null : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isSelected
                          ? const Color(0xFF0878C9)
                          : const Color(0xFFE2F1FC),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: isSelected
                            ? const Color(0xFF0878C9).withValues(alpha: 0.25)
                            : const Color(0xFF0878C9).withValues(alpha: 0.04),
                        blurRadius: isSelected ? 8 : 4,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        label,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: isSelected
                              ? FontWeight.bold
                              : FontWeight.w600,
                          color: isSelected
                              ? Colors.white
                              : const Color(0xFF6F8BA3),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 1.5,
                        ),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? Colors.white.withValues(alpha: 0.25)
                              : const Color(0xFFEAF7FF),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '$count',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: isSelected
                                ? Colors.white
                                : const Color(0xFF0878C9),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ── 4. Prominent Active / Current Order Card ───────────────────────────────
  Widget _buildActiveOrderHeroCard(BuildContext context, OrderModel order) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFBCE1F9), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.12),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header strip
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFFEAF7FF), Color(0xFFD8EEFD)],
              ),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Color(0xFF0878C9),
                      ),
                    ),
                    const SizedBox(width: 6),
                    const Text(
                      'CURRENT ACTIVE REFILL',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0878C9),
                        letterSpacing: 0.8,
                      ),
                    ),
                  ],
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _buildDeliveryMethodBadge(order.isStationPickup),
                    const SizedBox(width: 8),
                    StatusBadge(status: order.status),
                  ],
                ),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Order Number & Terminology
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: const Color(0xFFEAF7FF),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFD5E7F4)),
                      ),
                      child: Center(
                        child: Image.asset(
                          'assets/images/banner_gallon_splash.png',
                          width: 40,
                          height: 40,
                          fit: BoxFit.contain,
                          errorBuilder: (_, _, _) => const Icon(
                            Icons.water_drop_rounded,
                            color: Color(0xFF0878C9),
                            size: 26,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            order.orderNumber,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF12344D),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            order.itemSummary.isNotEmpty
                                ? order.itemSummary
                                : '5-Gallon Purified Water Refill',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF064B87),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${order.expectedEmptyGallons} Customer-Owned Gallon${order.expectedEmptyGallons > 1 ? 's' : ''} for Refill',
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: Color(0xFF6F8BA3),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 12),
                const Divider(height: 1, color: Color(0xFFEAF4FC)),
                const SizedBox(height: 12),

                // Amount, Payment & Date
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Total Amount',
                          style: TextStyle(
                            fontSize: 11,
                            color: Color(0xFF6F8BA3),
                          ),
                        ),
                        Row(
                          children: [
                            Text(
                              '₱${order.totalAmount.toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0878C9),
                              ),
                            ),
                            const SizedBox(width: 6),
                            _buildPaymentStatusBadge(order.paymentStatus),
                          ],
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text(
                          'Order Placed',
                          style: TextStyle(
                            fontSize: 11,
                            color: Color(0xFF6F8BA3),
                          ),
                        ),
                        Text(
                          _formatDate(order.createdAt),
                          style: const TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w500,
                            color: Color(0xFF12344D),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                const SizedBox(height: 14),

                // Track Order Action Button
                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => OrderDetailScreen(orderId: order.id),
                        ),
                      );
                    },
                    icon: const Icon(
                      Icons.timeline_rounded,
                      color: Colors.white,
                      size: 18,
                    ),
                    label: const Text(
                      'Track Order Live',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0878C9),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 5. Standard Order Card ─────────────────────────────────────────────────
  Widget _buildOrderCard(BuildContext context, OrderModel order) {
    final isActive = order.isActive;
    final isDelivered = order.status.toUpperCase() == 'DELIVERED';
    final isCompleted = order.status.toUpperCase() == 'COMPLETED';

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(orderId: order.id),
            ),
          );
        },
        borderRadius: BorderRadius.circular(20),
        child: Container(
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
              // Top Row: Order Number, Delivery Method & Status
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        order.orderNumber,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF12344D),
                        ),
                      ),
                      const SizedBox(width: 8),
                      _buildDeliveryMethodBadge(order.isStationPickup),
                    ],
                  ),
                  if (isDelivered || isCompleted)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2.5,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFD1FAE5),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        isCompleted ? 'Completed' : 'Delivered',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF059669),
                        ),
                      ),
                    )
                  else
                    StatusBadge(status: order.status),
                ],
              ),

              const SizedBox(height: 10),
              const Divider(height: 1, color: Color(0xFFEAF4FC)),
              const SizedBox(height: 10),

              // Middle: Service and Terminology
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Center(
                      child: Image.asset(
                        'assets/images/banner_gallon_splash.png',
                        width: 32,
                        height: 32,
                        fit: BoxFit.contain,
                        errorBuilder: (_, _, _) => const Icon(
                          Icons.water_drop_rounded,
                          color: Color(0xFF0878C9),
                          size: 20,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.itemSummary.isNotEmpty
                              ? order.itemSummary
                              : '5-Gallon Purified Water Refill',
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF12344D),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Refill Service • ${order.expectedEmptyGallons} Gallons (Customer-Owned)',
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF6F8BA3),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Bottom Row: Price, Date & Action Pill
              Row(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            '₱${order.totalAmount.toStringAsFixed(2)}',
                            style: const TextStyle(
                              fontSize: 15.5,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0878C9),
                            ),
                          ),
                          const SizedBox(width: 6),
                          _buildPaymentStatusBadge(order.paymentStatus),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          const Icon(
                            Icons.calendar_today_outlined,
                            size: 10.5,
                            color: Color(0xFF6F8BA3),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            _formatDate(order.createdAt),
                            style: const TextStyle(
                              fontSize: 10.5,
                              color: Color(0xFF6F8BA3),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: isActive
                          ? const Color(0xFF0878C9)
                          : const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          isActive ? 'Track Order' : 'View Details',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: isActive
                                ? Colors.white
                                : const Color(0xFF0878C9),
                          ),
                        ),
                        const SizedBox(width: 3),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 14,
                          color: isActive
                              ? Colors.white
                              : const Color(0xFF0878C9),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentStatusBadge(String paymentStatus) {
    final isPaid = paymentStatus.toUpperCase() == 'PAID';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
      decoration: BoxDecoration(
        color: isPaid ? const Color(0xFFD1FAE5) : const Color(0xFFFEF3C7),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        isPaid ? 'PAID' : 'PENDING',
        style: TextStyle(
          fontSize: 9.5,
          fontWeight: FontWeight.w700,
          color: isPaid ? const Color(0xFF059669) : const Color(0xFFD97706),
        ),
      ),
    );
  }

  // ── 6. Loading Skeleton ────────────────────────────────────────────────────
  Widget _buildLoadingSkeleton() {
    return Column(
      children: List.generate(3, (index) {
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE2F1FC)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 110,
                    height: 16,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                  Container(
                    width: 70,
                    height: 18,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 150,
                          height: 14,
                          decoration: BoxDecoration(
                            color: const Color(0xFFEAF7FF),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Container(
                          width: 100,
                          height: 10,
                          decoration: BoxDecoration(
                            color: const Color(0xFFEAF7FF),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      }),
    );
  }

  // ── 7. Empty State ─────────────────────────────────────────────────────────
  Widget _buildEmptyState() {
    return Container(
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2F1FC)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0xFFEAF7FF),
            ),
            child: const Center(
              child: Icon(
                Icons.water_drop_outlined,
                size: 36,
                color: Color(0xFF0878C9),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            _selectedFilter == 'All'
                ? 'No Orders Yet'
                : 'No $_selectedFilter Orders',
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _selectedFilter == 'All'
                ? 'You haven\'t placed a water refill order yet.\nSupply your containers and enjoy fresh drinking water.'
                : 'There are currently no orders in the "$_selectedFilter" tab.',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12.5,
              color: Color(0xFF6F8BA3),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: 180,
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CatalogScreen()),
                );
              },
              icon: const Icon(
                Icons.water_drop_rounded,
                color: Colors.white,
                size: 16,
              ),
              label: const Text(
                'Order Water',
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── 8. Error State ─────────────────────────────────────────────────────────
  Widget _buildErrorState(OrderProvider prov) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2F1FC)),
      ),
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: Color(0xFFEF4444),
          ),
          const SizedBox(height: 12),
          const Text(
            'Unable to load your orders.',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Please check your network connection and try again.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12, color: Color(0xFF6F8BA3)),
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => prov.loadOrders(),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0878C9),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            child: const Text(
              'Try Again',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliveryMethodBadge(bool isStationPickup) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
      decoration: BoxDecoration(
        color: isStationPickup ? const Color(0xFFE0F2FE) : const Color(0xFFF0FDF4),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
          color: isStationPickup ? const Color(0xFFBAE6FD) : const Color(0xFFBBF7D0),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isStationPickup ? Icons.storefront_rounded : Icons.delivery_dining_rounded,
            size: 11,
            color: isStationPickup ? const Color(0xFF0284C7) : const Color(0xFF16A34A),
          ),
          const SizedBox(width: 3.5),
          Text(
            isStationPickup ? 'Pickup' : 'Delivery',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: isStationPickup ? const Color(0xFF0369A1) : const Color(0xFF15803D),
            ),
          ),
        ],
      ),
    );
  }
}
