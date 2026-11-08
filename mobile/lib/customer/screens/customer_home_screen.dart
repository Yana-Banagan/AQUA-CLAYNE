import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../widgets/interactive_tutorial_overlay.dart';

import '../../core/providers/auth_provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/credit_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../shared_widgets/status_badge.dart';
import 'water_refill_services_screen.dart';
import 'orders_list_screen.dart';
import 'order_detail_screen.dart';
import 'credit_screen.dart';
import 'cart_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';
import 'payments_screen.dart';
import 'customer_shell.dart';

class CustomerHomeScreen extends StatefulWidget {
  const CustomerHomeScreen({super.key});

  @override
  State<CustomerHomeScreen> createState() => _CustomerHomeScreenState();
}

class _CustomerHomeScreenState extends State<CustomerHomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadDashboardData();
    });
  }

  Future<void> _loadDashboardData() async {
    if (!mounted) return;
    if (!context.read<AuthProvider>().isAuthenticated) return;
    await Future.wait([
      context.read<OrderProvider>().loadOrders(),
      context.read<NotificationProvider>().load(),
      context.read<CreditProvider>().loadCredit(),
    ]);
  }

  String _getGreeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) {
      return 'Good Morning,';
    } else if (hour < 17) {
      return 'Good Afternoon,';
    } else {
      return 'Good Evening,';
    }
  }

  String _formatDate(String raw) {
    if (raw.isEmpty) return 'Recent';
    try {
      final dt = DateTime.parse(raw).toLocal();
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

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final isGuest = user == null;
    final customerName = user?.name.isNotEmpty == true ? user!.name : 'Welcome';

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
              // Keep the dashboard actions visible while the content scrolls.
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                child: _buildHeader(context),
              ),
              Expanded(
                child: RefreshIndicator(
                  color: AppColors.primary,
                  onRefresh: _loadDashboardData,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 10),

                        // 2. Customer Greeting
                        _buildGreetingSection(customerName, isGuest: isGuest),

                        const SizedBox(height: 16),

                        // 3. Hero / Promotional Banner
                        _buildHeroBanner(context),

                        const SizedBox(height: 18),

                        _buildHowToOrderTutorial(),
                        const SizedBox(height: 18),

                        // 4. Quick Action Cards (Order Water, My Orders, Payment, My Profile)
                        _buildQuickActionCards(context),

                        if (!isGuest) ...[
                          const SizedBox(height: 20),
                          _buildRecentOrderSection(context),
                          const SizedBox(height: 20),
                          _buildQuickInfoSection(context),
                        ],

                        const SizedBox(height: 28),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── 1. Top Header ──────────────────────────────────────────────────────────
  Widget _buildHowToOrderTutorial() {
    return Container(
      key: TutorialTargetKeys.tutorialCard,
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFBEE1F9)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(
                Icons.auto_stories_rounded,
                color: Color(0xFF0878C9),
                size: 25,
              ),
              SizedBox(width: 8),
              Text(
                'Tutorial Guide',
                style: TextStyle(
                  color: Color(0xFF064B87),
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'New to Aqua Clayne? Explore the app through an interactive walkthrough and learn how to use its features step by step.',
            style: TextStyle(
              color: Color(0xFF6F8BA3),
              fontSize: 12.5,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: _openTutorialModal,
              child: Container(
                height: 88,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.22),
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.touch_app_rounded,
                      color: Colors.white,
                      size: 38,
                    ),
                    SizedBox(width: 10),
                    Text(
                      'START TUTORIAL',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _openTutorialModal() {
    InteractiveTutorialOverlay.show(context);
  }

  Widget _buildHeader(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // Left: Branding Logo
        Expanded(
          child: Row(
            children: [
              // Droplet Emblem
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [Color(0xFFEAF7FF), Color(0xFFC7E8FD)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.15),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: ClipOval(
                  child: Image.asset(
                    'assets/images/aqua_clayne_logo.png',
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const Icon(
                      Icons.water_drop_rounded,
                      color: Color(0xFF0878C9),
                      size: 24,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              // Brand Titles
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: const [
                  Text(
                    'AQUA CLAYNE',
                    style: TextStyle(
                      fontFamily: 'serif',
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                      color: Color(0xFF064B87),
                      letterSpacing: 1.1,
                      height: 1.0,
                    ),
                  ),
                  SizedBox(height: 2),
                  Text(
                    '— PURIFIED DRINKING WATER —',
                    style: TextStyle(
                      fontSize: 7.5,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0878C9),
                      letterSpacing: 0.8,
                    ),
                  ),
                  Text(
                    'Expect the Purest',
                    style: TextStyle(
                      fontStyle: FontStyle.italic,
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF2EA9E8),
                      height: 1.1,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        // Right: Cart, Notification Bell & Profile Avatar
        Consumer<NotificationProvider>(
          builder: (context, notifProv, _) {
            final unreadCount = context.watch<AuthProvider>().isAuthenticated
                ? notifProv.unreadCount
                : 0;
            return Row(
              children: [
                Consumer<CartProvider>(
                  builder: (context, cart, _) {
                    // Guest sessions must never display a prior customer's
                    // persisted cart count while session cleanup completes.
                    final cartCount = context.read<AuthProvider>().isAuthenticated
                        ? cart.itemCount
                        : 0;
                    return GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const CartScreen()),
                      );
                    },
                    child: Container(
                      key: TutorialTargetKeys.shoppingCart,
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white,
                        border: Border.all(color: const Color(0xFFD5E7F4)),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF0878C9)
                                .withValues(alpha: 0.08),
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
                              Icons.shopping_cart_outlined,
                              color: Color(0xFF0878C9),
                              size: 22,
                            ),
                          ),
                          if (cartCount > 0)
                            Positioned(
                              top: 4,
                              right: 4,
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
                                  cartCount > 9
                                      ? '9+'
                                      : '$cartCount',
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

                const SizedBox(width: 8),

                // Bell Button
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const NotificationsScreen(),
                      ),
                    );
                  },
                  child: Container(
                    key: TutorialTargetKeys.notificationBell,
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                      border: Border.all(color: const Color(0xFFD5E7F4)),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0878C9)
                              .withValues(alpha: 0.08),
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
                ),

                const SizedBox(width: 8),

                // Profile Avatar Button
                GestureDetector(
                  onTap: () {
                    CustomerShell.showTab(context, 4);
                  },
                  child: Container(
                    key: TutorialTargetKeys.headerProfile,
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                      border: Border.all(color: const Color(0xFFD5E7F4)),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0878C9)
                              .withValues(alpha: 0.08),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: const Center(
                      child: Icon(
                        Icons.person_rounded,
                        color: Color(0xFF0878C9),
                        size: 22,
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }

  // ── 2. Customer Greeting ───────────────────────────────────────────────────
  Widget _buildGreetingSection(String customerName, {required bool isGuest}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          isGuest ? 'Good Evening,' : _getGreeting(),
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: Color(0xFF0878C9),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          customerName,
          style: const TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: Color(0xFF12344D),
            height: 1.15,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          isGuest ? 'Browse our water products and services' : 'Customer',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: Color(0xFF6F8BA3),
          ),
        ),
      ],
    );
  }

  // ── 3. Hero Promotional Banner ─────────────────────────────────────────────
  Widget _buildHeroBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      height: 172,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFDDF2FE), Color(0xFFC7E8FD), Color(0xFFBCE3FC)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFBEE1F9)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.12),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: Stack(
          children: [
            // Left Content
            Positioned(
              left: 16,
              top: 16,
              bottom: 16,
              width: MediaQuery.of(context).size.width * 0.48,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text(
                        'Pure Water,\nHealthier You',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF064B87),
                          height: 1.18,
                        ),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Stay hydrated with\nAqua Clayne.',
                        style: TextStyle(
                          fontSize: 12.5,
                          color: Color(0xFF4A6B82),
                          height: 1.25,
                        ),
                      ),
                    ],
                  ),
                  // Order Now Pill Button
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const WaterRefillServicesScreen(),
                          ),
                        );
                      },
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        key: TutorialTargetKeys.orderNowButton,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                          ),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF0878C9)
                                  .withValues(alpha: 0.25),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: const [
                            Icon(
                              Icons.water_drop_rounded,
                              color: Colors.white,
                              size: 13,
                            ),
                            SizedBox(width: 4),
                            Text(
                              'Order Now',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            SizedBox(width: 3),
                            Icon(
                              Icons.chevron_right_rounded,
                              color: Colors.white,
                              size: 15,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Script Motto text on top right
            Positioned(
              right: 18,
              top: 14,
              child: const Text(
                'Clean\nSafe\nConvenient',
                textAlign: TextAlign.right,
                style: TextStyle(
                  fontStyle: FontStyle.italic,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0878C9),
                  height: 1.15,
                ),
              ),
            ),

            // Right Gallon with Splash Graphic
            Positioned(
              right: -4,
              bottom: -4,
              top: 18,
              child: Image.asset(
                'assets/images/banner_gallon_splash.png',
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Image.asset(
                  'assets/images/aqua_clayne_gallon.png',
                  fit: BoxFit.contain,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── 4. Quick Action Cards ──────────────────────────────────────────────────
  Widget _buildQuickActionCards(BuildContext context) {
    return Row(
      children: [
        // 1. Order Water
        Expanded(
          child: _buildActionCard(
            key: TutorialTargetKeys.orderWaterShortcut,
            title: 'Order Water',
            subtitle: 'Refill your gallons',
            icon: Icons.water_drop_rounded,
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const WaterRefillServicesScreen(),
                ),
              );
            },
          ),
        ),
        const SizedBox(width: 8),

        ...[
          Expanded(
            child: _buildActionCard(
              key: TutorialTargetKeys.myOrdersShortcut,
              title: 'My Orders',
              subtitle: 'Track your orders',
              icon: Icons.calendar_today_rounded,
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const OrdersListScreen())),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: _buildActionCard(
              key: TutorialTargetKeys.paymentShortcut,
              title: 'Payment',
              subtitle: 'Manage payments',
              icon: Icons.account_balance_wallet_rounded,
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const PaymentsScreen())),
            ),
          ),
          const SizedBox(width: 8),
        ],

        // 4. My Profile
        Expanded(
          child: _buildActionCard(
            key: TutorialTargetKeys.myProfileShortcut,
            title: 'My Profile',
            subtitle: 'View & edit info',
            icon: Icons.person_rounded,
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ProfileScreen()),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildActionCard({
    Key? key,
    required String title,
    required String subtitle,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return Material(
      key: key,
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 4),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
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
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: const BoxDecoration(
                  color: Color(0xFFE1F3FE),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Icon(icon, color: const Color(0xFF0878C9), size: 22),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 9, color: Color(0xFF6F8BA3)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── 5. Recent Order Section ────────────────────────────────────────────────
  Widget _buildRecentOrderSection(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Recent Order',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.bold,
                color: Color(0xFF12344D),
              ),
            ),
            GestureDetector(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const OrdersListScreen()),
                );
              },
              child: const Row(
                children: [
                  Text(
                    'View All',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF6F8BA3),
                    ),
                  ),
                  SizedBox(width: 2),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 16,
                    color: Color(0xFF6F8BA3),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Consumer<OrderProvider>(
          builder: (context, orderProv, _) {
            if (orderProv.loading && orderProv.orders.isEmpty) {
              return _buildOrderLoadingPlaceholder();
            }

            final orders = orderProv.orders;
            final recentOrder =
                orderProv.activeOrder ??
                (orders.isNotEmpty ? orders.first : null);

            if (recentOrder == null) {
              return Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFE2F1FC)),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: const BoxDecoration(
                        color: Color(0xFFEAF7FF),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.water_drop_outlined,
                        color: Color(0xFF0878C9),
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: const [
                          Text(
                            'No recent orders yet',
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF12344D),
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Place your customer gallon refill order.',
                            style: TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6F8BA3),
                            ),
                          ),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const WaterRefillServicesScreen(),
                          ),
                        );
                      },
                      child: const Text(
                        'Order Water',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF0878C9),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }

            final isDelivered = recentOrder.status.toUpperCase() == 'DELIVERED';

            return Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE2F1FC)),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // Left thumbnail image
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Center(
                      child: Image.asset(
                        'assets/images/banner_gallon_splash.png',
                        width: 44,
                        height: 44,
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

                  // Middle details
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          recentOrder.orderNumber,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF12344D),
                          ),
                        ),
                        const SizedBox(height: 4),
                        if (isDelivered)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2.5,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFD1FAE5),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Text(
                              'Delivered',
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF059669),
                              ),
                            ),
                          )
                        else
                          StatusBadge(status: recentOrder.status),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(
                              Icons.calendar_today_outlined,
                              size: 11,
                              color: Color(0xFF6F8BA3),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              _formatDate(recentOrder.createdAt),
                              style: const TextStyle(
                                fontSize: 11,
                                color: Color(0xFF6F8BA3),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  // Right View Details Button
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                OrderDetailScreen(orderId: recentOrder.id),
                          ),
                        );
                      },
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF7FF),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: const [
                            Text(
                              'View Details',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF0878C9),
                              ),
                            ),
                            SizedBox(width: 2),
                            Icon(
                              Icons.chevron_right_rounded,
                              size: 14,
                              color: Color(0xFF0878C9),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildOrderLoadingPlaceholder() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2F1FC)),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
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
                  width: 120,
                  height: 14,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEAF7FF),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  width: 80,
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
    );
  }

  // ── 6. Quick Info Section ──────────────────────────────────────────────────
  Widget _buildQuickInfoSection(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Quick Info',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: Color(0xFF12344D),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            // Card 1: Your Balance
            Expanded(
              child: Consumer<CreditProvider>(
                builder: (context, creditProv, _) {
                  final balance = creditProv.account?.balance ?? 0.0;
                  return _buildInfoCard(
                    title: 'Your Balance',
                    value: '₱ ${balance.toStringAsFixed(2)}',
                    icon: Icons.water_drop_rounded,
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const CreditScreen()),
                      );
                    },
                  );
                },
              ),
            ),

            const SizedBox(width: 10),

            // Card 2: Available Gallons
            Expanded(
              child: Consumer<OrderProvider>(
                builder: (context, orderProv, _) {
                  final active = orderProv.activeOrder;
                  final recent = orderProv.orders.isNotEmpty
                      ? orderProv.orders.first
                      : null;
                  final gallonCount =
                      active?.expectedEmptyGallons ??
                      (recent?.expectedEmptyGallons ?? 2);

                  return _buildInfoCard(
                    title: 'Available Gallons',
                    value: '$gallonCount pcs',
                    icon: Icons.inventory_2_rounded,
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const OrdersListScreen(),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildInfoCard({
    required String title,
    required String value,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2F1FC)),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: const BoxDecoration(
                  color: Color(0xFFE1F3FE),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Icon(icon, color: const Color(0xFF0878C9), size: 18),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 10.5,
                        color: Color(0xFF6F8BA3),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      value,
                      style: const TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF12344D),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: Color(0xFF0878C9),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
