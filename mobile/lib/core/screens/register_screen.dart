import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../widgets/aqua_clayne_auth_background.dart';
import '../widgets/aqua_clayne_auth_card.dart';
import '../widgets/aqua_clayne_password_field.dart';
import '../widgets/aqua_clayne_primary_button.dart';
import '../widgets/aqua_clayne_text_field.dart';
import '../../customer/screens/customer_shell.dart';
import '../models/user_model.dart';
import '../widgets/account_linking_dialog.dart';
import '../widgets/social_auth_section.dart';
import 'login_screen.dart';

/// Customer Registration Screen for AQUA CLAYNE.
///
/// Strictly creates CUSTOMER accounts.
/// Drivers and Admins are created exclusively by Admin via the web portal.
/// No role selection or driver registration is permitted here.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();

  // Form field controllers
  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _usernameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _mobileCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();
  final _birthdateCtrl = TextEditingController();
  final _passwordFocus = FocusNode();

  String _currentPassword = '';
  DateTime? _birthdate;
  bool _passwordHasFocus = false;
  bool _termsAccepted = false;
  String? _termsError;

  static const _allowedEmailDomains = {
    'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com',
    'aol.com', 'protonmail.com', 'zoho.com', 'yandex.com', 'lu.edu.ph',
  };
  static const _commonPasswords = {
    'password', 'password1', 'password123', '123456', '12345678', 'qwerty',
    'admin', 'admin123', 'letmein', 'welcome', 'abc123', 'iloveyou',
    'monkey', 'football', 'dragon', 'login',
  };

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _usernameCtrl.dispose();
    _emailCtrl.dispose();
    _mobileCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    _birthdateCtrl.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _passwordFocus.addListener(() {
      if (mounted) setState(() => _passwordHasFocus = _passwordFocus.hasFocus);
    });
  }

  String _normaliseName(String value) => value.trim().replaceAll(RegExp(r'\s+'), ' ');

  void _normaliseNameInput(TextEditingController controller, String value) {
    // Keep a single space between words while the customer types. Leading and
    // trailing whitespace is removed before the value is submitted.
    final normalised = value.replaceAll(RegExp(r'\s+'), ' ');
    if (normalised != value) {
      controller.value = TextEditingValue(
        text: normalised,
        selection: TextSelection.collapsed(offset: normalised.length),
      );
    }
  }

  String? _validateName(String? value, String label) {
    final name = _normaliseName(value ?? '');
    if (name.isEmpty) return '$label is required.';
    if (name.length < 2) return '$label must have at least 2 characters.';
    if (!RegExp(r'^[A-Za-z]+(?: [A-Za-z]+)*$').hasMatch(name)) {
      return '$label may contain letters and single spaces only.';
    }
    return null;
  }

  String _normalisePhone(String value) => value.replaceAll(RegExp(r'[\s-]'), '');

  String? _validatePhone(String? value) {
    final phone = _normalisePhone(value ?? '');
    if (phone.isEmpty) return 'Mobile number is required.';
    if (phone.length > 11) {
      return 'Mobile number must not exceed 11 digits.';
    }
    if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
      return 'Use exactly 11 digits in the format 09XXXXXXXXX.';
    }
    final digits = phone.replaceAll('+', '');
    if (RegExp(r'(\d)\1\1\1').hasMatch(digits) ||
        digits.split('').toSet().length == 1) {
      return 'Please enter a valid mobile number.';
    }
    return null;
  }

  String? _validateEmail(String? value) {
    final email = (value ?? '').trim().toLowerCase();
    if (email.isEmpty) return 'Email is required.';
    final at = email.lastIndexOf('@');
    if (at < 3 || at != email.indexOf('@') || at == email.length - 1) {
      return 'Please enter a valid email address.';
    }
    final domain = email.substring(at + 1);
    if (domain.endsWith('.co')) {
      return '.co email domains are not accepted.';
    }
    if (!_allowedEmailDomains.contains(domain)) {
      return 'Please use a supported email provider.';
    }
    return null;
  }

  bool _hasValidPassword(String value) => value.length >= 8 &&
      RegExp(r'[A-Z]').hasMatch(value) && RegExp(r'[a-z]').hasMatch(value) &&
      RegExp(r'\d').hasMatch(value) && RegExp(r'[^A-Za-z0-9\s]').hasMatch(value) &&
      !RegExp(r'\s').hasMatch(value) && !_commonPasswords.contains(value.toLowerCase());

  String? _validatePassword(String? value) {
    final password = value ?? '';
    if (password.isEmpty) return 'Password is required.';
    if (!_hasValidPassword(password)) return 'Use all password requirements below.';
    return null;
  }

  int _ageOn(DateTime birthdate, DateTime today) {
    var age = today.year - birthdate.year;
    if (DateTime(today.year, birthdate.month, birthdate.day).isAfter(today)) age--;
    return age;
  }

  String? _validateBirthdate(String? _) {
    if (_birthdate == null) return 'Birthdate is required.';
    final age = _ageOn(_birthdate!, DateTime.now());
    if (_birthdate!.isAfter(DateTime.now()) || age < 18 || age > 99) {
      return 'You must be between 18 and 99 years old to create an account.';
    }
    return null;
  }

  Future<void> _selectBirthdate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _birthdate ?? DateTime(2000, 1, 1),
      firstDate: DateTime(1956, 1, 1),
      lastDate: DateTime(2008, 12, 31),
      helpText: 'CONFIRM YOUR BIRTHDATE',
      confirmText: 'Confirm',
    );
    if (selected == null || !mounted) return;
    setState(() {
      _birthdate = selected;
      _birthdateCtrl.text = '${selected.month.toString().padLeft(2, '0')}/${selected.day.toString().padLeft(2, '0')}/${selected.year}';
    });
  }

  Widget _passwordRequirements() {
    if (!_passwordHasFocus || _currentPassword.isEmpty) return const SizedBox.shrink();
    final checks = <(String, bool)>[
      ('At least 8 characters', _currentPassword.length >= 8),
      ('One uppercase letter', RegExp(r'[A-Z]').hasMatch(_currentPassword)),
      ('One lowercase letter', RegExp(r'[a-z]').hasMatch(_currentPassword)),
      ('One number', RegExp(r'\d').hasMatch(_currentPassword)),
      ('One special character', RegExp(r'[^A-Za-z0-9\s]').hasMatch(_currentPassword)),
      ('No spaces', !RegExp(r'\s').hasMatch(_currentPassword)),
    ];
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 4),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: checks.map((check) => Padding(
        padding: const EdgeInsets.only(bottom: 3),
        child: Text('${check.$2 ? '✔' : '❌'} ${check.$1}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: check.$2 ? Colors.green.shade700 : AppColors.error)),
      )).toList()),
    );
  }

  Future<void> _handleRegister() async {
    final auth = context.read<AuthProvider>();
    if (auth.isLoading) return;

    if (!_termsAccepted) {
      setState(() {
        _termsError =
            'Please accept the Terms and Conditions before creating your account.';
      });
      return;
    }

    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();
    auth.clearError();

    final firstName = _normaliseName(_firstNameCtrl.text);
    final lastName = _normaliseName(_lastNameCtrl.text);
    final username = _usernameCtrl.text.trim();
    final email = _emailCtrl.text.trim().toLowerCase();
    final mobile = _normalisePhone(_mobileCtrl.text);
    final password = _passwordCtrl.text;
    final confirmPassword = _confirmPasswordCtrl.text;

    final normalizedPhone = mobile;
    if ('$firstName $lastName'.split(' ').length > 6) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Your full name cannot exceed 6 words.'),
      ));
      return;
    }

    // Prepare payload adhering to the backend contract.
    // Note: NEVER send 'role'. The Laravel backend authoritatively sets 'customer'.
    final payload = <String, dynamic>{
      'name': '$firstName $lastName',
      'first_name': firstName,
      'last_name': lastName,
      'username': username,
      'email': email,
      'phone': normalizedPhone,
      'mobile_number': normalizedPhone,
      'birthdate': '${_birthdate!.year.toString().padLeft(4, '0')}-${_birthdate!.month.toString().padLeft(2, '0')}-${_birthdate!.day.toString().padLeft(2, '0')}',
      'password': password,
      'password_confirmation': confirmPassword,
    };

    final success = await auth.register(payload);

    if (!mounted) return;

    if (!success) return;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Account created'),
        content: const Text(
          'Your account has been created successfully. You can now log in.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Continue to Login'),
          ),
        ],
      ),
    );
    if (!mounted) return;
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  void _navigateToCustomerShell(UserModel user) {
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      PageRouteBuilder(
        pageBuilder: (context, animation, secondaryAnimation) => const CustomerShell(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 300),
      ),
      (route) => false,
    );
  }

  Future<void> _handleSocialAuth(String provider) async {
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
      _navigateToCustomerShell(auth.user!);
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
        _navigateToCustomerShell(user);
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
            Text('$name Registration'),
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

  Future<void> _showTermsAndConditions() async {
    final accepted = await showDialog<bool>(
      context: context,
      barrierDismissible: true,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Terms and Conditions'),
        content: const SizedBox(
          width: double.maxFinite,
          child: SingleChildScrollView(
            child: Text(
              '''AQUA CLAYNE WATER REFILLING SERVICE

1. Account and eligibility
You must provide accurate, current information and be at least 18 years old to create an account. You are responsible for keeping your password confidential and for activity performed through your account.

2. Orders and delivery
Orders are subject to product availability, service-area coverage, and confirmation by AQUA CLAYNE. Please provide a complete delivery address, reachable contact number, and make customer-owned containers available when required.

3. Pricing and payment
Displayed prices, delivery fees, payment options, and order totals are confirmed at checkout. Cash, online payment, or approved account credit must be paid according to the selected method.

4. Customer-owned containers
You are responsible for ensuring containers presented for refill are safe, clean, and in serviceable condition. AQUA CLAYNE may decline unsafe or unsuitable containers.

5. Cancellations and service changes
You may request cancellation before processing begins. AQUA CLAYNE may adjust delivery timing or cancel an order when service, safety, payment, inventory, or operational conditions require it.

6. Privacy
We use your account, contact, delivery, and order information to provide and support the service, process payments, communicate order updates, and meet legal obligations. We protect this information in accordance with applicable law.

7. Acceptable use
Do not use the app for fraudulent, unlawful, abusive, or disruptive activity. We may suspend or close accounts that violate these terms or compromise the service.

8. Changes and contact
We may update these terms when needed. Continued use after an update means you accept the revised terms. Contact AQUA CLAYNE for questions about your account, orders, or these terms.

By selecting Accept, you confirm that you have read, understood, and agree to these Terms and Conditions.''',
              style: TextStyle(fontSize: 14, height: 1.45),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Close'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Accept'),
          ),
        ],
      ),
    );
    if (!mounted) return;
    setState(() {
      _termsAccepted = accepted == true;
      _termsError = null;
    });
  }

  Future<void> _onTermsChanged(bool enabled) async {
    if (!enabled) {
      setState(() {
        _termsAccepted = false;
        _termsError = null;
      });
      return;
    }
    await _showTermsAndConditions();
  }

  void _handleBackPress() {
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    } else {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    }
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
        child: Scaffold(
          backgroundColor: Colors.transparent,
          appBar: AppBar(
            backgroundColor: Colors.transparent,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(
                Icons.arrow_back_ios_new_rounded,
                color: AppColors.deepBlue,
                size: 20,
              ),
              onPressed: _handleBackPress,
              tooltip: 'Back to Login',
            ),
          centerTitle: true,
          title: const Text(
            'Create Account',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.deepBlue,
            ),
          ),
        ),
        body: Center(
          child: SingleChildScrollView(
            physics: const BouncingScrollPhysics(),
            padding: const EdgeInsets.symmetric(
              horizontal: 20.0,
              vertical: 12.0,
            ),
            child: Column(
              children: [
                // ── Registration Card ─────────────────────────────────
                AquaClayneAuthCard(
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // ── Header Text ───────────────────────────────
                        const Text(
                          'Create Account',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                            color: AppColors.deepBlue,
                            letterSpacing: -0.2,
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Create an account to manage your water refills with ease.',
                          style: TextStyle(
                            fontSize: 13.5,
                            color: AppColors.mutedText,
                            fontWeight: FontWeight.w500,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 20),

                        // ── Error Banner ──────────────────────────────
                        Consumer<AuthProvider>(
                          builder: (context, auth, _) {
                            if (auth.error == null) {
                              return const SizedBox.shrink();
                            }
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

                        // ── First Name & Last Name (Responsive 50%/50% Row) ───
                        LayoutBuilder(
                          builder: (context, constraints) {
                            // On desktop, laptop, tablet, and standard screens,
                            // display First Name and Last Name side-by-side with ~50% width each.
                            // On narrow screens (< 300px), stack vertically to prevent cramped fields.
                            final bool isHorizontal = constraints.maxWidth >= 300;
                            if (isHorizontal) {
                              return Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: AquaClayneTextField(
                                      label: 'First Name',
                                      hint: 'Enter your first name',
                                      controller: _firstNameCtrl,
                                      prefixIcon: Icons.person_outline_rounded,
                                      textInputAction: TextInputAction.next,
                                      autovalidateMode: AutovalidateMode.onUserInteraction,
                                      onChanged: (value) => _normaliseNameInput(_firstNameCtrl, value),
                                      validator: (val) => _validateName(val, 'First name'),
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: AquaClayneTextField(
                                      label: 'Last Name',
                                      hint: 'Enter your last name',
                                      controller: _lastNameCtrl,
                                      prefixIcon: Icons.person_outline_rounded,
                                      textInputAction: TextInputAction.next,
                                      autovalidateMode: AutovalidateMode.onUserInteraction,
                                      onChanged: (value) => _normaliseNameInput(_lastNameCtrl, value),
                                      validator: (val) => _validateName(val, 'Last name'),
                                    ),
                                  ),
                                ],
                              );
                            } else {
                              return Column(
                                children: [
                                  AquaClayneTextField(
                                    label: 'First Name',
                                    hint: 'Enter your first name',
                                    controller: _firstNameCtrl,
                                    prefixIcon: Icons.person_outline_rounded,
                                    textInputAction: TextInputAction.next,
                                    autovalidateMode: AutovalidateMode.onUserInteraction,
                                    onChanged: (value) => _normaliseNameInput(_firstNameCtrl, value),
                                    validator: (val) => _validateName(val, 'First name'),
                                  ),
                                  const SizedBox(height: 14),
                                  AquaClayneTextField(
                                    label: 'Last Name',
                                    hint: 'Enter your last name',
                                    controller: _lastNameCtrl,
                                    prefixIcon: Icons.person_outline_rounded,
                                    textInputAction: TextInputAction.next,
                                    autovalidateMode: AutovalidateMode.onUserInteraction,
                                    onChanged: (value) => _normaliseNameInput(_lastNameCtrl, value),
                                    validator: (val) => _validateName(val, 'Last name'),
                                  ),
                                ],
                              );
                            }
                          },
                        ),
                        const SizedBox(height: 14),

                        // ── Username Field ────────────────────────────
                        AquaClayneTextField(
                          label: 'Username',
                          hint: 'Choose a username',
                          controller: _usernameCtrl,
                          prefixIcon: Icons.alternate_email_rounded,
                          textInputAction: TextInputAction.next,
                          autovalidateMode: AutovalidateMode.onUserInteraction,
                          onChanged: (value) {
                            final normalized = value.trim().toLowerCase();
                            if (normalized != value) {
                              _usernameCtrl.value = TextEditingValue(
                                text: normalized,
                                selection: TextSelection.collapsed(offset: normalized.length),
                              );
                            }
                          },
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) {
                              return 'Username is required.';
                            }
                            if (val.trim().length < 3) {
                              return 'Username must be at least 3 characters.';
                            }
                            if (val.trim().length > 30) {
                              return 'Username must not exceed 30 characters.';
                            }
                            if (!RegExp(r'^[a-zA-Z0-9._]+$').hasMatch(val.trim())) {
                              return 'Use letters, numbers, dot or underscore.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),

                        // ── Email Field ───────────────────────────────
                        AquaClayneTextField(
                          label: 'Email Address',
                          hint: 'Enter your email',
                          controller: _emailCtrl,
                          keyboardType: TextInputType.emailAddress,
                          prefixIcon: Icons.email_outlined,
                          textInputAction: TextInputAction.next,
                          autovalidateMode: AutovalidateMode.onUserInteraction,
                          onChanged: (value) {
                            final normalised = value.toLowerCase();
                            if (normalised != value) {
                              _emailCtrl.value = TextEditingValue(
                                text: normalised,
                                selection: TextSelection.collapsed(offset: normalised.length),
                              );
                            }
                          },
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) {
                              return 'Email is required.';
                            }
                            final emailRegex = RegExp(
                              r'^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$',
                            );
                            if (!emailRegex.hasMatch(val.trim())) {
                              return 'Please enter a valid email address.';
                            }
                            return _validateEmail(val);
                          },
                        ),
                        const SizedBox(height: 14),

                        // ── Mobile Number Field ───────────────────────
                        AquaClayneTextField(
                          label: 'Mobile Number',
                          hint: '09XXXXXXXXX',
                          controller: _mobileCtrl,
                          keyboardType: TextInputType.phone,
                          prefixIcon: Icons.phone_android_rounded,
                          textInputAction: TextInputAction.next,
                          autovalidateMode: AutovalidateMode.onUserInteraction,
                          onChanged: (value) {
                            final normalised = _normalisePhone(value);
                            if (normalised != value) {
                              _mobileCtrl.value = TextEditingValue(
                                text: normalised,
                                selection: TextSelection.collapsed(offset: normalised.length),
                              );
                            }
                          },
                          validator: _validatePhone,
                        ),
                        const SizedBox(height: 14),

                        AquaClayneTextField(
                          label: 'Birthdate',
                          hint: 'MM/DD/YYYY',
                          controller: _birthdateCtrl,
                          readOnly: true,
                          onTap: _selectBirthdate,
                          prefixIcon: Icons.cake_outlined,
                          autovalidateMode: AutovalidateMode.onUserInteraction,
                          validator: _validateBirthdate,
                        ),
                        const SizedBox(height: 14),

                        // ── Password Field ────────────────────────────
                        AquaClaynePasswordField(
                          label: 'Password',
                          hint: 'Create a password',
                          controller: _passwordCtrl,
                          focusNode: _passwordFocus,
                          validationBorderColor: _currentPassword.isEmpty
                              ? null
                              : (_hasValidPassword(_currentPassword)
                                  ? Colors.green.shade700
                                  : AppColors.error),
                          textInputAction: TextInputAction.next,
                          onChanged: (val) {
                            setState(() {
                              _currentPassword = val;
                            });
                          },
                          validator: _validatePassword,
                        ),

                        // ── Real-time Password Strength Meter ─────────
                        _passwordRequirements(),
                        const SizedBox(height: 10),

                        // ── Confirm Password Field ────────────────────
                        AquaClaynePasswordField(
                          label: 'Confirm Password',
                          hint: 'Confirm your password',
                          controller: _confirmPasswordCtrl,
                          textInputAction: TextInputAction.done,
                          onFieldSubmitted: (_) => _handleRegister(),
                          validator: (val) {
                            if (val == null || val.isEmpty) {
                              return 'Please confirm your password.';
                            }
                            if (val != _passwordCtrl.text) {
                              return 'Passwords do not match.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 24),

                        // ── Create Account Button ─────────────────────
                        Container(
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.05),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: _termsError == null
                                  ? AppColors.primary.withValues(alpha: 0.18)
                                  : AppColors.error.withValues(alpha: 0.45),
                            ),
                          ),
                          child: SwitchListTile.adaptive(
                            value: _termsAccepted,
                            onChanged: _onTermsChanged,
                            activeThumbColor: AppColors.primary,
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                            title: const Text(
                              'I agree to the Terms and Conditions',
                              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.deepBlue),
                            ),
                            subtitle: const Text('Turn on to review and accept.', style: TextStyle(fontSize: 12)),
                          ),
                        ),
                        if (_termsError != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            _termsError!,
                            style: const TextStyle(color: AppColors.error, fontSize: 12.5, fontWeight: FontWeight.w600),
                          ),
                        ],
                        const SizedBox(height: 16),

                        Consumer<AuthProvider>(
                          builder: (context, auth, _) => AquaClaynePrimaryButton(
                            label: 'Create Account',
                            loading: auth.isLoading && !auth.isSocialLoading,
                            loadingLabel: 'Creating account...',
                            onPressed: auth.isLoading ? null : _handleRegister,
                          ),
                        ),
                        const SizedBox(height: 20),

                        // ── Social Authentication Section ────────────────
                        Consumer<AuthProvider>(
                          builder: (context, auth, _) => SocialAuthSection(
                            dividerText: 'OR SIGN UP WITH',
                            activeLoadingProvider: auth.socialLoadingProvider,
                            disabled: auth.isLoading,
                            onGooglePressed: () => _handleSocialAuth('google'),
                            onFacebookPressed: () => _handleSocialAuth('facebook'),
                          ),
                        ),
                        const SizedBox(height: 22),

                        // ── Login Navigation Link ─────────────────────
                        Center(
                          child: Wrap(
                            crossAxisAlignment: WrapCrossAlignment.center,
                            alignment: WrapAlignment.center,
                            children: [
                              const Text(
                                'Already have an account? ',
                                style: TextStyle(
                                  fontSize: 13.5,
                                  color: AppColors.mutedText,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              GestureDetector(
                                onTap: () => Navigator.pop(context),
                                child: const Text(
                                  'Log In',
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
                const SizedBox(height: 24),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
}
