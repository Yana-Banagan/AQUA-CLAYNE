import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_spacing.dart';

const _steps = [
  ('PENDING', 'Order Placed'),
  ('CONFIRMED', 'Order Confirmed'),
  ('ASSIGNED', 'Driver Assigned'),
  ('PICKED_UP', 'Empty Gallons Picked Up'),
  ('RECEIVED_AT_STATION', 'Received at Station'),
  ('REFILLING', 'Refilling'),
  ('READY_FOR_DELIVERY', 'Ready for Delivery'),
  ('OUT_FOR_DELIVERY', 'Out for Delivery'),
  ('DELIVERED', 'Delivered'),
  ('COMPLETED', 'Completed'),
];

const _stationPickupSteps = [
  ('PENDING', 'Order Placed'),
  ('CONFIRMED', 'Order Confirmed'),
  ('RECEIVED_AT_STATION', 'Received at Station'),
  ('REFILLING', 'Refilling'),
  ('READY_FOR_PICKUP', 'Ready for Pickup'),
  ('COMPLETED', 'Completed'),
];

const _terminalStatuses = {
  'REJECTED',
  'CANCELLED',
  'PICKUP_FAILED',
  'DELIVERY_FAILED',
};

int _stepIndex(String status, List<(String, String)> steps) {
  for (var i = 0; i < steps.length; i++) {
    if (steps[i].$1 == status.toUpperCase()) return i;
  }
  return -1;
}

class OrderTimeline extends StatelessWidget {
  final String currentStatus;
  final bool isStationPickup;

  const OrderTimeline({
    super.key,
    required this.currentStatus,
    this.isStationPickup = false,
  });

  @override
  Widget build(BuildContext context) {
    final upper = currentStatus.toUpperCase();

    if (_terminalStatuses.contains(upper)) {
      return _TerminalStep(status: upper);
    }

    final activeSteps = isStationPickup ? _stationPickupSteps : _steps;
    final currentIdx = _stepIndex(upper, activeSteps);

    return Column(
      children: List.generate(activeSteps.length, (i) {
        final (code, label) = activeSteps[i];
        final isDone = i < currentIdx;
        final isCurrent = i == currentIdx;
        final isUpcoming = i > currentIdx;
        final isLast = i == activeSteps.length - 1;

        return _TimelineRow(
          label: label,
          isDone: isDone,
          isCurrent: isCurrent,
          isUpcoming: isUpcoming,
          isLast: isLast,
          status: code,
        );
      }),
    );
  }
}

class _TerminalStep extends StatelessWidget {
  final String status;

  const _TerminalStep({required this.status});

  @override
  Widget build(BuildContext context) {
    final color = AppColors.statusColor(status);
    final label = status == 'REJECTED'
        ? 'Order Rejected'
        : status == 'CANCELLED'
            ? 'Order Cancelled'
            : status == 'PICKUP_FAILED'
                ? 'Pickup Failed'
                : 'Delivery Failed';

    return Container(
      padding: const EdgeInsets.all(AppSpacing.standard),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.cancel_outlined, color: color, size: 22),
          const SizedBox(width: AppSpacing.compact),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 15,
            ),
          ),
        ],
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  final String label;
  final bool isDone;
  final bool isCurrent;
  final bool isUpcoming;
  final bool isLast;
  final String status;

  const _TimelineRow({
    required this.label,
    required this.isDone,
    required this.isCurrent,
    required this.isUpcoming,
    required this.isLast,
    required this.status,
  });

  @override
  Widget build(BuildContext context) {
    final color = isCurrent
        ? AppColors.statusColor(status)
        : isDone
            ? AppColors.success
            : AppColors.disabled;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Dot + line column
          SizedBox(
            width: 24,
            child: Column(
              children: [
                // Dot
                Container(
                  width: 20,
                  height: 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: isDone || isCurrent ? color : Colors.transparent,
                    border: Border.all(
                      color: color,
                      width: isCurrent ? 2.5 : 2,
                    ),
                  ),
                  child: isDone
                      ? const Icon(Icons.check, color: Colors.white, size: 12)
                      : isCurrent
                          ? Container(
                              margin: const EdgeInsets.all(3),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: color,
                              ),
                            )
                          : null,
                ),
                // Line
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 2,
                      color: isDone ? AppColors.success : AppColors.divider,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.compact),
          // Label
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(
                bottom: isLast ? 0 : AppSpacing.standard,
                top: 1,
              ),
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: isCurrent ? FontWeight.w600 : FontWeight.normal,
                  color: isUpcoming
                      ? AppColors.textHint
                      : isCurrent
                          ? color
                          : AppColors.textPrimary,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
