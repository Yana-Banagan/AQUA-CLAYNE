import 'package:flutter/material.dart';

import '../screens/login_screen.dart';
import '../screens/register_screen.dart';

/// Prompts a guest before an action that would create an order or cart item.
Future<void> showGuestOrderPrompt(BuildContext context) async {
  await showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(Icons.lock_outline_rounded, color: Color(0xFF0878C9)),
      title: const Text('Login required'),
      content: const Text(
        'Please log in or create an account first before adding items to your cart or placing an order.',
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.pop(dialogContext),
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFF8FA9BF)),
          ),
          child: const Text('Not now'),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: () {
                  Navigator.pop(dialogContext);
                  Navigator.of(context, rootNavigator: true).push(
                    MaterialPageRoute(builder: (_) => const RegisterScreen()),
                  );
                },
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFF0878C9)),
                ),
                child: const Text('Create Account'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton(
                onPressed: () {
                  Navigator.pop(dialogContext);
                  Navigator.of(context, rootNavigator: true).push(
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white,
                  backgroundColor: const Color(0xFF0878C9),
                  side: const BorderSide(color: Color(0xFF0878C9)),
                ),
                child: const Text('Login'),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}
