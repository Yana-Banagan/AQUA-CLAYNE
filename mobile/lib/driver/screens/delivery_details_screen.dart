import 'package:flutter/material.dart';
import 'delivery_detail_screen.dart';

/// Legacy screen wrapper forwarding calls to the official [DeliveryDetailScreen].
/// This ensures backward compatibility while providing the modern Driver Scaffold,
/// GPS navigation, proof of delivery photo, payment collection, and status workflows.
class DeliveryDetailsScreen extends StatelessWidget {
  final String deliveryId;

  const DeliveryDetailsScreen({
    super.key,
    required this.deliveryId,
  });

  @override
  Widget build(BuildContext context) {
    return DeliveryDetailScreen(id: deliveryId);
  }
}
