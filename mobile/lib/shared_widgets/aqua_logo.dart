import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_text_styles.dart';

class AquaLogo extends StatelessWidget {
  final double size;
  final bool showTagline;
  final bool light;

  const AquaLogo({
    super.key,
    this.size = 60,
    this.showTagline = false,
    this.light = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = light ? Colors.white : AppColors.primary;
    final tagColor = light ? Colors.white70 : AppColors.textSecondary;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.water_drop_rounded, color: color, size: size * 0.55),
        ),
        const SizedBox(height: 10),
        Text(
          'AQUA CLAYNE',
          style: AppTextStyles.displayMedium.copyWith(
            color: color,
            letterSpacing: 1.5,
            fontSize: size * 0.38,
          ),
        ),
        if (showTagline) ...[
          const SizedBox(height: 4),
          Text(
            'Pure Water. Healthier Life.',
            style: TextStyle(
              color: tagColor,
              fontSize: size * 0.18,
              fontStyle: FontStyle.italic,
            ),
          ),
        ],
      ],
    );
  }
}
