import 'package:flutter/material.dart';
import 'package:mobile/core/utils/pht_datetime.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import '../../shared_widgets/status_badge.dart';
import '../services/navigation_launcher.dart';
import '../widgets/report_issue_dialog.dart';
import 'payment_collection_screen.dart';
import 'proof_of_delivery_screen.dart';
import 'route_map_screen.dart';

class DeliveryDetailScreen extends StatefulWidget {
  final String id;
  const DeliveryDetailScreen({super.key, required this.id});

  @override
  State<DeliveryDetailScreen> createState() => _DeliveryDetailScreenState();
}

class _DeliveryDetailScreenState extends State<DeliveryDetailScreen> {
  bool _updating = false;

  Future<void> _recordRfidScan(String deliveryId, String action) async {
    final controller = TextEditingController();
    final uid = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Scan gallon RFID'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'RFID tag UID',
            helperText: 'Use the connected RFID reader or enter the tag code.',
          ),
          onSubmitted: (value) => Navigator.pop(dialogContext, value.trim()),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, controller.text.trim()), child: const Text('Record scan')),
        ],
      ),
    );
    controller.dispose();
    if (uid == null || uid.isEmpty || !mounted) return;
    try {
      final result = await context.read<ApiClient>().post(
        '/driver/deliveries/$deliveryId/rfid-scans',
        {'tag_uid': uid, 'action': action},
        auth: true,
      );
      if (!mounted) return;
      final data = result is Map ? result : const {};
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('RFID recorded for gallon ${data['gallon_id'] ?? ''}.'),
        backgroundColor: DriverColors.success,
      ));
      await _load();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(error.toString()),
        backgroundColor: DriverColors.error,
      ));
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
    });
  }

  Future<void> _load() async {
    await context.read<DriverDeliveryProvider>().loadDetail(widget.id);
  }

  Future<void> _transitionStatus(String newStatus, {String? note}) async {
    if (_updating) return;
    setState(() => _updating = true);

    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.updateStatus(widget.id, newStatus, note: note);

    if (!mounted) return;
    setState(() => _updating = false);

    if (ok) {
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Delivery updated to ${_formatStatus(newStatus)}.'),
          backgroundColor: DriverColors.primary,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Unable to update delivery status.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  /// Completes the delivery from the driver workflow and refreshes the data
  /// used by the dashboard before returning to it.
  Future<void> _completeDelivery() async {
    if (_updating) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Complete Delivery'),
        content: const Text(
          'Confirm that the refilled gallons have been handed to the customer.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: DriverColors.primaryDark,
              foregroundColor: Colors.white,
            ),
            child: const Text('Mark Complete'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _updating = true);
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.markDelivered(widget.id);

    if (!mounted) return;
    setState(() => _updating = false);

    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Unable to complete this delivery.'),
          backgroundColor: DriverColors.error,
        ),
      );
      return;
    }

    // The delivery moves from the active list to history. Refresh both lists
    // so the dashboard reflects the completed delivery immediately.
    await Future.wait([
      prov.loadAssigned(),
      prov.loadHistory(),
    ]);

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Delivery completed successfully.'),
        backgroundColor: DriverColors.success,
      ),
    );
    Navigator.pop(context, true);
  }

  String _formatStatus(String status) {
    return status.replaceAll('_', ' ').toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Delivery Details',
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
            icon: const Icon(Icons.refresh_rounded, color: DriverColors.primary),
            onPressed: _updating ? null : _load,
          ),
        ],
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (context, prov, _) {
          final targetId = widget.id.trim().toLowerCase();
          final detailMatchesRoute = prov.detail != null &&
              (prov.detail!.id.trim().toLowerCase() == targetId ||
                  prov.detail!.orderId.trim().toLowerCase() == targetId);

          DeliveryModel? fallback;
          try {
            fallback = prov.assigned.firstWhere(
              (item) =>
                  item.id.trim().toLowerCase() == targetId ||
                  item.orderId.trim().toLowerCase() == targetId,
            );
          } catch (_) {
            try {
              fallback = prov.history.firstWhere(
                (item) =>
                    item.id.trim().toLowerCase() == targetId ||
                    item.orderId.trim().toLowerCase() == targetId,
              );
            } catch (_) {}
          }

          final d = detailMatchesRoute ? prov.detail : fallback;

          if (d == null && prov.loading) {
            return const LoadingView(message: 'Loading delivery details...');
          }

          if (d == null) {
            return Center(
              child: Container(
                margin: const EdgeInsets.all(24),
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: DriverColors.cardTranslucent,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: DriverColors.borderLight),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      color: DriverColors.error,
                      size: 40,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      prov.error ?? 'Delivery not found.',
                      style: const TextStyle(
                        fontSize: 15,
                        color: DriverColors.textPrimary,
                        fontWeight: FontWeight.w600,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: _load,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: DriverColors.primary,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Try Again'),
                    ),
                  ],
                ),
              ),
            );
          }

          final status = d.status.toLowerCase();
          final isCash = d.isCash;
          final isPaid = d.isPaid;
          final hasProof = d.hasProof;
          final paymentMethodLabel = d.isOnlinePay
              ? 'PAYMONGO ONLINE'
              : d.isCredit
              ? 'CREDIT ACCOUNT'
              : 'CASH';
          final paymentMethodColor = isCash
              ? DriverColors.warning
              : d.isOnlinePay
              ? DriverColors.success
              : DriverColors.info;
          final paymentMethodBackground = isCash
              ? DriverColors.warningLight
              : d.isOnlinePay
              ? DriverColors.successLight
              : DriverColors.infoLight;
          var newGallonCount = 0;
          for (final item in d.items) {
            if (item is! Map) continue;
            final product = item['product'] is Map ? item['product'] as Map : const {};
            final productType = (product['product_type'] ?? item['product_type'] ?? '').toString().toLowerCase();
            if (productType == 'new_gallon') {
              newGallonCount += int.tryParse((item['quantity'] ?? 0).toString()) ?? 0;
            }
          }

          return RefreshIndicator(
            color: DriverColors.primary,
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(AppSpacing.standard),
              children: [
                // ── Order Summary Card ───────────────────────────────
                _CardContainer(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: DriverColors.mintSoft,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              d.orderNumber,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: DriverColors.primaryDark,
                              ),
                            ),
                          ),
                          const Spacer(),
                          StatusBadge(status: d.status, driverStyle: true),
                        ],
                      ),
                      if (d.createdAt.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          'Created: ${PhtDateTime.parse(d.createdAt).toIso8601String().split('T').first}',
                          style: const TextStyle(
                            fontSize: 12,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                      ],
                      if (d.failureReason != null &&
                          d.failureReason!.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: DriverColors.errorLight,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: DriverColors.error.withValues(alpha: 0.3),
                            ),
                          ),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.warning_amber_rounded,
                                color: DriverColors.error,
                                size: 18,
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Issue: ${d.failureReason}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: DriverColors.error,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.compact),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _updating
                        ? null
                        : () => _recordRfidScan(
                              d.id,
                              const {'en_route_for_pickup', 'picked_up', 'at_station', 'received_at_station'}
                                      .contains(d.status)
                                  ? 'pickup'
                                  : 'delivery',
                            ),
                    icon: const Icon(Icons.nfc_rounded),
                    label: const Text('Scan / Record Gallon RFID'),
                  ),
                ),
                const SizedBox(height: AppSpacing.standard),

                // ── Customer & Address Card ──────────────────────────
                _CardContainer(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _SectionHeader(
                        icon: Icons.person_pin_circle_outlined,
                        title: 'CUSTOMER & LOCATION',
                      ),
                      const SizedBox(height: 12),
                      Text(
                        d.customerName,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.textPrimary,
                        ),
                      ),
                      if (d.customerPhone.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            const Icon(
                              Icons.phone_outlined,
                              size: 15,
                              color: DriverColors.primary,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              d.customerPhone,
                              style: const TextStyle(
                                fontSize: 13,
                                color: DriverColors.textSecondary,
                              ),
                            ),
                            const Spacer(),
                            InkWell(
                              onTap: () => NavigationLauncher.launchCall(
                                context,
                                d.customerPhone,
                              ),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: DriverColors.mintSoft,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Row(
                                  children: [
                                    Icon(
                                      Icons.call,
                                      size: 12,
                                      color: DriverColors.primary,
                                    ),
                                    SizedBox(width: 4),
                                    Text(
                                      'Call',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                        color: DriverColors.primary,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: 10),
                      const Divider(color: DriverColors.divider, height: 1),
                      const SizedBox(height: 10),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Padding(
                            padding: EdgeInsets.only(top: 2),
                            child: Icon(
                              Icons.location_on,
                              size: 16,
                              color: DriverColors.primary,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              d.fullAddress,
                              style: const TextStyle(
                                fontSize: 13,
                                color: DriverColors.textPrimary,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () => NavigationLauncher.launchNavigation(
                            context: context,
                            latitude: d.latitude,
                            longitude: d.longitude,
                            address: d.fullAddress,
                            customerName: d.customerName,
                            deliveryId: d.id,
                            deliveryStatus: d.status,
                          ),
                          icon: const Icon(Icons.navigation_rounded, size: 16),
                          label: const Text('Start Live Navigation (Point A ➔ Point B)'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: DriverColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            elevation: 0,
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => RouteMapScreen(delivery: d),
                            ),
                          ),
                          icon: const Icon(Icons.map_outlined, size: 16),
                          label: const Text('Preview Route Map (Point A ➔ Point B)'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: DriverColors.primary,
                            side: const BorderSide(color: DriverColors.primary),
                            padding: const EdgeInsets.symmetric(vertical: 11),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.standard),

                // ── Customer-Owned Refill Details Card ───────────────
                _CardContainer(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _SectionHeader(
                        icon: Icons.water_drop_outlined,
                        title: 'REFILL SERVICE + PAYMENT INFORMATION',
                      ),
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: DriverColors.mintSurface,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: DriverColors.borderLight),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.inventory_2_outlined,
                              color: DriverColors.primary,
                              size: 22,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'ORDERED REFILL SERVICE',
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      color: DriverColors.textSecondary,
                                    ),
                                  ),
                                  Text(
                                    '${d.expectedGallons} Gallons for Refill',
                                    style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                      color: DriverColors.primaryDark,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(child: _ServiceInfo(label: 'Refill gallons', value: '${d.expectedGallons} customer-owned')),
                          const SizedBox(width: 8),
                          Expanded(child: _ServiceInfo(label: 'New gallons', value: newGallonCount > 0 ? '$newGallonCount included' : 'None ordered')),
                        ],
                      ),
                      if (d.items.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        const Text(
                          'SERVICE DETAILS',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                        const SizedBox(height: 6),
                        ...d.items.map((item) {
                          final pName = item['product']?['name'] ??
                              item['product_name'] ??
                              'Water Refill Service';
                          final qty = item['quantity'] ?? 1;
                          final price = item['unit_price'] ?? 0;
                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 3),
                            child: Row(
                              children: [
                                Text(
                                  '• $qty x $pName',
                                  style: const TextStyle(
                                    fontSize: 13,
                                    color: DriverColors.textPrimary,
                                  ),
                                ),
                                const Spacer(),
                                Text(
                                  '₱$price',
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: DriverColors.textPrimary,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                      ],
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 12),
                        child: Divider(color: DriverColors.divider),
                      ),

                // ── Payment Information Card ─────────────────────────
                      const Text(
                        'PAYMENT INFORMATION',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Payment Method:',
                            style: TextStyle(
                              fontSize: 13,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: paymentMethodBackground,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              paymentMethodLabel,
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                color: paymentMethodColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            isCash && !isPaid ? 'Cash to Collect:' : 'Amount:',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.textPrimary,
                            ),
                          ),
                          Text(
                            '₱${d.totalAmount.toStringAsFixed(2)}',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: paymentMethodColor,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Payment Status:',
                            style: TextStyle(
                              fontSize: 13,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                          Text(
                            isPaid ? 'PAID' : 'PENDING COLLECTION',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: isPaid
                                  ? DriverColors.success
                                  : DriverColors.warning,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.standard),

                // ── Proof of Delivery Photo (if uploaded) ────────────
                if (hasProof) ...[
                  _CardContainer(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const _SectionHeader(
                          icon: Icons.camera_alt_outlined,
                          title: 'PROOF OF DELIVERY',
                        ),
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: DriverColors.successLight,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Row(
                            children: [
                              Icon(
                                Icons.check_circle,
                                color: DriverColors.success,
                                size: 18,
                              ),
                              SizedBox(width: 8),
                              Text(
                                'Proof photo uploaded and verified.',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: DriverColors.success,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.standard),
                ],

                // ── Operational Action Buttons ───────────────────────
                _buildWorkflowActions(context, d, status, isCash, isPaid, hasProof),
                const SizedBox(height: 14),

                // ── Report Issue Button ──────────────────────────────
                OutlinedButton.icon(
                  onPressed: _updating
                      ? null
                      : () async {
                          final reported = await ReportIssueDialog.show(
                            context,
                            deliveryId: d.id,
                            orderNumber: d.orderNumber,
                          );
                          if (reported == true) {
                            _load();
                          }
                        },
                  icon: const Icon(
                    Icons.report_problem_outlined,
                    color: DriverColors.warning,
                    size: 16,
                  ),
                  label: const Text('Report Delivery Issue'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: DriverColors.warning,
                    side: const BorderSide(
                      color: DriverColors.warning,
                      width: 1,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                  ),
                ),
                const SizedBox(height: 30),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildWorkflowActions(
    BuildContext context,
    DeliveryModel d,
    String status,
    bool isCash,
    bool isPaid,
    bool hasProof,
  ) {
    if (_updating) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: DriverColors.cardTranslucent,
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                valueColor: AlwaysStoppedAnimation<Color>(DriverColors.primary),
              ),
            ),
            SizedBox(width: 12),
            Text(
              'Updating delivery status...',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: DriverColors.primaryDark,
              ),
            ),
          ],
        ),
      );
    }

    switch (status) {
      case 'assigned':
        return ElevatedButton.icon(
          onPressed: () => _transitionStatus('picked_up'),
          icon: const Icon(Icons.inventory_2_outlined, size: 18),
          label: const Text('Pick Up Gallons'),
          style: ElevatedButton.styleFrom(
            backgroundColor: DriverColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            elevation: 0,
          ),
        );

      case 'en_route_for_pickup':
        return ElevatedButton.icon(
          onPressed: () => _transitionStatus('picked_up'),
          icon: const Icon(Icons.inventory_2_outlined, size: 18),
          label: const Text('Pick Up Gallons'),
          style: ElevatedButton.styleFrom(
            backgroundColor: DriverColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            elevation: 0,
          ),
        );

      case 'picked_up':
      case 'at_station':
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: DriverColors.mintSoft,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: DriverColors.border),
          ),
          child: const Row(
            children: [
              Icon(Icons.hourglass_top, color: DriverColors.primary),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Gallons are at the station undergoing refill and sealing. Once completed, this order will update to Ready for Delivery.',
                  style: TextStyle(
                    fontSize: 12,
                    color: DriverColors.primaryDark,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        );

      case 'ready_for_delivery':
        return ElevatedButton.icon(
          onPressed: () => _transitionStatus('out_for_delivery'),
          icon: const Icon(Icons.delivery_dining, size: 18),
          label: const Text('Ready to Deliver'),
          style: ElevatedButton.styleFrom(
            backgroundColor: DriverColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            elevation: 0,
          ),
        );

      case 'out_for_delivery':
        // Proof can be added at any point during delivery. Completion remains
        // available when the driver chooses to skip it.
        final proofAction = OutlinedButton.icon(
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ProofOfDeliveryScreen(delivery: d),
            ),
          ).then((_) => _load()),
          icon: const Icon(Icons.camera_alt_outlined, size: 18),
          label: Text(hasProof ? 'View / Update Proof' : 'Optional Proof of Delivery'),
          style: OutlinedButton.styleFrom(
            foregroundColor: DriverColors.primary,
            side: const BorderSide(color: DriverColors.primary),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );

        if (isCash && !isPaid) {
          return Column(
            children: [
              proofAction,
              const SizedBox(height: 10),
              OutlinedButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => PaymentCollectionScreen(delivery: d),
              ),
            ).then((_) => _load()),
            icon: const Icon(Icons.payments_outlined, size: 18),
            label: Text(
              'Record Cash Collection (₱${d.totalAmount.toStringAsFixed(2)})',
            ),
            style: OutlinedButton.styleFrom(
              foregroundColor: DriverColors.warning,
              side: const BorderSide(
                color: DriverColors.warning,
                width: 1.5,
              ),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
              ),
            ],
          );
        }

        return Column(
          children: [
            proofAction,
            const SizedBox(height: 10),
            ElevatedButton.icon(
              onPressed: _completeDelivery,
              icon: const Icon(Icons.check_circle_outline, size: 18),
              label: const Text('Mark Delivered & Complete'),
              style: ElevatedButton.styleFrom(
                backgroundColor: DriverColors.primaryDark,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
            ),
          ],
        );

      case 'delivered':
      case 'completed':
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: DriverColors.successLight,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: DriverColors.success.withValues(alpha: 0.3)),
          ),
          child: const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.verified, color: DriverColors.success),
              SizedBox(width: 8),
              Text(
                'Delivery Successfully Completed',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: DriverColors.success,
                ),
              ),
            ],
          ),
        );

      default:
        return const SizedBox.shrink();
    }
  }
}

class _CardContainer extends StatelessWidget {
  final Widget child;
  const _CardContainer({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.standard),
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
      child: child,
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  const _SectionHeader({required this.icon, required this.title});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: DriverColors.primary),
        const SizedBox(width: 6),
        Text(
          title,
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
            color: DriverColors.primaryDark,
            letterSpacing: 0.5,
          ),
        ),
      ],
    );
  }
}

class _ServiceInfo extends StatelessWidget {
  const _ServiceInfo({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(9),
    decoration: BoxDecoration(
      color: DriverColors.mintSurface,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: DriverColors.borderLight),
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 10, color: DriverColors.textSecondary)),
      const SizedBox(height: 3),
      Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: DriverColors.primaryDark)),
    ]),
  );
}
