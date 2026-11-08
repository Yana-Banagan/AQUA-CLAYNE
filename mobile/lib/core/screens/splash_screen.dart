import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../customer/screens/customer_shell.dart';
import '../../driver/screens/driver_shell.dart';

/// Premium, water-inspired mobile application splash screen for Aqua Clayne.
///
/// Animation sequence (Total 4.5s):
/// - 0.0–0.8s: Deep royal/navy blue background, center glow, floating particles
/// - 0.8–1.6s: Point of light appears, expands into underwater glow, ripple begins
/// - 0.8–2.0s: Concentric glowing cyan water ripples expand outward
/// - 1.6–2.5s: Authentic Aqua Clayne logo reveals from center outward with cyan glow
///             and specular water sheen sweep
/// - 2.5–3.2s: Cyan-blue aura, pulsing luminous ring around logo, subtle water reflection below
/// - 3.2–4.0s: Background deepens, flowing waves at bottom, elegant brand typography fades in
/// - 4.0–4.5s: Hold final splash screen before smooth transition to app
class SplashScreen extends StatefulWidget {
  final VoidCallback? onAnimationComplete;

  const SplashScreen({super.key, this.onAnimationComplete});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  final List<_WaterParticle> _particles = _WaterParticle.generate(32);
  bool _navigated = false;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 4500),
    );

    _ctrl.forward();
    _init();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Pre-cache authentic logo for instant, zero-jank reveal
    precacheImage(
      const AssetImage('assets/images/aqua_clayne_logo_splash.png'),
      context,
    );
  }

  Future<void> _init() async {
    final stopwatch = Stopwatch()..start();

    // Concurrently restore auth session
    try {
      final auth = Provider.of<AuthProvider?>(context, listen: false);
      await auth?.restoreSession();
    } catch (e) {
      debugPrint('[Splash] Session restore: $e');
    }

    // Wait until full 4.5s animation completes
    final elapsed = stopwatch.elapsedMilliseconds;
    if (elapsed < 4500) {
      await Future.delayed(Duration(milliseconds: 4500 - elapsed));
    }

    if (!mounted || _navigated) return;
    _navigate();
  }

  void _navigate() {
    if (_navigated) return;
    _navigated = true;

    if (widget.onAnimationComplete != null) {
      widget.onAnimationComplete!();
      return;
    }

    AuthProvider? auth;
    try {
      auth = Provider.of<AuthProvider?>(context, listen: false);
    } catch (_) {}

    Widget dest;
    if (auth != null && auth.isAuthenticated && auth.user != null) {
      dest = auth.user!.isDriver ? const DriverShell() : const CustomerShell();
    } else {
      dest = const CustomerShell();
    }

    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 600),
        pageBuilder: (_, animation, _) => FadeTransition(
          opacity: animation,
          child: dest,
        ),
      ),
      (_) => false,
    );
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final screenSize = media.size;

    // Responsive logo dimension (optimized for 9:16 smartphone canvas)
    final logoSize = math.min(screenSize.width * 0.62, 270.0);

    return Scaffold(
      backgroundColor: const Color(0xFF020917),
      body: AnimatedBuilder(
        animation: _ctrl,
        builder: (context, _) {
          final t = _ctrl.value; // 0.0 -> 1.0 (0.0s -> 4.5s)

          // Center coordinate for the logo and ripples (centered, slightly above mid-height)
          final centerY = screenSize.height * 0.44;
          final centerX = screenSize.width * 0.5;

          return Stack(
            fit: StackFit.expand,
            children: [
              // ── 1. Deep Water Atmospheric Background & Particles ──
              CustomPaint(
                painter: _DeepWaterBackgroundPainter(
                  progress: t,
                  particles: _particles,
                  centerX: centerX,
                  centerY: centerY,
                ),
                size: screenSize,
              ),

              // ── 2. Underwater Light Core & Water Ripples ──
              CustomPaint(
                painter: _WaterRipplesAndLightPainter(
                  progress: t,
                  centerX: centerX,
                  centerY: centerY,
                  logoSize: logoSize,
                ),
                size: screenSize,
              ),

              // ── 3. Flowing Water Waves at Bottom of Screen (Scene 6) ──
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                height: 150,
                child: CustomPaint(
                  painter: _FlowingWavesPainter(progress: t),
                ),
              ),

              // ── 4. Aqua Clayne Logo + Water Reflection Stack ──
              Positioned(
                left: centerX - (logoSize / 2),
                top: centerY - (logoSize / 2),
                width: logoSize,
                height: logoSize * 1.6, // extra vertical height for reflection
                child: _LogoAndReflectionStack(
                  progress: t,
                  logoSize: logoSize,
                ),
              ),

              // ── 5. Brand Typography "Aqua Clayne" (Scene 6) ──
              Positioned(
                left: 24,
                right: 24,
                top: centerY + (logoSize / 2) + 24,
                child: _BrandTypography(progress: t),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ============================================================================
// LOGO AND WATER REFLECTION COMPONENT
// ============================================================================

class _LogoAndReflectionStack extends StatelessWidget {
  final double progress;
  final double logoSize;

  const _LogoAndReflectionStack({
    required this.progress,
    required this.logoSize,
  });

  @override
  Widget build(BuildContext context) {
    // Scene 4: Reveal starts at t = 0.355 (1.6s) to t = 0.555 (2.5s)
    // Reveal factor expands from 0.0 to 1.0
    final revealFactor = ((progress - 0.355) / 0.18)
        .clamp(0.0, 1.0);
    final curvedReveal = Curves.easeInOutCubic.transform(revealFactor);

    // Scale slightly from 0.90 to 1.00 as it emerges
    final scaleFactor = 0.90 + (0.10 * curvedReveal);

    // Overall logo opacity
    final logoOpacity = ((progress - 0.355) / 0.12).clamp(0.0, 1.0);

    // Specular light reflection sheen sweep (t: 0.42 -> 0.60, 1.9s -> 2.7s)
    final sheenFactor = ((progress - 0.42) / 0.18).clamp(0.0, 1.0);

    // Scene 5 & 6: Water reflection underneath appears (t: 0.55 -> 0.72)
    final reflectionOpacity =
        (((progress - 0.55) / 0.16).clamp(0.0, 1.0)) * 0.22;

    if (logoOpacity <= 0.0) {
      return const SizedBox.shrink();
    }

    return Stack(
      clipBehavior: Clip.none,
      children: [
        // ── Subtle Mirrored Water Reflection Underneath (Scene 5 & 6) ──
        if (reflectionOpacity > 0.005)
          Positioned(
            left: 0,
            top: logoSize * 0.94,
            width: logoSize,
            height: logoSize * 0.45,
            child: Opacity(
              opacity: reflectionOpacity,
              child: Transform(
                alignment: Alignment.topCenter,
                transform: Matrix4.diagonal3Values(1.0, -0.42, 1.0), // flipped & squashed
                child: ShaderMask(
                  shaderCallback: (bounds) {
                    return LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: const [
                        Color(0xFFFFFFFF),
                        Color(0x00FFFFFF),
                      ],
                      stops: const [0.05, 0.75],
                    ).createShader(bounds);
                  },
                  blendMode: BlendMode.dstIn,
                  child: Image.asset(
                    'assets/images/aqua_clayne_logo_splash.png',
                    fit: BoxFit.contain,
                    filterQuality: FilterQuality.medium,
                  ),
                ),
              ),
            ),
          ),

        // ── Main Authentic Aqua Clayne Logo ──
        Positioned(
          left: 0,
          top: 0,
          width: logoSize,
          height: logoSize,
          child: Opacity(
            opacity: logoOpacity,
            child: Transform.scale(
              scale: scaleFactor,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  // Circular reveal mask from center outward
                  ClipPath(
                    clipper: _CircularRevealClipper(progress: curvedReveal),
                    child: Image.asset(
                      'assets/images/aqua_clayne_logo_splash.png',
                      fit: BoxFit.contain,
                      filterQuality: FilterQuality.high,
                    ),
                  ),

                  // Soft glowing cyan rim at the expanding reveal front
                  if (curvedReveal > 0.01 && curvedReveal < 0.99)
                    CustomPaint(
                      painter: _RevealRimPainter(
                        revealProgress: curvedReveal,
                        logoSize: logoSize,
                      ),
                    ),

                  // Water specular shimmer sweep across the logo (Scene 4)
                  if (sheenFactor > 0.0 && sheenFactor < 1.0)
                    CustomPaint(
                      painter: _SpecularSheenPainter(
                        sheenProgress: sheenFactor,
                        logoSize: logoSize,
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// ============================================================================
// CIRCULAR REVEAL CLIPPER
// ============================================================================

class _CircularRevealClipper extends CustomClipper<Path> {
  final double progress;

  _CircularRevealClipper({required this.progress});

  @override
  Path getClip(Size size) {
    if (progress >= 1.0) {
      return Path()..addRect(Rect.fromLTWH(0, 0, size.width, size.height));
    }
    final path = Path();
    final center = Offset(size.width / 2, size.height / 2);
    // Max radius reaches the outer circle boundary
    final maxRadius = (size.width / 2) * 1.08;
    final currentRadius = maxRadius * progress;

    path.addOval(Rect.fromCircle(center: center, radius: currentRadius));
    return path;
  }

  @override
  bool shouldReclip(_CircularRevealClipper oldClipper) =>
      oldClipper.progress != progress;
}

// ============================================================================
// SPECULAR WATER REFLECTION / SHEEN PAINTER
// ============================================================================

class _SpecularSheenPainter extends CustomPainter {
  final double sheenProgress;
  final double logoSize;

  _SpecularSheenPainter({
    required this.sheenProgress,
    required this.logoSize,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final clipPath = Path()
      ..addOval(Rect.fromCircle(
        center: Offset(size.width / 2, size.height / 2),
        radius: size.width * 0.42,
      ));
    canvas.save();
    canvas.clipPath(clipPath);

    // Diagonal sweep angle (-30 degrees)
    // Moves from top-left to bottom-right
    final sweepDistance = size.width * 2.2;
    final currentX = -size.width * 0.6 + (sweepDistance * sheenProgress);

    final rect = Rect.fromCenter(
      center: Offset(currentX, size.height / 2),
      width: size.width * 0.45,
      height: size.height * 1.5,
    );

    final paint = Paint()
      ..shader = const LinearGradient(
        colors: [
          Color(0x0000E5FF),
          Color(0x2500E5FF),
          Color(0x75FFFFFF),
          Color(0x2500E5FF),
          Color(0x0000E5FF),
        ],
        stops: [0.0, 0.35, 0.5, 0.65, 1.0],
      ).createShader(rect)
      ..blendMode = BlendMode.screen;

    canvas.save();
    canvas.translate(rect.center.dx, rect.center.dy);
    canvas.rotate(-math.pi / 5.5);
    canvas.translate(-rect.center.dx, -rect.center.dy);
    canvas.drawRect(rect, paint);
    canvas.restore();

    canvas.restore();
  }

  @override
  bool shouldRepaint(_SpecularSheenPainter oldDelegate) =>
      oldDelegate.sheenProgress != sheenProgress;
}

// ============================================================================
// REVEAL RIM GLOW PAINTER
// ============================================================================

class _RevealRimPainter extends CustomPainter {
  final double revealProgress;
  final double logoSize;

  _RevealRimPainter({
    required this.revealProgress,
    required this.logoSize,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final maxRadius = (size.width / 2) * 1.08;
    final r = maxRadius * revealProgress;

    // Glowing cyan outline on the reveal edge
    final alpha = ((1.0 - (revealProgress * 0.6)) * 0.85).clamp(0.0, 1.0);
    final glowPaint = Paint()
      ..color = const Color(0xFF00E5FF).withValues(alpha: alpha)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.5
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4.0);

    canvas.drawCircle(center, r, glowPaint);

    final corePaint = Paint()
      ..color = const Color(0xFFE0FFFF).withValues(alpha: alpha)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;

    canvas.drawCircle(center, r, corePaint);
  }

  @override
  bool shouldRepaint(_RevealRimPainter oldDelegate) =>
      oldDelegate.revealProgress != revealProgress;
}

// ============================================================================
// DEEP WATER ATMOSPHERIC BACKGROUND & FLOATING PARTICLES PAINTER
// ============================================================================

class _DeepWaterBackgroundPainter extends CustomPainter {
  final double progress;
  final List<_WaterParticle> particles;
  final double centerX;
  final double centerY;

  _DeepWaterBackgroundPainter({
    required this.progress,
    required this.particles,
    required this.centerX,
    required this.centerY,
  });

  @override
  void paint(Canvas canvas, Size size) {
    // ── 1. Base Deep Royal/Navy Blue Canvas Gradient ──
    // Subtle transition to slightly richer dark tones in Scene 6
    final tS6 = ((progress - 0.71) / 0.20).clamp(0.0, 1.0);

    final topColor = Color.lerp(
      const Color(0xFF020B1D),
      const Color(0xFF010815),
      tS6,
    )!;
    final midColor = Color.lerp(
      const Color(0xFF061E4A),
      const Color(0xFF031433),
      tS6,
    )!;
    final bottomColor = Color.lerp(
      const Color(0xFF020A1A),
      const Color(0xFF010610),
      tS6,
    )!;

    final bgRect = Rect.fromLTWH(0, 0, size.width, size.height);
    final bgPaint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [topColor, midColor, bottomColor],
        stops: const [0.0, 0.52, 1.0],
      ).createShader(bgRect);

    canvas.drawRect(bgRect, bgPaint);

    // ── 2. Subtle Radial Glow in Center (Clean Deep Water Feel) ──
    // Scene 1: subtle (0.20). Scene 5: gentle aura breathing (0.35 + pulse)
    double glowIntensity = 0.22;
    if (progress >= 0.55) {
      // Scene 5 & 6: breathing pulse
      final pulse = math.sin((progress - 0.55) * math.pi * 4.0);
      glowIntensity = 0.28 + (0.06 * pulse);
    } else if (progress >= 0.177) {
      // Rising during Scene 2
      final rise = ((progress - 0.177) / 0.18).clamp(0.0, 1.0);
      glowIntensity = 0.22 + (0.12 * rise);
    }

    final radialGlowPaint = Paint()
      ..shader = RadialGradient(
        center: Alignment(
          (centerX / size.width) * 2.0 - 1.0,
          (centerY / size.height) * 2.0 - 1.0,
        ),
        radius: 0.55,
        colors: [
          const Color(0xFF0A3B82).withValues(alpha: glowIntensity),
          const Color(0xFF021B42).withValues(alpha: glowIntensity * 0.4),
          Colors.transparent,
        ],
        stops: const [0.0, 0.45, 1.0],
      ).createShader(bgRect);

    canvas.drawRect(bgRect, radialGlowPaint);

    // ── 3. Floating Ambient Particles & Tiny Water Droplets ──
    for (final p in particles) {
      // Particle vertical floating motion with wrapping
      final yNorm = (p.initialY - (progress * p.speed)) % 1.0;
      final px = (p.initialX * size.width) +
          (math.sin((progress * 6.0) + p.driftPhase) * p.driftAmplitude);
      final py = yNorm * size.height;

      // Soft water particle glow
      final pColor = p.color.withValues(
        alpha: (p.baseOpacity * (0.65 + (0.35 * math.sin((progress * 4.0) + p.driftPhase))))
            .clamp(0.0, 1.0),
      );

      final pPaint = Paint()
        ..color = pColor
        ..maskFilter = MaskFilter.blur(BlurStyle.normal, p.radius * 0.6);

      canvas.drawCircle(Offset(px, py), p.radius, pPaint);

      // Bright pinpoint highlight in particle center
      final corePaint = Paint()
        ..color = Colors.white.withValues(alpha: pColor.a * 0.8);
      canvas.drawCircle(Offset(px, py), p.radius * 0.4, corePaint);
    }
  }

  @override
  bool shouldRepaint(_DeepWaterBackgroundPainter oldDelegate) => true;
}

// ============================================================================
// WATER RIPPLES & CENTER LIGHT PAINTER
// ============================================================================

class _WaterRipplesAndLightPainter extends CustomPainter {
  final double progress;
  final double centerX;
  final double centerY;
  final double logoSize;

  _WaterRipplesAndLightPainter({
    required this.progress,
    required this.centerX,
    required this.centerY,
    required this.logoSize,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(centerX, centerY);

    // ── SCENE 2: Point of Light Appears in Center (0.8s – 1.6s -> t: 0.177 – 0.355) ──
    if (progress >= 0.177 && progress <= 0.48) {
      final lightT = ((progress - 0.177) / 0.178).clamp(0.0, 1.0);
      // Light grows and glows, then softens as ripple spreads
      final fadeOut = progress > 0.355
          ? (1.0 - ((progress - 0.355) / 0.12)).clamp(0.0, 1.0)
          : 1.0;

      final lightRadius = (2.0 + (lightT * 14.0));
      final bloomRadius = (16.0 + (lightT * 65.0));
      final lightAlpha = (lightT * fadeOut).clamp(0.0, 1.0);

      // Cyan-blue underwater bloom glow
      final bloomPaint = Paint()
        ..shader = RadialGradient(
          colors: [
            const Color(0xFF00E5FF).withValues(alpha: 0.55 * lightAlpha),
            const Color(0xFF0072FF).withValues(alpha: 0.25 * lightAlpha),
            Colors.transparent,
          ],
          stops: const [0.0, 0.45, 1.0],
        ).createShader(Rect.fromCircle(center: center, radius: bloomRadius));
      canvas.drawCircle(center, bloomRadius, bloomPaint);

      // Intense concentrated cyan/white light core
      final corePaint = Paint()
        ..color = const Color(0xFFE0FFFF).withValues(alpha: lightAlpha)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2.5);
      canvas.drawCircle(center, lightRadius, corePaint);

      final hotSpotPaint = Paint()
        ..color = Colors.white.withValues(alpha: lightAlpha);
      canvas.drawCircle(center, lightRadius * 0.45, hotSpotPaint);
    }

    // ── SCENE 3: Smooth Elegant Concentric Water Ripples (0.8s – 2.2s) ──
    // 3 harmonic ripple fronts radiating outward
    final rippleStarts = [0.20, 0.27, 0.34];
    final maxRippleRadius = logoSize * 0.78;

    for (int i = 0; i < rippleStarts.length; i++) {
      final start = rippleStarts[i];
      if (progress >= start && progress <= start + 0.25) {
        final rProgress = ((progress - start) / 0.25).clamp(0.0, 1.0);
        final curvedR = Curves.easeOutCubic.transform(rProgress);

        final radius = 8.0 + (curvedR * maxRippleRadius);
        // Fade out smoothly as ripple expands
        final alpha = (math.sin(rProgress * math.pi) * (0.65 - (i * 0.12)))
            .clamp(0.0, 1.0);

        if (alpha > 0.01) {
          // Glow stroke
          final ripplePaint = Paint()
            ..color = const Color(0xFF00E5FF).withValues(alpha: alpha * 0.7)
            ..style = PaintingStyle.stroke
            ..strokeWidth = (2.6 - (curvedR * 1.4)).clamp(0.8, 2.6)
            ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2.5);

          canvas.drawCircle(center, radius, ripplePaint);

          // Crisp inner highlight ring
          final crispPaint = Paint()
            ..color = const Color(0xFFB2EBF2).withValues(alpha: alpha * 0.8)
            ..style = PaintingStyle.stroke
            ..strokeWidth = (1.2 - (curvedR * 0.6)).clamp(0.4, 1.2);

          canvas.drawCircle(center, radius, crispPaint);
        }
      }
    }

    // ── SCENE 5 & 6: Thin Luminous Circular Ring Gently Pulsing around Logo (2.5s – 4.5s) ──
    if (progress >= 0.555) {
      final ringIntro = ((progress - 0.555) / 0.12).clamp(0.0, 1.0);
      // Continuous gentle breathing pulse
      final pulse = math.sin((progress - 0.555) * math.pi * 3.5);

      // Exact radius of the authentic logo's circular border
      // In aqua_clayne_logo_splash.png, the outer ring is at ~0.334 of full image size
      final baseRingRadius = logoSize * 0.334;
      final pulsingRadius = baseRingRadius * (1.0 + (0.018 * pulse));
      final ringAlpha = (ringIntro * (0.50 + (0.15 * pulse))).clamp(0.0, 1.0);

      // Soft cyan outer bloom around the circular design
      final auraPaint = Paint()
        ..color = const Color(0xFF00E5FF).withValues(alpha: ringAlpha * 0.45)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.2
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 3.5);
      canvas.drawCircle(center, pulsingRadius, auraPaint);

      // Delicate luminous cyan ring
      final ringPaint = Paint()
        ..color = const Color(0xFF80DEEA).withValues(alpha: ringAlpha * 0.75)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.2;
      canvas.drawCircle(center, pulsingRadius, ringPaint);
    }
  }

  @override
  bool shouldRepaint(_WaterRipplesAndLightPainter oldDelegate) => true;
}

// ============================================================================
// FLOWING WATER WAVES AT SCREEN BOTTOM (SCENE 6)
// ============================================================================

class _FlowingWavesPainter extends CustomPainter {
  final double progress;

  _FlowingWavesPainter({required this.progress});

  @override
  void paint(Canvas canvas, Size size) {
    // Waves smoothly fade in starting at t = 0.60 (2.7s)
    final waveIntro = ((progress - 0.60) / 0.18).clamp(0.0, 1.0);
    if (waveIntro <= 0.005) return;

    final w = size.width;
    final h = size.height;

    // Phase driven by progress
    final phase1 = progress * 7.5;
    final phase2 = (progress * 6.0) + 1.2;

    // ── 1. Back Wave (Deep Indigo-Navy with Cyan Tint) ──
    final backPath = Path()..moveTo(0, h);
    for (double x = 0; x <= w; x += 3.0) {
      final y = (h * 0.52) +
          (math.sin((x / (w * 0.45)) + phase1) * 8.0) +
          (math.cos((x / (w * 0.32)) + phase1 * 0.7) * 4.0);
      backPath.lineTo(x, y);
    }
    backPath.lineTo(w, h);
    backPath.close();

    final backPaint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          const Color(0xFF0052A3).withValues(alpha: 0.16 * waveIntro),
          const Color(0xFF020B1D).withValues(alpha: 0.40 * waveIntro),
        ],
      ).createShader(Rect.fromLTWH(0, 0, w, h));

    canvas.drawPath(backPath, backPaint);

    // ── 2. Front Wave (Rich Translucent Cyan & Deep Blue) ──
    final frontPath = Path()..moveTo(0, h);
    for (double x = 0; x <= w; x += 3.0) {
      final y = (h * 0.66) +
          (math.sin((x / (w * 0.55)) + phase2) * 6.5) +
          (math.cos((x / (w * 0.28)) + phase2 * 0.8) * 3.5);
      frontPath.lineTo(x, y);
    }
    frontPath.lineTo(w, h);
    frontPath.close();

    final frontPaint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          const Color(0xFF00E5FF).withValues(alpha: 0.18 * waveIntro),
          const Color(0xFF002244).withValues(alpha: 0.55 * waveIntro),
        ],
      ).createShader(Rect.fromLTWH(0, 0, w, h));

    canvas.drawPath(frontPath, frontPaint);

    // ── 3. Subtle Glistening Crest Line on Front Wave ──
    final crestPaint = Paint()
      ..color = const Color(0xFF00E5FF).withValues(alpha: 0.30 * waveIntro)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 1.2);

    canvas.drawPath(frontPath, crestPaint);
  }

  @override
  bool shouldRepaint(_FlowingWavesPainter oldDelegate) => true;
}

// ============================================================================
// BRAND TYPOGRAPHY COMPONENT (SCENE 6)
// ============================================================================

class _BrandTypography extends StatelessWidget {
  final double progress;

  const _BrandTypography({required this.progress});

  @override
  Widget build(BuildContext context) {
    // Scene 6: Text fades in smoothly from t = 0.711 (3.2s) to t = 0.866 (3.9s)
    final textFactor = ((progress - 0.711) / 0.14).clamp(0.0, 1.0);
    final curvedText = Curves.easeOutCubic.transform(textFactor);

    if (textFactor <= 0.005) {
      return const SizedBox.shrink();
    }

    // Subtle upward settling slide (+10px -> 0px)
    final translateY = 10.0 * (1.0 - curvedText);

    return Opacity(
      opacity: curvedText,
      child: Transform.translate(
        offset: Offset(0, translateY),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Elegant modern sans-serif brand title
            const Text(
              'AQUA CLAYNE',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontFamily: 'Roboto',
                color: Color(0xFFF4FAFF),
                fontSize: 22,
                fontWeight: FontWeight.w400,
                letterSpacing: 5.5,
                shadows: [
                  Shadow(
                    color: Color(0x6600E5FF),
                    blurRadius: 18,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 6),
            // Light cyan modern tagline
            Text(
              'PURE WATER • HEALTHIER LIFE',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontFamily: 'Roboto',
                color: const Color(0xFF80DEEA).withValues(alpha: 0.85),
                fontSize: 9.5,
                fontWeight: FontWeight.w300,
                letterSpacing: 3.5,
              ),
            ),
            const SizedBox(height: 20),
            // ── Percentage Loading Indicator (Final Splash) ──
            _PercentageLoadingIndicator(progress: progress),
          ],
        ),
      ),
    );
  }
}

// ============================================================================
// PERCENTAGE LOADING INDICATOR (SCENE 6)
// ============================================================================

class _PercentageLoadingIndicator extends StatelessWidget {
  final double progress;

  const _PercentageLoadingIndicator({required this.progress});

  @override
  Widget build(BuildContext context) {
    // Progress starts when Scene 6 starts (t = 0.711, 3.2s) and completes to 100% by t = 0.92 (4.14s)
    final loadFactor = ((progress - 0.711) / 0.21).clamp(0.0, 1.0);
    final curvedFactor = Curves.easeOutCubic.transform(loadFactor);
    final percent = (curvedFactor * 100).toInt();

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // Percentage readout with cyan luminous shadow
        Text(
          '$percent%',
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontFamily: 'Roboto',
            color: Color(0xFFE0F7FA),
            fontSize: 12,
            fontWeight: FontWeight.w500,
            letterSpacing: 2.0,
            shadows: [
              Shadow(
                color: Color(0x8000E5FF),
                blurRadius: 10,
                offset: Offset(0, 1),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        // Slim, elegant water-inspired progress capsule
        Container(
          width: 140,
          height: 3.5,
          decoration: BoxDecoration(
            color: const Color(0x1A00E5FF),
            borderRadius: BorderRadius.circular(2),
            border: Border.all(
              color: const Color(0x3300E5FF),
              width: 0.8,
            ),
          ),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Container(
              width: 140 * curvedFactor,
              height: 3.5,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(2),
                gradient: const LinearGradient(
                  colors: [
                    Color(0xFF0072FF),
                    Color(0xFF00E5FF),
                    Color(0xFFE0FFFF),
                  ],
                ),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x6600E5FF),
                    blurRadius: 6,
                    spreadRadius: 0.5,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// ============================================================================
// WATER PARTICLE MODEL (DETERMINISTIC DISTRIBUTION)
// ============================================================================

class _WaterParticle {
  final double initialX;
  final double initialY;
  final double radius;
  final double baseOpacity;
  final double speed;
  final double driftPhase;
  final double driftAmplitude;
  final Color color;

  const _WaterParticle({
    required this.initialX,
    required this.initialY,
    required this.radius,
    required this.baseOpacity,
    required this.speed,
    required this.driftPhase,
    required this.driftAmplitude,
    required this.color,
  });

  static List<_WaterParticle> generate(int count) {
    final list = <_WaterParticle>[];
    // Deterministic pseudo-random sequence for reproducible beauty
    final random = math.Random(1337);

    const colors = [
      Color(0xFFE0F7FA), // Soft white-cyan
      Color(0xFF80DEEA), // Soft aqua
      Color(0xFF00E5FF), // Luminous cyan
      Color(0xFF40C4FF), // Electric blue
      Color(0xFFFFFFFF), // Pure white pinpoint
    ];

    for (int i = 0; i < count; i++) {
      list.add(
        _WaterParticle(
          initialX: 0.06 + (random.nextDouble() * 0.88),
          initialY: 0.05 + (random.nextDouble() * 0.90),
          radius: 1.0 + (random.nextDouble() * 2.2),
          baseOpacity: 0.18 + (random.nextDouble() * 0.40),
          speed: 0.06 + (random.nextDouble() * 0.10),
          driftPhase: random.nextDouble() * math.pi * 2.0,
          driftAmplitude: 6.0 + (random.nextDouble() * 12.0),
          color: colors[i % colors.length],
        ),
      );
    }
    return list;
  }
}
