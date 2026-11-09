import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/app_card.dart';
import '../../shared_widgets/status_badge.dart';

class RefillQueueDriverScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const RefillQueueDriverScreen({super.key, required this.delivery});

  @override
  State<RefillQueueDriverScreen> createState() =>
      _RefillQueueDriverScreenState();
}

class _RefillQueueDriverScreenState extends State<RefillQueueDriverScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DriverDeliveryProvider>().loadDetail(widget.delivery.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DriverColors.background,
      appBar: AppBar(
        title: const Text('Refill Queue'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => context
                .read<DriverDeliveryProvider>()
                .loadDetail(widget.delivery.id),
          ),
        ],
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (_, prov, _) {
          final detail = prov.detail ?? widget.delivery;
          final orderMap =
              (detail.raw['order'] as Map<String, dynamic>?) ?? {};
          final queue =
              orderMap['queue'] as Map<String, dynamic>?;

          return ListView(
            padding: const EdgeInsets.all(AppSpacing.standard),
            children: [
              // Order card
              AppCard(
                child: Row(children: [
                  const Icon(Icons.water_drop,
                      color: DriverColors.primary, size: 20),
                  const SizedBox(width: AppSpacing.small),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(detail.orderNumber,
                            style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 14)),
                        Text(
                            '${detail.expectedGallons} gallons — ${detail.customerName}',
                            style: const TextStyle(
                                fontSize: 12,
                                color: DriverColors.textSecondary)),
                      ],
                    ),
                  ),
                  StatusBadge(status: detail.status, driverStyle: true),
                ]),
              ),

              // Queue position
              if (queue != null) ...[
                AppCard(
                  color: DriverColors.infoLight,
                  child: Column(children: [
                    const Text('Queue Position',
                        style: TextStyle(
                            fontSize: 13, color: DriverColors.textSecondary)),
                    const SizedBox(height: AppSpacing.small),
                    Text(
                      '#${queue['position'] ?? queue['queue_number'] ?? '-'}',
                      style: const TextStyle(
                          fontSize: 52,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primary),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Queue ${queue['queue_number'] ?? ''}',
                      style: const TextStyle(
                          fontSize: 13, color: DriverColors.textSecondary),
                    ),
                    const SizedBox(height: AppSpacing.compact),
                    StatusBadge(
                        status:
                            queue['status']?.toString() ?? 'WAITING',
                        driverStyle: true),
                  ]),
                ),
              ] else ...[
                AppCard(
                  color: AppColors.warningLight,
                  child: Column(children: [
                    const Icon(Icons.hourglass_empty,
                        color: AppColors.warning, size: 36),
                    const SizedBox(height: AppSpacing.small),
                    const Text('Waiting for queue assignment',
                        style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: DriverColors.textPrimary)),
                    const SizedBox(height: 4),
                    const Text(
                      'The station will add this order to the refill queue after inspecting the gallons.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          fontSize: 12,
                          color: DriverColors.textSecondary,
                          height: 1.4),
                    ),
                  ]),
                ),
              ],

              // Refill timeline
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Refill Progress',
                        style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600)),
                    const SizedBox(height: AppSpacing.standard),
                    ..._buildRefillTimeline(detail.status),
                  ],
                ),
              ),

              // Note — driver cannot change queue
              Container(
                padding: const EdgeInsets.all(AppSpacing.compact),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: DriverColors.divider),
                ),
                child: const Row(children: [
                  Icon(Icons.lock_outline,
                      color: DriverColors.textSecondary, size: 16),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Queue position is managed by station staff only.',
                      style: TextStyle(
                          fontSize: 12, color: DriverColors.textSecondary),
                    ),
                  ),
                ]),
              ),
            ],
          );
        },
      ),
    );
  }

  List<Widget> _buildRefillTimeline(String currentStatus) {
    const steps = [
      ('RECEIVED_AT_STATION', 'Received at Station'),
      ('WAITING_IN_QUEUE', 'In Refill Queue'),
      ('REFILLING', 'Refilling'),
      ('READY_FOR_DELIVERY', 'Ready for Delivery'),
    ];

    final statusOrder = steps.map((s) => s.$1).toList();
    final currentIdx = statusOrder.indexOf(currentStatus.toUpperCase());

    return List.generate(steps.length, (i) {
      final (code, label) = steps[i];
      final isDone = i < currentIdx;
      final isCurrent = i == currentIdx;
      final isLast = i == steps.length - 1;
      final color = isCurrent
                          ? DriverColors.statusColor(code)
          : isDone
              ? AppColors.success
              : AppColors.disabled;

      return IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              width: 24,
              child: Column(children: [
                Container(
                  width: 20,
                  height: 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: isDone || isCurrent
                        ? color
                        : Colors.transparent,
                    border: Border.all(color: color, width: 2),
                  ),
                  child: isDone
                      ? const Icon(Icons.check,
                          color: Colors.white, size: 12)
                      : isCurrent
                          ? Container(
                              margin: const EdgeInsets.all(3),
                              decoration: BoxDecoration(
                                  shape: BoxShape.circle, color: color),
                            )
                          : null,
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                        width: 2,
                        color: isDone
                            ? AppColors.success
                            : DriverColors.divider),
                  ),
              ]),
            ),
            const SizedBox(width: AppSpacing.compact),
            Expanded(
              child: Padding(
                padding:
                    EdgeInsets.only(bottom: isLast ? 0 : AppSpacing.standard),
                child: Text(label,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: isCurrent
                            ? FontWeight.w600
                            : FontWeight.normal,
                        color: isCurrent
                            ? color
                            : isDone
                                ? DriverColors.textPrimary
                                : DriverColors.textHint)),
              ),
            ),
          ],
        ),
      );
    });
  }
}
