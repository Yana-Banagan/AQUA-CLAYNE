import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Elevated, rounded authentication card container.
///
/// Designed to provide high legibility for forms over the storefront background.
class AquaClayneAuthCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry? padding;

  const AquaClayneAuthCard({
    super.key,
    required this.child,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
        child: Container(
          width: double.infinity,
          padding: padding ??
              const EdgeInsets.symmetric(
                horizontal: 24.0,
                vertical: 28.0,
              ),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.96),
            borderRadius: BorderRadius.circular(28.0),
            border: Border.all(
              color: AppColors.border,
              width: 1.2,
            ),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF003C6E).withValues(alpha: 0.12),
                blurRadius: 36,
                offset: const Offset(0, 16),
                spreadRadius: 0,
              ),
              BoxShadow(
                color: AppColors.aquaBlue.withValues(alpha: 0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: child,
        ),
      ),
    );
  }
}
