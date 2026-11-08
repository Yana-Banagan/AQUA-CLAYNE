import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import 'pickup_batch_detail_screen.dart';

class PickupBatchesScreen extends StatefulWidget {
  const PickupBatchesScreen({super.key});

  @override
  State<PickupBatchesScreen> createState() => _PickupBatchesScreenState();
}

class _PickupBatchesScreenState extends State<PickupBatchesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refresh();
    });
  }

  Future<void> _refresh() async {
    await context.read<DriverDeliveryProvider>().loadPickupBatches();
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
        return 'READY';
      case 'completed':
        return 'COMPLETED';
      default:
        return status.toUpperCase();
    }
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Pickup Batches',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (context, prov, _) {
          if (prov.loading && prov.pickupBatches.isEmpty) {
            return const LoadingView(message: 'Loading pickup batches...');
          }

          final batches = prov.pickupBatches;

          if (batches.isEmpty) {
            return RefreshIndicator(
              onRefresh: _refresh,
              color: DriverColors.primary,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(vertical: 80, horizontal: 24),
                children: [
                  Container(
                    padding: const EdgeInsets.all(32),
                    decoration: BoxDecoration(
                      color: DriverColors.cardTranslucent,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: DriverColors.borderLight),
                    ),
                    child: Column(
                      children: [
                        const Icon(
                          Icons.inventory_2_outlined,
                          size: 48,
                          color: DriverColors.primary,
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'No Pickup Batches',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: DriverColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'No multi-stop pickup batches currently assigned to your vehicle.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 13,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _refresh,
            color: DriverColors.primary,
            child: ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(AppSpacing.standard),
              itemCount: batches.length,
              itemBuilder: (context, index) {
                final batch = batches[index];
                final id = batch['id']?.toString() ?? '';
                final batchCode = batch['batch_code']?.toString() ?? 'Batch';
                final area = batch['area']?.toString() ?? 'Unassigned Area';
                final status = (batch['status'] ?? 'ready').toString();
                final totalOrders = (batch['total_orders'] as num?)?.toInt() ?? 0;
                final totalGallons = (batch['total_gallons'] as num?)?.toInt() ?? 0;
                const maxCapacity = 30;

                return Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: DriverColors.cardTranslucent,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: DriverColors.borderLight),
                    boxShadow: const [
                      BoxShadow(
                        color: DriverColors.shadow,
                        blurRadius: 8,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Material(
                    color: Colors.transparent,
                    borderRadius: BorderRadius.circular(18),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(18),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => PickupBatchDetailScreen(batchId: id),
                          ),
                        ).then((_) => _refresh());
                      },
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  batchCode,
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                    color: DriverColors.primaryDark,
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: _statusColor(status).withValues(alpha: 0.15),
                                    borderRadius: BorderRadius.circular(10),
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
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                const Icon(Icons.location_on_outlined, size: 16, color: DriverColors.primary),
                                const SizedBox(width: 4),
                                Text(
                                  area,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: DriverColors.textPrimary,
                                  ),
                                ),
                                const Spacer(),
                                Text(
                                  '$totalOrders stops',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: DriverColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),

                            // Gauge
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  'Capacity load:',
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: DriverColors.textSecondary,
                                  ),
                                ),
                                Text(
                                  '$totalGallons / $maxCapacity gal',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: totalGallons > 26 ? DriverColors.warning : DriverColors.primaryDark,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            ClipRRect(
                              borderRadius: BorderRadius.circular(4),
                              child: LinearProgressIndicator(
                                value: (totalGallons / maxCapacity).clamp(0.0, 1.0),
                                backgroundColor: Colors.grey.shade200,
                                color: totalGallons > 26 ? Colors.amber.shade700 : DriverColors.primary,
                                minHeight: 6,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
