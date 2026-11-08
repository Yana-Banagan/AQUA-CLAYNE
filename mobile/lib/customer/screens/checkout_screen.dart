// DEPRECATED: This screen has been replaced by the multi-step checkout flow.
// checkout_address_screen.dart → checkout_gallon_screen.dart →
// checkout_payment_screen.dart → checkout_review_screen.dart
//
// This file is kept only to prevent import errors from old references.
// It is not navigated to from any active screen.

import 'package:flutter/material.dart';

class CheckoutScreen extends StatelessWidget {
  const CheckoutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: Text('Checkout is handled by the multi-step flow.')),
    );
  }
}
