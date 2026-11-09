import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../widgets/aqua_clayne_auth_background.dart';
import '../widgets/aqua_clayne_auth_card.dart';
import '../widgets/aqua_clayne_password_field.dart';
import '../widgets/aqua_clayne_primary_button.dart';
import '../widgets/aqua_clayne_text_field.dart';
import '../widgets/forgot_password_dialog.dart';
import '../../customer/screens/customer_shell.dart';
import '../../driver/screens/driver_shell.dart';
import '../models/user_model.dart';
import '../widgets/account_linking_dialog.dart';
import '../widgets/social_auth_section.dart';
import 'register_screen.dart';

/// Production-ready Login screen for AQUA CLAYNE Water Refilling Station.
///
/// Features:
/// - Storefront photo background with translucent aqua gradient overlay
/// - Official AQUA CLAYNE water droplet branding & slogan
/// - Clean white elevated auth card (responsive, keyboard-safe)
/// - Username/Email input and Password field with visibility toggle
/// - Remember me checkbox
/// - Informational "Forgot Password?" dialog (identifying backend support status)
/// - Debounced full-width login button with loading indicator
/// - Error handling with human-friendly messages
/// - Seamless role-based routing (Customer -> CustomerShell, Driver -> DriverShell)
class LoginScreen extends StatefulWidget {
  final String? sessionExpiredMessage;
  const LoginScreen({super.key, this.sessionExpiredMessage});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _rememberMe = false;
  DateTime? _lastBackPressTime;

  void _handleBackPress() {
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
      return;
    }

    final now = DateTime.now();
    if (_lastBackPressTime == null ||
        now.difference(_lastBackPressTime!) > const Duration(seconds: 2)) {
      _lastBackPressTime = now;
      ScaffoldMessenger.of(context).removeCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Press back again to exit Aqua Clayne'),
          duration: const Duration(seconds: 2),
          behavior: SnackBarBehavior.floating,
          action: SnackBarAction(
            label: 'Storefront',
            textColor: Colors.white,
            onPressed: () {
              Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
                MaterialPageRoute(builder: (_) => const CustomerShell()),
                (route) => false,
              );
            },
          ),
        ),
      );
    } else {
      SystemNavigator.pop();
    }
  }

  @override
  void initState() {
    super.initState();
    if (widget.sessionExpiredMessage != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(widget.sessionExpiredMessage!),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 4),
          ),
        );
      });
    }
  }

  @override
  void dispose() {

    _loginCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    final auth = context.read<AuthProvider>();
    if (auth.isLoading) return;

    if (!_formKey.currentState!.validate()) return;

    // Dismiss keyboard
    FocusScope.of(context).unfocus();

    auth.clearError();
    final success = await auth.login(
      _loginCtrl.text.trim(),
      _passCtrl.text,
    );

    if (!mounted) return;

    if (success && auth.user != null) {
      _navigateToDestination(auth.user!);
    }
  }

  void _navigateToDestination(UserModel user) {
    final destination = user.isDriver
        ? const DriverShell()
        : const CustomerShell();

    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      PageRouteBuilder(
        pageBuilder: (context, animation, secondaryAnimation) => destination,
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 300),
      ),
      (route) => false,
    );
  }

  Future<void> _handleSocialLogin(String provider) async {
    final auth = context.read<AuthProvider>();
    if (auth.isLoading) return;

    FocusScope.of(context).unfocus();
    auth.clearError();

    final result = await auth.startSocialAuth(provider);
    if (!mounted) return;

    if (result.status == SocialAuthStatus.unconfigured) {
      _showUnconfiguredDialog(provider, result.message);
    } else if (result.status == SocialAuthStatus.needsLinking) {
      if (result.linkData != null) {
        _handleAccountLinking(result.linkData!);
      }
    } else if (result.status == SocialAuthStatus.error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result.message ?? 'Authentication failed. Please try again.'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } else if (result.status == SocialAuthStatus.success && auth.isAuthenticated && auth.user != null) {
      _navigateToDestination(auth.user!);
    }
  }

  Future<void> _handleAccountLinking(Map<String, dynamic> linkData) async {
    final provider = linkData['provider']?.toString() ?? 'Social';
    final email = linkData['email']?.toString() ?? '';
    final linkToken = linkData['link_token']?.toString() ?? '';

    final linked = await AccountLinkingDialog.show(
      context: context,
      provider: provider,
      email: email,
      linkToken: linkToken,
      onConfirmLink: (token, password) async {
        return await context.read<AuthProvider>().verifyAndLinkAccount(token, password);
      },
    );

    if (linked == true && mounted) {
      final user = context.read<AuthProvider>().user;
      if (user != null) {
        _navigateToDestination(user);
      }
    }
  }

  void _showUnconfiguredDialog(String provider, String? message) {
    final name = provider.isNotEmpty ? provider[0].toUpperCase() + provider.substring(1) : 'Social';
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Row(
          children: [
            const Icon(Icons.settings_suggest_rounded, color: AppColors.aquaBlue, size: 26),
            const SizedBox(width: 10),
            Text('$name Sign-In'),
          ],
        ),
        content: Text(
          message ??
              '$name authentication is not configured yet. Server administrator must set OAuth client credentials in the backend environment.',
          style: const TextStyle(fontSize: 14, color: AppColors.darkText, height: 1.45),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.aquaBlue)),
          ),
        ],
      ),
    );
  }

  void _showForgotPasswordDialog() {
    showDialog(
      context: context,
      builder: (_) => ForgotPasswordDialog(email: _loginCtrl.text),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _handleBackPress();
      },
      child: AquaClayneAuthBackground(
        child: Stack(
          children: [
            Positioned(
              top: 8,
              left: 8,
              child: Material(
                color: Colors.white.withValues(alpha: 0.9),
                shape: const CircleBorder(),
                child: IconButton(
                  icon: const Icon(
                    Icons.arrow_back_rounded,
                    color: AppColors.deepBlue,
                  ),
                  tooltip: 'Back',
                  onPressed: () {
                    if (Navigator.of(context).canPop()) {
                      Navigator.of(context).pop();
                    } else {
                      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
                        MaterialPageRoute(builder: (_) => const CustomerShell()),
                        (route) => false,
                      );
                    }
                  },
                ),
              ),
            ),
          Center(
            child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.symmetric(
            horizontal: 20.0,
            vertical: 24.0,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // ── Form Card ──────────────────────────────────────────
              AquaClayneAuthCard(
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // ── Centered Main Heading ──────────────────────
                      const Center(
                        child: Text(
                          'Welcome',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w800,
                            color: AppColors.deepBlue,
                            letterSpacing: -0.2,
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // ── Error Banner ────────────────────────────────
                      Consumer<AuthProvider>(
                        builder: (context, auth, _) {
                          if (auth.error == null) return const SizedBox.shrink();
                          return Container(
                            margin: const EdgeInsets.only(bottom: 16),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 12,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.errorLight,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: AppColors.error.withValues(alpha: 0.3),
                              ),
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
                                    auth.error!,
                                    style: const TextStyle(
                                      color: AppColors.error,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),

                      // ── Username or Email Field ─────────────────────
                      AquaClayneTextField(
                        label: 'Username, Email, or Mobile',
                        hint: 'Username, email, or 09XXXXXXXXX',
                        controller: _loginCtrl,
                        prefixIcon: Icons.person_outline_rounded,
                        keyboardType: TextInputType.text,
                        textInputAction: TextInputAction.next,
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'Please enter your username, email, or mobile number.';
                          }
                          if (value.trim().length > 150) {
                            return 'Login information must not exceed 150 characters.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // ── Password Field ──────────────────────────────
                      AquaClaynePasswordField(
                        label: 'Password',
                        hint: 'Password',
                        controller: _passCtrl,
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) => _handleLogin(),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please enter your password.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 10),

                      // ── Remember Me & Forgot Password ───────────────
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              SizedBox(
                                width: 24,
                                height: 24,
                                child: Checkbox(
                                  value: _rememberMe,
                                  onChanged: (val) {
                                    setState(() {
                                      _rememberMe = val ?? false;
                                    });
                                  },
                                  activeColor: AppColors.aquaBlue,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  side: const BorderSide(
                                    color: AppColors.border,
                                    width: 1.5,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              GestureDetector(
                                onTap: () {
                                  setState(() {
                                    _rememberMe = !_rememberMe;
                                  });
                                },
                                child: const Text(
                                  'Remember me',
                                  style: TextStyle(
                                    fontSize: 13,
                                    color: AppColors.mutedText,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          GestureDetector(
                            onTap: _showForgotPasswordDialog,
                            child: const Text(
                              'Forgot Password?',
                              style: TextStyle(
                                fontSize: 13,
                                color: AppColors.aquaBlue,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),

                      // ── Log In Button ───────────────────────────────
                      Consumer<AuthProvider>(
                        builder: (context, auth, _) => AquaClaynePrimaryButton(
                          label: 'Log In',
                          loading: auth.isLoading && !auth.isSocialLoading,
                          loadingLabel: 'Logging in...',
                          onPressed: auth.isLoading ? null : _handleLogin,
                        ),
                      ),
                      const SizedBox(height: 20),

                      // ── Social Authentication Section ────────────────
                      Consumer<AuthProvider>(
                        builder: (context, auth, _) => SocialAuthSection(
                          activeLoadingProvider: auth.socialLoadingProvider,
                          disabled: auth.isLoading,
                          onGooglePressed: () => _handleSocialLogin('google'),
                          onFacebookPressed: () => _handleSocialLogin('facebook'),
                        ),
                      ),
                      const SizedBox(height: 22),

                      // ── Register Navigation Link ────────────────────
                      Center(
                        child: Wrap(
                          crossAxisAlignment: WrapCrossAlignment.center,
                          alignment: WrapAlignment.center,
                          children: [
                            const Text(
                              "Don't have an account? ",
                              style: TextStyle(
                                fontSize: 13.5,
                                color: AppColors.mutedText,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            GestureDetector(
                              onTap: () {
                                context.read<AuthProvider>().clearError();
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => const RegisterScreen(),
                                  ),
                                );
                              },
                              child: const Text(
                                'Create Account',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: AppColors.aquaBlue,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
            ),
          ),
        ],
      ),
    ),
  );
}
}
