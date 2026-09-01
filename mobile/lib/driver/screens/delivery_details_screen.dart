import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';
import '../data/delivery_repository.dart';

class DeliveryDetailsScreen extends StatefulWidget {
  final String deliveryId;

  const DeliveryDetailsScreen({super.key, required this.deliveryId});

  @override
  State<DeliveryDetailsScreen> createState() => _DeliveryDetailsScreenState();
}

class _DeliveryDetailsScreenState extends State<DeliveryDetailsScreen> {
  late final DeliveryRepository repository;

  Map<String, dynamic>? delivery;

  bool loading = true;
  bool updating = false;

  @override
  void initState() {
    super.initState();

    repository = DeliveryRepository(ApiClient(TokenStorage()));

    _loadDetails();
  }

  // ================================================================
  // LOAD DELIVERY
  // ================================================================

  Future<void> _loadDetails() async {
    try {
      final result = await repository.details(widget.deliveryId);

      if (!mounted) return;

      setState(() {
        delivery = result;
        loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        loading = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;

      setState(() {
        loading = false;
      });

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to load delivery.')));
    }
  }

  // ================================================================
  // UPDATE STATUS
  // ================================================================

  Future<void> _updateStatus(String newStatus) async {
    if (updating) return;

    setState(() {
      updating = true;
    });

    try {
      final updated = await repository.updateStatus(
        widget.deliveryId,
        newStatus,
      );

      if (!mounted) return;

      setState(() {
        delivery = updated;
        updating = false;
      });

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Status updated to $newStatus')));
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to update delivery status.')),
      );
    }
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery Details')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery Details')),
        body: const Center(child: Text('Delivery not found.')),
      );
    }

    final order = delivery!['order'] as Map<String, dynamic>?;

    final status = delivery!['status']?.toString() ?? 'unknown';

    final eta = delivery!['eta']?.toString();

    final customer = order?['customer'] as Map<String, dynamic>?;

    final user = customer?['user'] as Map<String, dynamic>?;

    final address = order?['address'] as Map<String, dynamic>?;

    final customerName = user?['name']?.toString() ?? 'Unknown Customer';

    final customerPhone = user?['phone']?.toString() ?? 'No phone number';

    final addressLine = address?['address_line']?.toString() ?? 'No address';

    final items = order?['items'] as List? ?? [];

    return Scaffold(
      appBar: AppBar(title: const Text('Delivery Details')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ========================================================
          // CUSTOMER
          // ========================================================

          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Customer',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  Text(customerName, style: const TextStyle(fontSize: 16)),
                  const SizedBox(height: 6),
                  Text(customerPhone),
                  const SizedBox(height: 12),
                  const Text(
                    'Delivery Address',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Text(addressLine),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),

          // ========================================================
          // STATUS
          // ========================================================
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Delivery Status',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    status,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text('ETA: ${eta ?? 'Not set'}'),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),

          // ========================================================
          // ORDER ITEMS
          // ========================================================
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Order Items',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 10),
                  if (items.isEmpty)
                    const Text('No order items found.')
                  else
                    ...items.map((item) {
                      final itemMap = Map<String, dynamic>.from(item as Map);

                      final product =
                          itemMap['product'] as Map<String, dynamic>?;

                      final name =
                          product?['name']?.toString() ?? 'Unknown Product';

                      final quantity = itemMap['quantity']?.toString() ?? '0';

                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(name),
                        trailing: Text('x$quantity'),
                      );
                    }),
                ],
              ),
            ),
          ),

          const SizedBox(height: 20),

          // ========================================================
          // ASSIGNED -> EN ROUTE
          // ========================================================
          if (status == 'assigned')
            SizedBox(
              height: 50,
              child: ElevatedButton(
                onPressed: updating ? null : () => _updateStatus('en_route'),
                child: updating
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(),
                      )
                    : const Text('Start Delivery'),
              ),
            ),

          // ========================================================
          // EN ROUTE -> DELIVERED / FAILED
          // ========================================================
          if (status == 'en_route') ...[
            SizedBox(
              height: 50,
              child: ElevatedButton(
                onPressed: updating ? null : () => _updateStatus('delivered'),
                child: updating
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(),
                      )
                    : const Text('Mark Delivered'),
              ),
            ),

            const SizedBox(height: 10),

            SizedBox(
              height: 50,
              child: OutlinedButton(
                onPressed: updating ? null : () => _updateStatus('failed'),
                child: const Text('Mark Failed'),
              ),
            ),
          ],

          // ========================================================
          // COMPLETED
          // ========================================================
          if (status == 'delivered' || status == 'failed')
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),
              child: Center(child: Text('This delivery is completed.')),
            ),
        ],
      ),
    );
  }
}
