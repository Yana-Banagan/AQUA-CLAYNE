import 'package:flutter/material.dart';
import 'package:mobile/core/utils/pht_datetime.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_delivery_card.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../../shared_widgets/loading_view.dart';
import 'delivery_detail_screen.dart';

class DeliveryHistoryScreen extends StatefulWidget {
  const DeliveryHistoryScreen({super.key});

  @override
  State<DeliveryHistoryScreen> createState() => _DeliveryHistoryScreenState();
}

class _DeliveryHistoryScreenState extends State<DeliveryHistoryScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
    });
  }

  Future<void> _load() async {
    await context.read<DriverDeliveryProvider>().loadHistory();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Delivery History',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
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
                Tab(text: 'All Completed'),
                Tab(text: 'Today'),
                Tab(text: 'This Week'),
              ],
            ),
          ),
        ),
      ),
      body: Consumer<DriverDeliveryProvider>(
        builder: (context, prov, _) {
          if (prov.loading && prov.history.isEmpty) {
            return const LoadingView(message: 'Loading delivery history...');
          }

          final now = PhtDateTime.now();

          final today = prov.history.where((d) {
            try {
              final dt = PhtDateTime.parse(d.createdAt);
              return dt.year == now.year &&
                  dt.month == now.month &&
                  dt.day == now.day;
            } catch (_) {
              return false;
            }
          }).toList();

          final weekStart = now.subtract(Duration(days: now.weekday - 1));
          final thisWeek = prov.history.where((d) {
            try {
              final dt = PhtDateTime.parse(d.createdAt);
              return dt.isAfter(weekStart.subtract(const Duration(seconds: 1)));
            } catch (_) {
              return false;
            }
          }).toList();

          return TabBarView(
            controller: _tabs,
            children: [
              _HistoryListTab(
                deliveries: prov.history,
                emptySubtitle: 'No completed deliveries on record yet.',
                onRefresh: _load,
              ),
              _HistoryListTab(
                deliveries: today,
                emptySubtitle: 'No deliveries completed today yet.',
                onRefresh: _load,
              ),
              _HistoryListTab(
                deliveries: thisWeek,
                emptySubtitle: 'No deliveries completed this week.',
                onRefresh: _load,
              ),
            ],
          );
        },
      ),
    );
  }
}

class _HistoryListTab extends StatelessWidget {
  final List<DeliveryModel> deliveries;
  final String emptySubtitle;
  final Future<void> Function() onRefresh;

  const _HistoryListTab({
    required this.deliveries,
    required this.emptySubtitle,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    if (deliveries.isEmpty) {
      return RefreshIndicator(
        color: DriverColors.primary,
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 60, horizontal: 24),
          children: [
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
                    Icons.history,
                    size: 48,
                    color: DriverColors.textMuted,
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'No completed deliveries yet',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.textPrimary,
                    ),
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
        padding: const EdgeInsets.all(AppSpacing.standard),
        itemCount: deliveries.length,
        itemBuilder: (context, index) {
          final d = deliveries[index];
          return DriverDeliveryCard(
            deliveryModel: d,
            showNavigateButton: false,
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
