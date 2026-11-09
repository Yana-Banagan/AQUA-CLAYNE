import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import '../services/navigation_launcher.dart';
import 'delivery_detail_screen.dart';

class DeliveryRouteDetailScreen extends StatefulWidget {
  final String routeId;

  const DeliveryRouteDetailScreen({super.key, required this.routeId});

  @override
  State<DeliveryRouteDetailScreen> createState() => _DeliveryRouteDetailScreenState();
}

class _DeliveryRouteDetailScreenState extends State<DeliveryRouteDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  Future<void> _loadData() async {
    await context.read<DriverDeliveryProvider>().loadDeliveryRoute(widget.routeId);
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'in_progress':
        return DriverColors.mintAccent;
      case 'completed':
        return DriverColors.success;
      case 'ready':
        return DriverColors.primary;
      case 'cancelled':
        return DriverColors.error;
      default:
        return DriverColors.textMuted;
    }
  }

  String _statusLabel(String status) {
    switch (status.toLowerCase()) {
      case 'in_progress':
        return 'IN PROGRESS';
      case 'ready':
        return 'READY / ASSIGNED';
      case 'completed':
        return 'COMPLETED';
      case 'cancelled':
        return 'CANCELLED';
      default:
        return status.toUpperCase();
    }
  }

  Future<void> _startRoute() async {
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.updateDeliveryRouteStatus(widget.routeId, 'in_progress');
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Delivery route started! Proceed to first drop-off.'),
          backgroundColor: DriverColors.primary,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to start route.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  Future<void> _completeRoute() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Complete Delivery Route'),
        content: const Text('Have all scheduled customer deliveries on this route been finished?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: DriverColors.primary,
              foregroundColor: Colors.white,
            ),
            child: const Text('Complete Route'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.updateDeliveryRouteStatus(widget.routeId, 'completed');
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Delivery route completed successfully!'),
          backgroundColor: DriverColors.success,
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to complete route.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Delivery Route',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
        ],
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (context, prov, _) {
          if (prov.loading && prov.currentRoute == null) {
            return const LoadingView(message: 'Loading delivery route details...');
          }

          final route = prov.currentRoute;
          if (route == null) {
            return const Center(child: Text('Route not found.'));
          }

          final status = (route['status'] ?? 'ready').toString();
          final area = route['area']?.toString() ?? 'Unassigned Area';
          final routeCode = route['route_code']?.toString() ?? 'Route';
          final totalGallons = (route['total_gallons'] as num?)?.toInt() ?? 0;
          const maxCapacity = 30;
          final stops = (route['stops'] as List?)?.cast<Map<String, dynamic>>() ?? [];

          final isReady = status == 'ready';
          final isInProgress = status == 'in_progress';

          final deliveredCount = stops.where((s) => s['status'] == 'delivered').length;
          final allDone = stops.isNotEmpty && stops.every((s) => s['status'] == 'delivered' || s['status'] == 'failed');

          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(AppSpacing.standard),
                  children: [
                    // Header Card with Capacity Gauge
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
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                routeCode,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: DriverColors.primaryDark,
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: _statusColor(status).withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: _statusColor(status)),
                                ),
                                child: Text(
                                  _statusLabel(status),
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: _statusColor(status),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              const Icon(Icons.near_me_outlined, size: 16, color: DriverColors.primary),
                              const SizedBox(width: 4),
                              Text(
                                area,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: DriverColors.textPrimary,
                                ),
                              ),
                              const Spacer(),
                              Text(
                                '${stops.length} drop-offs',
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: DriverColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),

                          // Capacity Meter
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Vehicle Load:',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: DriverColors.textSecondary,
                                ),
                              ),
                              Text(
                                '$totalGallons / $maxCapacity gal (${((totalGallons / maxCapacity) * 100).round()}%)',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: totalGallons > 26 ? DriverColors.warning : DriverColors.primaryDark,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(
                              value: (totalGallons / maxCapacity).clamp(0.0, 1.0),
                              backgroundColor: Colors.grey.shade200,
                              color: totalGallons > 26 ? Colors.amber.shade700 : DriverColors.primary,
                              minHeight: 8,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.standard),

                    // Stops Header
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'DELIVERY STOPS',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: DriverColors.primaryDark,
                            letterSpacing: 0.5,
                          ),
                        ),
                        Text(
                          '$deliveredCount of ${stops.length} delivered',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: DriverColors.mintAccent,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    // Stops List
                    if (stops.isEmpty)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(32),
                          child: Text('No delivery stops assigned to this route.'),
                        ),
                      )
                    else
                      ...stops.asMap().entries.map((entry) {
                        final index = entry.key;
                        final stop = entry.value;
                        final order = stop['order'] as Map<String, dynamic>? ?? {};
                        final customer = order['customer'] as Map<String, dynamic>? ?? {};
                        final user = customer['user'] as Map<String, dynamic>? ?? {};
                        final customerName = user['name']?.toString() ?? 'Customer';
                        final phone = user['phone']?.toString() ?? '';
                        final address = order['address'] as Map<String, dynamic>? ?? {};
                        final addressLine = address['address_line']?.toString() ??
                            address['street']?.toString() ?? '';
                        final city = address['city']?.toString() ?? '';
                        final fullAddress = city.isNotEmpty ? '$addressLine, $city' : addressLine;
                        final lat = double.tryParse(address['latitude']?.toString() ?? '');
                        final lng = double.tryParse(address['longitude']?.toString() ?? '');

                        final payment = order['payment'] as Map<String, dynamic>? ?? {};
                        final payMethod = (payment['method'] ?? order['payment_method'] ?? 'cash').toString().toLowerCase();
                        final payStatus = (payment['status'] ?? order['payment_status'] ?? 'pending').toString().toLowerCase();
                        final isPaidOnline = payMethod == 'online' || payStatus == 'paid';
                        final totalAmt = (payment['amount'] ?? order['total_amount'] ?? 0);

                        final stopStatus = (stop['status'] ?? 'pending').toString().toLowerCase();
                        final isDelivered = stopStatus == 'delivered';
                        final isFailed = stopStatus == 'failed';
                        final deliveryId = stop['delivery_id']?.toString() ?? '';

                        return Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: DriverColors.cardTranslucent,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isDelivered
                                  ? DriverColors.success.withValues(alpha: 0.5)
                                  : DriverColors.borderLight,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Stop sequence circle
                                  CircleAvatar(
                                    radius: 14,
                                    backgroundColor: isDelivered
                                        ? DriverColors.success
                                        : DriverColors.primary,
                                    child: Text(
                                      '${index + 1}',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                        color: Colors.white,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          customerName,
                                          style: const TextStyle(
                                            fontSize: 15,
                                            fontWeight: FontWeight.bold,
                                            color: DriverColors.textPrimary,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          fullAddress,
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: DriverColors.textSecondary,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  // Quick Call & Map actions
                                  if (phone.isNotEmpty)
                                    IconButton(
                                      icon: const Icon(Icons.phone, size: 20, color: DriverColors.primary),
                                      visualDensity: VisualDensity.compact,
                                      onPressed: () => NavigationLauncher.launchCall(context, phone),
                                    ),
                                  IconButton(
                                    icon: const Icon(Icons.navigation_outlined, size: 20, color: DriverColors.primary),
                                    visualDensity: VisualDensity.compact,
                                    onPressed: () => NavigationLauncher.launchNavigation(
                                      context: context,
                                      address: fullAddress,
                                      latitude: lat,
                                      longitude: lng,
                                      customerName: customerName,
                                      deliveryId: deliveryId.isEmpty ? null : deliveryId,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 10),

                              // Payment Status Indicator
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(
                                  color: isPaidOnline
                                      ? DriverColors.mintSoft
                                      : payMethod == 'credit'
                                          ? DriverColors.infoLight
                                          : DriverColors.warningLight,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: isPaidOnline
                                        ? DriverColors.mintAccent.withValues(alpha: 0.5)
                                        : payMethod == 'credit'
                                            ? DriverColors.info.withValues(alpha: 0.3)
                                            : DriverColors.warning.withValues(alpha: 0.3),
                                  ),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      isPaidOnline
                                          ? Icons.check_circle
                                          : payMethod == 'credit'
                                              ? Icons.credit_score
                                              : Icons.attach_money,
                                      size: 14,
                                      color: isPaidOnline
                                          ? DriverColors.success
                                          : payMethod == 'credit'
                                              ? DriverColors.info
                                              : DriverColors.warning,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      isPaidOnline
                                          ? 'PAID ONLINE (PayMongo) • No Cash Needed'
                                          : payMethod == 'credit'
                                              ? 'CREDIT ACCOUNT (₱$totalAmt)'
                                              : 'COLLECT CASH: ₱$totalAmt',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
                                        color: isPaidOnline
                                            ? DriverColors.primaryDark
                                            : payMethod == 'credit'
                                                ? DriverColors.info
                                                : DriverColors.textPrimary,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Divider(height: 18),

                              // Stop footer actions
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    'Order #${order['order_number'] ?? ''}',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      color: DriverColors.textSecondary,
                                    ),
                                  ),
                                  if (isDelivered)
                                    const Row(
                                      children: [
                                        Icon(Icons.check_circle, size: 16, color: DriverColors.success),
                                        SizedBox(width: 4),
                                        Text(
                                          'Delivered',
                                          style: TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.bold,
                                            color: DriverColors.success,
                                          ),
                                        ),
                                      ],
                                    )
                                  else if (isFailed)
                                    const Text(
                                      'Failed',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                        color: DriverColors.error,
                                      ),
                                    )
                                  else
                                    ElevatedButton(
                                      onPressed: () {
                                        if (deliveryId.isNotEmpty) {
                                          Navigator.push(
                                            context,
                                            MaterialPageRoute(
                                              builder: (_) => DeliveryDetailScreen(id: deliveryId),
                                            ),
                                          ).then((_) => _loadData());
                                        } else {
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            const SnackBar(content: Text('Delivery details not available.')),
                                          );
                                        }
                                      },
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: DriverColors.primary,
                                        foregroundColor: Colors.white,
                                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                        visualDensity: VisualDensity.compact,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                      ),
                                      child: const Text(
                                        'Process Drop-off',
                                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        );
                      }),
                  ],
                ),
              ),

              // Bottom Action Bar
              if (isReady)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(top: BorderSide(color: DriverColors.borderLight)),
                  ),
                  child: SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: ElevatedButton.icon(
                      icon: const Icon(Icons.play_arrow_rounded),
                      label: const Text(
                        'Start Delivery Route',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: DriverColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      onPressed: _startRoute,
                    ),
                  ),
                )
              else if (isInProgress)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(top: BorderSide(color: DriverColors.borderLight)),
                  ),
                  child: SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: ElevatedButton.icon(
                      icon: const Icon(Icons.check_circle_outline),
                      label: Text(
                        allDone
                            ? 'All Stops Delivered • Complete Route'
                            : 'Complete Delivery Route',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: allDone ? DriverColors.success : DriverColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      onPressed: _completeRoute,
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
