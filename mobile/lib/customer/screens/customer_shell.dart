import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/payment_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../customer/providers/address_provider.dart';
import '../../customer/providers/credit_provider.dart';
import '../../customer/providers/complaint_provider.dart';
import '../../shared_widgets/lazy_indexed_stack.dart';
import '../../core/screens/chat_screen.dart';
import '../widgets/interactive_tutorial_overlay.dart';
import 'customer_home_screen.dart';
import 'orders_list_screen.dart';
import 'payments_screen.dart';
import 'profile_screen.dart';
import '../../driver/screens/driver_shell.dart';

class CustomerShell extends StatefulWidget {
  final int initialIndex;
  const CustomerShell({super.key, this.initialIndex = 0});

  static final ValueNotifier<int> _selectedTab = ValueNotifier<int>(0);

  /// Returns to the existing shell and selects a tab, preserving providers and
  /// realtime subscriptions while leaving standalone screens.
  static void showTab(BuildContext context, int index) {
    _selectedTab.value = index.clamp(0, 4).toInt();
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  State<CustomerShell> createState() => _CustomerShellState();
}

class _CustomerShellState extends State<CustomerShell> {
  int _index = 0;
  final List<int> _tabHistory = [0];
  DateTime? _lastBackPressTime;
  bool _isHandlingBack = false;
  final List<GlobalKey<NavigatorState>> _tabNavigators = List.generate(
    5,
    (_) => GlobalKey<NavigatorState>(),
  );

  static const _screens = [
    CustomerHomeScreen(),
    OrdersListScreen(),
    PaymentsScreen(showStandaloneNavigation: false),
    ChatScreen(),
    ProfileScreen(showStandaloneNavigation: false),
  ];

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex.clamp(0, _screens.length - 1).toInt();
    _tabHistory.clear();
    _tabHistory.add(_index);
    CustomerShell._selectedTab.value = _index;
    CustomerShell._selectedTab.addListener(_syncSelectedTab);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final auth = context.read<AuthProvider>();
      if (auth.user?.isDriver == true) {
        if (!mounted) return;
        Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const DriverShell()),
          (route) => false,
        );
        return;
      }
      if (!auth.isAuthenticated) {
        // Providers outlive this shell, so wipe the prior customer's data
        // before displaying the guest storefront.
        context.read<NotificationProvider>().clearSessionData();
        context.read<OrderProvider>().clearSessionData();
        context.read<PaymentProvider>().clearSessionData();
        context.read<AddressProvider>().clearSessionData();
        context.read<CreditProvider>().clearSessionData();
        context.read<ComplaintProvider>().clearSessionData();
        await context.read<CartProvider>().clearForGuest();
        return;
      }
      if (!mounted) return;

      // ── Start Supabase Realtime for customer-facing data ────────
      context.read<OrderProvider>().startRealtime();
      context.read<NotificationProvider>().startRealtime();
      context.read<PaymentProvider>().startRealtime();

      // ── Initial data loads ──────────────────────────────────────
    });
  }

  @override
  void dispose() {
    CustomerShell._selectedTab.removeListener(_syncSelectedTab);
    try {
      context.read<OrderProvider>().stopRealtime();
      context.read<NotificationProvider>().stopRealtime();
      context.read<PaymentProvider>().stopRealtime();
    } catch (_) {}
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    if (user?.isDriver == true) {
      return const SizedBox.shrink();
    }
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _handleBackPress();
      },
      child: Scaffold(
        // Keep the shared bottom bar in the shell while each tab pushes pages
        // onto its own route stack.
        body: LazyIndexedStack(
          index: _index,
          children: List.generate(_screens.length, (index) {
            return Navigator(
              key: _tabNavigators[index],
              onGenerateRoute: (settings) => MaterialPageRoute<void>(
                settings: settings,
                builder: (_) => _screens[index],
              ),
            );
          }),
        ),
        bottomNavigationBar: _buildCustomBottomNavBar(),
      ),
    );
  }

  Widget _buildCustomBottomNavBar() {
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
                key: TutorialTargetKeys.homeNavTab,
                index: 0,
                label: 'Home',
                icon: Icons.home_outlined,
                activeIcon: Icons.home_rounded,
              ),
              _buildNavItem(
                key: TutorialTargetKeys.ordersNavTab,
                index: 1,
                label: 'Orders',
                icon: Icons.article_outlined,
                activeIcon: Icons.article_rounded,
              ),
              _buildNavItem(
                key: TutorialTargetKeys.paymentsNavTab,
                index: 2,
                label: 'Payments',
                icon: Icons.account_balance_wallet_outlined,
                activeIcon: Icons.account_balance_wallet_rounded,
              ),
              _buildNavItem(
                key: TutorialTargetKeys.chatNavTab,
                index: 3,
                label: 'Chat',
                icon: Icons.chat_bubble_outline_rounded,
                activeIcon: Icons.chat_bubble_rounded,
              ),
              _buildNavItem(
                key: TutorialTargetKeys.profileNavTab,
                index: 4,
                label: 'Profile',
                icon: Icons.person_outline_rounded,
                activeIcon: Icons.person_rounded,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    Key? key,
    required int index,
    required String label,
    required IconData icon,
    required IconData activeIcon,
  }) {
    final isActive = _index == index;

    return Expanded(
      child: Material(
        key: key,
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _selectTab(index),
          splashColor: const Color(0xFF0878C9).withValues(alpha: 0.1),
          highlightColor: Colors.transparent,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                isActive ? activeIcon : icon,
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
              // Small active indicator/underline matching reference image
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

  void _handleBackPress() {
    // 1. Pop nested routes within the current active tab
    final currentNav = _tabNavigators[_index].currentState;
    if (currentNav != null && currentNav.canPop()) {
      currentNav.pop();
      return;
    }

    // 2. Return to the previously visited tab in history
    if (_tabHistory.length > 1) {
      _isHandlingBack = true;
      try {
        _tabHistory.removeLast();
        final prevTab = _tabHistory.last;
        CustomerShell._selectedTab.value = prevTab;
        setState(() => _index = prevTab);
      } finally {
        _isHandlingBack = false;
      }
      return;
    }

    // 3. Fallback to Home tab if currently on another tab without history
    if (_index != 0) {
      _isHandlingBack = true;
      try {
        _tabHistory.clear();
        _tabHistory.add(0);
        CustomerShell._selectedTab.value = 0;
        setState(() => _index = 0);
      } finally {
        _isHandlingBack = false;
      }
      return;
    }

    // 4. At true root (Home tab with no history): confirm exit on double back press
    final now = DateTime.now();
    if (_lastBackPressTime == null ||
        now.difference(_lastBackPressTime!) > const Duration(seconds: 2)) {
      _lastBackPressTime = now;
      ScaffoldMessenger.of(context).removeCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Press back again to exit Aqua Clayne'),
          duration: Duration(seconds: 2),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } else {
      SystemNavigator.pop();
    }
  }

  void _syncSelectedTab() {
    final newIndex = CustomerShell._selectedTab.value;
    if (!mounted) return;
    if (_index == newIndex) {
      if (!_isHandlingBack) {
        _tabNavigators[newIndex].currentState?.popUntil((route) => route.isFirst);
      }
      return;
    }
    if (!_isHandlingBack) {
      if (newIndex == 0) {
        _tabHistory.clear();
        _tabHistory.add(0);
      } else {
        _tabHistory.remove(newIndex);
        _tabHistory.add(newIndex);
      }
      _tabNavigators[newIndex].currentState?.popUntil((route) => route.isFirst);
    }
    setState(() => _index = newIndex);
  }

  void _selectTab(int index) {
    if (_index == index) {
      _tabNavigators[index].currentState?.popUntil((route) => route.isFirst);
    } else {
      if (index == 0) {
        _tabHistory.clear();
        _tabHistory.add(0);
      } else {
        _tabHistory.remove(index);
        _tabHistory.add(index);
      }
      setState(() => _index = index);
      if (CustomerShell._selectedTab.value != index) {
        CustomerShell._selectedTab.value = index;
      }
    }
  }
}
