import 'package:flutter/material.dart';
import 'payments_screen.dart';

/// Legacy alias for PaymentsScreen so existing routes from profile and home screens
/// seamlessly navigate to the full Payments Dashboard.
class CreditScreen extends StatelessWidget {
  const CreditScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const PaymentsScreen();
  }
}
