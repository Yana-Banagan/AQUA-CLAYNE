import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/address_provider.dart';
import '../../core/models/address_model.dart';
import '../../shared_widgets/confirmation_dialog.dart';
import 'add_address_screen.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AddressProvider>().load();
    });
  }

  Future<void> _navigateToAddAddress([AddressModel? address]) async {
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => AddAddressScreen(address: address),
      ),
    );

    if (result == true && mounted) {
      context.read<AddressProvider>().load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen Aqua Clayne water background ──────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Screen Content ────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context),
                Expanded(
                  child: Consumer<AddressProvider>(
                    builder: (context, prov, _) {
                      if (prov.loading && prov.addresses.isEmpty) {
                        return _buildLoadingSkeleton();
                      }

                      if (prov.error != null && prov.addresses.isEmpty) {
                        return _buildErrorState(prov);
                      }

                      if (prov.addresses.isEmpty) {
                        return _buildEmptyState();
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: prov.load,
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: prov.addresses.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 12),
                          itemBuilder: (ctx, i) {
                            final addr = prov.addresses[i];
                            return _buildAddressCard(ctx, addr, prov);
                          },
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: FloatingActionButton.extended(
          onPressed: () => _navigateToAddAddress(),
          icon: const Icon(Icons.add_location_alt_rounded, size: 20),
          label: const Text(
            'Add New Address',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
          ),
          backgroundColor: const Color(0xFF0878C9),
          foregroundColor: Colors.white,
          elevation: 4,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
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
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'My Addresses',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Manage your delivery addresses',
                  style: TextStyle(
                    fontSize: 12,
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
  // ADDRESS CARD
  // ====================================================================
  Widget _buildAddressCard(
    BuildContext context,
    AddressModel address,
    AddressProvider prov,
  ) {
    final isDefault = address.isDefault;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isDefault ? const Color(0xFF0878C9) : const Color(0xFFE5F1FA),
          width: isDefault ? 1.6 : 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: isDefault ? 0.08 : 0.04),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Icon Avatar
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: isDefault
                        ? const Color(0xFFEAF7FF)
                        : const Color(0xFFF4F9FD),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isDefault
                          ? const Color(0xFFBCE3FD)
                          : const Color(0xFFE5F1FA),
                    ),
                  ),
                  child: Icon(
                    _getAddressIcon(address.label),
                    color: const Color(0xFF0878C9),
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),

                // Label and Address Details
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              address.label.isNotEmpty ? address.label : 'Delivery Address',
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF12344D),
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (isDefault) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 2.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFE8F5E9),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFF81C784)),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.check_circle_rounded,
                                      size: 11, color: Color(0xFF2E7D32)),
                                  SizedBox(width: 3),
                                  Text(
                                    'Default',
                                    style: TextStyle(
                                      fontSize: 10.5,
                                      fontWeight: FontWeight.bold,
                                      color: Color(0xFF2E7D32),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        address.fullAddress.isNotEmpty
                            ? address.fullAddress
                            : address.addressLine,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF4A6982),
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const Divider(height: 1, color: Color(0xFFF0F5FA)),

          // Actions Row
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Row(
              children: [
                if (!isDefault)
                  Expanded(
                    child: TextButton.icon(
                      onPressed: () => prov.setDefault(address.id),
                      icon: const Icon(Icons.star_outline_rounded,
                          size: 16, color: Color(0xFF0878C9)),
                      label: const Text(
                        'Set Default',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF0878C9),
                        ),
                      ),
                      style: TextButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                      ),
                    ),
                  ),
                Expanded(
                  child: TextButton.icon(
                    onPressed: () => _navigateToAddAddress(address),
                    icon: const Icon(Icons.edit_outlined,
                        size: 16, color: Color(0xFF0878C9)),
                    label: const Text(
                      'Edit',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0878C9),
                      ),
                    ),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                  ),
                ),
                Expanded(
                  child: TextButton.icon(
                    onPressed: () async {
                      final confirmed = await showConfirmationDialog(
                        context,
                        title: 'Delete Address?',
                        message:
                            'Are you sure you want to delete "${address.label}"? This action cannot be undone.',
                        confirmLabel: 'Delete',
                        isDanger: true,
                      );
                      if (confirmed && context.mounted) {
                        final ok = await prov.delete(address.id);
                        if (!ok && context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(prov.error ?? 'Failed to delete address.'),
                              backgroundColor: const Color(0xFFDC2626),
                            ),
                          );
                        }
                      }
                    },
                    icon: const Icon(Icons.delete_outline_rounded,
                        size: 16, color: Color(0xFFDC2626)),
                    label: const Text(
                      'Delete',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFFDC2626),
                      ),
                    ),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  IconData _getAddressIcon(String label) {
    final lower = label.toLowerCase();
    if (lower.contains('office') || lower.contains('work')) {
      return Icons.business_rounded;
    }
    if (lower.contains('condo') || lower.contains('apartment')) {
      return Icons.apartment_rounded;
    }
    return Icons.home_rounded;
  }

  // ====================================================================
  // EMPTY STATE
  // ====================================================================
  Widget _buildEmptyState() {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 36),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 18,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF7FF),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFBCE3FD), width: 1.2),
                ),
                child: const Icon(
                  Icons.location_off_rounded,
                  color: Color(0xFF0878C9),
                  size: 34,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'No Saved Addresses Yet',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Add an address to make water refill ordering fast and seamless.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 13,
                  color: Color(0xFF6F8BA3),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 22),
              ElevatedButton.icon(
                onPressed: () => _navigateToAddAddress(),
                icon: const Icon(Icons.add_location_alt_rounded, size: 18),
                label: const Text(
                  'Add New Address',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  elevation: 0,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // ERROR STATE
  // ====================================================================
  Widget _buildErrorState(AddressProvider prov) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFFEE2E2)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline_rounded,
                  color: Color(0xFFDC2626), size: 40),
              const SizedBox(height: 12),
              const Text(
                'Something went wrong',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                prov.error ?? 'Unable to load your addresses. Please try again.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12.5, color: Color(0xFF6F8BA3)),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: prov.load,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Try Again',
                    style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // LOADING SKELETON
  // ====================================================================
  Widget _buildLoadingSkeleton() {
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
      itemCount: 3,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => Container(
        height: 110,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.8),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5F1FA)),
        ),
        child: const Center(
          child: SizedBox(
            width: 24,
            height: 24,
            child: CircularProgressIndicator(
              color: Color(0xFF0878C9),
              strokeWidth: 2.2,
            ),
          ),
        ),
      ),
    );
  }
}
