import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/models/user_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/repositories/auth_repository.dart';
import '../../core/screens/login_screen.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/confirmation_dialog.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../providers/driver_delivery_provider.dart';

class DriverProfileScreen extends StatefulWidget {
  const DriverProfileScreen({super.key});

  @override
  State<DriverProfileScreen> createState() => _DriverProfileScreenState();
}

class _DriverProfileScreenState extends State<DriverProfileScreen> {
  static const _maxPhotoBytes = 5 * 1024 * 1024;
  bool _uploadingPhoto = false;

  Future<void> _choosePhotoSource() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('Take a photo'), onTap: () => Navigator.pop(context, ImageSource.camera)),
        ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('Choose from gallery'), onTap: () => Navigator.pop(context, ImageSource.gallery)),
      ])),
    );
    if (source == null || !mounted) return;
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 90);
    if (picked == null || !mounted) return;
    final photo = File(picked.path);
    final extension = picked.name.split('.').last.toLowerCase();
    if (!const {'jpg', 'jpeg', 'png', 'webp'}.contains(extension)) {
      _showMessage('Please select a JPG, PNG, or WEBP image.', error: true);
      return;
    }
    if (await photo.length() > _maxPhotoBytes) {
      if (mounted) _showMessage('Profile picture must be 5 MB or smaller.', error: true);
      return;
    }
    if (!mounted) return;
    await _uploadPhoto(photo);
  }

  Future<void> _uploadPhoto(File photo) async {
    setState(() => _uploadingPhoto = true);
    try {
      final data = await context.read<AuthRepository>().uploadProfilePhoto(photo);
      if (!mounted) return;
      context.read<AuthProvider>().updateUser(UserModel.fromMap(data['user'] ?? data));
      _showMessage('Profile picture updated successfully.');
    } catch (_) {
      if (mounted) _showMessage('Could not upload the profile picture. Please try again.', error: true);
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  void _showMessage(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message), behavior: SnackBarBehavior.floating, backgroundColor: error ? DriverColors.error : DriverColors.success));
  }

  Future<void> _logout(BuildContext context) async {
    final confirmed = await showConfirmationDialog(
      context,
      title: 'Log Out',
      message: 'Are you sure you want to log out of your driver account?',
      confirmLabel: 'Log Out',
      isDanger: true,
    );

    if (confirmed != true || !context.mounted) return;

    final auth = context.read<AuthProvider>();
    showDialog<void>(context: context, barrierDismissible: false, builder: (_) => const PopScope(canPop: false, child: Center(child: CircularProgressIndicator())));
    await auth.logout();

    if (!context.mounted) return;

    context.read<DriverDeliveryProvider>().clearSessionData();

    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final driverName = user?.name.isNotEmpty == true ? user!.name : 'Driver';
    final email = user?.email ?? 'driver@aquaclayne.com';
    final phone = user?.phone ?? 'No phone registered';

    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Driver Profile',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.standard),
        children: [
          // Driver Identity Card
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 10,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: Row(
              children: [
                Stack(children: [
                  CircleAvatar(radius: 32, backgroundColor: DriverColors.mintSoft, backgroundImage: user?.avatarUrl != null ? NetworkImage(user!.avatarUrl!) : null, child: user?.avatarUrl == null ? Text(driverName.isNotEmpty ? driverName[0].toUpperCase() : 'D', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: DriverColors.primaryDark)) : null),
                  Positioned(right: -2, bottom: -2, child: Material(color: DriverColors.primary, shape: const CircleBorder(), child: InkWell(onTap: _uploadingPhoto ? null : _choosePhotoSource, customBorder: const CircleBorder(), child: Padding(padding: const EdgeInsets.all(7), child: _uploadingPhoto ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.camera_alt_outlined, size: 16, color: Colors.white))))),
                ]),
                const SizedBox(width: AppSpacing.standard),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        driverName,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: DriverColors.mintSoft,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'OFFICIAL DRIVER',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: DriverColors.primaryDark,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: DriverColors.successLight,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.circle,
                                  size: 6,
                                  color: DriverColors.success,
                                ),
                                SizedBox(width: 4),
                                Text(
                                  'ACTIVE',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: DriverColors.success,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        email,
                        style: const TextStyle(
                          fontSize: 12,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                      Text(
                        phone,
                        style: const TextStyle(
                          fontSize: 12,
                          color: DriverColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Operational Information Card
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 8,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(
                      Icons.shield_outlined,
                      size: 16,
                      color: DriverColors.primary,
                    ),
                    SizedBox(width: 6),
                    Text(
                      'DRIVER AUTHORIZATION',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: DriverColors.primaryDark,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _InfoRow(
                  label: 'Account Status',
                  value: 'Active (Admin Managed)',
                  icon: Icons.verified_user_outlined,
                  color: DriverColors.success,
                ),
                const Divider(color: DriverColors.divider, height: 16),
                _InfoRow(
                  label: 'Station',
                  value: 'Aqua Clayne Station Main',
                  icon: Icons.storefront_outlined,
                  color: DriverColors.primary,
                ),
                const Divider(color: DriverColors.divider, height: 16),
                _InfoRow(
                  label: 'Service Scope',
                  value: 'Refill Intake, Transport & Delivery',
                  icon: Icons.local_shipping_outlined,
                  color: DriverColors.mintAccent,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // App Settings & About
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 8,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(
                      Icons.info_outline_rounded,
                      size: 16,
                      color: DriverColors.primary,
                    ),
                    SizedBox(width: 6),
                    Text(
                      'SYSTEM INFORMATION',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: DriverColors.primaryDark,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _InfoRow(
                  label: 'System Version',
                  value: 'Aqua Clayne v2.0 (Mobile)',
                  icon: Icons.mobile_friendly_outlined,
                  color: DriverColors.textSecondary,
                ),
                const Divider(color: DriverColors.divider, height: 16),
                _InfoRow(
                  label: 'Realtime Sync',
                  value: 'Connected via Supabase Realtime',
                  icon: Icons.sync,
                  color: DriverColors.primary,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Logout Button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _logout(context),
              icon: const Icon(Icons.logout, size: 18),
              label: const Text('Log Out'),
              style: ElevatedButton.styleFrom(
                backgroundColor: DriverColors.error,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
                elevation: 0,
              ),
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _InfoRow({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(width: 10),
        Text(
          label,
          style: const TextStyle(
            fontSize: 13,
            color: DriverColors.textSecondary,
          ),
        ),
        const Spacer(),
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: color == DriverColors.textSecondary
                ? DriverColors.textPrimary
                : color,
          ),
        ),
      ],
    );
  }
}
