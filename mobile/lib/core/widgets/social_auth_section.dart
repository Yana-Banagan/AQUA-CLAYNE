import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'aqua_clayne_social_button.dart';
import 'social_brand_icons.dart';

/// Section containing the "OR CONTINUE WITH" divider and social login buttons
/// for Google and Facebook with responsive layout (stacked on mobile, side-by-side on tablet/desktop).
class SocialAuthSection extends StatelessWidget {
  final VoidCallback? onGooglePressed;
  final VoidCallback? onFacebookPressed;
  final String? activeLoadingProvider; // 'google', 'facebook', or null
  final bool disabled;
  final String dividerText;

  const SocialAuthSection({
    super.key,
    this.onGooglePressed,
    this.onFacebookPressed,
    this.activeLoadingProvider,
    this.disabled = false,
    this.dividerText = 'OR CONTINUE WITH',
  });

  @override
  Widget build(BuildContext context) {
    final bool isGoogleLoading = activeLoadingProvider == 'google';
    final bool isFacebookLoading = activeLoadingProvider == 'facebook';
    final bool isAnySocialLoading = activeLoadingProvider != null;

    final googleOnPressed = (disabled || isAnySocialLoading) ? null : onGooglePressed;
    final facebookOnPressed = (disabled || isAnySocialLoading) ? null : onFacebookPressed;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // ── Divider ──────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              const Expanded(
                child: Divider(
                  color: AppColors.border,
                  thickness: 1.0,
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Text(
                  dividerText,
                  style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.8,
                    color: AppColors.mutedText,
                  ),
                ),
              ),
              const Expanded(
                child: Divider(
                  color: AppColors.border,
                  thickness: 1.0,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // ── Social Buttons (Responsive Layout) ───────────────────────
        LayoutBuilder(
          builder: (context, constraints) {
            final bool wide = constraints.maxWidth >= 500;

            final googleButton = AquaClayneSocialButton(
              provider: SocialProviderType.google,
              icon: const GoogleLogo(size: 22),
              label: 'Continue with Google',
              loading: isGoogleLoading,
              loadingLabel: 'Connecting to Google...',
              onPressed: googleOnPressed,
            );

            final facebookButton = AquaClayneSocialButton(
              provider: SocialProviderType.facebook,
              icon: const FacebookLogo(size: 22),
              label: 'Continue with Facebook',
              loading: isFacebookLoading,
              loadingLabel: 'Connecting to Facebook...',
              onPressed: facebookOnPressed,
            );

            if (wide) {
              return Row(
                children: [
                  Expanded(child: googleButton),
                  const SizedBox(width: 12),
                  Expanded(child: facebookButton),
                ],
              );
            }

            return Column(
              children: [
                googleButton,
                const SizedBox(height: 12),
                facebookButton,
              ],
            );
          },
        ),
      ],
    );
  }
}
