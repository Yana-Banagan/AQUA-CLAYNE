import 'package:flutter/material.dart';
import '../core/theme/driver_colors.dart';

/// Reusable Scaffold for Driver Mobile screens.
/// Features the light green/mint water-themed background:
/// `assets/images/driver_dashboard_bg.png`.
class DriverScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget body;
  final Widget? bottomNavigationBar;
  final Widget? floatingActionButton;
  final bool resizeToAvoidBottomInset;
  final bool useSafeArea;
  final Color? backgroundColor;

  const DriverScaffold({
    super.key,
    this.appBar,
    required this.body,
    this.bottomNavigationBar,
    this.floatingActionButton,
    this.resizeToAvoidBottomInset = true,
    this.useSafeArea = true,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    Widget content = body;
    if (useSafeArea) {
      content = SafeArea(child: content);
    }

    return Theme(
      data: DriverColors.theme(Theme.of(context)),
      child: Scaffold(
      backgroundColor: backgroundColor ?? DriverColors.background,
      appBar: appBar,
      resizeToAvoidBottomInset: resizeToAvoidBottomInset,
      bottomNavigationBar: bottomNavigationBar,
      floatingActionButton: floatingActionButton,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Reusable green water-themed background image ─────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/driver_dashboard_background.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
              errorBuilder: (context, error, stackTrace) {
                return Image.asset(
                  'assets/images/driver_dashboard_bg.png',
                  fit: BoxFit.cover,
                  alignment: Alignment.center,
                  errorBuilder: (context, error, stackTrace) {
                    return Container(
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Color(0xFFEAF8E8),
                            Color(0xFFFFFFFF),
                            Color(0xFFD4EED8),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),

          // ── Screen Content ──────────────────────────────────────────
          content,
        ],
      ),
      ),
    );
  }
}
