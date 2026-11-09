import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/models/user_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/repositories/auth_repository.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/aqua_clayne_text_field.dart';
import '../../customer/providers/address_provider.dart';
import 'addresses_screen.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameCtrl;
  late final TextEditingController _emailCtrl;
  late final TextEditingController _phoneCtrl;

  bool _saving = false;
  String? _error;
  File? _profilePhoto;

  static const _maxProfilePhotoBytes = 5 * 1024 * 1024;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameCtrl = TextEditingController(text: user?.name ?? '');
    _emailCtrl = TextEditingController(text: user?.email ?? '');
    _phoneCtrl = TextEditingController(text: user?.phone ?? '');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickProfilePhoto() async {
    final image = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 90,
    );
    if (image == null) return;

    final photo = File(image.path);
    if (await photo.length() > _maxProfilePhotoBytes) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profile picture must be 5 MB or smaller.'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: 3),
        ),
      );
      return;
    }

    if (mounted) setState(() => _profilePhoto = photo);
  }

  Future<void> _save() async {
    // 1. Validate form fields
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    final repo = context.read<AuthRepository>();
    final authProvider = context.read<AuthProvider>();

    // 2. If the user selected a new profile picture, upload it first
    UserModel? photoUser;
    if (_profilePhoto != null) {
      try {
        final photoData = await repo.uploadProfilePhoto(_profilePhoto!);
        photoUser = UserModel.fromMap(photoData['user'] ?? photoData);
      } catch (e) {
        if (!mounted) return;
        final rawMsg = e.toString().replaceFirst('Exception: ', '').replaceFirst('ApiException: ', '');
        final friendlyMsg = rawMsg.contains('cURL') ||
                rawMsg.contains('Supabase') ||
                rawMsg.contains('host') ||
                rawMsg.contains('connection') ||
                rawMsg.contains('SocketException') ||
                rawMsg.contains('Failed host lookup')
            ? "We couldn't upload your profile picture. Please check your internet connection and try again."
            : rawMsg;
        // User inputs remain preserved in _nameCtrl, _emailCtrl, _phoneCtrl, and _profilePhoto
        setState(() {
          _error = friendlyMsg;
          _saving = false;
        });
        return;
      }
    }

    // 3. Update user profile information (name, email, phone) through backend API
    try {
      final data = await repo.updateProfile({
        'name': _nameCtrl.text.trim(),
        'email': _emailCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
      });

      if (!mounted) return;

      var updatedUser = UserModel.fromMap(data['user'] ?? data);
      // 4. Update the user's profile image reference/URL if photo was uploaded
      if (photoUser?.avatarUrl != null &&
          (updatedUser.avatarUrl == null || updatedUser.avatarUrl!.isEmpty)) {
        updatedUser = updatedUser.copyWith(avatarUrl: photoUser!.avatarUrl);
      }

      // 5. Refresh the user state so new profile data and avatar are immediately visible
      authProvider.updateUser(updatedUser);

      // 6. Display success message only after backend confirms changes
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profile updated successfully.'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );

      Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().replaceFirst('Exception: ', '').replaceFirst('ApiException: ', '');
      setState(() => _error = msg);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final addressProv = context.watch<AddressProvider>();
    final defaultAddress = addressProv.defaultAddress;

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
                // Top Header
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
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
                      const Text(
                        'Edit Profile',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ],
                  ),
                ),

                // Form Scroll View
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        children: [
                          // ── Avatar Preview Card ─────────────────────────
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(vertical: 20),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                                  blurRadius: 16,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Column(
                              children: [
                                Stack(
                                  children: [
                                    GestureDetector(
                                      onTap: _pickProfilePhoto,
                                      child: Container(
                                        width: 76,
                                        height: 76,
                                        decoration: const BoxDecoration(
                                          shape: BoxShape.circle,
                                          gradient: LinearGradient(
                                            colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                                            begin: Alignment.topLeft,
                                            end: Alignment.bottomRight,
                                          ),
                                        ),
                                        child: ClipOval(
                                          child: _profilePhoto != null
                                              ? Image.file(_profilePhoto!, fit: BoxFit.cover)
                                              : user?.avatarUrl != null && user!.avatarUrl!.isNotEmpty
                                                  ? Image.network(user.avatarUrl!, fit: BoxFit.cover)
                                                  : Center(
                                                      child: Text(
                                                        _nameCtrl.text.isNotEmpty
                                                            ? _nameCtrl.text[0].toUpperCase()
                                                            : (user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : 'U'),
                                                        style: const TextStyle(
                                                          fontSize: 32,
                                                          fontWeight: FontWeight.w800,
                                                          color: Colors.white,
                                                        ),
                                                      ),
                                                    ),
                                        ),
                                      ),
                                    ),
                                    Positioned(
                                      bottom: 0,
                                      right: 0,
                                      child: Container(
                                        padding: const EdgeInsets.all(4),
                                        decoration: const BoxDecoration(
                                          color: Color(0xFF0878C9),
                                          shape: BoxShape.circle,
                                        ),
                                        child: const Icon(
                                          Icons.camera_alt_rounded,
                                          size: 14,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                TextButton.icon(
                                  onPressed: _pickProfilePhoto,
                                  icon: const Icon(Icons.photo_library_outlined, size: 17),
                                  label: const Text('Upload profile picture (max 5 MB)'),
                                  style: TextButton.styleFrom(
                                    foregroundColor: const Color(0xFF0878C9),
                                  ),
                                ),
                                Text(
                                  user?.role.toUpperCase() ?? 'CUSTOMER',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF0878C9),
                                    letterSpacing: 0.8,
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 16),

                          // ── Personal Details Card ───────────────────────
                          Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                                  blurRadius: 16,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'PERSONAL INFORMATION',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF6F8BA3),
                                    letterSpacing: 0.8,
                                  ),
                                ),
                                const SizedBox(height: 16),

                                // Error message banner
                                if (_error != null) ...[
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                    decoration: BoxDecoration(
                                      color: AppColors.errorLight,
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(
                                          Icons.error_outline_rounded,
                                          color: AppColors.error,
                                          size: 20,
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            _error!,
                                            style: const TextStyle(
                                              color: AppColors.error,
                                              fontSize: 13,
                                              fontWeight: FontWeight.w500,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                ],

                                // Full Name
                                AquaClayneTextField(
                                  label: 'Full Name',
                                  hint: 'Enter your full name',
                                  controller: _nameCtrl,
                                  prefixIcon: Icons.person_outline_rounded,
                                  textInputAction: TextInputAction.next,
                                  validator: (v) =>
                                      (v == null || v.trim().isEmpty) ? 'Name is required' : null,
                                ),

                                const SizedBox(height: 16),

                                // Email
                                AquaClayneTextField(
                                  label: 'Email Address',
                                  hint: 'Enter your email address',
                                  controller: _emailCtrl,
                                  prefixIcon: Icons.email_outlined,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  validator: (v) {
                                    if (v == null || v.trim().isEmpty) {
                                      return 'Email is required';
                                    }
                                    if (!v.contains('@') || !v.contains('.')) {
                                      return 'Enter a valid email address';
                                    }
                                    return null;
                                  },
                                ),

                                const SizedBox(height: 16),

                                // Phone Number
                                AquaClayneTextField(
                                  label: 'Mobile Number',
                                  hint: 'e.g. 09123456789',
                                  controller: _phoneCtrl,
                                  keyboardType: TextInputType.phone,
                                  prefixIcon: Icons.phone_outlined,
                                  textInputAction: TextInputAction.done,
                                  validator: (v) =>
                                      (v == null || v.trim().isEmpty) ? 'Mobile number is required' : null,
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 16),

                          // ── Delivery Address Quick Card ─────────────────
                          Container(
                            padding: const EdgeInsets.all(18),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                                  blurRadius: 16,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
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
                                        'Manage',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                          color: Color(0xFF0878C9),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Container(
                                      width: 36,
                                      height: 36,
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFEAF7FF),
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: const Icon(
                                        Icons.location_on_outlined,
                                        color: Color(0xFF0878C9),
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: defaultAddress != null
                                          ? Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  defaultAddress.label.isNotEmpty
                                                      ? defaultAddress.label
                                                      : 'Default Address',
                                                  style: const TextStyle(
                                                    fontSize: 14,
                                                    fontWeight: FontWeight.w700,
                                                    color: Color(0xFF12344D),
                                                  ),
                                                ),
                                                const SizedBox(height: 2),
                                                Text(
                                                  defaultAddress.fullAddress.isNotEmpty
                                                      ? defaultAddress.fullAddress
                                                      : defaultAddress.addressLine,
                                                  style: const TextStyle(
                                                    fontSize: 12.5,
                                                    color: Color(0xFF6F8BA3),
                                                    height: 1.3,
                                                  ),
                                                ),
                                              ],
                                            )
                                          : const Text(
                                              'No delivery address saved yet.',
                                              style: TextStyle(
                                                fontSize: 13,
                                                color: Color(0xFF6F8BA3),
                                                fontStyle: FontStyle.italic,
                                              ),
                                            ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 24),

                          // ── Action Buttons ──────────────────────────────
                          SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: ElevatedButton(
                              onPressed: _saving ? null : _save,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF0878C9),
                                foregroundColor: Colors.white,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                              ),
                              child: _saving
                                  ? const SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.2,
                                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                      ),
                                    )
                                  : const Text(
                                      'Save Changes',
                                      style: TextStyle(
                                        fontSize: 15,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                            ),
                          ),

                          const SizedBox(height: 10),

                          SizedBox(
                            width: double.infinity,
                            height: 48,
                            child: OutlinedButton(
                              onPressed: _saving ? null : () => Navigator.pop(context),
                              style: OutlinedButton.styleFrom(
                                side: const BorderSide(color: Color(0xFFD5E7F4), width: 1.2),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                              ),
                              child: const Text(
                                'Cancel',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF6F8BA3),
                                ),
                              ),
                            ),
                          ),
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
}
