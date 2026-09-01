import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';
import '../data/delivery_repository.dart';
import 'delivery_details_screen.dart';

class AssignedDeliveriesScreen extends StatefulWidget {
  const AssignedDeliveriesScreen({super.key});

  @override
  State<AssignedDeliveriesScreen> createState() =>
      _AssignedDeliveriesScreenState();
}

class _AssignedDeliveriesScreenState extends State<AssignedDeliveriesScreen> {
  late final DeliveryRepository repository;

  late Future<List<Map<String, dynamic>>> deliveriesFuture;

  @override
  void initState() {
    super.initState();

    repository = DeliveryRepository(ApiClient(TokenStorage()));

    deliveriesFuture = repository.assigned();
  }

  Future<void> _refresh() async {
    setState(() {
      deliveriesFuture = repository.assigned();
    });

    await deliveriesFuture;
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
      appBar: AppBar(title: const Text('Assigned Deliveries')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: deliveriesFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              return Center(
                child: Text(
                  snapshot.error is ApiException
                      ? (snapshot.error as ApiException).message
                      : 'Unable to load deliveries.',
                ),
              );
            }

            final deliveries = snapshot.data ?? [];

            if (deliveries.isEmpty) {
              return const Center(
                child: Text('No deliveries assigned right now.'),
              );
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
                    leading: const CircleAvatar(
                      child: Icon(Icons.local_shipping),
                    ),
                    title: Text('Order #${_shortId(orderId)}'),
                    subtitle: Text('Status: $status'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: deliveryId == null
                        ? null
                        : () async {
                            await Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => DeliveryDetailsScreen(
                                  deliveryId: deliveryId,
                                ),
                              ),
                            );

                            if (mounted) {
                              _refresh();
                            }
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
