import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Full-width primary action button for AQUA CLAYNE authentication.
///
/// Features:
/// - Aqua Blue background with smooth pressed state
/// - Rounded corners (16px)
/// - Elevation & soft glow shadow
/// - Integrated loading spinner and message
/// - Disables automatically when loading to prevent duplicate requests
class AquaClaynePrimaryButton extends StatefulWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final String? loadingLabel;
  final IconData? icon;

  const AquaClaynePrimaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.loading = false,
    this.loadingLabel,
    this.icon,
  });

  @override
  State<AquaClaynePrimaryButton> createState() => _AquaClaynePrimaryButtonState();
}

class _AquaClaynePrimaryButtonState extends State<AquaClaynePrimaryButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final bool isInteractive = widget.onPressed != null && !widget.loading;

    return GestureDetector(
      onTapDown: isInteractive ? (_) => setState(() => _pressed = true) : null,
      onTapUp: isInteractive ? (_) => setState(() => _pressed = false) : null,
      onTapCancel: isInteractive ? () => setState(() => _pressed = false) : null,
      child: AnimatedScale(
        scale: _pressed ? 0.98 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          width: double.infinity,
          height: 54,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: isInteractive
                  ? [
                      AppColors.aquaBlue,
                      AppColors.deepBlue,
                    ]
                  : [
                      AppColors.aquaBlue.withValues(alpha: 0.60),
                      AppColors.deepBlue.withValues(alpha: 0.60),
                    ],
            ),
            boxShadow: isInteractive
                ? [
                    BoxShadow(
                      color: AppColors.aquaBlue.withValues(alpha: 0.35),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ]
                : [],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: isInteractive ? widget.onPressed : null,
              child: Center(
                child: widget.loading
                    ? Row(
                        mainAxisSize: MainAxisSize.min,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.4,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          ),
                          if (widget.loadingLabel != null) ...[
                            const SizedBox(width: 12),
                            Text(
                              widget.loadingLabel!,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                                letterSpacing: 0.3,
                              ),
                            ),
                          ],
                        ],
                      )
                    : Row(
                        mainAxisSize: MainAxisSize.min,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          if (widget.icon != null) ...[
                            Icon(widget.icon, color: Colors.white, size: 20),
                            const SizedBox(width: 8),
                          ],
                          Text(
                            widget.label,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
