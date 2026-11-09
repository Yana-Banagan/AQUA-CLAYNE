import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/driver_colors.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final bool small;
  final bool driverStyle;

  const StatusBadge({
    super.key,
    required this.status,
    this.small = false,
    this.driverStyle = false,
  });

  String _label(String s) {
    switch (s.toUpperCase()) {
      case 'PENDING':
        return 'Pending';
      case 'CONFIRMED':
        return 'Confirmed';
      case 'ASSIGNED':
        return 'Assigned';
      case 'PICKED_UP':
        return 'Picked Up';
      case 'RECEIVED_AT_STATION':
        return 'At Station';
      case 'WAITING_IN_QUEUE':
        return 'In Queue';
      case 'REFILLING':
        return 'Refilling';
      case 'READY_FOR_DELIVERY':
        return 'Ready';
      case 'READY_FOR_PICKUP':
        return 'Ready for Pickup';
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
      // Delivery statuses
      case 'EN_ROUTE_FOR_PICKUP':
        return 'En Route';
      case 'AT_STATION':
        return 'At Station';
      case 'FAILED':
        return 'Failed';
      // Queue statuses
      case 'WAITING':
        return 'Waiting';
      case 'IN_PROGRESS':
        return 'In Progress';
      // Payment statuses
      case 'PAID':
        return 'Paid';
      case 'VERIFIED':
        return 'Verified';
      case 'PARTIAL':
        return 'Partial';
      default:
        return s
            .toLowerCase()
            .replaceAll('_', ' ')
            .split(' ')
            .map((w) => w.isNotEmpty
                ? '${w[0].toUpperCase()}${w.substring(1)}'
                : w)
            .join(' ');
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = driverStyle
        ? DriverColors.statusColor(status)
        : AppColors.statusColor(status);
    final bg = color.withValues(alpha: 0.12);
    final label = _label(status);
    final fontSize = small ? 10.0 : 11.0;
    final hPad = small ? 6.0 : 8.0;
    final vPad = small ? 2.0 : 3.0;

    return Container(
      padding: EdgeInsets.symmetric(horizontal: hPad, vertical: vPad),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: small ? 5 : 6,
            height: small ? 5 : 6,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: fontSize,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}
