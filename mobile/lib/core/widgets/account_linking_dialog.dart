import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'aqua_clayne_password_field.dart';
import 'aqua_clayne_primary_button.dart';

/// Secure dialog that prompts users to confirm their existing password
/// before linking a Google or Facebook account with an already registered email.
class AccountLinkingDialog extends StatefulWidget {
  final String provider;
  final String email;
  final String linkToken;
  final Future<bool> Function(String linkToken, String password) onConfirmLink;

  const AccountLinkingDialog({
    super.key,
    required this.provider,
    required this.email,
    required this.linkToken,
    required this.onConfirmLink,
  });

  static Future<bool?> show({
    required BuildContext context,
    required String provider,
    required String email,
    required String linkToken,
    required Future<bool> Function(String linkToken, String password) onConfirmLink,
  }) {
    return showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AccountLinkingDialog(
        provider: provider,
        email: email,
        linkToken: linkToken,
        onConfirmLink: onConfirmLink,
      ),
    );
  }

  @override
  State<AccountLinkingDialog> createState() => _AccountLinkingDialogState();
}

class _AccountLinkingDialogState extends State<AccountLinkingDialog> {
  final _passwordCtrl = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate() || _loading) return;

    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final success = await widget.onConfirmLink(
        widget.linkToken,
        _passwordCtrl.text,
      );

      if (!mounted) return;

      if (success) {
        Navigator.of(context).pop(true);
      } else {
        setState(() {
          _loading = false;
          _errorMessage = 'Invalid password. Please check and try again.';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _errorMessage = e.toString().replaceAll('Exception:', '').trim();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final providerTitle = widget.provider.isNotEmpty
        ? widget.provider[0].toUpperCase() + widget.provider.substring(1)
        : 'Social';

    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      backgroundColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 440),
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Header Icon
                Center(
                  child: Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: AppColors.lightAqua,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.border, width: 1.5),
                    ),
                    child: const Icon(
                      Icons.link_rounded,
                      color: AppColors.aquaBlue,
                      size: 30,
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Title
                const Text(
                  'Link Your Account',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppColors.darkText,
                  ),
                ),
                const SizedBox(height: 10),

                // Description
                RichText(
                  textAlign: TextAlign.center,
                  text: TextSpan(
                    style: const TextStyle(
                      fontSize: 13.5,
                      color: AppColors.mutedText,
                      height: 1.45,
                    ),
                    children: [
                      const TextSpan(text: 'An account registered with '),
                      TextSpan(
                        text: widget.email,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppColors.darkText,
                        ),
                      ),
                      TextSpan(
                        text: ' already exists. Enter your password to securely link your $providerTitle account and preserve your orders and history.',
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Password Field
                AquaClaynePasswordField(
                  label: 'Account Password',
                  hint: 'Enter your password',
                  controller: _passwordCtrl,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _handleSubmit(),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter your account password.';
                    }
                    return null;
                  },
                ),

                if (_errorMessage != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: AppColors.errorLight,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.error.withValues(alpha: 0.2)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 18),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _errorMessage!,
                            style: const TextStyle(
                              fontSize: 12.5,
                              color: AppColors.error,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 24),

                // Actions
                AquaClaynePrimaryButton(
                  label: 'Verify & Link Account',
                  loading: _loading,
                  loadingLabel: 'Verifying...',
                  onPressed: _loading ? null : _handleSubmit,
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _loading ? null : () => Navigator.of(context).pop(false),
                  child: const Text(
                    'Cancel',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.mutedText,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
