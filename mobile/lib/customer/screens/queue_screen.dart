import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/queue_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../shared_widgets/loading_view.dart';
import '../../shared_widgets/status_badge.dart';
import '../../shared_widgets/app_card.dart';
import 'order_detail_screen.dart';
import 'catalog_screen.dart';

class QueueScreen extends StatefulWidget {
  const QueueScreen({super.key});

  @override
  State<QueueScreen> createState() => _QueueScreenState();
}

class _QueueScreenState extends State<QueueScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final op = context.read<OrderProvider>();
    final qp = context.read<QueueProvider>();
    await op.loadOrders();
    if (!mounted) return;
    final active = op.activeOrder;
    if (active != null) {
      await qp.loadForOrder(active.id);
    } else {
      await qp.loadActiveQueue();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('My Queue'),
        actions: [
          Container(
            margin: const EdgeInsets.symmetric(vertical: 12),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.infoLight,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.divider),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    color: AppColors.success,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 5),
                const Text(
                  'Station Open',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh Queue',
            onPressed: _load,
          ),
        ],
      ),
      body: Consumer2<OrderProvider, QueueProvider>(
        builder: (context, op, qp, _) {
          if (op.loading || qp.loading) {
            return const LoadingView(message: 'Checking queue status...');
          }

          final activeOrder = op.activeOrder;
          final inQueue = qp.inQueue ||
              (activeOrder != null &&
                  [
                    'WAITING_IN_QUEUE',
                    'REFILLING',
                    'READY_FOR_DELIVERY',
                  ].contains(activeOrder.status.toUpperCase()));

          // Case 1: No active order and no queue entry
          if (activeOrder == null && !qp.inQueue && qp.position == 0) {
            return RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.15),
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.standard),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 88,
                            height: 88,
                            decoration: BoxDecoration(
                              color: AppColors.infoLight,
                              shape: BoxShape.circle,
                              border: Border.all(color: AppColors.divider),
                            ),
                            child: const Icon(
                              Icons.hourglass_empty_rounded,
                              size: 44,
                              color: AppColors.primary,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.standard),
                          const Text(
                            'You\'re not currently in a queue.',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                              color: AppColors.textPrimary,
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: AppSpacing.compact),
                          const Text(
                            'Place a refill order to track your customer-owned container in real time.',
                            style: TextStyle(
                              fontSize: 13,
                              color: AppColors.textSecondary,
                              height: 1.4,
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: AppSpacing.section),
                          SizedBox(
                            width: 200,
                            height: 48,
                            child: ElevatedButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const CatalogScreen(),
                                ),
                              ),
                              icon: const Icon(Icons.water_drop, color: Colors.white),
                              label: const Text(
                                'Order Water',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 15,
                                  color: Colors.white,
                                ),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
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
                  ),
                ],
              ),
            );
          }

          // Case 2: Order exists, but empty gallons are not yet intake-verified at station
          if (!inQueue && activeOrder != null) {
            return RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.standard),
                children: [
                  _buildOrderCard(context, activeOrder),
                  const SizedBox(height: AppSpacing.standard),
                  _buildPreQueueStatusCard(activeOrder.status),
                  const SizedBox(height: AppSpacing.standard),
                  _buildIntakeWorkflowGuide(),
                ],
              ),
            );
          }

          // Case 3: In Active Refill Queue
          final currentPos = qp.position > 0 ? qp.position : 1;
          final waitMins = qp.estimatedWaitMinutes > 0
              ? qp.estimatedWaitMinutes
              : (currentPos == 1 ? 5 : (currentPos - 1) * 15);
          final orderNumber = activeOrder?.orderNumber ??
              (qp.orderNumber.isNotEmpty ? qp.orderNumber : 'Order');
          final orderId = activeOrder?.id ?? qp.queueInfo?['order_id']?.toString() ?? '';
          final gallonCount = activeOrder?.expectedEmptyGallons ??
              (qp.gallons > 0 ? qp.gallons : 1);
          final currentStatus = activeOrder?.status.toUpperCase() ??
              qp.status.toUpperCase();

          return RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.standard),
              children: [
                // 1. Hero Queue Card
                _buildHeroQueueCard(
                  position: currentPos,
                  waitMinutes: waitMins,
                  queueNumber: qp.queueNumber,
                  isRefilling: currentStatus == 'REFILLING',
                ),

                const SizedBox(height: AppSpacing.standard),

                // 2. Current Order Card
                _buildQueueOrderCard(
                  context: context,
                  orderId: orderId,
                  orderNumber: orderNumber,
                  gallonCount: gallonCount,
                  orderStatus: currentStatus,
                ),

                const SizedBox(height: AppSpacing.standard),

                // 3. 4-Step Visual Progress Stepper
                _buildRefillStepper(currentStatus),

                const SizedBox(height: AppSpacing.standard),

                // 4. Refill Process Guide
                _buildRefillProcessInfoCard(),
              ],
            ),
          );
        },
      ),
    );
  }

  // ── Hero Queue Card ────────────────────────────────────────────────────────
  Widget _buildHeroQueueCard({
    required int position,
    required int waitMinutes,
    required String queueNumber,
    required bool isRefilling,
  }) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF064B87), Color(0xFF0878C9)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      padding: const EdgeInsets.all(AppSpacing.standard),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'LIVE REFILL QUEUE',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                ],
              ),
              if (queueNumber.isNotEmpty)
                Text(
                  queueNumber,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.standard),
          Text(
            '#$position',
            style: const TextStyle(
              fontSize: 64,
              fontWeight: FontWeight.w900,
              color: Colors.white,
              height: 1.0,
            ),
          ),
          const SizedBox(height: AppSpacing.compact),
          Text(
            position <= 1
                ? (isRefilling
                    ? 'Your container is being refilled right now!'
                    : 'You\'re next in line for refilling!')
                : '${position - 1} container${position - 1 > 1 ? 's' : ''} ahead of you',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w500,
              color: Colors.white,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: AppSpacing.standard),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.timer_outlined, size: 16, color: Colors.white),
                const SizedBox(width: 6),
                Text(
                  'Estimated wait: ~$waitMinutes mins',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── Queue Order Card ───────────────────────────────────────────────────────
  Widget _buildQueueOrderCard({
    required BuildContext context,
    required String orderId,
    required String orderNumber,
    required int gallonCount,
    required String orderStatus,
  }) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                orderNumber,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                  color: AppColors.textPrimary,
                ),
              ),
              StatusBadge(status: orderStatus),
            ],
          ),
          const SizedBox(height: AppSpacing.compact),
          Row(
            children: [
              const Icon(Icons.water_drop, color: AppColors.primary, size: 16),
              const SizedBox(width: 6),
              Text(
                '$gallonCount × 5-Gallon Container${gallonCount > 1 ? 's' : ''}',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
          if (orderId.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.standard),
            SizedBox(
              width: double.infinity,
              height: 42,
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => OrderDetailScreen(orderId: orderId),
                  ),
                ),
                icon: const Icon(Icons.receipt_long, size: 16),
                label: const Text('View Order Details'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.primary,
                  side: const BorderSide(color: AppColors.primary),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ── 4-Step Refill Progress Tracker ─────────────────────────────────────────
  Widget _buildRefillStepper(String currentStatus) {
    int activeStep = 1; // 0: Intake, 1: Queue, 2: Refilling, 3: Ready
    if (currentStatus == 'READY_FOR_DELIVERY' ||
        currentStatus == 'OUT_FOR_DELIVERY' ||
        currentStatus == 'DELIVERED') {
      activeStep = 3;
    } else if (currentStatus == 'REFILLING') {
      activeStep = 2;
    } else if (currentStatus == 'WAITING_IN_QUEUE' ||
        currentStatus == 'WAITING' ||
        currentStatus == 'IN_PROGRESS') {
      activeStep = 1;
    }

    final steps = [
      {'title': 'Intake Verified', 'icon': Icons.check_circle_outline},
      {'title': 'In Queue', 'icon': Icons.format_list_numbered},
      {'title': 'Refilling', 'icon': Icons.water_drop},
      {'title': 'Ready for Delivery', 'icon': Icons.local_shipping_outlined},
    ];

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Refill Progress',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 15,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: AppSpacing.standard),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: List.generate(steps.length * 2 - 1, (index) {
              if (index.isOdd) {
                final lineStepIndex = index ~/ 2;
                final isDone = lineStepIndex < activeStep;
                return Expanded(
                  child: Container(
                    margin: const EdgeInsets.only(top: 14),
                    height: 3,
                    color: isDone ? AppColors.primary : AppColors.divider,
                  ),
                );
              }
              final stepIndex = index ~/ 2;
              final isPassed = stepIndex < activeStep;
              final isCurrent = stepIndex == activeStep;
              final step = steps[stepIndex];

              return SizedBox(
                width: 68,
                child: Column(
                  children: [
                    Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: isPassed
                            ? AppColors.primary
                            : isCurrent
                                ? AppColors.infoLight
                                : AppColors.cardBackground,
                        border: Border.all(
                          color: isPassed || isCurrent
                              ? AppColors.primary
                              : AppColors.divider,
                          width: isCurrent ? 2 : 1,
                        ),
                      ),
                      child: Center(
                        child: isPassed
                            ? const Icon(Icons.check, size: 16, color: Colors.white)
                            : Icon(
                                step['icon'] as IconData,
                                size: 14,
                                color: isCurrent
                                    ? AppColors.primary
                                    : AppColors.textSecondary,
                              ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      step['title'] as String,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight:
                            isCurrent ? FontWeight.bold : FontWeight.normal,
                        color: isCurrent
                            ? AppColors.primary
                            : isPassed
                                ? AppColors.textPrimary
                                : AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              );
            }),
          ),
        ],
      ),
    );
  }

  // ── Pre-Queue Status Card ──────────────────────────────────────────────────
  Widget _buildOrderCard(BuildContext context, dynamic order) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                order.orderNumber,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                  color: AppColors.textPrimary,
                ),
              ),
              StatusBadge(status: order.status),
            ],
          ),
          const SizedBox(height: AppSpacing.compact),
          Row(
            children: [
              const Icon(Icons.inventory_2_outlined,
                  color: AppColors.primary, size: 16),
              const SizedBox(width: 6),
              Text(
                '${order.expectedEmptyGallons} Gallon Container${order.expectedEmptyGallons > 1 ? 's' : ''}',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.standard),
          SizedBox(
            width: double.infinity,
            height: 42,
            child: OutlinedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => OrderDetailScreen(orderId: order.id),
                ),
              ),
              icon: const Icon(Icons.receipt_long, size: 16),
              label: const Text('View Order Details'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primary,
                side: const BorderSide(color: AppColors.primary),
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

  Widget _buildPreQueueStatusCard(String status) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.warningLight.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
      ),
      padding: const EdgeInsets.all(AppSpacing.standard),
      child: Column(
        children: [
          const Icon(Icons.hourglass_top_rounded,
              color: AppColors.warning, size: 38),
          const SizedBox(height: AppSpacing.compact),
          const Text(
            'Awaiting Station Intake',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _getPreQueueExplanation(status),
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  String _getPreQueueExplanation(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return 'Your order has been submitted. Station staff will confirm and assign container pickup.';
      case 'CONFIRMED':
        return 'Your order is confirmed. A driver is being scheduled for container collection.';
      case 'ASSIGNED':
        return 'A driver has been assigned and is heading to collect your empty gallons.';
      case 'PICKED_UP':
        return 'Driver has collected your empty containers and is transporting them to the station.';
      case 'RECEIVED_AT_STATION':
        return 'Containers arrived at station. Our team is conducting physical inspection before queue entry.';
      default:
        return 'Your customer-owned gallons will enter the live refill queue immediately after station inspection.';
    }
  }

  Widget _buildIntakeWorkflowGuide() {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.shield_outlined, color: AppColors.primary, size: 18),
              SizedBox(width: 8),
              Text(
                'Customer-Owned Container Policy',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.compact),
          const Text(
            'To protect water purity and customer safety, all empty gallons are checked for cracks, structural integrity, and cleanliness prior to entering the automated refill queue.',
            style: TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRefillProcessInfoCard() {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.verified_outlined, color: AppColors.primary, size: 18),
              SizedBox(width: 8),
              Text(
                'AQUA CLAYNE Standards',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.compact),
          _buildProcessStep(
            '1. Sanitization',
            '3-stage container washing with food-grade sanitizing wash.',
          ),
          const Divider(height: 16, color: AppColors.divider),
          _buildProcessStep(
            '2. Multi-Stage Refill',
            'Purified water passed through Reverse Osmosis, UV, and Ozone.',
          ),
          const Divider(height: 16, color: AppColors.divider),
          _buildProcessStep(
            '3. Sealed Delivery',
            'Capped with tamper-evident seal and dispatched directly to your address.',
          ),
        ],
      ),
    );
  }

  Widget _buildProcessStep(String title, String desc) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 13,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          desc,
          style: const TextStyle(
            fontSize: 12,
            color: AppColors.textSecondary,
          ),
        ),
      ],
    );
  }
}
