import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';
import '../data/delivery_repository.dart';
import 'assigned_deliveries_screen.dart';
import 'delivery_history_screen.dart';

class DriverHomeScreen extends StatefulWidget {
  const DriverHomeScreen({super.key});

  @override
  State<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  late final DeliveryRepository deliveryRepository;

  bool loading = true;
  int assignedCount = 0;

  String driverName = 'Driver';

  @override
  void initState() {
    super.initState();

    deliveryRepository = DeliveryRepository(ApiClient(TokenStorage()));

    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    try {
      final deliveries = await deliveryRepository.assigned();

      if (!mounted) return;

      setState(() {
        assignedCount = deliveries.length;
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

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to load dashboard.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Hello, $driverName')),
      body: RefreshIndicator(
        onRefresh: _loadDashboard,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const SizedBox(height: 10),

            const Text(
              'Driver Dashboard',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 20),

            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Assigned Deliveries',
                      style: TextStyle(fontSize: 16),
                    ),

                    const SizedBox(height: 10),

                    loading
                        ? const CircularProgressIndicator()
                        : Text(
                            '$assignedCount',
                            style: const TextStyle(
                              fontSize: 36,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 20),

            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                icon: const Icon(Icons.local_shipping),
                label: const Text('View Assigned Deliveries'),
                onPressed: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const AssignedDeliveriesScreen(),
                    ),
                  );

                  if (mounted) {
                    _loadDashboard();
                  }
                },
              ),
            ),

            const SizedBox(height: 12),

            SizedBox(
              height: 52,
              child: OutlinedButton.icon(
                icon: const Icon(Icons.history),
                label: const Text('Delivery History'),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const DeliveryHistoryScreen(),
                    ),
                  );
                },
              ),
            ),

            const SizedBox(height: 30),

            const Card(
              child: ListTile(
                leading: Icon(Icons.person),
                title: Text('Driver Profile'),
                subtitle: Text('Profile information'),
                trailing: Icon(Icons.chevron_right),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
