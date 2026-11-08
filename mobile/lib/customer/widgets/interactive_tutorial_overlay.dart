import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

/// Centralized GlobalKeys identifying the 14 key tutorial targets on the customer interface.
class TutorialTargetKeys {
  TutorialTargetKeys._();

  static final GlobalKey orderNowButton =
      GlobalKey(debugLabel: 'tutorial_order_now');
  static final GlobalKey shoppingCart =
      GlobalKey(debugLabel: 'tutorial_shopping_cart');
  static final GlobalKey notificationBell =
      GlobalKey(debugLabel: 'tutorial_notification_bell');
  static final GlobalKey headerProfile =
      GlobalKey(debugLabel: 'tutorial_header_profile');
  static final GlobalKey tutorialCard =
      GlobalKey(debugLabel: 'tutorial_guide_card');
  static final GlobalKey orderWaterShortcut =
      GlobalKey(debugLabel: 'tutorial_order_water');
  static final GlobalKey myOrdersShortcut =
      GlobalKey(debugLabel: 'tutorial_my_orders');
  static final GlobalKey paymentShortcut =
      GlobalKey(debugLabel: 'tutorial_payment');
  static final GlobalKey myProfileShortcut =
      GlobalKey(debugLabel: 'tutorial_my_profile');
  static final GlobalKey homeNavTab =
      GlobalKey(debugLabel: 'tutorial_home_tab');
  static final GlobalKey ordersNavTab =
      GlobalKey(debugLabel: 'tutorial_orders_tab');
  static final GlobalKey paymentsNavTab =
      GlobalKey(debugLabel: 'tutorial_payments_tab');
  static final GlobalKey chatNavTab =
      GlobalKey(debugLabel: 'tutorial_chat_tab');
  static final GlobalKey profileNavTab =
      GlobalKey(debugLabel: 'tutorial_profile_tab');
}

/// Metadata model for each guided tutorial step.
class _TutorialStep {
  final String title;
  final String description;
  final GlobalKey targetKey;
  final double borderRadius;
  final double padding;

  const _TutorialStep({
    required this.title,
    required this.description,
    required this.targetKey,
    this.borderRadius = 16.0,
    this.padding = 6.0,
  });
}

/// In-place spotlight guided tour overlay for the Aqua Clayne Customer Dashboard.
class InteractiveTutorialOverlay extends StatefulWidget {
  const InteractiveTutorialOverlay({super.key});

  /// Opens the interactive spotlight guided walkthrough.
  static Future<void> show(BuildContext context) {
    return showGeneralDialog(
      context: context,
      barrierDismissible: false,
      barrierColor: Colors.transparent,
      transitionDuration: const Duration(milliseconds: 220),
      pageBuilder: (dialogContext, _, _) => const InteractiveTutorialOverlay(),
    );
  }

  @override
  State<InteractiveTutorialOverlay> createState() =>
      _InteractiveTutorialOverlayState();
}

class _InteractiveTutorialOverlayState extends State<InteractiveTutorialOverlay>
    with SingleTickerProviderStateMixin {
  int _currentStep = 0;
  Rect? _targetRect;
  late final AnimationController _pulseCtrl;

  static final List<_TutorialStep> _steps = [
    _TutorialStep(
      title: 'Order Now button',
      description:
          'Start your water order here. Browse available water services and select the refill option you need.',
      targetKey: TutorialTargetKeys.orderNowButton,
      borderRadius: 20.0,
      padding: 6.0,
    ),
    _TutorialStep(
      title: 'Shopping Cart icon',
      description:
          'View the items you have added to your cart. You can review your selected products before checkout.',
      targetKey: TutorialTargetKeys.shoppingCart,
      borderRadius: 22.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Notification Bell icon',
      description:
          'Check your notifications for order updates, delivery information, payment reminders, and other important announcements.',
      targetKey: TutorialTargetKeys.notificationBell,
      borderRadius: 22.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Profile icon (header)',
      description:
          'Access your account information and manage your personal details and settings.',
      targetKey: TutorialTargetKeys.headerProfile,
      borderRadius: 22.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Tutorial Guide card',
      description:
          'You can open this guide whenever you need help navigating the Aqua Clayne application.',
      targetKey: TutorialTargetKeys.tutorialCard,
      borderRadius: 22.0,
      padding: 6.0,
    ),
    _TutorialStep(
      title: 'Order Water shortcut',
      description:
          'Use this shortcut to browse water services and place a new refill order.',
      targetKey: TutorialTargetKeys.orderWaterShortcut,
      borderRadius: 18.0,
      padding: 5.0,
    ),
    _TutorialStep(
      title: 'My Orders shortcut',
      description:
          'View your previous and current orders and check their latest statuses.',
      targetKey: TutorialTargetKeys.myOrdersShortcut,
      borderRadius: 18.0,
      padding: 5.0,
    ),
    _TutorialStep(
      title: 'Payment shortcut',
      description:
          'Review your payment records and check your payment status.',
      targetKey: TutorialTargetKeys.paymentShortcut,
      borderRadius: 18.0,
      padding: 5.0,
    ),
    _TutorialStep(
      title: 'My Profile shortcut',
      description:
          'View and update your account information.',
      targetKey: TutorialTargetKeys.myProfileShortcut,
      borderRadius: 18.0,
      padding: 5.0,
    ),
    _TutorialStep(
      title: 'Home navigation tab',
      description:
          'Return to your customer dashboard anytime by selecting Home.',
      targetKey: TutorialTargetKeys.homeNavTab,
      borderRadius: 14.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Orders navigation tab',
      description:
          'Open your order history and monitor your ongoing orders.',
      targetKey: TutorialTargetKeys.ordersNavTab,
      borderRadius: 14.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Payments navigation tab',
      description:
          'Access your payment history and available payment information.',
      targetKey: TutorialTargetKeys.paymentsNavTab,
      borderRadius: 14.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Chat navigation tab',
      description:
          'Use this section to access the available customer communication or support features.',
      targetKey: TutorialTargetKeys.chatNavTab,
      borderRadius: 14.0,
      padding: 4.0,
    ),
    _TutorialStep(
      title: 'Profile navigation tab',
      description:
          'Manage your account details and profile settings.',
      targetKey: TutorialTargetKeys.profileNavTab,
      borderRadius: 14.0,
      padding: 4.0,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat(reverse: true);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _focusOnStep(0);
    });
  }

  @override
  void dispose() {
    _pulseCtrl.dispose();
    super.dispose();
  }

  Rect? _computeRect(GlobalKey key) {
    final ctx = key.currentContext;
    if (ctx == null) return null;
    final renderBox = ctx.findRenderObject() as RenderBox?;
    if (renderBox == null || !renderBox.hasSize) return null;
    final origin = renderBox.localToGlobal(Offset.zero);
    return origin & renderBox.size;
  }

  Future<void> _focusOnStep(int index) async {
    if (index < 0 || index >= _steps.length) return;
    setState(() => _currentStep = index);

    final key = _steps[index].targetKey;
    final ctx = key.currentContext;

    if (ctx != null) {
      try {
        await Scrollable.ensureVisible(
          ctx,
          alignment: 0.42,
          duration: const Duration(milliseconds: 260),
          curve: Curves.easeInOutCubic,
        );
      } catch (_) {}
    }

    if (!mounted) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      setState(() {
        _targetRect = _computeRect(key);
      });
    });
  }

  void _onNext() {
    if (_currentStep < _steps.length - 1) {
      _focusOnStep(_currentStep + 1);
    } else {
      // Step 14 FINISH: close tutorial
      Navigator.of(context).pop();
    }
  }

  void _onPrevious() {
    if (_currentStep > 0) {
      _focusOnStep(_currentStep - 1);
    }
  }

  void _onSkip() {
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final screenSize = media.size;
    final step = _steps[_currentStep];
    final isLast = _currentStep == _steps.length - 1;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        if (_currentStep > 0) {
          _onPrevious();
        } else {
          _onSkip();
        }
      },
      child: Material(
        color: Colors.transparent,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // ── 1. Animated Spotlight Cutout & Dimmer Overlay ────────
            AnimatedBuilder(
              animation: _pulseCtrl,
              builder: (context, _) {
                return CustomPaint(
                  size: screenSize,
                  painter: _SpotlightPainter(
                    targetRect: _targetRect,
                    borderRadius: step.borderRadius,
                    padding: step.padding,
                    pulseValue: _pulseCtrl.value,
                  ),
                );
              },
            ),

            // ── 2. Tap Absorber to prevent clicks to underlying items ─
            Positioned.fill(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () {}, // Blocks touches to background
              ),
            ),

            // ── 3. Positioned Tooltip / Modal Card ────────────────────
            _buildPositionedTooltip(media, step, isLast),
          ],
        ),
      ),
    );
  }

  Widget _buildPositionedTooltip(
    MediaQueryData media,
    _TutorialStep step,
    bool isLast,
  ) {
    final screenSize = media.size;
    final padding = media.padding;
    final rect = _targetRect;

    // Determine vertical placement: below or above the target
    double? top;
    double? bottom;

    const horizontalMargin = 16.0;
    final cardWidth = screenSize.width - (horizontalMargin * 2);

    if (rect == null) {
      // Fallback: center in screen
      top = screenSize.height * 0.35;
    } else {
      final targetCenterY = rect.center.dy;
      final isNearBottom = targetCenterY > (screenSize.height * 0.52);

      if (isNearBottom) {
        // Place above the element
        bottom = (screenSize.height - rect.top) + 14.0;
        // Don't let it overflow status bar
        if (bottom > screenSize.height - padding.top - 240) {
          bottom = screenSize.height - padding.top - 240;
        }
      } else {
        // Place below the element
        top = rect.bottom + 14.0;
        // Don't let it overflow bottom nav area
        if (top > screenSize.height - padding.bottom - 220) {
          top = screenSize.height - padding.bottom - 220;
        }
      }
    }

    return Positioned(
      left: horizontalMargin,
      width: cardWidth,
      top: top,
      bottom: bottom,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 200),
        child: Container(
          key: ValueKey<int>(_currentStep),
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: const Color(0xFFCCE4F7),
              width: 1.4,
            ),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF04192F).withValues(alpha: 0.32),
                blurRadius: 26,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top row: Step pill & SKIP button
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.lightAqua,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFBCE3FD)),
                    ),
                    child: Text(
                      'STEP ${_currentStep + 1} OF ${_steps.length}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w900,
                        color: AppColors.aquaBlue,
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                  InkWell(
                    onTap: _onSkip,
                    borderRadius: BorderRadius.circular(8),
                    child: const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      child: Text(
                        'SKIP',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.mutedText,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // Feature Title
              Text(
                step.title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                  color: AppColors.deepBlue,
                  letterSpacing: -0.2,
                ),
              ),
              const SizedBox(height: 6),

              // Feature Description
              Text(
                step.description,
                style: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w500,
                  color: Color(0xFF38536C),
                  height: 1.42,
                ),
              ),
              const SizedBox(height: 16),

              // Bottom control buttons
              Row(
                children: [
                  // PREVIOUS Button (hidden/disabled on step 1)
                  if (_currentStep > 0) ...[
                    SizedBox(
                      height: 42,
                      child: OutlinedButton(
                        onPressed: _onPrevious,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.deepBlue,
                          side: const BorderSide(
                            color: Color(0xFFBCE3FD),
                            width: 1.4,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text(
                          'PREVIOUS',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.4,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                  ],

                  const Spacer(),

                  // NEXT or FINISH Button
                  SizedBox(
                    height: 42,
                    child: ElevatedButton.icon(
                      onPressed: _onNext,
                      icon: Icon(
                        isLast
                            ? Icons.check_circle_rounded
                            : Icons.arrow_forward_rounded,
                        size: 17,
                      ),
                      label: Text(
                        isLast ? 'FINISH' : 'NEXT',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.4,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.aquaBlue,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(horizontal: 20),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Custom painter cutting out a spotlight window over the highlighted target.
class _SpotlightPainter extends CustomPainter {
  final Rect? targetRect;
  final double borderRadius;
  final double padding;
  final double pulseValue;

  _SpotlightPainter({
    required this.targetRect,
    required this.borderRadius,
    required this.padding,
    required this.pulseValue,
  });

  @override
  void paint(Canvas canvas, Size size) {
    const overlayColor = Color(0xB8031527);
    final bgPaint = Paint()..color = overlayColor;

    if (targetRect == null) {
      canvas.drawRect(Offset.zero & size, bgPaint);
      return;
    }

    final inflated = targetRect!.inflate(padding);
    final rrect = RRect.fromRectAndRadius(
      inflated,
      Radius.circular(borderRadius),
    );

    // Path combining full screen rect and cutout window via evenOdd
    final path = Path()
      ..addRect(Offset.zero & size)
      ..addRRect(rrect)
      ..fillType = PathFillType.evenOdd;

    canvas.drawPath(path, bgPaint);

    // Glowing cyan/aqua border around cutout
    final borderPaint = Paint()
      ..color = const Color(0xFF2EA9E8).withValues(
        alpha: 0.75 + (0.25 * pulseValue),
      )
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4;

    canvas.drawRRect(rrect, borderPaint);

    // Outer faint pulse ring
    final haloPaint = Paint()
      ..color = const Color(0xFF0878C9).withValues(
        alpha: 0.28 * (1.0 - pulseValue),
      )
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.5 + (3.0 * pulseValue);

    canvas.drawRRect(rrect.inflate(2.0 + (2.5 * pulseValue)), haloPaint);
  }

  @override
  bool shouldRepaint(_SpotlightPainter oldDelegate) {
    return oldDelegate.targetRect != targetRect ||
        oldDelegate.pulseValue != pulseValue ||
        oldDelegate.borderRadius != borderRadius;
  }
}

