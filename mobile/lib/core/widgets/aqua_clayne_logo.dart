import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Official branding header widget for AQUA CLAYNE authentication screens.
///
/// Displays:
/// 1. Water droplet emblem in a subtle glass/aqua circle
/// 2. "AQUA CLAYNE"
/// 3. "PURIFIED DRINKING WATER"
/// 4. "Expect the Purest"
class AquaClayneLogo extends StatelessWidget {
  final double iconSize;
  final bool compact;

  const AquaClayneLogo({
    super.key,
    this.iconSize = 44,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // ── Water Droplet Icon Emblem ─────────────────────────────────
        Container(
          width: iconSize + 22,
          height: iconSize + 22,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Colors.white,
                AppColors.lightAqua,
              ],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.aquaBlue.withValues(alpha: 0.22),
                blurRadius: 18,
                offset: const Offset(0, 6),
              ),
            ],
            border: Border.all(
              color: AppColors.border,
              width: 1.5,
            ),
          ),
          child: Center(
            child: Icon(
              Icons.water_drop_rounded,
              size: iconSize,
              color: AppColors.aquaBlue,
            ),
          ),
        ),
        SizedBox(height: compact ? 8 : 12),

        // ── Main Brand Title ──────────────────────────────────────────
        const Text(
          'AQUA CLAYNE',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: 2.5,
            color: AppColors.deepBlue,
            height: 1.1,
          ),
        ),
        const SizedBox(height: 4),

        // ── Purified Drinking Water ───────────────────────────────────
        const Text(
          'PURIFIED DRINKING WATER',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.8,
            color: AppColors.aquaBlue,
          ),
        ),
        const SizedBox(height: 2),

        // ── Tagline ───────────────────────────────────────────────────
        Text(
          'Expect the Purest',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            fontStyle: FontStyle.italic,
            color: AppColors.mutedText.withValues(alpha: 0.9),
            letterSpacing: 0.5,
          ),
        ),
      ],
    );
  }
}
