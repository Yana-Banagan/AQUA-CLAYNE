import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/providers/auth_provider.dart';
import '../../core/screens/login_screen.dart';
import '../../core/screens/register_screen.dart';
import 'customer_shell.dart';
import '../../customer/providers/address_provider.dart';
import '../../customer/providers/credit_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../customer/providers/payment_provider.dart';
import '../../customer/providers/queue_provider.dart';
import '../../customer/providers/complaint_provider.dart';
import 'add_address_screen.dart';
import 'addresses_screen.dart';
import 'change_password_screen.dart';
import 'complaints_screen.dart';
import 'edit_profile_screen.dart';
import 'faq_screen.dart';
import 'notifications_screen.dart';
import 'orders_list_screen.dart';
import 'payments_screen.dart';
import 'settings_screen.dart';

class ProfileScreen extends StatefulWidget {
  final bool showStandaloneNavigation;
  const ProfileScreen({super.key, this.showStandaloneNavigation = false});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _loggingOut = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadProfileData();
    });
  }

  Future<void> _loadProfileData() async {
    if (!mounted) return;
    if (!context.read<AuthProvider>().isAuthenticated) return;
    await Future.wait([
      context.read<AddressProvider>().load(),
      context.read<CreditProvider>().loadCredit(),
      context.read<NotificationProvider>().load(),
    ]);
  }

  Future<void> _handleLogout() async {
    if (_loggingOut) return;

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.logout_rounded, color: Color(0xFFDC2626), size: 24),
            SizedBox(width: 10),
            Text(
              'Log Out?',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: Color(0xFF12344D),
              ),
            ),
          ],
        ),
        content: const Text(
          'Are you sure you want to log out of your AQUA CLAYNE account?',
          style: TextStyle(fontSize: 14, color: Color(0xFF6F8BA3), height: 1.4),
        ),
        actionsPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 12,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text(
              'Cancel',
              style: TextStyle(
                color: Color(0xFF6F8BA3),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: const Text(
              'Log Out',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    setState(() => _loggingOut = true);

    try {
      try { context.read<NotificationProvider>().clearSessionData(); } catch (_) {}
      try { context.read<OrderProvider>().clearSessionData(); } catch (_) {}
      try { context.read<PaymentProvider>().clearSessionData(); } catch (_) {}
      try { context.read<AddressProvider>().clearSessionData(); } catch (_) {}
      try { context.read<CreditProvider>().clearSessionData(); } catch (_) {}
      try { context.read<QueueProvider>().clearSessionData(); } catch (_) {}
      try { context.read<ComplaintProvider>().clearSessionData(); } catch (_) {}
      try { context.read<CartProvider>().clearCart(); } catch (_) {}

      await context.read<AuthProvider>().logout();
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const CustomerShell()),
        (_) => false,
      );
    } catch (_) {
      if (mounted) setState(() => _loggingOut = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final canPop = widget.showStandaloneNavigation && Navigator.canPop(context);
    final user = context.watch<AuthProvider>().user;

    if (user == null) {
      return _buildGuestProfile(context, canPop);
    }

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
            bottom: canPop,
            child: Column(
              children: [
                _buildHeader(context, canPop),
                Expanded(
                  child: RefreshIndicator(
                    color: const Color(0xFF0878C9),
                    backgroundColor: Colors.white,
                    onRefresh: _loadProfileData,
                    child: SingleChildScrollView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 6, 16, 100),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildTitleSection(),
                          const SizedBox(height: 14),

                          // 1. Profile Identity Card
                          _buildProfileIdentityCard(context, user),
                          const SizedBox(height: 16),

                          // 2. Personal Information Card
                          _buildPersonalInfoCard(user),
                          const SizedBox(height: 16),

                          // 3. Delivery Address Card
                          _buildDeliveryAddressCard(context),
                          const SizedBox(height: 16),

                          // 5. Account Information Card
                          _buildAccountInfoCard(user),
                          const SizedBox(height: 16),

                          // 6. Quick Action Shortcuts
                          _buildQuickActionsCard(context),
                          const SizedBox(height: 16),

                          // 7. Security Settings Card
                          _buildSecurityCard(context),
                          const SizedBox(height: 20),

                          // 8. Logout Button
                          _buildLogoutButton(context),
                          const SizedBox(height: 16),
                        ],
                      ),
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

  Widget _buildGuestProfile(BuildContext context, bool canPop) {
    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (canPop)
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.arrow_back_ios_new_rounded),
                    ),
                  const Spacer(),
                  const Center(
                    child: Icon(
                      Icons.account_circle_outlined,
                      size: 72,
                      color: Color(0xFF0878C9),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Center(
                    child: Text(
                      'Welcome to AQUA CLAYNE',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 25,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF12344D),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Create an account to place orders, save delivery addresses, and track your water deliveries.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 15, height: 1.45, color: Color(0xFF52718B)),
                  ),
                  const SizedBox(height: 28),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: () => Navigator.of(context, rootNavigator: true).push(
                        MaterialPageRoute(builder: (_) => const RegisterScreen()),
                      ),
                      child: const Text('Create an Account'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Center(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context, rootNavigator: true).push(
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                      ),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(150, 46),
                        side: const BorderSide(
                          color: Color(0xFF0878C9),
                          width: 1.4,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: const Text('Login'),
                    ),
                  ),
                  const Spacer(flex: 2),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context, bool canPop) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          if (canPop)
            _buildCircularBackButton(context)
          else
            _buildBrandHeader(),
          _buildNotificationButton(context),
        ],
      ),
    );
  }

  Widget _buildCircularBackButton(BuildContext context) {
    return Material(
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
    );
  }

  Widget _buildBrandHeader() {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: const Color(0xFFEAF7FF),
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFBCE3FD), width: 1),
          ),
          child: ClipOval(
            child: Image.asset(
              'assets/images/aqua_clayne_logo.png',
              fit: BoxFit.cover,
            ),
          ),
        ),
        const SizedBox(width: 10),
        const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'AQUA CLAYNE',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: Color(0xFF064B87),
                letterSpacing: 0.5,
              ),
            ),
            Text(
              'Water Refilling Station',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: Color(0xFF6F8BA3),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildNotificationButton(BuildContext context) {
    return Consumer<NotificationProvider>(
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
                    border: Border.all(
                      color: const Color(0xFFD5E7F4),
                      width: 1,
                    ),
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
                  constraints: const BoxConstraints(
                    minWidth: 18,
                    minHeight: 18,
                  ),
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
    );
  }

  // ====================================================================
  // TITLE SECTION
  // ====================================================================
  Widget _buildTitleSection() {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Profile',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: Color(0xFF12344D),
            letterSpacing: -0.3,
          ),
        ),
        SizedBox(height: 3),
        Text(
          'Manage your personal account, address, and settings',
          style: TextStyle(
            fontSize: 13,
            color: Color(0xFF6F8BA3),
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // 1. PROFILE IDENTITY CARD
  // ====================================================================
  Widget _buildProfileIdentityCard(BuildContext context, dynamic user) {
    final name = user?.name.isNotEmpty == true ? user!.name : 'Customer';
    final email = user?.email ?? '';
    final role = (user?.role?.isNotEmpty == true ? user!.role : 'Customer')
        .toUpperCase();
    final avatarUrl = user?.avatarUrl;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          // Avatar
          Stack(
            children: [
              Container(
                width: 82,
                height: 82,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.25),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: avatarUrl != null && avatarUrl.isNotEmpty
                    ? ClipRRect(
                        borderRadius: BorderRadius.circular(41),
                        child: Image.network(
                          avatarUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              _buildAvatarFallback(name),
                        ),
                      )
                    : _buildAvatarFallback(name),
              ),
              Positioned(
                bottom: 0,
                right: 0,
                child: Container(
                  padding: const EdgeInsets.all(5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0878C9),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                  child: const Icon(
                    Icons.water_drop_rounded,
                    size: 14,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Name
          Text(
            name,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: Color(0xFF12344D),
            ),
            textAlign: TextAlign.center,
          ),

          const SizedBox(height: 5),

          // Role Badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF7FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFBCE3FD)),
            ),
            child: Text(
              role,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
                color: Color(0xFF0878C9),
                letterSpacing: 0.6,
              ),
            ),
          ),

          if (email.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              email,
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF6F8BA3),
                fontWeight: FontWeight.w500,
              ),
              textAlign: TextAlign.center,
            ),
          ],

          const SizedBox(height: 16),

          // Edit Profile Action Button
          OutlinedButton.icon(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const EditProfileScreen()),
              );
            },
            icon: const Icon(
              Icons.edit_outlined,
              size: 16,
              color: Color(0xFF0878C9),
            ),
            label: const Text(
              'Edit Profile',
              style: TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
                color: Color(0xFF0878C9),
              ),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFFD5E7F4), width: 1.2),
              backgroundColor: const Color(0xFFF4F9FD),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAvatarFallback(String name) {
    return Center(
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : 'U',
        style: const TextStyle(
          fontSize: 34,
          fontWeight: FontWeight.w800,
          color: Colors.white,
        ),
      ),
    );
  }

  // ====================================================================
  // 2. PERSONAL INFORMATION CARD
  // ====================================================================
  Widget _buildPersonalInfoCard(dynamic user) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'PERSONAL INFORMATION',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF6F8BA3),
              letterSpacing: 0.8,
            ),
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 14,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            children: [
              _buildInfoRow(
                icon: Icons.person_outline_rounded,
                label: 'Full Name',
                value: user?.name.isNotEmpty == true ? user!.name : 'Customer',
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 20),
              _buildInfoRow(
                icon: Icons.email_outlined,
                label: 'Email Address',
                value: user?.email.isNotEmpty == true
                    ? user!.email
                    : 'Not provided',
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 20),
              _buildInfoRow(
                icon: Icons.phone_outlined,
                label: 'Mobile Number',
                value: user?.phone?.isNotEmpty == true
                    ? user!.phone!
                    : 'Not provided',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: const Color(0xFFEAF7FF),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: const Color(0xFF0878C9), size: 19),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF6F8BA3),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // 3. DELIVERY ADDRESS CARD
  // ====================================================================
  Widget _buildDeliveryAddressCard(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'DELIVERY ADDRESS',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF6F8BA3),
                  letterSpacing: 0.8,
                ),
              ),
              GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AddressesScreen()),
                  );
                },
                child: const Text(
                  'Manage Addresses',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0878C9),
                  ),
                ),
              ),
            ],
          ),
        ),
        Consumer<AddressProvider>(
          builder: (context, addrProv, _) {
            final defaultAddress = addrProv.defaultAddress;

            return Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                    blurRadius: 14,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: defaultAddress != null
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: const Color(0xFFEAF7FF),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(
                                Icons.home_rounded,
                                color: Color(0xFF0878C9),
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Row(
                                children: [
                                  Text(
                                    defaultAddress.label.isNotEmpty
                                        ? defaultAddress.label
                                        : 'Home',
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF12344D),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE8F5E9),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(
                                        color: const Color(0xFF81C784),
                                      ),
                                    ),
                                    child: const Text(
                                      'Default',
                                      style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                        color: Color(0xFF2E7D32),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Padding(
                          padding: const EdgeInsets.only(left: 46),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                defaultAddress.fullAddress.isNotEmpty
                                    ? defaultAddress.fullAddress
                                    : defaultAddress.addressLine,
                                style: const TextStyle(
                                  fontSize: 13.5,
                                  color: Color(0xFF12344D),
                                  height: 1.35,
                                ),
                              ),
                              const SizedBox(height: 12),
                              OutlinedButton.icon(
                                onPressed: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => const AddressesScreen(),
                                    ),
                                  );
                                },
                                icon: const Icon(
                                  Icons.edit_location_alt_outlined,
                                  size: 15,
                                  color: Color(0xFF0878C9),
                                ),
                                label: const Text(
                                  'Edit Address',
                                  style: TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF0878C9),
                                  ),
                                ),
                                style: OutlinedButton.styleFrom(
                                  side: const BorderSide(
                                    color: Color(0xFFD5E7F4),
                                    width: 1,
                                  ),
                                  backgroundColor: const Color(0xFFF4F9FD),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 14,
                                    vertical: 6,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(
                              Icons.location_off_outlined,
                              color: Color(0xFF6F8BA3),
                              size: 20,
                            ),
                            SizedBox(width: 8),
                            Text(
                              'No saved delivery address found.',
                              style: TextStyle(
                                fontSize: 13,
                                color: Color(0xFF6F8BA3),
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton.icon(
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const AddAddressScreen(),
                              ),
                            );
                          },
                          icon: const Icon(
                            Icons.add_location_alt_outlined,
                            size: 16,
                          ),
                          label: const Text(
                            'Add Delivery Address',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF0878C9),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 8,
                            ),
                          ),
                        ),
                      ],
                    ),
            );
          },
        ),
      ],
    );
  }

  // ====================================================================
  // 5. ACCOUNT INFORMATION CARD
  // ====================================================================
  Widget _buildAccountInfoCard(dynamic user) {
    final status = user?.status?.isNotEmpty == true ? user!.status! : 'active';
    final isActive = status.toLowerCase() == 'active';
    final role = (user?.role?.isNotEmpty == true ? user!.role : 'Customer');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'ACCOUNT INFORMATION',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF6F8BA3),
              letterSpacing: 0.8,
            ),
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 14,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Row(
                    children: [
                      Icon(
                        Icons.verified_user_outlined,
                        color: Color(0xFF0878C9),
                        size: 19,
                      ),
                      SizedBox(width: 10),
                      Text(
                        'Account Status',
                        style: TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: isActive
                          ? const Color(0xFFE8F5E9)
                          : const Color(0xFFFFEBEE),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isActive
                            ? const Color(0xFF81C784)
                            : const Color(0xFFE57373),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.circle,
                          color: isActive
                              ? const Color(0xFF2E7D32)
                              : const Color(0xFFC62828),
                          size: 7,
                        ),
                        const SizedBox(width: 5),
                        Text(
                          status.toUpperCase(),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: isActive
                                ? const Color(0xFF2E7D32)
                                : const Color(0xFFC62828),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Row(
                    children: [
                      Icon(
                        Icons.badge_outlined,
                        color: Color(0xFF0878C9),
                        size: 19,
                      ),
                      SizedBox(width: 10),
                      Text(
                        'Account Type',
                        style: TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ],
                  ),
                  Text(
                    role,
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0878C9),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // 6. QUICK ACTIONS / SHORTCUTS CARD
  // ====================================================================
  Widget _buildQuickActionsCard(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'QUICK ACTIONS',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF6F8BA3),
              letterSpacing: 0.8,
            ),
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 14,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            children: [
              _buildMenuTile(
                icon: Icons.article_outlined,
                title: 'My Orders',
                subtitle: 'View recent and past orders',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const OrdersListScreen()),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.account_balance_wallet_outlined,
                title: 'Payment History',
                subtitle: 'View payments, statements, and balances',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const PaymentsScreen()),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.location_on_outlined,
                title: 'Saved Addresses',
                subtitle: 'Manage delivery addresses and defaults',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AddressesScreen()),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.notifications_outlined,
                title: 'Notifications',
                subtitle: 'Order alerts, refilling queue, and notices',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const NotificationsScreen(),
                    ),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.help_outline_rounded,
                title: 'Help & FAQs',
                subtitle: 'Frequently asked questions and guides',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const FaqScreen()),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.feedback_outlined,
                title: 'Complaints & Support',
                subtitle: 'Submit inquiries or issue reports',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const ComplaintsScreen()),
                  );
                },
              ),
              const Divider(color: Color(0xFFF0F5FA), height: 1, indent: 56),
              _buildMenuTile(
                icon: Icons.settings_outlined,
                title: 'App Settings',
                subtitle: 'Notification preferences and terms',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const SettingsScreen()),
                  );
                },
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMenuTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF7FF),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: const Color(0xFF0878C9), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF12344D),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: Color(0xFF6F8BA3),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                color: Color(0xFFB0C5D8),
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // 7. SECURITY SETTINGS CARD
  // ====================================================================
  Widget _buildSecurityCard(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'SECURITY',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF6F8BA3),
              letterSpacing: 0.8,
            ),
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 14,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: _buildMenuTile(
            icon: Icons.lock_outline_rounded,
            title: 'Change Password',
            subtitle: 'Update your account login password',
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
              );
            },
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // 8. LOGOUT BUTTON
  // ====================================================================
  Widget _buildLogoutButton(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: OutlinedButton(
        onPressed: _loggingOut ? null : () => _handleLogout(),
        style: OutlinedButton.styleFrom(
          side: const BorderSide(color: Color(0xFFFCA5A5), width: 1.2),
          backgroundColor: const Color(0xFFFEF2F2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        child: _loggingOut
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.2,
                  valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFDC2626)),
                ),
              )
            : const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.logout_rounded,
                    color: Color(0xFFDC2626),
                    size: 20,
                  ),
                  SizedBox(width: 8),
                  Text(
                    'Log Out',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFFDC2626),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
