import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/driver_delivery_provider.dart';
import 'delivery_details_screen.dart';

class AssignedDeliveriesScreen extends StatefulWidget {
  const AssignedDeliveriesScreen({super.key});

  @override
  State<AssignedDeliveriesScreen> createState() =>
      _AssignedDeliveriesScreenState();
}

class _AssignedDeliveriesScreenState extends State<AssignedDeliveriesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && context.read<DriverDeliveryProvider>().assigned.isEmpty) {
        context.read<DriverDeliveryProvider>().loadAssigned();
      }
    });
  }

  Future<void> _refresh() async {
    await context.read<DriverDeliveryProvider>().loadAssigned();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Assigned Deliveries')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: Consumer<DriverDeliveryProvider>(
          builder: (context, provider, _) {
            if (provider.loading && provider.assigned.isEmpty) {
              return const Center(child: CircularProgressIndicator());
            }

            if (provider.error != null && provider.assigned.isEmpty) {
              return Center(
                child: Text(
                  provider.error ?? 'Unable to load deliveries.',
                ),
              );
            }

            final deliveries = provider.assigned;

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

                final deliveryId = delivery.id;

                return Card(
                  margin: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  child: ListTile(
                    leading: const CircleAvatar(
                      child: Icon(Icons.local_shipping),
                    ),
                    title: Text('Order #${delivery.orderNumber}'),
                    subtitle: Text('Status: ${delivery.status}'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: deliveryId.isEmpty
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
                              await _refresh();
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
