import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/app_card.dart';
import '../../shared_widgets/status_badge.dart';
import 'ready_for_delivery_screen.dart';

class RefillStatusScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const RefillStatusScreen({super.key, required this.delivery});

  @override
  State<RefillStatusScreen> createState() => _RefillStatusScreenState();
}

class _RefillStatusScreenState extends State<RefillStatusScreen> {
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
        title: const Text('Refill Status'),
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

          return ListView(
            padding: const EdgeInsets.all(AppSpacing.standard),
            children: [
              // Status banner
              _StatusBanner(status: detail.status),
              const SizedBox(height: AppSpacing.standard),

              // Order info
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Text(detail.orderNumber,
                          style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold)),
                      const Spacer(),
                      StatusBadge(status: detail.status, driverStyle: true),
                    ]),
                    const SizedBox(height: 4),
                    Text(
                      '${detail.expectedGallons} gallons — ${detail.customerName}',
                      style: const TextStyle(
                          fontSize: 13, color: DriverColors.textSecondary),
                    ),
                  ],
                ),
              ),

              // Progress timeline
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Progress',
                        style: TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w600)),
                    const SizedBox(height: AppSpacing.standard),
                    ..._steps(detail.status),
                  ],
                ),
              ),

              // Navigate to Ready screen when ready
              if (detail.status.toUpperCase() == 'READY_FOR_DELIVERY')
                ElevatedButton.icon(
                  onPressed: () => Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          ReadyForDeliveryScreen(delivery: detail),
                    ),
                  ),
                  icon: const Icon(Icons.check_circle_outline),
                  label: const Text('Collect Filled Gallons'),
                ),
            ],
          );
        },
      ),
    );
  }

  List<Widget> _steps(String currentStatus) {
    const steps = [
      ('RECEIVED_AT_STATION', 'Received at Station'),
      ('WAITING_IN_QUEUE', 'In Queue'),
      ('REFILLING', 'Refilling'),
      ('READY_FOR_DELIVERY', 'Ready for Delivery'),
      ('OUT_FOR_DELIVERY', 'Out for Delivery'),
      ('DELIVERED', 'Delivered'),
      ('COMPLETED', 'Completed'),
    ];

    final idx = steps.indexWhere(
        (s) => s.$1 == currentStatus.toUpperCase());

    return List.generate(steps.length, (i) {
      final (code, label) = steps[i];
      final done = i < idx;
      final current = i == idx;
      final last = i == steps.length - 1;
      final color = current
          ? DriverColors.statusColor(code)
          : done
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
                    color: done || current ? color : Colors.transparent,
                    border: Border.all(color: color, width: 2),
                  ),
                  child: done
                      ? const Icon(Icons.check,
                          color: Colors.white, size: 12)
                      : current
                          ? Container(
                              margin: const EdgeInsets.all(3),
                              decoration: BoxDecoration(
                                  shape: BoxShape.circle, color: color),
                            )
                          : null,
                ),
                if (!last)
                  Expanded(
                    child: Container(
                        width: 2,
                        color: done
                            ? AppColors.success
                            : DriverColors.divider),
                  ),
              ]),
            ),
            const SizedBox(width: AppSpacing.compact),
            Expanded(
              child: Padding(
                padding: EdgeInsets.only(
                    bottom: last ? 0 : AppSpacing.standard, top: 1),
                child: Text(label,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight:
                            current ? FontWeight.w600 : FontWeight.normal,
                        color: current
                            ? color
                            : done
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

class _StatusBanner extends StatelessWidget {
  final String status;
  const _StatusBanner({required this.status});

  @override
  Widget build(BuildContext context) {
    final upper = status.toUpperCase();
    final color = DriverColors.statusColor(upper);

    final (icon, message) = switch (upper) {
      'WAITING_IN_QUEUE' => (
          Icons.hourglass_top,
          'Your order is waiting in the refill queue.'
        ),
      'REFILLING' => (
          Icons.water_drop,
          'Your order is currently being refilled!'
        ),
      'READY_FOR_DELIVERY' => (
          Icons.check_circle,
          'Gallons are ready! Proceed to collect them.'
        ),
      _ => (Icons.info_outline, 'Tracking refill progress…'),
    };

    return Container(
      padding: const EdgeInsets.all(AppSpacing.standard),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(children: [
        Icon(icon, color: color, size: 30),
        const SizedBox(width: AppSpacing.compact),
        Expanded(
          child: Text(message,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: color)),
        ),
      ]),
    );
  }
}
