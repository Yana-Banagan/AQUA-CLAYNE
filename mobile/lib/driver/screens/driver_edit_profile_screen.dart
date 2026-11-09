import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/repositories/auth_repository.dart';
import '../../core/models/user_model.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/app_button.dart';
import '../../shared_widgets/app_text_field.dart';

class DriverEditProfileScreen extends StatefulWidget {
  const DriverEditProfileScreen({super.key});

  @override
  State<DriverEditProfileScreen> createState() =>
      _DriverEditProfileScreenState();
}

class _DriverEditProfileScreenState
    extends State<DriverEditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameCtrl;
  late final TextEditingController _phoneCtrl;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameCtrl = TextEditingController(text: user?.name ?? '');
    _phoneCtrl = TextEditingController(text: user?.phone ?? '');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final repo = context.read<AuthRepository>();
      final data = await repo.updateProfile({
        'name': _nameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
      });
      if (!mounted) return;
      context.read<AuthProvider>().updateUser(
            UserModel.fromMap(data['user'] ?? data),
          );
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Profile updated successfully.')),
      );
      Navigator.pop(context);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DriverColors.background,
      appBar: AppBar(title: const Text('Edit Profile')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.standard),
          child: Form(
            key: _formKey,
            child: Column(children: [
              const SizedBox(height: AppSpacing.standard),
              CircleAvatar(
                radius: 40,
                backgroundColor: DriverColors.primaryLight.withValues(alpha: 0.15),
                child: Text(
                  _nameCtrl.text.isNotEmpty
                      ? _nameCtrl.text[0].toUpperCase()
                      : 'D',
                  style: const TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.primaryLight),
                ),
              ),
              const SizedBox(height: AppSpacing.major),
              AppTextField(
                label: 'Full Name',
                controller: _nameCtrl,
                prefixIcon: Icons.person_outline,
                textInputAction: TextInputAction.next,
                validator: (v) => v?.trim().isEmpty == true
                    ? 'Name is required'
                    : null,
              ),
              const SizedBox(height: AppSpacing.compact),
              AppTextField(
                label: 'Phone Number',
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                prefixIcon: Icons.phone_outlined,
                textInputAction: TextInputAction.done,
                validator: (v) => v?.trim().isEmpty == true
                    ? 'Phone is required'
                    : null,
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.standard),
                Container(
                  padding: const EdgeInsets.all(AppSpacing.compact),
                  decoration: BoxDecoration(
                    color: AppColors.errorLight,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(_error!,
                      style: const TextStyle(
                          color: AppColors.error, fontSize: 13)),
                ),
              ],
              const SizedBox(height: AppSpacing.section),
              AppButton(
                label: 'Save Changes',
                loading: _saving,
                onPressed: _saving ? null : _save,
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
