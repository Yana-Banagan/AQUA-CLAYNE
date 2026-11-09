import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../customer/providers/address_provider.dart';
import '../../customer/providers/credit_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/order_provider.dart';
import '../../customer/providers/cart_provider.dart';
import '../../customer/providers/payment_provider.dart';
import '../../customer/providers/queue_provider.dart';
import '../../customer/providers/complaint_provider.dart';
import 'customer_shell.dart';
import 'addresses_screen.dart';
import 'change_password_screen.dart';
import 'complaints_screen.dart';
import 'edit_profile_screen.dart';
import 'faq_screen.dart';
import 'submit_complaint_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _loggingOut = false;

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
        actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                  borderRadius: BorderRadius.circular(12)),
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
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 6, 16, 80),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // SECTION 1: ACCOUNT
                        _buildSectionHeader('ACCOUNT'),
                        _buildCardContainer([
                          _buildMenuTile(
                            icon: Icons.person_outline_rounded,
                            title: 'Edit Profile',
                            subtitle: 'Update your name, email, and contact info',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => const EditProfileScreen()),
                              );
                            },
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildMenuTile(
                            icon: Icons.lock_outline_rounded,
                            title: 'Change Password',
                            subtitle: 'Update your login password',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) =>
                                        const ChangePasswordScreen()),
                              );
                            },
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildMenuTile(
                            icon: Icons.location_on_outlined,
                            title: 'My Addresses',
                            subtitle: 'Manage saved delivery addresses',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => const AddressesScreen()),
                              );
                            },
                          ),
                        ]),
                        const SizedBox(height: 18),

                        // SECTION 2: SUPPORT
                        _buildSectionHeader('SUPPORT & INQUIRIES'),
                        _buildCardContainer([
                          _buildMenuTile(
                            icon: Icons.help_outline_rounded,
                            title: 'Frequently Asked Questions',
                            subtitle: 'Guides, ordering rules, and account info',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => const FaqScreen()),
                              );
                            },
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildMenuTile(
                            icon: Icons.feedback_outlined,
                            title: 'My Complaints',
                            subtitle: 'View submitted concerns and track status',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => const ComplaintsScreen()),
                              );
                            },
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildMenuTile(
                            icon: Icons.add_comment_outlined,
                            title: 'Submit Complaint',
                            subtitle: 'Report a delivery, billing, or water issue',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) =>
                                        const SubmitComplaintScreen()),
                              );
                            },
                          ),
                        ]),
                        const SizedBox(height: 18),

                        // SECTION 3: ABOUT & LEGAL
                        _buildSectionHeader('APP INFORMATION'),
                        _buildCardContainer([
                          _buildMenuTile(
                            icon: Icons.description_outlined,
                            title: 'Terms & Conditions',
                            subtitle: 'Service rules and usage terms',
                            onTap: () => _showDialog(
                              'Terms & Conditions',
                              'Welcome to AQUA CLAYNE Water Refilling Station Management System.\n\nBy placing orders and utilizing our refill services, you agree to:\n1. Provide accurate delivery addresses and contact information.\n2. Ensure empty gallons are ready and in good physical condition at pickup.\n3. Settle water refill charges via Cash on Delivery, GCash, or approved Customer Account Credit.\n4. Comply with station sanitization and queue guidelines.',
                            ),
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildMenuTile(
                            icon: Icons.privacy_tip_outlined,
                            title: 'Privacy Policy',
                            subtitle: 'Customer data and account privacy',
                            onTap: () => _showDialog(
                              'Privacy Policy',
                              'AQUA CLAYNE respects your privacy.\n\nWe collect and securely store your account details (name, phone, delivery addresses) solely for processing water refill orders, deliveries, and payment settlements.\n\nYour personal information is never sold or shared with third parties.',
                            ),
                          ),
                          const Divider(
                              color: Color(0xFFF0F5FA), height: 1, indent: 56),
                          _buildInfoTile(
                            icon: Icons.info_outline_rounded,
                            title: 'App Version',
                            value: '1.0.0 (Build 100)',
                          ),
                        ]),
                        const SizedBox(height: 24),

                        // SECTION 4: LOGOUT
                        _buildLogoutButton(),
                        const SizedBox(height: 16),
                      ],
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
                  'Settings',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Manage your account and app preferences',
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

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: Color(0xFF6F8BA3),
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  Widget _buildCardContainer(List<Widget> children) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.05),
            blurRadius: 14,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(children: children),
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
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF7FF),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(icon, color: const Color(0xFF0878C9), size: 20),
              ),
              const SizedBox(width: 13),
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

  Widget _buildInfoTile({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: const Color(0xFFF4F9FD),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: const Color(0xFF0878C9), size: 20),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: Color(0xFF12344D),
              ),
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: Color(0xFF6F8BA3),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLogoutButton() {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: OutlinedButton(
        onPressed: _loggingOut ? null : _handleLogout,
        style: OutlinedButton.styleFrom(
          side: const BorderSide(color: Color(0xFFFCA5A5), width: 1.2),
          backgroundColor: const Color(0xFFFEF2F2),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
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
                  Icon(Icons.logout_rounded, color: Color(0xFFDC2626), size: 20),
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

  void _showDialog(String title, String body) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          title,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: Color(0xFF12344D),
          ),
        ),
        content: SingleChildScrollView(
          child: Text(
            body,
            style: const TextStyle(
              fontSize: 13.5,
              color: Color(0xFF4A6982),
              height: 1.5,
            ),
          ),
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0878C9),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text(
              'Close',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
