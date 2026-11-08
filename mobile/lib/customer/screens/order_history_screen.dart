import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/order_repository.dart';
import '../../core/services/realtime_service.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  VoidCallback? _removeRealtimeListener;

  @override
  void initState() {
    super.initState();

    // Initial order history load
    _future = context.read<OrderRepository>().list();

    // Listen for delivery status changes
    //
    // Driver status changes create a realtime delivery signal.
    // Laravel also updates the corresponding order status.
    _removeRealtimeListener = context.read<RealtimeService>().listenToEntity(
      entityType: 'delivery',
      onSignal: _reload,
    );
  }

  void _reload() {
    if (!mounted) {
      return;
    }

    setState(() {
      _future = context.read<OrderRepository>().list();
    });
  }

  Future<void> _refresh() async {
    _reload();

    await _future;
  }

  @override
  void dispose() {
    _removeRealtimeListener?.call();

    super.dispose();
  }

  String _shortOrderId(dynamic value) {
    final id = value?.toString() ?? '';

    if (id.isEmpty) {
      return 'Unknown';
    }

    // Show only the first 8 characters.
    if (id.length > 8) {
      return id.substring(0, 8).toUpperCase();
    }

    return id.toUpperCase();
  }

  String _formatStatus(dynamic value) {
    final status = value?.toString().trim() ?? '';

    if (status.isEmpty) {
      return 'Unknown';
    }

    return status
        .replaceAll('_', ' ')
        .split(' ')
        .map(
          (word) => word.isEmpty
              ? ''
              : '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  double _parseTotal(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString() ?? '0') ?? 0.0;
  }

  int _getItemCount(Map<String, dynamic> order) {
    final value = order['item_count'];

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '0') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Order History')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          // --------------------------------------------------
          // LOADING
          // --------------------------------------------------

          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          // --------------------------------------------------
          // ERROR
          // --------------------------------------------------

          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline, size: 60),

                    const SizedBox(height: 16),

                    const Text(
                      'Failed to load orders.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      snapshot.error.toString(),
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 20),

                    ElevatedButton.icon(
                      onPressed: _reload,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final orders = snapshot.data ?? [];

          // --------------------------------------------------
          // EMPTY
          // --------------------------------------------------

          if (orders.isEmpty) {
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 180),

                  Icon(Icons.receipt_long_outlined, size: 70),

                  SizedBox(height: 16),

                  Center(
                    child: Text(
                      'No orders yet.',
                      style: TextStyle(
                        fontSize: 21,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),

                  SizedBox(height: 8),

                  Center(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 24),
                      child: Text(
                        'Your completed and current orders will appear here.',
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
                ],
              ),
            );
          }

          // --------------------------------------------------
          // ORDER LIST
          // --------------------------------------------------

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: orders.length,
              itemBuilder: (context, index) {
                final order = orders[index];

                return _OrderCard(
                  order: order,
                  shortOrderId: _shortOrderId(order['id']),
                  status: _formatStatus(order['status']),
                  total: _parseTotal(order['total']),
                  itemCount: _getItemCount(order),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

// ============================================================
// ORDER CARD
// ============================================================

class _OrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final String shortOrderId;
  final String status;
  final double total;
  final int itemCount;

  const _OrderCard({
    required this.order,
    required this.shortOrderId,
    required this.status,
    required this.total,
    required this.itemCount,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ------------------------------------------------
            // ORDER ID
            // ------------------------------------------------

            Row(
              children: [
                const Icon(Icons.receipt_long_outlined),

                const SizedBox(width: 8),

                Expanded(
                  child: Text(
                    'Order #$shortOrderId',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),

                _StatusBadge(status: status),
              ],
            ),

            const SizedBox(height: 16),

            // ------------------------------------------------
            // TOTAL
            // ------------------------------------------------
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Total', style: TextStyle(fontSize: 16)),

                Text(
                  '₱${total.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 10),

            // ------------------------------------------------
            // ITEM COUNT
            // ------------------------------------------------
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Items', style: TextStyle(fontSize: 16)),

                Text(
                  '$itemCount ${itemCount == 1 ? 'item' : 'items'}',
                  style: TextStyle(fontSize: 16, color: Colors.grey.shade700),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// STATUS BADGE
// ============================================================

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: _statusColor(status).withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        status,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.bold,
          color: _statusColor(status),
        ),
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return Colors.orange;

      case 'confirmed':
        return Colors.blue;

      case 'preparing':
        return Colors.deepPurple;

      case 'out for delivery':
      case 'out_for_delivery':
        return Colors.indigo;

      case 'delivered':
        return Colors.green;

      case 'completed':
        return Colors.green;

      case 'cancelled':
      case 'canceled':
        return Colors.red;

      default:
        return Colors.grey;
    }
  }
}
