import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_bottom_navigation.dart';
import '../../shared_widgets/lazy_indexed_stack.dart';
import '../../core/screens/chat_screen.dart';
import 'driver_dashboard_screen.dart';
import 'my_deliveries_screen.dart';
import 'delivery_history_screen.dart';
import 'driver_profile_screen.dart';

class DriverShell extends StatefulWidget {
  final int initialIndex;
  const DriverShell({super.key, this.initialIndex = 0});

  static void showTab(BuildContext context, int index) {
    final state = context.findAncestorStateOfType<_DriverShellState>();
    if (state != null) {
      state.setIndex(index);
    } else {
      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => DriverShell(initialIndex: index)),
        (route) => false,
      );
    }
  }

  @override
  State<DriverShell> createState() => _DriverShellState();
}

class _DriverShellState extends State<DriverShell> {
  late int _index;
  final List<int> _tabHistory = [0];
  DateTime? _lastBackPressTime;

  void setIndex(int index) {
    if (_index != index && mounted) {
      if (index == 0) {
        _tabHistory.clear();
        _tabHistory.add(0);
      } else {
        _tabHistory.remove(index);
        _tabHistory.add(index);
      }
      setState(() => _index = index);
    }
  }

  final _screens = const [
    DriverDashboardScreen(),
    MyDeliveriesScreen(),
    DeliveryHistoryScreen(),
    ChatScreen(showBottomNavigation: false),
    DriverProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _tabHistory.clear();
    _tabHistory.add(_index);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;

      // ── Start Supabase Realtime for driver-facing data ──────────
      // DriverDeliveryProvider subscribes to 'delivery' and 'order' signals.
      context.read<DriverDeliveryProvider>().startRealtime();

      // ── Initial data load ───────────────────────────────────────
    });
  }

  @override
  void dispose() {
    // Stop realtime subscriptions on logout / shell unmount
    try {
      context.read<DriverDeliveryProvider>().stopRealtime();
    } catch (_) {}
    super.dispose();
  }

  void _handleBackPress() {
    // 1. If the driver navigated between tabs, go back to previous tab
    if (_tabHistory.length > 1) {
      setState(() {
        _tabHistory.removeLast();
        _index = _tabHistory.last;
      });
      return;
    }

    // 2. Fallback to Dashboard tab if currently on another tab without history
    if (_index != 0) {
      setState(() {
        _tabHistory.clear();
        _tabHistory.add(0);
        _index = 0;
      });
      return;
    }

    // 3. At true root (Dashboard with no history): confirm exit on double back press
    final now = DateTime.now();
    if (_lastBackPressTime == null ||
        now.difference(_lastBackPressTime!) > const Duration(seconds: 2)) {
      _lastBackPressTime = now;
      ScaffoldMessenger.of(context).removeCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Press back again to exit Aqua Clayne Driver'),
          duration: Duration(seconds: 2),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } else {
      SystemNavigator.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _handleBackPress();
      },
      child: Theme(
        data: DriverColors.theme(Theme.of(context)),
        child: Scaffold(
          body: LazyIndexedStack(index: _index, children: _screens),
          bottomNavigationBar: DriverBottomNavigation(
            currentIndex: _index,
            onTap: setIndex,
          ),
        ),
      ),
    );
  }
}
