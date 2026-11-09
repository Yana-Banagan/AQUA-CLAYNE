import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../repositories/auth_repository.dart';
import '../theme/app_colors.dart';

class ForgotPasswordDialog extends StatefulWidget {
  /// Taken from the login field, so it is never re-entered in this dialog.
  final String email;

  const ForgotPasswordDialog({super.key, required this.email});

  @override
  State<ForgotPasswordDialog> createState() => _ForgotPasswordDialogState();
}

class _ForgotPasswordDialogState extends State<ForgotPasswordDialog> {
  final _password = TextEditingController();
  final _confirmPassword = TextEditingController();
  final _otp = List.generate(4, (_) => TextEditingController());
  final _focus = List.generate(4, (_) => FocusNode());
  int _step = 1;
  bool _loading = false;
  String? _error;
  String? _token;

  String get _email => widget.email.trim().toLowerCase();
  bool get _hasValidEmail =>
      RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(_email);

  @override
  void dispose() {
    _password.dispose();
    _confirmPassword.dispose();
    for (final controller in _otp) {
      controller.dispose();
    }
    for (final focusNode in _focus) {
      focusNode.dispose();
    }
    super.dispose();
  }

  String _message(Object error) => error
      .toString()
      .replaceFirst('Exception: ', '')
      .replaceFirst('ApiException: ', '');

  Future<void> _send() async {
    if (!_hasValidEmail) {
      setState(() {
        _error = 'Enter your registered email address on the login page first.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await context.read<AuthRepository>().sendPasswordOtp(_email);
      if (!mounted) return;
      setState(() => _step = 2);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _focus.first.requestFocus();
      });
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _verify() async {
    final code = _otp.map((controller) => controller.text).join();
    if (!RegExp(r'^\d{4}$').hasMatch(code)) {
      setState(() => _error = 'Enter the 4-digit OTP.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final token =
          await context.read<AuthRepository>().verifyPasswordOtp(_email, code);
      if (token.isEmpty) throw Exception('Unable to verify OTP.');
      if (mounted) {
        setState(() {
          _token = token;
          _step = 3;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _reset() async {
    final password = _password.text;
    final isValid = password.length >= 8 &&
        RegExp(r'[A-Z]').hasMatch(password) &&
        RegExp(r'[a-z]').hasMatch(password) &&
        RegExp(r'\d').hasMatch(password) &&
        RegExp(r'[^A-Za-z0-9\s]').hasMatch(password) &&
        !RegExp(r'\s').hasMatch(password);
    if (!isValid) {
      setState(() {
        _error =
            'Use 8+ characters with uppercase, lowercase, a number, a special character, and no spaces.';
      });
      return;
    }
    if (_password.text != _confirmPassword.text) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await context.read<AuthRepository>().resetPassword(
            email: _email,
            resetToken: _token!,
            password: password,
          );
      if (!mounted) return;
      Navigator.pop(context);
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Password reset'),
          content: const Text(
            'Your password has been reset successfully. You can now log in.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Return to Login'),
            ),
          ],
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final actionLabel =
        _step == 1 ? 'Send OTP' : _step == 2 ? 'Verify OTP' : 'Save Password';
    final action = _step == 1 ? _send : _step == 2 ? _verify : _reset;

    return AlertDialog(
      title: Text(
        _step == 1
            ? 'Forgot Password'
            : _step == 2
                ? 'Verify OTP'
                : 'Create New Password',
      ),
      content: SizedBox(
        width: 340,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (_step == 1) ...[
              const Text(
                'We will send a 4-digit OTP to the email entered on the login page. It expires in 1 minute.',
              ),
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.lightAqua,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.email_outlined, color: AppColors.aquaBlue),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(_email.isEmpty ? 'No email entered' : _email),
                    ),
                  ],
                ),
              ),
            ],
            if (_step == 2) ...[
              Text('Enter the 4-digit code sent to $_email.'),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List.generate(
                  4,
                  (index) => SizedBox(
                    width: 54,
                    child: TextField(
                      controller: _otp[index],
                      focusNode: _focus[index],
                      textAlign: TextAlign.center,
                      keyboardType: TextInputType.number,
                      maxLength: 1,
                      onChanged: (value) {
                        if (value.isNotEmpty && index < 3) {
                          _focus[index + 1].requestFocus();
                        }
                      },
                      decoration: const InputDecoration(
                        counterText: '',
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ),
                ),
              ),
            ],
            if (_step == 3) ...[
              const Text(
                'Use 8+ characters with uppercase, lowercase, number, special character, and no spaces.',
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _password,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'New Password',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _confirmPassword,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm New Password',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  _error!,
                  style: const TextStyle(color: AppColors.error, fontSize: 12),
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _loading ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: _loading ? null : action,
          child: _loading
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : Text(actionLabel),
        ),
      ],
    );
  }
}
