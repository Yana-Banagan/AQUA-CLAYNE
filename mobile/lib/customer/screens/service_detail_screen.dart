import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/product_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/widgets/guest_order_prompt.dart';
import '../../customer/providers/cart_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../shared_widgets/app_button.dart';
import '../../shared_widgets/gallon_quantity_stepper.dart';
import 'cart_screen.dart';

class ServiceDetailScreen extends StatefulWidget {
  final ProductModel product;
  const ServiceDetailScreen({super.key, required this.product});

  @override
  State<ServiceDetailScreen> createState() => _ServiceDetailScreenState();
}

class _ServiceDetailScreenState extends State<ServiceDetailScreen> {
  int _qty = 1;

  double get _subtotal => widget.product.price * _qty;

  @override
  Widget build(BuildContext context) {
    final p = widget.product;
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Service Details')),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Hero image ──────────────────────────────────────
            Container(
              width: double.infinity,
              height: 200,
              color: AppColors.infoLight,
              child: const Center(
                child: Icon(Icons.water_drop_rounded,
                    size: 90, color: AppColors.primary),
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(AppSpacing.standard),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ── Name & Price ─────────────────────────────
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(p.name,
                            style: const TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary)),
                      ),
                      Text(
                        '₱${p.price.toStringAsFixed(2)}',
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: AppColors.primary),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text('per refill',
                      style: TextStyle(
                          fontSize: 13, color: AppColors.textSecondary)),

                  // ── Availability ─────────────────────────────
                  const SizedBox(height: AppSpacing.compact),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: p.isAvailable
                          ? AppColors.successLight
                          : AppColors.errorLight,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      p.isAvailable ? 'Available' : 'Currently Unavailable',
                      style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: p.isAvailable
                              ? AppColors.success
                              : AppColors.error),
                    ),
                  ),

                  // ── Description ──────────────────────────────
                  if (p.description != null &&
                      p.description!.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.standard),
                    const Text('About this service',
                        style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary)),
                    const SizedBox(height: 6),
                    Text(p.description!,
                        style: const TextStyle(
                            fontSize: 14,
                            color: AppColors.textSecondary,
                            height: 1.5)),
                  ],

                  // ── Customer-owned gallon notice ─────────────
                  const SizedBox(height: AppSpacing.standard),
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.compact),
                    decoration: BoxDecoration(
                      color: AppColors.warningLight,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                          color: AppColors.warning.withValues(alpha: 0.3)),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.info_outline,
                            color: AppColors.warning, size: 20),
                        SizedBox(width: AppSpacing.small),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('CUSTOMER-OWNED GALLONS',
                                  style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.warning,
                                      letterSpacing: 0.5)),
                              SizedBox(height: 3),
                              Text(
                                'Please prepare your empty gallons. They will be collected by the driver for refilling.',
                                style: TextStyle(
                                    fontSize: 12,
                                    color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  // ── Quantity selector ────────────────────────
                  const SizedBox(height: AppSpacing.section),
                  const Text('Quantity',
                      style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary)),
                  const SizedBox(height: AppSpacing.compact),
                  Row(
                    children: [
                      GallonQuantityStepper(
                        value: _qty,
                        onChanged: (v) => setState(() => _qty = v),
                      ),
                      const Spacer(),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          const Text('Subtotal',
                              style: TextStyle(
                                  fontSize: 12,
                                  color: AppColors.textSecondary)),
                          Text(
                            '₱${_subtotal.toStringAsFixed(2)}',
                            style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primary),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.major),

                  // ── Add to Cart ──────────────────────────────
                  AppButton(
                    label: 'Add to Cart',
                    icon: Icons.shopping_cart_outlined,
                    onPressed: p.isAvailable
                        ? () async {
                            if (!context.read<AuthProvider>().isAuthenticated) {
                              await showGuestOrderPrompt(context);
                              return;
                            }
                            final cart = context.read<CartProvider>();
                            for (var i = 0; i < _qty; i++) {
                              cart.add(p);
                            }
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content:
                                    Text('$_qty × ${p.name} added to cart'),
                                duration: const Duration(seconds: 3),
                                action: SnackBarAction(
                                  label: 'View Cart',
                                  onPressed: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => const CartScreen()),
                                  ),
                                ),
                              ),
                            );
                            Navigator.pop(context);
                          }
                        : null,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
