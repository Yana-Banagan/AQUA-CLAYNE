import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

enum PasswordStrength { none, weak, medium, strong }

/// Real-time password strength indicator with animated bars and helper labels.
class PasswordStrengthIndicator extends StatelessWidget {
  final String password;

  const PasswordStrengthIndicator({
    super.key,
    required this.password,
  });

  PasswordStrength get strength {
    if (password.isEmpty) return PasswordStrength.none;

    int score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (RegExp(r'[A-Z]').hasMatch(password)) score++;
    if (RegExp(r'[a-z]').hasMatch(password)) score++;
    if (RegExp(r'[0-9]').hasMatch(password)) score++;
    if (RegExp(r'[!@#\$&*~%^()_\-+={}\[\]:;"<>,.?/\\|]').hasMatch(password)) {
      score++;
    }

    if (password.length < 8 || score <= 2) {
      return PasswordStrength.weak;
    } else if (score <= 4) {
      return PasswordStrength.medium;
    } else {
      return PasswordStrength.strong;
    }
  }

  Color _getColor(PasswordStrength s) {
    switch (s) {
      case PasswordStrength.weak:
        return const Color(0xFFE53935); // red/orange
      case PasswordStrength.medium:
        return const Color(0xFFFB8C00); // amber
      case PasswordStrength.strong:
        return const Color(0xFF43A047); // green
      case PasswordStrength.none:
        return AppColors.border;
    }
  }

  String _getLabel(PasswordStrength s) {
    switch (s) {
      case PasswordStrength.weak:
        return 'Weak';
      case PasswordStrength.medium:
        return 'Medium';
      case PasswordStrength.strong:
        return 'Strong';
      case PasswordStrength.none:
        return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = strength;
    if (s == PasswordStrength.none) {
      return const SizedBox.shrink();
    }

    final color = _getColor(s);
    final label = _getLabel(s);

    final int activeSegments = s == PasswordStrength.weak
        ? 1
        : s == PasswordStrength.medium
            ? 2
            : 3;

    return Padding(
      padding: const EdgeInsets.only(top: 8.0, bottom: 4.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              for (int i = 1; i <= 3; i++) ...[
                Expanded(
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    height: 4,
                    decoration: BoxDecoration(
                      color: i <= activeSegments
                          ? color
                          : AppColors.border.withValues(alpha: 0.6),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                if (i < 3) const SizedBox(width: 6),
              ],
            ],
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Password strength: $label',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: color,
                ),
              ),
              if (s == PasswordStrength.weak)
                const Text(
                  'Use 8+ chars, numbers & symbols',
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.mutedText,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
