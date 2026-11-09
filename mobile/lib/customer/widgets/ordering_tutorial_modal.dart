import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

/// Interactive, step-by-step onboarding walkthrough for Guest users in Aqua Clayne.
///
/// Replaces the static tutorial video with an interactive, 8-step guide
/// explaining actual application navigation, product selection, checkout, and tracking.
class OrderingTutorialModal extends StatefulWidget {
  const OrderingTutorialModal({super.key});

  /// Opens the interactive tutorial as a styled, scroll-safe modal sheet.
  static Future<void> show(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      enableDrag: true,
      builder: (_) => const OrderingTutorialModal(),
    );
  }

  @override
  State<OrderingTutorialModal> createState() => _OrderingTutorialModalState();
}

class _OrderingTutorialModalState extends State<OrderingTutorialModal> {
  int _currentStep = 0;
  static const int _totalSteps = 8;
  late final PageController _pageController;

  @override
  void initState() {
    super.initState();
    _currentStep = 0;
    _pageController = PageController(initialPage: 0);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _goToStep(int step) {
    if (step < 0 || step >= _totalSteps) return;
    setState(() => _currentStep = step);
    _pageController.animateToPage(
      step,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeInOutCubic,
    );
  }

  void _onNext() {
    if (_currentStep < _totalSteps - 1) {
      _goToStep(_currentStep + 1);
    } else {
      // Final step: Close tutorial and return to Guest interface
      Navigator.of(context).pop();
    }
  }

  void _onPrevious() {
    if (_currentStep > 0) {
      _goToStep(_currentStep - 1);
    }
  }

  void _onSkip() {
    // Immediately close tutorial without modifying user state or triggering login/order
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final isLastStep = _currentStep == _totalSteps - 1;
    final progress = (_currentStep + 1) / _totalSteps;

    return PopScope(
      canPop: _currentStep == 0,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        if (_currentStep > 0) {
          _onPrevious();
        } else {
          Navigator.of(context).pop();
        }
      },
      child: SafeArea(
        top: false,
        child: Container(
          height: media.size.height * 0.92,
          decoration: const BoxDecoration(
            color: Color(0xFFF4F9FD),
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            boxShadow: [
              BoxShadow(
                color: Color(0x33064B87),
                blurRadius: 28,
                offset: Offset(0, -6),
              ),
            ],
          ),
          child: Column(
            children: [
              // ── 1. Top Drag Handle ─────────────────────────────────
              Center(
                child: Container(
                  margin: const EdgeInsets.only(top: 10, bottom: 4),
                  width: 44,
                  height: 4.5,
                  decoration: BoxDecoration(
                    color: const Color(0xFFC7DEEE),
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),

              // ── 2. Header: Title, Step Indicator & Skip Button ─────
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 6, 12, 10),
                child: Row(
                  children: [
                    // Droplet icon badge
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: AppColors.lightAqua,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFBCE3FD)),
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.water_drop_rounded,
                          color: AppColors.aquaBlue,
                          size: 20,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),

                    // Title & Step counter
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'HOW TO ORDER',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                              color: AppColors.deepBlue,
                              letterSpacing: 0.4,
                            ),
                          ),
                          const SizedBox(height: 1),
                          Text(
                            'Step ${_currentStep + 1} of $_totalSteps',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: AppColors.mutedText,
                            ),
                          ),
                        ],
                      ),
                    ),

                    // SKIP button
                    TextButton(
                      onPressed: _onSkip,
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.mutedText,
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'SKIP',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // ── 3. Visual Progress Bar ──────────────────────────────
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: TweenAnimationBuilder<double>(
                    duration: const Duration(milliseconds: 250),
                    curve: Curves.easeOutCubic,
                    tween: Tween<double>(begin: 0, end: progress),
                    builder: (context, value, _) {
                      return LinearProgressIndicator(
                        value: value,
                        minHeight: 5,
                        backgroundColor: const Color(0xFFD6E9F7),
                        valueColor: const AlwaysStoppedAnimation<Color>(
                          AppColors.aquaBlue,
                        ),
                      );
                    },
                  ),
                ),
              ),

              const SizedBox(height: 8),

              // ── 4. Main Interactive Step Content (PageView) ────────
              Expanded(
                child: PageView(
                  controller: _pageController,
                  physics: const BouncingScrollPhysics(),
                  onPageChanged: (index) {
                    setState(() => _currentStep = index);
                  },
                  children: [
                    _buildStep1Welcome(),
                    _buildStep2ExploreHome(),
                    _buildStep3BrowseServices(),
                    _buildStep4SelectRefill(),
                    _buildStep5AddToCart(),
                    _buildStep6Checkout(),
                    _buildStep7TrackOrder(),
                    _buildStep8GetStarted(),
                  ],
                ),
              ),

              // ── 5. Bottom Navigation Controls (Previous / Next) ────
              Container(
                padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: const Border(
                    top: BorderSide(color: Color(0xFFE5F1FA), width: 1.2),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0878C9).withValues(alpha: 0.05),
                      blurRadius: 10,
                      offset: const Offset(0, -3),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    // PREVIOUS Button (disabled/hidden on step 1)
                    if (_currentStep > 0) ...[
                      Expanded(
                        flex: 4,
                        child: SizedBox(
                          height: 50,
                          child: OutlinedButton.icon(
                            onPressed: _onPrevious,
                            icon: const Icon(Icons.arrow_back_rounded, size: 18),
                            label: const Text(
                              'PREVIOUS',
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.3,
                              ),
                            ),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.deepBlue,
                              side: const BorderSide(
                                color: Color(0xFFBCE3FD),
                                width: 1.4,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              backgroundColor: const Color(0xFFF7FBFF),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                    ],

                    // NEXT or GET STARTED Button
                    Expanded(
                      flex: 6,
                      child: SizedBox(
                        height: 50,
                        child: ElevatedButton.icon(
                          onPressed: _onNext,
                          icon: Icon(
                            isLastStep
                                ? Icons.check_circle_rounded
                                : Icons.arrow_forward_rounded,
                            size: 19,
                          ),
                          label: Text(
                            isLastStep ? 'GET STARTED' : 'NEXT',
                            style: const TextStyle(
                              fontSize: 14.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.aquaBlue,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // STEP BUILDERS (1 through 8)
  // ====================================================================

  /// Step 1 — Welcome to Aqua Clayne
  Widget _buildStep1Welcome() {
    return _StepContentLayout(
      stepTag: 'STEP 1 OF 8 • WELCOME',
      title: 'Welcome to Aqua Clayne',
      description:
          'Aqua Clayne provides pure, refreshing drinking water delivered directly to your doorstep. '
          'Follow this quick interactive guide to learn how to place your first refill in minutes.',
      visual: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            // Water droplet halo
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: const RadialGradient(
                  colors: [Color(0xFFE1F4FE), Color(0xFFBAE5FD)],
                ),
                border: Border.all(color: const Color(0xFF90D5FB), width: 2),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.aquaBlue.withValues(alpha: 0.18),
                    blurRadius: 18,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: const Center(
                child: Icon(
                  Icons.water_drop_rounded,
                  size: 48,
                  color: AppColors.aquaBlue,
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Pure, Safe & Reliable Water',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: AppColors.darkText,
              ),
            ),
            const SizedBox(height: 12),
            _benefitPill('💧 Multi-Stage Purified Water'),
            _benefitPill('🚚 Fast Doorstep Delivery & Station Pickup'),
            _benefitPill('🔄 Standard Round & Slim Gallon Refills'),
          ],
        ),
      ),
      tipText: 'You can browse services freely as a guest without creating an account first.',
    );
  }

  /// Step 2 — Explore the Home Screen
  Widget _buildStep2ExploreHome() {
    return _StepContentLayout(
      stepTag: 'STEP 2 OF 8 • HOME NAVIGATION',
      title: 'Explore the Home Screen',
      description:
          'The Home screen is your central dashboard. Tap the Quick Action cards to browse water refills, '
          'track your previous orders, review payments, or contact customer support.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.dashboard_rounded, color: AppColors.aquaBlue, size: 18),
                SizedBox(width: 8),
                Text(
                  'Quick Action Shortcuts',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: AppColors.deepBlue,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _mockActionCard(
                    icon: Icons.water_drop_rounded,
                    label: 'Order Water',
                    isHighlighted: true,
                    badge: 'START HERE',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _mockActionCard(
                    icon: Icons.receipt_long_rounded,
                    label: 'My Orders',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _mockActionCard(
                    icon: Icons.account_balance_wallet_rounded,
                    label: 'Payments',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _mockActionCard(
                    icon: Icons.chat_bubble_rounded,
                    label: 'Support Chat',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      tipText: 'Tap "Order Water" anytime from Home to start choosing your refills.',
    );
  }

  /// Step 3 — Browse Water Refilling Services
  Widget _buildStep3BrowseServices() {
    return _StepContentLayout(
      stepTag: 'STEP 3 OF 8 • SERVICE CATALOG',
      title: 'Browse Water Refilling Services',
      description:
          'Tap "Order Water" to see our available water products. Filter between All, Purified, '
          'and Mineral options to see live container pricing and stock availability.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Category filter chips
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _mockCategoryChip('All', isActive: false),
                  const SizedBox(width: 6),
                  _mockCategoryChip('Purified', isActive: true),
                  const SizedBox(width: 6),
                  _mockCategoryChip('Mineral', isActive: false),
                ],
              ),
            ),
            const SizedBox(height: 14),
            // Mock product card
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FCFF),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFCCE4F7)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: AppColors.lightAqua,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.local_drink_rounded,
                      color: AppColors.aquaBlue,
                      size: 28,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Round Gallon Refill (5 Gal)',
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: AppColors.darkText,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Multi-stage purified water refill',
                          style: TextStyle(
                            fontSize: 11,
                            color: AppColors.mutedText,
                          ),
                        ),
                        SizedBox(height: 4),
                        Row(
                          children: [
                            Text(
                              '₱25.00',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                                color: AppColors.aquaBlue,
                              ),
                            ),
                            SizedBox(width: 8),
                            Text(
                              '● In Stock',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF2E7D32),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      tipText: 'Both refills for customer-owned containers and new bottle purchases are listed here.',
    );
  }

  /// Step 4 — Select Your Refill & Quantity
  Widget _buildStep4SelectRefill() {
    return _StepContentLayout(
      stepTag: 'STEP 4 OF 8 • SELECT QUANTITY',
      title: 'Select Your Refill & Quantity',
      description:
          'Choose the container type you own (Round or Slim gallon) and use the plus (+) and minus (-) '
          'stepper controls to select the exact number of refills you need.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            // Container type selector
            Row(
              children: [
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: AppColors.lightAqua,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.aquaBlue, width: 1.5),
                    ),
                    child: const Column(
                      children: [
                        Icon(Icons.radio_button_checked_rounded,
                            color: AppColors.aquaBlue, size: 18),
                        SizedBox(height: 4),
                        Text('Round Gallon',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.deepBlue,
                            )),
                        Text('(Dispenser)',
                            style: TextStyle(
                              fontSize: 10,
                              color: AppColors.mutedText,
                            )),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFD5E7F4)),
                    ),
                    child: const Column(
                      children: [
                        Icon(Icons.radio_button_off_rounded,
                            color: AppColors.mutedText, size: 18),
                        SizedBox(height: 4),
                        Text('Slim Gallon',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.darkText,
                            )),
                        Text('(With Faucet)',
                            style: TextStyle(
                              fontSize: 10,
                              color: AppColors.mutedText,
                            )),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Stepper simulation
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF7FBFF),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFBCE3FD)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Quantity',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppColors.deepBlue,
                    ),
                  ),
                  Row(
                    children: [
                      _mockStepperBtn(Icons.remove_rounded),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 14),
                        child: Text(
                          '2',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                            color: AppColors.darkText,
                          ),
                        ),
                      ),
                      _mockStepperBtn(Icons.add_rounded),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),

            // Subtotal preview
            const Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Subtotal for 2 gallons:',
                    style: TextStyle(fontSize: 12, color: AppColors.mutedText)),
                Text('₱50.00',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                      color: AppColors.aquaBlue,
                    )),
              ],
            ),
          ],
        ),
      ),
      tipText: 'You can adjust quantities or mix Round and Slim containers in your cart.',
    );
  }

  /// Step 5 — Add to Cart & Review
  Widget _buildStep5AddToCart() {
    return _StepContentLayout(
      stepTag: 'STEP 5 OF 8 • CART REVIEW',
      title: 'Add to Cart & Review',
      description:
          'Tap "Add to Cart" to stage your order. View your shopping cart anytime to review selected items, '
          'modify quantities, or remove containers before proceeding.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            // Cart items preview
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF7FBFF),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFD5E7F4)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.shopping_bag_outlined,
                      color: AppColors.aquaBlue, size: 24),
                  SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('2 × Purified Round Gallon Refill',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: AppColors.darkText,
                            )),
                        Text('Refill existing containers',
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.mutedText,
                            )),
                      ],
                    ),
                  ),
                  Text('₱50.00',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppColors.deepBlue,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Cart checkout banner preview
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF0878C9), Color(0xFF064B87)],
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Cart Total',
                          style: TextStyle(fontSize: 11, color: Color(0xFFBCE3FD))),
                      Text('₱50.00',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                          )),
                    ],
                  ),
                  Row(
                    children: [
                      Text('Checkout',
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          )),
                      SizedBox(width: 4),
                      Icon(Icons.arrow_forward_rounded,
                          color: Colors.white, size: 16),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      tipText: 'Your cart items are saved on your device while you continue browsing.',
    );
  }

  /// Step 6 — Proceed to Checkout
  Widget _buildStep6Checkout() {
    return _StepContentLayout(
      stepTag: 'STEP 6 OF 8 • CHECKOUT PROCESS',
      title: 'Proceed to Checkout',
      description:
          'Choose between Home Delivery or Station Pickup. Specify your empty gallon exchange count, '
          'and choose your payment method: Cash, Online (GCash/Maya), or Account Credit.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            // 4-step progress mockup
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _checkoutStepPill('1. Address', isDone: true),
                _checkoutDivider(),
                _checkoutStepPill('2. Gallons', isDone: true),
                _checkoutDivider(),
                _checkoutStepPill('3. Payment', isDone: true),
                _checkoutDivider(),
                _checkoutStepPill('4. Review', isDone: false),
              ],
            ),
            const SizedBox(height: 14),

            // Guest note card
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF9E6),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFFE082)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.person_pin_rounded,
                      color: Color(0xFFB78103), size: 20),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Account Required at Checkout: When guests proceed to checkout, '
                      'you will be prompted to sign in or register so we can record your delivery address.',
                      style: TextStyle(
                        fontSize: 11.5,
                        color: Color(0xFF7A5400),
                        height: 1.35,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      tipText: 'Registration only takes 10 seconds and lets you track your orders anytime.',
    );
  }

  /// Step 7 — Track Your Order
  Widget _buildStep7TrackOrder() {
    return _StepContentLayout(
      stepTag: 'STEP 7 OF 8 • REAL-TIME TRACKING',
      title: 'Track Your Order in Real-Time',
      description:
          'Once placed, monitor your order through every stage: Intake, Washing, Refilling, and Delivery. '
          'Registered customers can track delivery drivers live on the map and receive status notifications.',
      visual: Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            // Timeline simulation
            _trackingTimelineItem(
              icon: Icons.check_circle_rounded,
              title: 'Order Confirmed',
              subtitle: 'Queued for washing & refill',
              color: const Color(0xFF2E7D32),
              isDone: true,
            ),
            _trackingTimelineConnector(),
            _trackingTimelineItem(
              icon: Icons.local_car_wash_rounded,
              title: 'Water Refilled & Sealed',
              subtitle: 'Quality inspected',
              color: const Color(0xFF2E7D32),
              isDone: true,
            ),
            _trackingTimelineConnector(),
            _trackingTimelineItem(
              icon: Icons.delivery_dining_rounded,
              title: 'Out for Delivery',
              subtitle: 'Driver Juan D. is on the way',
              color: AppColors.aquaBlue,
              isDone: false,
              isActive: true,
            ),
          ],
        ),
      ),
      tipText: 'Check the "My Orders" tab anytime to review real-time progress and receipts.',
    );
  }

  /// Step 8 — Get Started
  Widget _buildStep8GetStarted() {
    return _StepContentLayout(
      stepTag: 'STEP 8 OF 8 • YOU ARE READY!',
      title: "You're Ready to Order!",
      description:
          'You know everything needed to enjoy fresh, reliable purified water from Aqua Clayne. '
          'Tap "GET STARTED" below to explore our services or create your account.',
      visual: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
        decoration: _cardBoxDecoration(),
        child: Column(
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: const Color(0xFFE8F5E9),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFA5D6A7), width: 2),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF2E7D32).withValues(alpha: 0.16),
                    blurRadius: 18,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: const Center(
                child: Icon(
                  Icons.check_circle_rounded,
                  color: Color(0xFF2E7D32),
                  size: 48,
                ),
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Ready for Fresh Water?',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: AppColors.darkText,
              ),
            ),
            const SizedBox(height: 12),
            _benefitPill('✔ Transparent gallon pricing & delivery rates'),
            _benefitPill('✔ Customer support available anytime in Chat'),
            _benefitPill('✔ Review this tutorial anytime via "How to Order"'),
          ],
        ),
      ),
      tipText: 'Tap "GET STARTED" below to close the guide and begin browsing.',
    );
  }

  // ====================================================================
  // REUSABLE HELPER WIDGETS
  // ====================================================================

  BoxDecoration _cardBoxDecoration() {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: const Color(0xFFCCE4F7), width: 1.2),
      boxShadow: [
        BoxShadow(
          color: const Color(0xFF0878C9).withValues(alpha: 0.06),
          blurRadius: 14,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  Widget _benefitPill(String text) {
    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FBFF),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE5F1FA)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Flexible(
            child: Text(
              text,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppColors.darkText,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _mockActionCard({
    required IconData icon,
    required String label,
    bool isHighlighted = false,
    String? badge,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: isHighlighted ? AppColors.lightAqua : const Color(0xFFF7FBFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isHighlighted ? AppColors.aquaBlue : const Color(0xFFCCE4F7),
          width: isHighlighted ? 1.8 : 1,
        ),
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  icon,
                  color: isHighlighted ? AppColors.aquaBlue : AppColors.deepBlue,
                  size: 24,
                ),
                const SizedBox(height: 6),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: isHighlighted ? AppColors.aquaBlue : AppColors.darkText,
                  ),
                ),
              ],
            ),
          ),
          if (badge != null)
            Positioned(
              top: -6,
              right: -2,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFE65100),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  badge,
                  style: const TextStyle(
                    fontSize: 8.5,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _mockCategoryChip(String text, {required bool isActive}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      decoration: BoxDecoration(
        color: isActive ? AppColors.aquaBlue : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isActive ? AppColors.aquaBlue : const Color(0xFFCCE4F7),
        ),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: isActive ? Colors.white : AppColors.darkText,
        ),
      ),
    );
  }

  Widget _mockStepperBtn(IconData icon) {
    return Container(
      width: 28,
      height: 28,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFBCE3FD)),
      ),
      child: Center(
        child: Icon(icon, size: 16, color: AppColors.aquaBlue),
      ),
    );
  }

  Widget _checkoutStepPill(String title, {required bool isDone}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: isDone ? const Color(0xFFE8F5E9) : const Color(0xFFF0F7FF),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isDone ? const Color(0xFFA5D6A7) : const Color(0xFFCCE4F7),
        ),
      ),
      child: Text(
        title,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.bold,
          color: isDone ? const Color(0xFF2E7D32) : AppColors.deepBlue,
        ),
      ),
    );
  }

  Widget _checkoutDivider() {
    return const Icon(
      Icons.chevron_right_rounded,
      size: 14,
      color: Color(0xFF90C2E6),
    );
  }

  Widget _trackingTimelineItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required bool isDone,
    bool isActive = false,
  }) {
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
            border: Border.all(color: color, width: 1.5),
          ),
          child: Center(
            child: Icon(icon, color: color, size: 18),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: isActive ? AppColors.aquaBlue : AppColors.darkText,
                ),
              ),
              Text(
                subtitle,
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.mutedText,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _trackingTimelineConnector() {
    return Padding(
      padding: const EdgeInsets.only(left: 15, top: 2, bottom: 2),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Container(
          width: 2,
          height: 14,
          color: const Color(0xFFCCE4F7),
        ),
      ),
    );
  }
}

/// Standardized layout for an individual step in the tutorial modal.
class _StepContentLayout extends StatelessWidget {
  final String stepTag;
  final String title;
  final String description;
  final Widget visual;
  final String? tipText;

  const _StepContentLayout({
    required this.stepTag,
    required this.title,
    required this.description,
    required this.visual,
    this.tipText,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Step category tag pill
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.lightAqua,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFBCE3FD)),
            ),
            child: Text(
              stepTag,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w900,
                color: AppColors.aquaBlue,
                letterSpacing: 0.6,
              ),
            ),
          ),
          const SizedBox(height: 8),

          // Main Step Title
          Text(
            title,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: AppColors.deepBlue,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 6),

          // Step Description
          Text(
            description,
            style: const TextStyle(
              fontSize: 13.5,
              color: Color(0xFF4A657D),
              height: 1.45,
            ),
          ),
          const SizedBox(height: 14),

          // Step Visual Mockup / Illustration Card
          visual,

          if (tipText != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFEAF5FD),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFD0E7F8)),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.tips_and_updates_rounded,
                    color: AppColors.aquaBlue,
                    size: 16,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      tipText!,
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: Color(0xFF336085),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

