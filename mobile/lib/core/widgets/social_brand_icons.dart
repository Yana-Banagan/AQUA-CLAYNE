import 'dart:math' as math;
import 'package:flutter/material.dart';

/// Official Google 4-color "G" Logo rendered with crisp vector geometry.
class GoogleLogo extends StatelessWidget {
  final double size;
  const GoogleLogo({super.key, this.size = 22.0});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        size: Size(size, size),
        painter: _GoogleLogoPainter(),
      ),
    );
  }
}

class _GoogleLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final double s = size.width;
    final double center = s / 2.0;
    final double radius = s * 0.38;
    final double strokeWidth = s * 0.18;

    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.butt;

    final rect = Rect.fromCircle(center: Offset(center, center), radius: radius);

    // Blue arc (bottom-right: ~0 to 45 deg)
    paint.color = const Color(0xFF4285F4);
    canvas.drawArc(rect, 0.0, math.pi / 4.0, false, paint);

    // Green arc (bottom: 45 to 135 deg)
    paint.color = const Color(0xFF34A853);
    canvas.drawArc(rect, math.pi / 4.0, math.pi / 2.0, false, paint);

    // Yellow arc (left: 135 to 225 deg)
    paint.color = const Color(0xFFFBBC05);
    canvas.drawArc(rect, 3.0 * math.pi / 4.0, math.pi / 2.0, false, paint);

    // Red arc (top: 225 to 335 deg)
    paint.color = const Color(0xFFEA4335);
    canvas.drawArc(rect, 5.0 * math.pi / 4.0, 11.0 * math.pi / 18.0, false, paint);

    // Blue horizontal bar from center to right edge
    final barPaint = Paint()
      ..style = PaintingStyle.fill
      ..color = const Color(0xFF4285F4);

    final barRect = Rect.fromLTWH(
      center - (strokeWidth * 0.1),
      center - (strokeWidth / 2.0),
      radius + (strokeWidth * 0.55),
      strokeWidth,
    );
    canvas.drawRect(barRect, barPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Official Facebook "f" Logo in the authentic Facebook blue circle.
class FacebookLogo extends StatelessWidget {
  final double size;
  const FacebookLogo({super.key, this.size = 22.0});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        size: Size(size, size),
        painter: _FacebookLogoPainter(),
      ),
    );
  }
}

class _FacebookLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final double s = size.width;
    final double center = s / 2.0;

    // Facebook blue background circle
    final bgPaint = Paint()
      ..color = const Color(0xFF1877F2)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(center, center), center, bgPaint);

    // White "f"
    final whitePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;

    // Proportional path for "f" inside circle
    final path = Path();
    final double scale = s / 24.0;

    path.moveTo(14.5 * scale, 24.0 * scale);
    path.lineTo(14.5 * scale, 14.8 * scale);
    path.lineTo(17.6 * scale, 14.8 * scale);
    path.lineTo(18.1 * scale, 11.2 * scale);
    path.lineTo(14.5 * scale, 11.2 * scale);
    path.lineTo(14.5 * scale, 8.9 * scale);
    path.cubicTo(
      14.5 * scale, 7.9 * scale,
      14.8 * scale, 7.2 * scale,
      16.2 * scale, 7.2 * scale,
    );
    path.lineTo(18.2 * scale, 7.2 * scale);
    path.lineTo(18.2 * scale, 4.0 * scale);
    path.cubicTo(
      17.8 * scale, 3.9 * scale,
      16.6 * scale, 3.8 * scale,
      15.2 * scale, 3.8 * scale,
    );
    path.cubicTo(
      12.2 * scale, 3.8 * scale,
      10.2 * scale, 5.6 * scale,
      10.2 * scale, 9.0 * scale,
    );
    path.lineTo(10.2 * scale, 11.2 * scale);
    path.lineTo(7.0 * scale, 11.2 * scale);
    path.lineTo(7.0 * scale, 14.8 * scale);
    path.lineTo(10.2 * scale, 14.8 * scale);
    path.lineTo(10.2 * scale, 23.5 * scale);
    path.cubicTo(
      10.8 * scale, 23.8 * scale,
      11.4 * scale, 24.0 * scale,
      12.0 * scale, 24.0 * scale,
    );
    path.cubicTo(
      12.8 * scale, 24.0 * scale,
      13.7 * scale, 23.8 * scale,
      14.5 * scale, 24.0 * scale,
    );
    path.close();

    canvas.drawPath(path, whitePaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
