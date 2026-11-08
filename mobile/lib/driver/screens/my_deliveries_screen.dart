import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_delivery_card.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import 'delivery_detail_screen.dart';
import 'pickup_batches_screen.dart';
import 'delivery_routes_screen.dart';

class MyDeliveriesScreen extends StatefulWidget {
  final int initialTab;
  const MyDeliveriesScreen({super.key, this.initialTab = 0});

  @override
  State<MyDeliveriesScreen> createState() => _MyDeliveriesScreenState();
}

class _MyDeliveriesScreenState extends State<MyDeliveriesScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final TextEditingController _searchCtrl = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabs = TabController(
      length: 3,
      vsync: this,
      initialIndex: widget.initialTab.clamp(0, 2),
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refresh();
    });
  }

  Future<void> _refresh() async {
    final prov = context.read<DriverDeliveryProvider>();
    await Future.wait([
      prov.loadAssigned(),
      prov.loadHistory(),
      prov.loadPickupBatches(),
      prov.loadDeliveryRoutes(),
    ]);
  }

  @override
  void dispose() {
    _tabs.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  List<DeliveryModel> _filter(List<DeliveryModel> list) {
    if (_searchQuery.isEmpty) return list;
    final q = _searchQuery.toLowerCase();
    return list.where((d) {
      return d.orderNumber.toLowerCase().contains(q) ||
          d.customerName.toLowerCase().contains(q) ||
          d.fullAddress.toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'My Deliveries',
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
            tooltip: 'Pickup Batches',
            icon: const Icon(Icons.inventory_2_outlined),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const PickupBatchesScreen()),
            ).then((_) => _refresh()),
          ),
          IconButton(
            tooltip: 'Delivery Routes',
            icon: const Icon(Icons.alt_route_rounded),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DeliveryRoutesScreen()),
            ).then((_) => _refresh()),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: DriverColors.borderLight),
            ),
            child: TabBar(
              controller: _tabs,
              labelColor: Colors.white,
              unselectedLabelColor: DriverColors.textSecondary,
              indicatorSize: TabBarIndicatorSize.tab,
              indicator: BoxDecoration(
                color: DriverColors.primary,
                borderRadius: BorderRadius.circular(12),
              ),
              labelStyle: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 13,
              ),
              unselectedLabelStyle: const TextStyle(
                fontWeight: FontWeight.w500,
                fontSize: 13,
              ),
              dividerColor: Colors.transparent,
              tabs: const [
                Tab(text: 'All Active'),
                Tab(text: 'Pickup'),
                Tab(text: 'Delivery'),
              ],
            ),
          ),
        ),
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (context, prov, _) {
          if (prov.loading && prov.assigned.isEmpty) {
            return const LoadingView(message: 'Loading your deliveries...');
          }

          final allActive = _filter(prov.assigned);
          final pickup = _filter(prov.pickupPhase);
          final delivery = _filter(prov.deliveryPhase);

          return Column(
            children: [
              // Search input
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.standard,
                  vertical: AppSpacing.compact,
                ),
                child: Container(
                  decoration: BoxDecoration(
                    color: DriverColors.cardTranslucent,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: DriverColors.borderLight),
                  ),
                  child: TextField(
                    controller: _searchCtrl,
                    onChanged: (val) => setState(() => _searchQuery = val.trim()),
                    style: const TextStyle(
                      fontSize: 14,
                      color: DriverColors.textPrimary,
                    ),
                    decoration: InputDecoration(
                      hintText: 'Search order #, customer, address...',
                      hintStyle: const TextStyle(
                        fontSize: 13,
                        color: DriverColors.textHint,
                      ),
                      prefixIcon: const Icon(
                        Icons.search,
                        color: DriverColors.primary,
                        size: 20,
                      ),
                      suffixIcon: _searchQuery.isNotEmpty
                          ? IconButton(
                              icon: const Icon(
                                Icons.clear,
                                size: 18,
                                color: DriverColors.textMuted,
                              ),
                              onPressed: () {
                                _searchCtrl.clear();
                                setState(() => _searchQuery = '');
                              },
                            )
                          : null,
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(
                        vertical: 12,
                        horizontal: 12,
                      ),
                    ),
                  ),
                ),
              ),

              // Tab contents
              Expanded(
                child: TabBarView(
                  controller: _tabs,
                  children: [
                    _DeliveryListTab(
                      deliveries: allActive,
                      emptyTitle: 'No active deliveries',
                      emptySubtitle:
                          'You do not have any assigned deliveries right now.',
                      onRefresh: _refresh,
                    ),
                    _DeliveryListTab(
                      deliveries: pickup,
                      emptyTitle: 'No individual pickup tasks',
                      emptySubtitle:
                          'No individual orders in pickup phase. Check your assigned Pickup Batches above.',
                      onRefresh: _refresh,
                      headerWidget: prov.pickupBatches.isNotEmpty
                          ? Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: DriverColors.mintSoft,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: DriverColors.border),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.inventory_2_outlined, color: DriverColors.primary, size: 22),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '${prov.pickupBatches.length} Pickup Batches Assigned',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 13,
                                            color: DriverColors.primaryDark,
                                          ),
                                        ),
                                        const Text(
                                          'Multi-stop vehicle pickup workflow',
                                          style: TextStyle(fontSize: 11, color: DriverColors.textSecondary),
                                        ),
                                      ],
                                    ),
                                  ),
                                  ElevatedButton(
                                    onPressed: () => Navigator.push(
                                      context,
                                      MaterialPageRoute(builder: (_) => const PickupBatchesScreen()),
                                    ).then((_) => _refresh()),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: DriverColors.primary,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                      visualDensity: VisualDensity.compact,
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                    child: const Text('Open Batches', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                  ),
                                ],
                              ),
                            )
                          : null,
                    ),
                    _DeliveryListTab(
                      deliveries: delivery,
                      emptyTitle: 'No individual delivery tasks',
                      emptySubtitle:
                          'No individual orders ready for delivery. Check your assigned Delivery Routes above.',
                      onRefresh: _refresh,
                      headerWidget: prov.deliveryRoutes.isNotEmpty
                          ? Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: DriverColors.mintSoft,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: DriverColors.border),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.alt_route_rounded, color: DriverColors.primary, size: 22),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '${prov.deliveryRoutes.length} Delivery Routes Assigned',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 13,
                                            color: DriverColors.primaryDark,
                                          ),
                                        ),
                                        const Text(
                                          'Multi-stop vehicle drop-off sequence',
                                          style: TextStyle(fontSize: 11, color: DriverColors.textSecondary),
                                        ),
                                      ],
                                    ),
                                  ),
                                  ElevatedButton(
                                    onPressed: () => Navigator.push(
                                      context,
                                      MaterialPageRoute(builder: (_) => const DeliveryRoutesScreen()),
                                    ).then((_) => _refresh()),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: DriverColors.primary,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                      visualDensity: VisualDensity.compact,
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                    child: const Text('Open Routes', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                  ),
                                ],
                              ),
                            )
                          : null,
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _DeliveryListTab extends StatelessWidget {
  final List<DeliveryModel> deliveries;
  final String emptyTitle;
  final String emptySubtitle;
  final Future<void> Function() onRefresh;
  final Widget? headerWidget;

  const _DeliveryListTab({
    required this.deliveries,
    required this.emptyTitle,
    required this.emptySubtitle,
    required this.onRefresh,
    this.headerWidget,
  });

  @override
  Widget build(BuildContext context) {
    if (deliveries.isEmpty) {
      return RefreshIndicator(
        color: DriverColors.primary,
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 24),
          children: [
            ?headerWidget,
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(32),
              decoration: BoxDecoration(
                color: DriverColors.cardTranslucent,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: DriverColors.borderLight),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.inventory_2_outlined,
                    size: 48,
                    color: DriverColors.textMuted,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    emptyTitle,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.textPrimary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    emptySubtitle,
                    style: const TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: DriverColors.primary,
      onRefresh: onRefresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.standard,
          vertical: AppSpacing.compact,
        ),
        itemCount: deliveries.length + (headerWidget != null ? 1 : 0),
        itemBuilder: (context, index) {
          if (headerWidget != null) {
            if (index == 0) return headerWidget!;
            index -= 1;
          }
          final d = deliveries[index];
          return DriverDeliveryCard(
            deliveryModel: d,
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => DeliveryDetailScreen(id: d.id),
              ),
            ).then((_) => onRefresh()),
          );
        },
      ),
    );
  }
}
