import 'package:flutter/material.dart';
import 'water_refill_services_screen.dart';

export 'water_refill_services_screen.dart';

/// Legacy alias for WaterRefillServicesScreen so existing routes from
/// customer home, orders, payments, and profile seamlessly open the
/// unified Water Refill Services Dashboard.
class CatalogScreen extends StatelessWidget {
  const CatalogScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const WaterRefillServicesScreen();
  }
}
