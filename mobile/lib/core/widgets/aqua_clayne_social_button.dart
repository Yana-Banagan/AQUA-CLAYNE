import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

enum SocialProviderType { google, facebook }

/// Social authentication button styled to match AQUA CLAYNE's premium card design.
///
/// Features:
/// - Consistent height (52px) and border radius (16px) matching AquaClaynePrimaryButton
/// - Official Google or Facebook branding & vector logos
/// - Tactile pressed state with scale animation
/// - Built-in loading spinner and disabled state to prevent duplicate clicks
/// - Accessible touch target and high contrast typography
class AquaClayneSocialButton extends StatefulWidget {
  final SocialProviderType provider;
  final Widget icon;
  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final String? loadingLabel;

  const AquaClayneSocialButton({
    super.key,
    required this.provider,
    required this.icon,
    required this.label,
    this.onPressed,
    this.loading = false,
    this.loadingLabel,
  });

  @override
  State<AquaClayneSocialButton> createState() => _AquaClayneSocialButtonState();
}

class _AquaClayneSocialButtonState extends State<AquaClayneSocialButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final bool isInteractive = widget.onPressed != null && !widget.loading;

    return Semantics(
      button: true,
      enabled: isInteractive,
      label: widget.label,
      child: GestureDetector(
        onTapDown: isInteractive ? (_) => setState(() => _pressed = true) : null,
        onTapUp: isInteractive ? (_) => setState(() => _pressed = false) : null,
        onTapCancel: isInteractive ? () => setState(() => _pressed = false) : null,
        child: AnimatedScale(
          scale: _pressed ? 0.98 : 1.0,
          duration: const Duration(milliseconds: 100),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            height: 52,
            decoration: BoxDecoration(
              color: isInteractive
                  ? (_pressed ? const Color(0xFFF1F5F9) : Colors.white)
                  : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isInteractive ? AppColors.border : const Color(0xFFE2E8F0),
                width: 1.2,
              ),
              boxShadow: isInteractive && !_pressed
                  ? const [
                      BoxShadow(
                        color: Color(0x0C000000),
                        blurRadius: 8,
                        offset: Offset(0, 2),
                      ),
                    ]
                  : [],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: isInteractive ? widget.onPressed : null,
                splashColor: AppColors.lightAqua,
                highlightColor: Colors.transparent,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (widget.loading) ...[
                        SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              widget.provider == SocialProviderType.facebook
                                  ? const Color(0xFF1877F2)
                                  : AppColors.aquaBlue,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Flexible(
                          child: Text(
                            widget.loadingLabel ?? 'Connecting...',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: AppColors.mutedText,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ] else ...[
                        widget.icon,
                        const SizedBox(width: 12),
                        Flexible(
                          child: Text(
                            widget.label,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: AppColors.darkText,
                              letterSpacing: 0.1,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
