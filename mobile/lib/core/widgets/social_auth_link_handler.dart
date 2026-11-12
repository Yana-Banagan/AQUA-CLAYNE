import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../../customer/screens/customer_shell.dart';

class SocialAuthLinkHandler extends StatefulWidget {
  final Widget child;

  const SocialAuthLinkHandler({super.key, required this.child});

  @override
  State<SocialAuthLinkHandler> createState() => _SocialAuthLinkHandlerState();
}

class _SocialAuthLinkHandlerState extends State<SocialAuthLinkHandler> {
  final AppLinks _appLinks = AppLinks();
  final Set<String> _handledLinks = <String>{};
  StreamSubscription<Uri>? _subscription;

  @override
  void initState() {
    super.initState();
    _subscription = _appLinks.uriLinkStream.listen(
      _handleUri,
      onError: (Object error) {
        debugPrint(
          '[SocialAuthLinkHandler] Failed to receive app link: $error',
        );
        _showMessage(
          'Could not receive the sign-in response. Please try again.',
        );
      },
    );
    _readInitialLink();
  }

  Future<void> _readInitialLink() async {
    try {
      final initialLink = await _appLinks.getInitialLink();
      if (initialLink != null) {
        await _handleUri(initialLink);
      }
    } catch (error) {
      debugPrint(
        '[SocialAuthLinkHandler] Failed to read initial app link: $error',
      );
      _showMessage(
        'Could not receive the sign-in response. Please reopen the app and try again.',
      );
    }
  }

  Future<void> _handleUri(Uri uri) async {
    if (!mounted ||
        uri.scheme != 'aquaclayne' ||
        uri.host != 'auth' ||
        uri.path != '/callback' ||
        !_handledLinks.add(uri.toString())) {
      return;
    }

    final status = uri.queryParameters['status'];
    if (status != 'authenticated') {
      final message = switch (status) {
        'canceled' => 'Sign-in was canceled. You can try again.',
        'needs_linking' => 'An account already uses this email. Sign in to that account, then link your social account from your profile.',
        _ => 'Social sign-in could not be completed. Please return to the app and try again.',
      };
      _showMessage(message);
      return;
    }

    final code = uri.queryParameters['code'];
    if (code == null || !RegExp(r'^[A-Za-z0-9_-]{43}$').hasMatch(code)) {
      _showMessage('The sign-in response was invalid. Please try again.');
      return;
    }

    final result = await context.read<AuthProvider>().completeMobileSocialAuth(
      code,
    );
    if (!mounted) return;

    if (result.status != SocialAuthStatus.success || result.user == null) {
      _showMessage(result.message ?? 'Sign-in failed. Please try again.');
      return;
    }

    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const CustomerShell()),
      (_) => false,
    );
  }

  void _showMessage(String message) {
    if (!mounted) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ScaffoldMessenger.maybeOf(context)?.showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
    });
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
