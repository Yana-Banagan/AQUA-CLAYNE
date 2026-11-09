import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Layered background for AQUA CLAYNE authentication screens.
///
/// Composition:
/// 1. Storefront background image (`assets/images/aqua_clayne_background.png`) with [BoxFit.cover]
/// 2. Translucent gradient overlay (Deep Blue -> Aqua Blue -> Light Aqua/White)
/// 3. Authentication content / scrollable cards
class AquaClayneAuthBackground extends StatelessWidget {
  final Widget child;

  const AquaClayneAuthBackground({
    super.key,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.lightAqua,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Layer 1: Storefront Photo Background ───────────────────
          Image.asset(
            'assets/images/aqua_clayne_background.png',
            fit: BoxFit.cover,
            alignment: Alignment.topCenter,
            errorBuilder: (context, error, stackTrace) {
              // Fallback background in case asset is missing
              return Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      AppColors.deepBlue,
                      AppColors.aquaBlue,
                      AppColors.lightAqua,
                    ],
                  ),
                ),
              );
            },
          ),

          // ── Layer 2: Translucent Blue & White Gradient Overlay ──────
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: const [0.0, 0.38, 0.75, 1.0],
                colors: [
                  AppColors.deepBlue.withValues(alpha: 0.42),
                  AppColors.aquaBlue.withValues(alpha: 0.55),
                  AppColors.lightAqua.withValues(alpha: 0.92),
                  AppColors.lightAqua.withValues(alpha: 0.98),
                ],
              ),
            ),
          ),

          // ── Layer 3: Subtle Top Ambient Glow ────────────────────────
          Positioned(
            top: -60,
            right: -60,
            child: Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.accentBlue.withValues(alpha: 0.20),
              ),
            ),
          ),

          // ── Layer 4: Screen Content ─────────────────────────────────
          SafeArea(
            child: child,
          ),
        ],
      ),
    );
  }
}
