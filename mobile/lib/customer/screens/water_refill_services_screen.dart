import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/product_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/widgets/guest_order_prompt.dart';
import '../../customer/data/product_repository.dart';
import '../../customer/providers/cart_provider.dart';
import '../../customer/providers/notification_provider.dart';
import 'cart_screen.dart';
import 'notifications_screen.dart';

class WaterRefillServicesScreen extends StatefulWidget {
  const WaterRefillServicesScreen({super.key});

  @override
  State<WaterRefillServicesScreen> createState() => _WaterRefillServicesScreenState();
}

class _WaterRefillServicesScreenState extends State<WaterRefillServicesScreen> {
  late Future<List<ProductModel>> _servicesFuture;
  String _selectedCategory = 'All';
  final List<String> _categories = ['All', 'Purified', 'Mineral'];

  ProductModel? _selectedService;
  int _quantity = 1;
  bool _addedToCart = false;

  @override
  void initState() {
    super.initState();
    _loadServices();
  }

  void _loadServices() {
    setState(() {
      _servicesFuture = context
          .read<ProductRepository>()
          .list()
          .then((raw) => raw
              .map(ProductModel.fromMap)
              .where((s) => !s.name.toLowerCase().contains('alkaline'))
              .toList());
    });
  }

  List<ProductModel> _filterServices(List<ProductModel> services) {
    final nonAlkaline = services
        .where((s) => !s.name.toLowerCase().contains('alkaline'))
        .toList();
    if (_selectedCategory == 'All') return nonAlkaline;
    return nonAlkaline.where((s) {
      final name = s.name.toLowerCase();
      final desc = (s.description ?? '').toLowerCase();
      final target = _selectedCategory.toLowerCase();
      return name.contains(target) || desc.contains(target);
    }).toList();
  }

  void _onSelectService(ProductModel service) {
    if (!service.isAvailable) return;
    setState(() {
      _selectedService = service;
      _quantity = 1; // Default to 1 gallon on new service selection
      _addedToCart = false;
    });
  }

  void _incrementQuantity() {
    setState(() {
      _quantity++;
      _addedToCart = false;
    });
  }

  void _decrementQuantity() {
    if (_quantity > 1) {
      setState(() {
        _quantity--;
        _addedToCart = false;
      });
    }
  }

  Future<void> _addToCart() async {
    if (_selectedService == null || !_selectedService!.isAvailable) return;
    if (!context.read<AuthProvider>().isAuthenticated) {
      await showGuestOrderPrompt(context);
      return;
    }

    context.read<CartProvider>().setProductQuantity(
      _selectedService!,
      _quantity,
    );

    setState(() => _addedToCart = true);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$_quantity × ${_selectedService!.name} added to cart')),
    );
  }

  void _continueToOrder() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const CartScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
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
                _buildHeader(context),
                _buildCategoryFilters(),
                Expanded(
                  child: FutureBuilder<List<ProductModel>>(
                    future: _servicesFuture,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return _buildLoadingState();
                      }

                      if (snapshot.hasError) {
                        return _buildErrorState();
                      }

                      final allServices = snapshot.data ?? [];
                      final filteredServices = _filterServices(allServices);

                      if (filteredServices.isEmpty) {
                        return _buildEmptyState();
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: () async => _loadServices(),
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 110),
                          children: [
                            // Refill Services List
                            ...filteredServices.map((service) {
                              final isSelected = _selectedService?.id == service.id;
                              return Padding(
                                padding: const EdgeInsets.only(bottom: 14),
                                child: _buildServiceCard(service, isSelected),
                              );
                            }),

                            // Selected Service Summary Card
                            if (_selectedService != null) ...[
                              const SizedBox(height: 6),
                              _buildSelectedServiceSummary(),
                              const SizedBox(height: 14),
                            ],
                          ],
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
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // Left: Back Button & Title
          Row(
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
              const SizedBox(width: 12),
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Water Refill Services',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF12344D),
                      letterSpacing: -0.2,
                    ),
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Choose a refill service for your order',
                    style: TextStyle(
                      fontSize: 11.5,
                      color: Color(0xFF6F8BA3),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ],
          ),

          // Right: Cart and notification actions
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Consumer<CartProvider>(
                builder: (context, cart, _) => Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const CartScreen()),
                        ),
                        borderRadius: BorderRadius.circular(21),
                        child: Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: const Color(0xFFD5E7F4),
                              width: 1,
                            ),
                          ),
                          child: const Icon(
                            Icons.shopping_cart_outlined,
                            color: Color(0xFF0878C9),
                            size: 22,
                          ),
                        ),
                      ),
                    ),
                    if (cart.itemCount > 0)
                      Positioned(
                        top: -2,
                        right: -2,
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: Color(0xFFE53935),
                            shape: BoxShape.circle,
                          ),
                          constraints: const BoxConstraints(
                            minWidth: 18,
                            minHeight: 18,
                          ),
                          child: Center(
                            child: Text(
                              cart.itemCount > 99 ? '99+' : '${cart.itemCount}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                height: 1,
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Consumer<NotificationProvider>(
                builder: (context, notifProv, _) {
              final unreadCount = context.watch<AuthProvider>().isAuthenticated
                  ? notifProv.unreadCount
                  : 0;
              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => const NotificationsScreen(),
                          ),
                        );
                      },
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
                          Icons.notifications_outlined,
                          color: Color(0xFF0878C9),
                          size: 22,
                        ),
                      ),
                    ),
                  ),
                  if (unreadCount > 0)
                    Positioned(
                      top: -2,
                      right: -2,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Color(0xFFE53935),
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                        child: Center(
                          child: Text(
                            unreadCount > 99 ? '99+' : '$unreadCount',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              height: 1,
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              );
                },
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // CATEGORY FILTERS
  // ====================================================================
  Widget _buildCategoryFilters() {
    return Container(
      height: 46,
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: _categories.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final cat = _categories[index];
          final isSelected = _selectedCategory == cat;

          return Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => setState(() => _selectedCategory = cat),
              borderRadius: BorderRadius.circular(20),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFF0878C9) : Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isSelected ? const Color(0xFF0878C9) : const Color(0xFFD5E7F4),
                    width: 1.2,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: isSelected
                          ? const Color(0xFF0878C9).withValues(alpha: 0.25)
                          : const Color(0xFF0878C9).withValues(alpha: 0.04),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Center(
                  child: Text(
                    cat,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                      color: isSelected ? Colors.white : const Color(0xFF12344D),
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  // ====================================================================
  // REFILL SERVICE CARD (HORIZONTAL PRODUCT DESIGN)
  // ====================================================================
  Widget _buildServiceCard(ProductModel service, bool isSelected) {
    final hasImage = service.imageUrl != null && service.imageUrl!.isNotEmpty;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isSelected
              ? const Color(0xFF0878C9)
              : const Color(0xFFE5F1FA),
          width: isSelected ? 2.0 : 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: isSelected
                ? const Color(0xFF0878C9).withValues(alpha: 0.16)
                : const Color(0xFF0878C9).withValues(alpha: 0.05),
            blurRadius: isSelected ? 16 : 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: service.isAvailable ? () => _onSelectService(service) : null,
          borderRadius: BorderRadius.circular(20),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Upper section: Two-column layout ──────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Left side: Product Image / Illustration Container
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0F7FC),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: isSelected
                              ? const Color(0xFFBCE3FD)
                              : const Color(0xFFE1EFF8),
                          width: 1,
                        ),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Center(
                        child: hasImage
                            ? (service.imageUrl!.startsWith('http')
                                ? Image.network(
                                    service.imageUrl!,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, __, ___) => Image.asset(
                                      'assets/images/aqua_clayne_gallon.png',
                                      fit: BoxFit.contain,
                                    ),
                                  )
                                : Image.asset(
                                    service.imageUrl!,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, __, ___) => Image.asset(
                                      'assets/images/aqua_clayne_gallon.png',
                                      fit: BoxFit.contain,
                                    ),
                                  ))
                            : Image.asset(
                                'assets/images/aqua_clayne_gallon.png',
                                fit: BoxFit.contain,
                                width: 64,
                                height: 64,
                              ),
                      ),
                    ),

                    const SizedBox(width: 14),

                    // Right side: Name, short description, and availability pill
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Top row: Availability pill & Selected indicator
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8.5, vertical: 3.5),
                                decoration: BoxDecoration(
                                  color: service.isAvailable
                                      ? const Color(0xFFE8F5E9)
                                      : const Color(0xFFFFEBEE),
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(
                                    color: service.isAvailable
                                        ? const Color(0xFF81C784)
                                        : const Color(0xFFE57373),
                                    width: 0.8,
                                  ),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.circle,
                                      color: service.isAvailable
                                          ? const Color(0xFF2E7D32)
                                          : const Color(0xFFC62828),
                                      size: 6.5,
                                    ),
                                    const SizedBox(width: 4.5),
                                    Text(
                                      service.isAvailable
                                          ? 'Available'
                                          : 'Unavailable',
                                      style: TextStyle(
                                        fontSize: 10.5,
                                        fontWeight: FontWeight.w700,
                                        color: service.isAvailable
                                            ? const Color(0xFF2E7D32)
                                            : const Color(0xFFC62828),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              if (isSelected)
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 7, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFE0F2FE),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: const Text(
                                    'Selected',
                                    style: TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF0284C7),
                                    ),
                                  ),
                                ),
                            ],
                          ),

                          const SizedBox(height: 6),

                          // Product Name
                          Text(
                            service.name,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF12344D),
                              letterSpacing: -0.2,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),

                          const SizedBox(height: 4),

                          // Short Description / Product details
                          Text(
                            service.description != null &&
                                    service.description!.isNotEmpty
                                ? service.description!
                                : (service.isNewGallon
                                    ? 'Brand-new 5-gallon container filled with purified water'
                                    : 'Customer provides container for refill'),
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF6F8BA3),
                              height: 1.3,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 14),
                const Divider(color: Color(0xFFF0F5FA), height: 1),
                const SizedBox(height: 12),

                // ── Bottom section: Horizontal alignment ──────────────
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Left side: Price
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          service.isNewGallon ? 'New Container' : 'Refill Service',
                          style: const TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF8FA9BF),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Text(
                              '₱${service.price.toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF0878C9),
                                letterSpacing: -0.3,
                              ),
                            ),
                            const SizedBox(width: 3),
                            const Text(
                              '/ gallon',
                              style: TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF6F8BA3),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),

                    // Right side: Add to Cart button
                    if (service.isAvailable)
                      ElevatedButton.icon(
                        onPressed: () => _addServiceToCart(service),
                        icon: const Icon(Icons.add_shopping_cart_rounded, size: 16),
                        label: const Text(
                          'Add to Cart',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0878C9),
                          foregroundColor: Colors.white,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 10),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      )
                    else
                      ElevatedButton(
                        onPressed: null,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFF1F5F9),
                          disabledForegroundColor: const Color(0xFF94A3B8),
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 10),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: const Text(
                          'Unavailable',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _addServiceToCart(ProductModel service) async {
    if (!context.read<AuthProvider>().isAuthenticated) {
      await showGuestOrderPrompt(context);
      return;
    }
    context.read<CartProvider>().add(service);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${service.name} added to cart'),
        action: SnackBarAction(
          label: 'View Cart',
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const CartScreen()),
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // SELECTED SERVICE SUMMARY CARD
  // ====================================================================
  Widget _buildSelectedServiceSummary() {
    final service = _selectedService!;
    final subtotal = service.price * _quantity;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFF0878C9), width: 1.8),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.14),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section Title
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.check_circle_rounded, color: Color(0xFF0878C9), size: 18),
                  SizedBox(width: 6),
                  Text(
                    'SELECTED REFILL SERVICE',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0878C9),
                      letterSpacing: 0.8,
                    ),
                  ),
                ],
              ),
              IconButton(
                icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF6F8BA3)),
                onPressed: () => setState(() {
                  _selectedService = null;
                  _addedToCart = false;
                }),
                visualDensity: VisualDensity.compact,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Service Title & Rate
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  service.name,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                  ),
                ),
              ),
              Text(
                '₱${service.price.toStringAsFixed(2)} / gal',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF6F8BA3),
                ),
              ),
            ],
          ),

          const Divider(color: Color(0xFFF0F5FA), height: 24),

          // Quantity Stepper
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Number of Gallons',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF12344D),
                    ),
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Customer-owned empty gallons',
                    style: TextStyle(
                      fontSize: 11.5,
                      color: Color(0xFF6F8BA3),
                    ),
                  ),
                ],
              ),

              // Stepper controls
              Container(
                decoration: BoxDecoration(
                  color: const Color(0xFFF4F9FD),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFD5E7F4), width: 1.2),
                ),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.remove_rounded),
                      iconSize: 18,
                      color: _quantity > 1 ? const Color(0xFF0878C9) : const Color(0xFFB0C5D8),
                      onPressed: _quantity > 1 ? _decrementQuantity : null,
                      padding: const EdgeInsets.all(8),
                      constraints: const BoxConstraints(),
                    ),
                    Container(
                      constraints: const BoxConstraints(minWidth: 36),
                      child: Text(
                        '$_quantity',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.add_rounded),
                      iconSize: 18,
                      color: const Color(0xFF0878C9),
                      onPressed: _incrementQuantity,
                      padding: const EdgeInsets.all(8),
                      constraints: const BoxConstraints(),
                    ),
                  ],
                ),
              ),
            ],
          ),

          const Divider(color: Color(0xFFF0F5FA), height: 24),

          // Subtotal Row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Subtotal',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF6F8BA3),
                ),
              ),
              Text(
                '₱${subtotal.toStringAsFixed(2)}',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0878C9),
                ),
              ),
            ],
          ),

          const SizedBox(height: 18),

          // Add the selected service first, then allow the customer to review
          // the cart and continue through checkout.
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _addedToCart ? _continueToOrder : _addToCart,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    _addedToCart ? 'Continue to Order' : 'Add to Cart',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  SizedBox(width: 8),
                  Icon(
                    _addedToCart
                        ? Icons.arrow_forward_rounded
                        : Icons.shopping_cart_outlined,
                    size: 18,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // STATES: LOADING, ERROR, EMPTY
  // ====================================================================
  Widget _buildLoadingState() {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: 3,
      separatorBuilder: (_, _) => const SizedBox(height: 14),
      itemBuilder: (_, _) => Container(
        height: 160,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.7),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFE5F1FA)),
        ),
        child: const Center(
          child: CircularProgressIndicator(
            color: Color(0xFF0878C9),
            strokeWidth: 2.5,
          ),
        ),
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: const Color(0xFFFFEBEE),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFEF9A9A)),
              ),
              child: const Icon(
                Icons.error_outline_rounded,
                color: Color(0xFFC62828),
                size: 36,
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Unable to load refill services.',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: Color(0xFF12344D),
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 6),
            const Text(
              'Please check your connection and try again.',
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 18),
            ElevatedButton.icon(
              onPressed: _loadServices,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text(
                'Try Again',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF7FF),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFBCE3FD)),
              ),
              child: const Icon(
                Icons.water_drop_outlined,
                color: Color(0xFF0878C9),
                size: 36,
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'No refill services available',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: Color(0xFF12344D),
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 6),
            const Text(
              'There are currently no available\nwater refill services.',
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
                height: 1.4,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 18),
            ElevatedButton.icon(
              onPressed: _loadServices,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text(
                'Refresh',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
