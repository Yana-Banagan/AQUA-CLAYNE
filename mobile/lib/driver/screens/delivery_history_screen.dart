import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';
import '../data/delivery_repository.dart';
import 'delivery_details_screen.dart';

class DeliveryHistoryScreen extends StatefulWidget {
  const DeliveryHistoryScreen({super.key});

  @override
  State<DeliveryHistoryScreen> createState() => _DeliveryHistoryScreenState();
}

class _DeliveryHistoryScreenState extends State<DeliveryHistoryScreen> {
  late final DeliveryRepository repository;

  late Future<List<Map<String, dynamic>>> historyFuture;

  @override
  void initState() {
    super.initState();

    repository = DeliveryRepository(ApiClient(TokenStorage()));

    historyFuture = repository.history();
  }

  Future<void> _refresh() async {
    setState(() {
      historyFuture = repository.history();
    });

    await historyFuture;
  }

  String _shortId(dynamic id) {
    if (id == null) {
      return 'Unknown';
    }

    final value = id.toString();

    if (value.length <= 8) {
      return value;
    }

    return value.substring(0, 8);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery History')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: historyFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              return Center(
                child: Text(
                  snapshot.error is ApiException
                      ? (snapshot.error as ApiException).message
                      : 'Unable to load delivery history.',
                ),
              );
            }

            final deliveries = snapshot.data ?? [];

            if (deliveries.isEmpty) {
              return const Center(child: Text('No completed deliveries yet.'));
            }

            return ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: deliveries.length,
              itemBuilder: (context, index) {
                final delivery = deliveries[index];

                final deliveryId = delivery['id']?.toString();

                final orderId = delivery['order_id'];

                final status = delivery['status']?.toString() ?? 'unknown';

                return Card(
                  margin: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  child: ListTile(
                    leading: Icon(
                      status == 'delivered' ? Icons.check_circle : Icons.error,
                    ),
                    title: Text('Order #${_shortId(orderId)}'),
                    subtitle: Text('Status: $status'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: deliveryId == null
                        ? null
                        : () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => DeliveryDetailsScreen(
                                  deliveryId: deliveryId,
                                ),
                              ),
                            );
                          },
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
