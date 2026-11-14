import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import '../services/navigation_launcher.dart';

class PickupBatchDetailScreen extends StatefulWidget {
  final String batchId;

  const PickupBatchDetailScreen({super.key, required this.batchId});

  @override
  State<PickupBatchDetailScreen> createState() => _PickupBatchDetailScreenState();
}

class _PickupBatchDetailScreenState extends State<PickupBatchDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  Future<void> _loadData() async {
    await context.read<DriverDeliveryProvider>().loadPickupBatch(widget.batchId);
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

  Future<void> _startBatch() async {
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.updatePickupBatchStatus(widget.batchId, 'in_progress');
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pickup batch started! Proceed to first stop.'),
          backgroundColor: DriverColors.primary,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to start batch.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  Future<void> _completeBatch() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Return to Station & Intake'),
        content: const Text(
          'Are you returning to the station with collected customer bottles? '
          'Upon arrival, station staff will inspect empty containers before refilling begins.',
        ),
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
            child: const Text('Confirm Return'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.updatePickupBatchStatus(widget.batchId, 'completed');
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pickup batch completed! Hand over bottles at station intake.'),
          backgroundColor: DriverColors.success,
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to complete batch.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  void _showCollectModal(Map<String, dynamic> item) {
    final itemId = item['id']?.toString() ?? '';
    final order = item['order'] as Map<String, dynamic>? ?? {};
    final customer = order['customer'] as Map<String, dynamic>? ?? {};
    final user = customer['user'] as Map<String, dynamic>? ?? {};
    final customerName = user['name']?.toString() ?? 'Customer';
    final expected = (item['expected_gallons'] as num?)?.toInt() ?? 0;

    int count = expected > 0 ? expected : 1;
    final notesCtrl = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return Container(
            padding: EdgeInsets.only(
              left: 20,
              right: 20,
              top: 24,
              bottom: MediaQuery.of(context).viewInsets.bottom + 24,
            ),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Collect Empty Gallons',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: DriverColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$customerName • Order #${order['order_number'] ?? ''}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: DriverColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 20),

                // Gallon Counter
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    color: DriverColors.cardTranslucent,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: DriverColors.borderLight),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Bottles Collected',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.textPrimary,
                            ),
                          ),
                          Text(
                            'Expected: $expected gallons',
                            style: const TextStyle(
                              fontSize: 12,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                      Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.remove_circle_outline),
                            color: DriverColors.primary,
                            iconSize: 30,
                            onPressed: count > 0
                                ? () => setModalState(() => count--)
                                : null,
                          ),
                          Container(
                            constraints: const BoxConstraints(minWidth: 36),
                            alignment: Alignment.center,
                            child: Text(
                              '$count',
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                color: DriverColors.primaryDark,
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.add_circle_outline),
                            color: DriverColors.primary,
                            iconSize: 30,
                            onPressed: () => setModalState(() => count++),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),

                // Notes input
                TextField(
                  controller: notesCtrl,
                  decoration: InputDecoration(
                    labelText: 'Notes (optional, e.g. bottle condition)',
                    labelStyle: const TextStyle(fontSize: 13),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),
                const SizedBox(height: 20),

                // Submit Button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: () async {
                      Navigator.pop(ctx);
                      final messenger = ScaffoldMessenger.of(context);
                      final prov = context.read<DriverDeliveryProvider>();
                      final ok = await prov.collectPickupStop(
                        widget.batchId,
                        itemId,
                        count,
                        notes: notesCtrl.text.trim(),
                      );
                      if (!mounted) return;
                      if (ok) {
                        messenger.showSnackBar(
                          SnackBar(
                            content: Text('Stop marked collected ($count gal).'),
                            backgroundColor: DriverColors.primary,
                          ),
                        );
                      } else {
                        messenger.showSnackBar(
                          SnackBar(
                            content: Text(prov.error ?? 'Failed to update stop.'),
                            backgroundColor: DriverColors.error,
                          ),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: DriverColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Confirm Picked Up',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Pickup Batch',
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
          if (prov.loading && prov.currentBatch == null) {
            return const LoadingView(message: 'Loading pickup batch details...');
          }

          final batch = prov.currentBatch;
          if (batch == null) {
            return const Center(child: Text('Batch not found.'));
          }

          final status = (batch['status'] ?? 'ready').toString();
          final area = batch['area']?.toString() ?? 'Unassigned Area';
          final batchCode = batch['batch_code']?.toString() ?? 'Batch';
          final totalGallons = (batch['total_gallons'] as num?)?.toInt() ?? 0;
          final maxCapacity = 30;
          final items = (batch['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];

          final isReady = status == 'ready';
          final isInProgress = status == 'in_progress';

          final collectedCount = items.where((it) => it['status'] == 'picked_up').length;
          final allDone = items.isNotEmpty && items.every((it) => it['status'] == 'picked_up' || it['status'] == 'failed');

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
                                batchCode,
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
                              const Icon(Icons.location_on_outlined, size: 16, color: DriverColors.primary),
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
                                '${items.length} customer stops',
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
                          'PICKUP STOPS',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: DriverColors.primaryDark,
                            letterSpacing: 0.5,
                          ),
                        ),
                        Text(
                          '$collectedCount of ${items.length} collected',
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
                    if (items.isEmpty)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(32),
                          child: Text('No stops in this batch.'),
                        ),
                      )
                    else
                      ...items.asMap().entries.map((entry) {
                        final index = entry.key;
                        final item = entry.value;
                        final order = item['order'] as Map<String, dynamic>? ?? {};
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

                        final expected = (item['expected_gallons'] as num?)?.toInt() ?? 0;
                        final itemStatus = (item['status'] ?? 'pending').toString().toLowerCase();
                        final isPickedUp = itemStatus == 'picked_up';
                        final isFailed = itemStatus == 'failed';

                        return Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: DriverColors.cardTranslucent,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isPickedUp
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
                                    backgroundColor: isPickedUp
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
                                    ),
                                  ),
                                ],
                              ),
                              const Divider(height: 18),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: DriverColors.mintSoft,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      'Order #${order['order_number'] ?? ''} • $expected gal expected',
                                      style: const TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: DriverColors.primaryDark,
                                      ),
                                    ),
                                  ),
                                  if (isPickedUp)
                                    Row(
                                      children: [
                                        const Icon(Icons.check_circle, size: 16, color: DriverColors.success),
                                        const SizedBox(width: 4),
                                        Text(
                                          'Collected (${item['received_gallons'] ?? expected} gal)',
                                          style: const TextStyle(
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
                                      onPressed: () => _showCollectModal(item),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: DriverColors.primary,
                                        foregroundColor: Colors.white,
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                        visualDensity: VisualDensity.compact,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                      ),
                                      child: const Text(
                                        'Collect Gallons',
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
                        'Start Pickup Batch',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: DriverColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      onPressed: _startBatch,
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
                      icon: const Icon(Icons.storefront_outlined),
                      label: Text(
                        allDone
                            ? 'All Collected • Return to Station & Intake'
                            : 'Return to Station & Intake',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: allDone ? DriverColors.success : DriverColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      onPressed: _completeBatch,
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
