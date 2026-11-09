import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_delivery_card.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import '../../shared_widgets/status_badge.dart';
import '../services/navigation_launcher.dart';
import 'delivery_detail_screen.dart';
import 'driver_notifications_screen.dart';
import 'pickup_batches_screen.dart';
import 'delivery_routes_screen.dart';
import 'driver_shell.dart';

class DriverDashboardScreen extends StatefulWidget {
  const DriverDashboardScreen({super.key});

  @override
  State<DriverDashboardScreen> createState() => _DriverDashboardScreenState();
}

class _DriverDashboardScreenState extends State<DriverDashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  Future<void> _loadData() async {
    final prov = context.read<DriverDeliveryProvider>();
    await Future.wait([
      prov.loadAssigned(),
      prov.loadHistory(),
      prov.loadPickupBatches(),
      prov.loadDeliveryRoutes(),
    ]);
  }

  String _greeting() {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final driverName = user?.name.isNotEmpty == true ? user!.name : 'Driver';

    return DriverScaffold(
      body: RefreshIndicator(
        color: DriverColors.primary,
        onRefresh: _loadData,
        child: Consumer<DriverDeliveryProvider>(
          builder: (context, prov, _) {
            // Calculate active delivery (prioritize out_for_delivery, en_route, picked_up, then assigned)
            DeliveryModel? activeDelivery;
            final inProgressList = prov.assigned.where((d) {
              final s = d.status.toLowerCase();
              return s == 'out_for_delivery' ||
                  s == 'en_route_for_pickup' ||
                  s == 'picked_up';
            }).toList();

            if (inProgressList.isNotEmpty) {
              activeDelivery = inProgressList.first;
            } else if (prov.assigned.isNotEmpty) {
              activeDelivery = prov.assigned.first;
            }

            final remainingCount = prov.assigned.length;
            final completedCount = prov.history
                .where((d) => d.status.toLowerCase() == 'delivered')
                .length;
            final totalAssignedCount = remainingCount + completedCount;
            final issuesCount = prov.assigned
                    .where((d) => d.status.toLowerCase() == 'failed')
                    .length +
                prov.history
                    .where((d) => d.status.toLowerCase() == 'failed')
                    .length;

            final hasActiveDelivery = activeDelivery != null &&
                ['out_for_delivery', 'en_route_for_pickup', 'picked_up']
                    .contains(activeDelivery.status.toLowerCase());

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.standard,
                vertical: AppSpacing.standard,
              ),
              children: [
                // ── Top Brand Bar ────────────────────────────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Water drop logo badge
                    Container(
                      // Match the customer-home logo dimensions.
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: DriverColors.primary.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: DriverColors.primary.withValues(alpha: 0.25),
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
                    const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'AQUA CLAYNE',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                            color: DriverColors.primaryDark,
                            letterSpacing: 0.8,
                          ),
                        ),
                        Text(
                          'Driver Dashboard',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: DriverColors.mintAccent,
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),

                    // Driver status pill
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: hasActiveDelivery
                            ? DriverColors.warningLight
                            : DriverColors.mintSoft,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: hasActiveDelivery
                              ? DriverColors.warning.withValues(alpha: 0.4)
                              : DriverColors.border,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.circle,
                            color: hasActiveDelivery
                                ? DriverColors.warning
                                : DriverColors.primary,
                            size: 7,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            hasActiveDelivery ? 'On Delivery' : 'Available',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: hasActiveDelivery
                                  ? DriverColors.warning
                                  : DriverColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),

                    // Notification Bell Button
                    InkWell(
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const DriverNotificationsScreen(),
                        ),
                      ),
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.all(7),
                        decoration: BoxDecoration(
                          color: DriverColors.cardTranslucent,
                          shape: BoxShape.circle,
                          border: Border.all(color: DriverColors.borderLight),
                          boxShadow: const [
                            BoxShadow(
                              color: DriverColors.shadow,
                              blurRadius: 6,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.notifications_outlined,
                          color: DriverColors.primaryDark,
                          size: 19,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),

                    // Profile Avatar Button (direct access to DriverProfileScreen)
                    InkWell(
                      onTap: () => DriverShell.showTab(context, 4),
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: DriverColors.primaryDark,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: DriverColors.mintAccent,
                            width: 1.5,
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: DriverColors.shadow,
                              blurRadius: 6,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Center(
                          child: Text(
                            driverName.isNotEmpty
                                ? driverName[0].toUpperCase()
                                : 'D',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // ── Driver Greeting Card ──────────────────────────────────
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        DriverColors.primaryDark.withValues(alpha: 0.92),
                        DriverColors.primary.withValues(alpha: 0.88),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: const [
                      BoxShadow(
                        color: DriverColors.shadow,
                        blurRadius: 10,
                        offset: Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_greeting()}, $driverName! 👋',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                          letterSpacing: -0.2,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Here are your assigned deliveries for today.',
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.white70,
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.standard),

                // ── Loading state ───────────────────────────────────────
                if (prov.loading && prov.assigned.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: LoadingView(message: 'Loading delivery dashboard...'),
                  )
                else ...[
                  // ── Today's Deliveries Summary Card ─────────────────────
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.standard),
                    decoration: BoxDecoration(
                      color: DriverColors.cardTranslucent,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: DriverColors.borderLight),
                      boxShadow: const [
                        BoxShadow(
                          color: DriverColors.shadow,
                          blurRadius: 10,
                          offset: Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(
                              Icons.analytics_outlined,
                              size: 16,
                              color: DriverColors.primary,
                            ),
                            SizedBox(width: 6),
                            Text(
                              "TODAY'S DELIVERIES",
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: DriverColors.primaryDark,
                                letterSpacing: 0.6,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            _SummaryMetric(
                              label: 'Assigned',
                              value: '$totalAssignedCount',
                              color: DriverColors.primary,
                              icon: Icons.assignment_outlined,
                            ),
                            const SizedBox(width: 10),
                            _SummaryMetric(
                              label: 'Completed',
                              value: '$completedCount',
                              color: DriverColors.success,
                              icon: Icons.check_circle_outlined,
                            ),
                            const SizedBox(width: 10),
                            _SummaryMetric(
                              label: 'Remaining',
                              value: '$remainingCount',
                              color: DriverColors.warning,
                              icon: Icons.pending_actions_outlined,
                            ),
                          ],
                        ),
                        if (issuesCount > 0) ...[
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: DriverColors.warningLight,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: DriverColors.warning
                                    .withValues(alpha: 0.3),
                              ),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.report_problem_outlined,
                                  size: 14,
                                  color: DriverColors.warning,
                                ),
                                const SizedBox(width: 6),
                                Text(
                                  '$issuesCount deliver${issuesCount == 1 ? 'y has' : 'ies have'} reported issues',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: DriverColors.warning,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.standard),

                  // ── Active / Current Delivery Card ──────────────────────
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: hasActiveDelivery
                              ? DriverColors.warning
                              : (activeDelivery != null
                                  ? DriverColors.primary
                                  : DriverColors.textMuted),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Text(
                        'CURRENT ACTIVE DELIVERY',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: DriverColors.primaryDark,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (activeDelivery != null)
                    _ActiveDeliveryCard(
                      delivery: activeDelivery,
                      onRefresh: _loadData,
                    )
                  else
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        vertical: 24,
                        horizontal: 16,
                      ),
                      decoration: BoxDecoration(
                        color: DriverColors.cardTranslucent,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: DriverColors.borderLight),
                        boxShadow: const [
                          BoxShadow(
                            color: DriverColors.shadow,
                            blurRadius: 8,
                            offset: Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: const BoxDecoration(
                              color: DriverColors.mintSoft,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.local_shipping_outlined,
                              color: DriverColors.primary,
                              size: 28,
                            ),
                          ),
                          const SizedBox(height: 10),
                          const Text(
                            'No active delivery',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Your assigned deliveries will appear here.',
                            style: TextStyle(
                              fontSize: 12,
                              color: DriverColors.textSecondary,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: AppSpacing.standard),

                  // ── Fleet Dispatch Operations (2-Driver System) ────────────
                  Row(
                    children: [
                      Expanded(
                        child: _DashboardActionTile(
                          icon: Icons.inventory_2_outlined,
                          title: 'Pickup Batches',
                          subtitle: '${prov.pickupBatches.length} assigned • 30 gal max',
                          color: DriverColors.primary,
                          badge: prov.activeBatchesCount > 0 ? '${prov.activeBatchesCount}' : null,
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const PickupBatchesScreen(),
                            ),
                          ).then((_) => _loadData()),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.compact),
                      Expanded(
                        child: _DashboardActionTile(
                          icon: Icons.alt_route_rounded,
                          title: 'Delivery Routes',
                          subtitle: '${prov.deliveryRoutes.length} assigned • 30 gal max',
                          color: DriverColors.mintAccent,
                          badge: prov.activeRoutesCount > 0 ? '${prov.activeRoutesCount}' : null,
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const DeliveryRoutesScreen(),
                            ),
                          ).then((_) => _loadData()),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.compact),

                  // ── Quick Actions ────────────────────────────────────────
                  Row(
                    children: [
                      Expanded(
                        child: _DashboardActionTile(
                          icon: Icons.local_shipping_outlined,
                          title: 'Assigned Deliveries',
                          subtitle: '$remainingCount active',
                          color: DriverColors.primary,
                          onTap: () => DriverShell.showTab(context, 1),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.compact),
                      Expanded(
                        child: _DashboardActionTile(
                          icon: Icons.history_rounded,
                          title: 'Delivery History',
                          subtitle: '$completedCount completed',
                          color: DriverColors.mintAccent,
                          onTap: () => DriverShell.showTab(context, 2),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.standard),

                  // ── Assigned Deliveries List ────────────────────────────
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'ASSIGNED DELIVERIES',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: DriverColors.primaryDark,
                          letterSpacing: 0.5,
                        ),
                      ),
                      if (prov.assigned.length > 3)
                        TextButton(
                          onPressed: () => DriverShell.showTab(context, 1),
                          style: TextButton.styleFrom(
                            foregroundColor: DriverColors.primary,
                            padding: EdgeInsets.zero,
                          ),
                          child: const Text(
                            'View All >',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  if (prov.assigned.isEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        vertical: 36,
                        horizontal: 20,
                      ),
                      decoration: BoxDecoration(
                        color: DriverColors.cardTranslucent,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: DriverColors.borderLight),
                      ),
                      child: const Column(
                        children: [
                          Icon(
                            Icons.check_circle_outline,
                            color: DriverColors.primary,
                            size: 44,
                          ),
                          SizedBox(height: 10),
                          Text(
                            'No deliveries assigned yet',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.textPrimary,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'When the admin assigns an order to you, it will appear here in real time.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 13,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    ...prov.assigned.take(3).map(
                          (d) => DriverDeliveryCard(
                            deliveryModel: d,
                            onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => DeliveryDetailScreen(id: d.id),
                              ),
                            ).then((_) => _loadData()),
                          ),
                        ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SummaryMetric extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  final IconData icon;

  const _SummaryMetric({
    required this.label,
    required this.value,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(height: 4),
            Text(
              value,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: color,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: DriverColors.textSecondary,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _ActiveDeliveryCard extends StatelessWidget {
  final DeliveryModel delivery;
  final VoidCallback onRefresh;

  const _ActiveDeliveryCard({
    required this.delivery,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final status = delivery.status.toUpperCase();

    return Container(
      decoration: BoxDecoration(
        color: DriverColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: DriverColors.primary.withValues(alpha: 0.4),
          width: 1.5,
        ),
        boxShadow: const [
          BoxShadow(
            color: DriverColors.shadow,
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.standard),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Order Number & Status
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: DriverColors.mintSoft,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      delivery.orderNumber,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: DriverColors.primaryDark,
                      ),
                    ),
                  ),
                  const Spacer(),
                  StatusBadge(status: status, driverStyle: true),
                ],
              ),
              const SizedBox(height: 12),

              // Customer name & Refill quantity
              Row(
                children: [
                  const Icon(
                    Icons.person_pin_circle_outlined,
                    color: DriverColors.primary,
                    size: 20,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          delivery.customerName,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: DriverColors.textPrimary,
                          ),
                        ),
                        if (delivery.customerPhone.isNotEmpty)
                          Text(
                            delivery.customerPhone,
                            style: const TextStyle(
                              fontSize: 12,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: DriverColors.mintSoft,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: DriverColors.borderLight),
                    ),
                    child: Text(
                      '${delivery.expectedGallons} Gallons for Refill',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: DriverColors.primaryDark,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              // Address
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: DriverColors.mintSurface,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: DriverColors.borderLight),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.location_on,
                      size: 16,
                      color: DriverColors.primary,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        delivery.fullAddress,
                        style: const TextStyle(
                          fontSize: 13,
                          color: DriverColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),

              // Payment indicator badge
              if (delivery.isOnlinePay)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: DriverColors.successLight,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: DriverColors.success.withValues(alpha: 0.3)),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.verified, size: 16, color: DriverColors.success),
                      SizedBox(width: 6),
                      Text(
                        'PAID ONLINE (PayMongo)',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: DriverColors.success,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ],
                  ),
                )
              else if (delivery.isCash)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: delivery.isPaid ? DriverColors.successLight : DriverColors.warningLight,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: (delivery.isPaid ? DriverColors.success : DriverColors.warning)
                          .withValues(alpha: 0.3),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.payments_outlined,
                        size: 16,
                        color: delivery.isPaid ? DriverColors.success : DriverColors.warning,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        delivery.isPaid
                            ? 'Payment: Cash (Collected)'
                            : 'Cash to Collect: ₱${delivery.totalAmount.toStringAsFixed(2)}',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: delivery.isPaid ? DriverColors.success : DriverColors.warning,
                        ),
                      ),
                    ],
                  ),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: DriverColors.infoLight,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: DriverColors.info.withValues(alpha: 0.3)),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.credit_card, size: 16, color: DriverColors.info),
                      SizedBox(width: 6),
                      Text(
                        'Payment: Approved Credit',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: DriverColors.info,
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 14),

              // Actions: Navigate & View Delivery (min 50px touch targets for drivers)
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => NavigationLauncher.launchNavigation(
                        context: context,
                        latitude: delivery.latitude,
                        longitude: delivery.longitude,
                        address: delivery.fullAddress,
                        customerName: delivery.customerName,
                        deliveryId: delivery.id,
                        deliveryStatus: delivery.status,
                      ),
                      icon: const Icon(Icons.navigation_outlined, size: 18),
                      label: const Text(
                        'Navigate',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: DriverColors.primary,
                        side: const BorderSide(color: DriverColors.primary, width: 1.5),
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => DeliveryDetailScreen(id: delivery.id),
                        ),
                      ).then((_) => onRefresh()),
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      label: const Text(
                        'View Delivery',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: DriverColors.primary,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
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
}

class _DashboardActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;
  final String? badge;

  const _DashboardActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
    this.badge,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.compact),
          decoration: BoxDecoration(
            color: DriverColors.cardTranslucent,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: DriverColors.borderLight),
            boxShadow: const [
              BoxShadow(
                color: DriverColors.shadow,
                blurRadius: 6,
                offset: Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: DriverColors.textPrimary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 11,
                        color: DriverColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              if (badge != null) ...[
                const SizedBox(width: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    badge!,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
