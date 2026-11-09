import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/address_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../core/models/address_model.dart';
import '../../core/utils/delivery_restriction.dart';
import '../../shared_widgets/confirmation_dialog.dart';
import '../../core/config/station_config.dart';
import 'add_address_screen.dart';
import 'checkout_gallon_screen.dart';
import 'checkout_step_bar.dart';

class CheckoutAddressScreen extends StatefulWidget {
  const CheckoutAddressScreen({super.key});

  @override
  State<CheckoutAddressScreen> createState() => _CheckoutAddressScreenState();
}

class _CheckoutAddressScreenState extends State<CheckoutAddressScreen> {
  String? _selectedId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final prov = context.read<AddressProvider>();
      await prov.load();
      if (mounted) {
        final def = prov.defaultAddress;
        if (def != null) {
          setState(() => _selectedId = def.id);
        } else if (prov.addresses.isNotEmpty) {
          setState(() => _selectedId = prov.addresses.first.id);
        }
      }
    });
  }

  Future<void> _navigateToAddAddress() async {
    final newAddressId = await Navigator.push<String?>(
      context,
      MaterialPageRoute(builder: (_) => const AddAddressScreen()),
    );
    if (!mounted) return;
    await context.read<AddressProvider>().load();
    if (newAddressId != null && mounted) {
      setState(() => _selectedId = newAddressId);
    }
  }

  Future<void> _navigateToEditAddress(AddressModel address) async {
    final savedAddressId = await Navigator.push<String?>(
      context,
      MaterialPageRoute(builder: (_) => AddAddressScreen(address: address)),
    );
    if (!mounted) return;

    await context.read<AddressProvider>().load();
    if (savedAddressId != null && mounted) {
      setState(() => _selectedId = savedAddressId);
    }
  }

  Future<void> _deleteAddress(AddressModel address) async {
    final confirmed = await showConfirmationDialog(
      context,
      title: 'Delete Address?',
      message:
          'Are you sure you want to delete "${address.label}"? This action cannot be undone.',
      confirmLabel: 'Delete',
      isDanger: true,
    );
    if (!confirmed || !mounted) return;

    final provider = context.read<AddressProvider>();
    final deleted = await provider.delete(address.id);
    if (!mounted) return;

    if (!deleted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(provider.error ?? 'Failed to delete address.'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
      return;
    }

    if (_selectedId == address.id) {
      final nextAddress = provider.defaultAddress;
      setState(() => _selectedId = nextAddress?.id);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen background image ────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Content ─────────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context, cart),
                const CheckoutStepBar(step: 1),
                _buildFulfillmentSelector(context, cart),
                Expanded(
                  child: cart.isStationPickup
                      ? _buildStationPickupView(context)
                      : Consumer<AddressProvider>(
                          builder: (context, prov, _) {
                            if (prov.loading && prov.addresses.isEmpty) {
                              return _buildLoadingState();
                            }

                            if (prov.addresses.isEmpty) {
                              return _buildEmptyState();
                            }

                            return RefreshIndicator(
                              color: const Color(0xFF0878C9),
                              backgroundColor: Colors.white,
                              onRefresh: () => prov.load(),
                              child: ListView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding:
                                    const EdgeInsets.fromLTRB(16, 6, 16, 20),
                                children: [
                                  // Section label
                                  const Padding(
                                    padding:
                                        EdgeInsets.only(left: 4, bottom: 10),
                                    child: Text(
                                      'Saved Delivery Addresses',
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF12344D),
                                        letterSpacing: 0.2,
                                      ),
                                    ),
                                  ),

                                  // Addresses
                                  ...prov.addresses.map((address) {
                                    final isSelected =
                                        _selectedId == address.id;
                                    return Padding(
                                      padding:
                                          const EdgeInsets.only(bottom: 12),
                                      child: _buildAddressCard(
                                          address, isSelected),
                                    );
                                  }),

                                  const SizedBox(height: 6),

                                  // Add New Address Card / Button
                                  _buildAddNewAddressButton(),
                                ],
                              ),
                            );
                          },
                        ),
                ),

                // Bottom Sticky Continue Action
                _buildBottomAction(cart),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context, CartProvider cart) {
    final isPickup = cart.isStationPickup;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          // Circular Back Button
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => Navigator.of(context).pop(),
              borderRadius: BorderRadius.circular(21),
              child: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFD5E7F4), width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.arrow_back_rounded,
                  color: Color(0xFF0878C9),
                  size: 22,
                ),
              ),
            ),
          ),
          const SizedBox(width: 14),
          // Screen Title & Subtitle
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isPickup ? 'Station Pickup' : 'Select Delivery Address',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  isPickup
                      ? 'Pick up your refill directly at the Aqua Clayne station'
                      : 'Choose where your refill will be delivered',
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF6F8BA3),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // FULFILLMENT METHOD SELECTOR (HOME DELIVERY vs STATION PICKUP)
  // ====================================================================
  Widget _buildFulfillmentSelector(BuildContext context, CartProvider cart) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 6, 16, 12),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF4FC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFCCE4F7)),
      ),
      child: Row(
        children: [
          // Home Delivery Option
          Expanded(
            child: InkWell(
              onTap: () => cart.setDeliveryMethod('home_delivery'),
              borderRadius: BorderRadius.circular(12),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
                decoration: BoxDecoration(
                  color: cart.isHomeDelivery ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: cart.isHomeDelivery
                      ? [
                          BoxShadow(
                            color: const Color(0xFF0878C9).withValues(alpha: 0.12),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.delivery_dining_rounded,
                      size: 22,
                      color: cart.isHomeDelivery
                          ? const Color(0xFF0878C9)
                          : const Color(0xFF6F8BA3),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Home Delivery',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: cart.isHomeDelivery
                                ? FontWeight.w800
                                : FontWeight.w600,
                            color: cart.isHomeDelivery
                                ? const Color(0xFF12344D)
                                : const Color(0xFF6F8BA3),
                          ),
                        ),
                        Text(
                          '₱5.00 Fee',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                            color: cart.isHomeDelivery
                                ? const Color(0xFF0878C9)
                                : const Color(0xFF8FA9BF),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Station Pickup Option
          Expanded(
            child: InkWell(
              onTap: () => cart.setDeliveryMethod('station_pickup'),
              borderRadius: BorderRadius.circular(12),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
                decoration: BoxDecoration(
                  color: cart.isStationPickup ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: cart.isStationPickup
                      ? [
                          BoxShadow(
                            color: const Color(0xFF059669).withValues(alpha: 0.12),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.storefront_rounded,
                      size: 22,
                      color: cart.isStationPickup
                          ? const Color(0xFF059669)
                          : const Color(0xFF6F8BA3),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Station Pickup',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: cart.isStationPickup
                                ? FontWeight.w800
                                : FontWeight.w600,
                            color: cart.isStationPickup
                                ? const Color(0xFF12344D)
                                : const Color(0xFF6F8BA3),
                          ),
                        ),
                        Text(
                          'FREE (₱0.00)',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w700,
                            color: cart.isStationPickup
                                ? const Color(0xFF059669)
                                : const Color(0xFF8FA9BF),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // STATION PICKUP VIEW
  // ====================================================================
  Widget _buildStationPickupView(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 24),
      children: [
        // Highlight Badge Banner
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFFECFDF5), Color(0xFFD1FAE5)],
            ),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFA7F3D0)),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
                child: const Center(
                  child: Icon(
                    Icons.store_rounded,
                    color: Color(0xFF059669),
                    size: 22,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Direct Station Pickup Selected',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF065F46),
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Collect your water refill at the Aqua Clayne counter with ₱0 delivery fee.',
                      style: TextStyle(
                        fontSize: 12,
                        color: Color(0xFF047857),
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Station Details Card
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFD5E7F4)),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE0F2FE),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Text(
                      'PRIMARY REFILLING HUB',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0369A1),
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                  const Spacer(),
                  const Text(
                    'OPEN NOW',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF059669),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Text(
                'Aqua Clayne Refilling Station',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 6),
              const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.location_on_rounded,
                    size: 16,
                    color: Color(0xFF0878C9),
                  ),
                  SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      StationConfig.formattedStationCard,
                      style: TextStyle(
                        fontSize: 13,
                        color: Color(0xFF52718B),
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Divider(color: Color(0xFFEAF4FC)),
              const SizedBox(height: 10),
              const Row(
                children: [
                  Icon(
                    Icons.access_time_rounded,
                    size: 16,
                    color: Color(0xFF6F8BA3),
                  ),
                  SizedBox(width: 6),
                  Text(
                    'Operating Hours: Mon - Sat 7:00 AM - 6:00 PM',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF12344D),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Pickup Instructions
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'HOW STATION PICKUP WORKS',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF64748B),
                  letterSpacing: 0.8,
                ),
              ),
              const SizedBox(height: 10),
              _buildStepRow('1', 'Submit your order and choose your payment method.'),
              const SizedBox(height: 8),
              _buildStepRow('2', 'Bring your clean empty gallons to the Aqua Clayne station counter.'),
              const SizedBox(height: 8),
              _buildStepRow('3', 'Track queue status in real time and collect your filled water containers once ready!'),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStepRow(String number, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 20,
          height: 20,
          decoration: const BoxDecoration(
            color: Color(0xFF0878C9),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              number,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 12.5,
              color: Color(0xFF334155),
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // ADDRESS CARD
  // ====================================================================
  Widget _buildAddressCard(AddressModel address, bool isSelected) {
    final isAllowed = DeliveryRestriction.isAddressAllowed(address);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isSelected
              ? (isAllowed ? const Color(0xFF0878C9) : const Color(0xFFDC2626))
              : const Color(0xFFE5F1FA),
          width: isSelected ? 2.0 : 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: isSelected
                ? (isAllowed ? const Color(0xFF0878C9) : const Color(0xFFDC2626)).withValues(alpha: 0.12)
                : const Color(0xFF0878C9).withValues(alpha: 0.04),
            blurRadius: isSelected ? 12 : 6,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            setState(() => _selectedId = address.id);
            if (!isAllowed) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text(DeliveryRestriction.restrictionMessage),
                  backgroundColor: Color(0xFFC62828),
                  duration: Duration(seconds: 4),
                ),
              );
            }
          },
          borderRadius: BorderRadius.circular(18),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Radio button indicator
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Container(
                    width: 22,
                    height: 22,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: isSelected
                          ? const Color(0xFF0878C9)
                          : Colors.transparent,
                      border: Border.all(
                        color: isSelected
                            ? const Color(0xFF0878C9)
                            : const Color(0xFFB0C5D8),
                        width: 2,
                      ),
                    ),
                    child: isSelected
                        ? const Center(
                            child: Icon(
                              Icons.circle,
                              size: 10,
                              color: Colors.white,
                            ),
                          )
                        : null,
                  ),
                ),
                const SizedBox(width: 14),

                // Address info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Label + Default Badge
                      Row(
                        children: [
                          Icon(
                            address.label.toLowerCase().contains('work') ||
                                    address.label.toLowerCase().contains('office')
                                ? Icons.business_rounded
                                : Icons.home_rounded,
                            size: 17,
                            color: isSelected
                                ? const Color(0xFF0878C9)
                                : const Color(0xFF6F8BA3),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            address.label,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: isSelected
                                  ? const Color(0xFF0878C9)
                                  : const Color(0xFF12344D),
                            ),
                          ),
                          if (address.isDefault) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEAF7FF),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(
                                    color: const Color(0xFFBCE3FD), width: 0.8),
                              ),
                              child: const Text(
                                'Default',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF0878C9),
                                ),
                              ),
                            ),
                          ],
                          if (!isAllowed) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF2F2),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(
                                    color: const Color(0xFFFECACA), width: 0.8),
                              ),
                              child: const Text(
                                'Outside Area',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFFDC2626),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 6),

                      // Full address text
                      Text(
                        address.fullAddress,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF4A6882),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          TextButton.icon(
                            onPressed: () => _navigateToEditAddress(address),
                            icon: const Icon(
                              Icons.edit_outlined,
                              size: 17,
                              color: Color(0xFF0878C9),
                            ),
                            label: const Text('Edit'),
                            style: TextButton.styleFrom(
                              foregroundColor: const Color(0xFF0878C9),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 6,
                              ),
                              textStyle: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          const SizedBox(width: 4),
                          TextButton.icon(
                            onPressed: () => _deleteAddress(address),
                            icon: const Icon(
                              Icons.delete_outline_rounded,
                              size: 17,
                              color: Color(0xFFDC2626),
                            ),
                            label: const Text('Delete'),
                            style: TextButton.styleFrom(
                              foregroundColor: const Color(0xFFDC2626),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 6,
                              ),
                              textStyle: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // ADD NEW ADDRESS BUTTON
  // ====================================================================
  Widget _buildAddNewAddressButton() {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _navigateToAddAddress,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.8),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: const Color(0xFF0878C9).withValues(alpha: 0.35),
              width: 1.2,
            ),
          ),
          child: const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.add_location_alt_outlined,
                  color: Color(0xFF0878C9), size: 20),
              SizedBox(width: 8),
              Text(
                'Add New Delivery Address',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF0878C9),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // LOADING STATE
  // ====================================================================
  Widget _buildLoadingState() {
    return const Center(
      child: CircularProgressIndicator(
        color: Color(0xFF0878C9),
        strokeWidth: 2.5,
      ),
    );
  }

  // ====================================================================
  // EMPTY STATE
  // ====================================================================
  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF7FF),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFBCE3FD)),
              ),
              child: const Icon(
                Icons.location_off_outlined,
                color: Color(0xFF0878C9),
                size: 38,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'No Saved Addresses',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF12344D),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Please add a delivery address to continue with your refill order.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
                height: 1.4,
              ),
            ),
            const SizedBox(height: 22),
            ElevatedButton.icon(
              onPressed: _navigateToAddAddress,
              icon: const Icon(Icons.add_rounded, size: 18),
              label: const Text(
                'Add Delivery Address',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ====================================================================
  // BOTTOM STICKY ACTION
  // ====================================================================
  Widget _buildBottomAction(CartProvider cart) {
    final isStationPickup = cart.isStationPickup;
    final isEnabled = isStationPickup || _selectedId != null;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFE5F1FA), width: 1),
        ),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton(
            onPressed: isEnabled
                ? () {
                    if (!isStationPickup) {
                      final prov = context.read<AddressProvider>();
                      final selected = prov.addresses
                          .where((a) => a.id == _selectedId)
                          .firstOrNull;
                      if (selected == null ||
                          !DeliveryRestriction.isAddressAllowed(selected)) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content:
                                Text(DeliveryRestriction.restrictionMessage),
                            backgroundColor: Color(0xFFC62828),
                            duration: Duration(seconds: 4),
                          ),
                        );
                        return;
                      }
                    }
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => CheckoutGallonScreen(
                          addressId: isStationPickup ? null : _selectedId,
                        ),
                      ),
                    );
                  }
                : null,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0878C9),
              foregroundColor: Colors.white,
              disabledBackgroundColor: const Color(0xFFD3E4F0),
              disabledForegroundColor: const Color(0xFF8FA9BF),
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  isStationPickup
                      ? 'Continue with Station Pickup'
                      : 'Continue to Gallon Info',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 8),
                const Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
