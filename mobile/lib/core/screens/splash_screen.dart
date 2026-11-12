import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/providers/auth_provider.dart';
import '../../customer/screens/customer_shell.dart';
import '../../driver/screens/driver_shell.dart';

/// Clean, professional branded splash screen for Aqua Clayne.
///
/// Matches the native Android launch screen:
/// - Solid dark navy background (#041C49) filling the screen and system bars
/// - Authentic Aqua Clayne logo centered without white tiles or framing
/// - Refined brand typography ("AQUA CLAYNE / Purified Drinking Water")
/// - Concurrent authentication session restoration
/// - Smooth transition to the customer or driver shell with zero flash
class SplashScreen extends StatefulWidget {
  final VoidCallback? onAnimationComplete;

  const SplashScreen({super.key, this.onAnimationComplete});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _animCtrl;
  late final Animation<double> _fadeAnim;
  late final Animation<double> _scaleAnim;
  bool _navigated = false;

  static const Color _navyBackground = Color(0xFF041C49);
  static const Duration _minSplashDuration = Duration(milliseconds: 1800);

  @override
  void initState() {
    super.initState();

    _animCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );

    _fadeAnim = CurvedAnimation(
      parent: _animCtrl,
      curve: Curves.easeOut,
    );

    _scaleAnim = Tween<double>(begin: 0.94, end: 1.0).animate(
      CurvedAnimation(
        parent: _animCtrl,
        curve: Curves.easeOutCubic,
      ),
    );

    _animCtrl.forward();
    _bootstrap();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Pre-cache authentic logo for instant, zero-jank rendering
    precacheImage(
      const AssetImage('assets/images/aqua_clayne_logo.png'),
      context,
    );
  }

  Future<void> _bootstrap() async {
    final stopwatch = Stopwatch()..start();

    // 1. Concurrently restore auth session from storage/API
    try {
      final auth = Provider.of<AuthProvider?>(context, listen: false);
      await auth?.restoreSession();
    } catch (e) {
      debugPrint('[Splash] Session restore error: $e');
    }

    // 2. Ensure minimum display time so the branding is established smoothly
    final elapsed = stopwatch.elapsedMilliseconds;
    final remaining = _minSplashDuration.inMilliseconds - elapsed;
    if (remaining > 0) {
      await Future.delayed(Duration(milliseconds: remaining));
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
        transitionDuration: const Duration(milliseconds: 400),
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
    _animCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final screenSize = MediaQuery.of(context).size;
    // Responsive, well-proportioned logo dimension with ample surrounding space
    final logoSize = math.min(screenSize.width * 0.44, 180.0);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
        systemNavigationBarColor: _navyBackground,
        systemNavigationBarIconBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: _navyBackground,
        body: SizedBox.expand(
          child: AnimatedBuilder(
            animation: _animCtrl,
            builder: (context, _) {
              return FadeTransition(
                opacity: _fadeAnim,
                child: ScaleTransition(
                  scale: _scaleAnim,
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // ── Authentic Aqua Clayne Logo ──────────────────────────
                        // Use the transparent launch artwork without a circular
                        // mask so the logo is not given an unwanted border.
                        SizedBox(
                          width: logoSize,
                          height: logoSize,
                          child: Image.asset(
                            'assets/images/aqua_clayne_logo_splash.png',
                            width: logoSize,
                            height: logoSize,
                            fit: BoxFit.contain,
                            filterQuality: FilterQuality.high,
                          ),
                        ),

                        const SizedBox(height: 24),

                        // ── Brand Name Typography ─────────────────────────────
                        const Text(
                          'AQUA CLAYNE',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 3.5,
                          ),
                        ),

                        const SizedBox(height: 6),

                        // ── Tagline Typography ────────────────────────────────
                        const Text(
                          'Purified Drinking Water',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Color(0xFF7DD3FC),
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                            letterSpacing: 1.2,
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
