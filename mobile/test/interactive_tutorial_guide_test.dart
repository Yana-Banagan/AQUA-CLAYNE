import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/customer/widgets/interactive_tutorial_overlay.dart';

void main() {
  group('Interactive Tutorial Guide (14-Step Spotlight Tour) Tests', () {
    Widget buildTestHarness() {
      return MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Column(
              children: [
                // Header icons
                Row(
                  children: [
                    Container(
                      key: TutorialTargetKeys.shoppingCart,
                      width: 42,
                      height: 42,
                      child: const Icon(Icons.shopping_cart),
                    ),
                    Container(
                      key: TutorialTargetKeys.notificationBell,
                      width: 42,
                      height: 42,
                      child: const Icon(Icons.notifications),
                    ),
                    Container(
                      key: TutorialTargetKeys.headerProfile,
                      width: 42,
                      height: 42,
                      child: const Icon(Icons.person),
                    ),
                  ],
                ),
                // Hero banner button
                ElevatedButton(
                  key: TutorialTargetKeys.orderNowButton,
                  onPressed: () {},
                  child: const Text('Order Now'),
                ),
                // Tutorial Guide Card
                Container(
                  key: TutorialTargetKeys.tutorialCard,
                  padding: const EdgeInsets.all(12),
                  child: Builder(
                    builder: (ctx) => ElevatedButton(
                      onPressed: () => InteractiveTutorialOverlay.show(ctx),
                      child: const Text('START TUTORIAL'),
                    ),
                  ),
                ),
                // Shortcuts
                Row(
                  children: [
                    Container(
                      key: TutorialTargetKeys.orderWaterShortcut,
                      width: 80,
                      height: 80,
                      child: const Text('Order Water'),
                    ),
                    Container(
                      key: TutorialTargetKeys.myOrdersShortcut,
                      width: 80,
                      height: 80,
                      child: const Text('My Orders'),
                    ),
                    Container(
                      key: TutorialTargetKeys.paymentShortcut,
                      width: 80,
                      height: 80,
                      child: const Text('Payment'),
                    ),
                    Container(
                      key: TutorialTargetKeys.myProfileShortcut,
                      width: 80,
                      height: 80,
                      child: const Text('My Profile'),
                    ),
                  ],
                ),
                const SizedBox(height: 500),
              ],
            ),
          ),
          bottomNavigationBar: Row(
            children: [
              Container(
                key: TutorialTargetKeys.homeNavTab,
                width: 50,
                height: 50,
                child: const Text('Home'),
              ),
              Container(
                key: TutorialTargetKeys.ordersNavTab,
                width: 50,
                height: 50,
                child: const Text('Orders'),
              ),
              Container(
                key: TutorialTargetKeys.paymentsNavTab,
                width: 50,
                height: 50,
                child: const Text('Payments'),
              ),
              Container(
                key: TutorialTargetKeys.chatNavTab,
                width: 50,
                height: 50,
                child: const Text('Chat'),
              ),
              Container(
                key: TutorialTargetKeys.profileNavTab,
                width: 50,
                height: 50,
                child: const Text('Profile'),
              ),
            ],
          ),
        ),
      );
    }

    testWidgets('starts at Step 1 (Order Now button) with proper initial controls',
        (tester) async {
      await tester.pumpWidget(buildTestHarness());
      await tester.pumpAndSettle();

      // Launch tutorial
      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();

      // Verify Step 1
      expect(find.text('STEP 1 OF 14'), findsOneWidget);
      expect(find.text('Order Now button'), findsOneWidget);
      expect(
        find.text(
            'Start your water order here. Browse available water services and select the refill option you need.'),
        findsOneWidget,
      );

      // Verify controls on Step 1: PREVIOUS is hidden, NEXT & SKIP are visible
      expect(find.text('PREVIOUS'), findsNothing);
      expect(find.text('NEXT'), findsOneWidget);
      expect(find.text('SKIP'), findsOneWidget);
    });

    testWidgets('cycles through all 14 steps sequentially and reaches FINISH',
        (tester) async {
      await tester.pumpWidget(buildTestHarness());
      await tester.pumpAndSettle();

      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();

      final expectedSteps = [
        'Order Now button',
        'Shopping Cart icon',
        'Notification Bell icon',
        'Profile icon (header)',
        'Tutorial Guide card',
        'Order Water shortcut',
        'My Orders shortcut',
        'Payment shortcut',
        'My Profile shortcut',
        'Home navigation tab',
        'Orders navigation tab',
        'Payments navigation tab',
        'Chat navigation tab',
        'Profile navigation tab',
      ];

      for (int i = 0; i < expectedSteps.length; i++) {
        expect(find.text('STEP ${i + 1} OF 14'), findsOneWidget);
        expect(find.text(expectedSteps[i]), findsOneWidget);

        if (i < expectedSteps.length - 1) {
          expect(find.text('NEXT'), findsOneWidget);
          await tester.tap(find.text('NEXT'));
          await tester.pumpAndSettle();
        } else {
          // Final step: button is FINISH
          expect(find.text('FINISH'), findsOneWidget);
          expect(find.text('NEXT'), findsNothing);
        }
      }

      // Tap FINISH: closes the tutorial
      await tester.tap(find.text('FINISH'));
      await tester.pumpAndSettle();

      expect(find.text('STEP 14 OF 14'), findsNothing);
      expect(find.text('START TUTORIAL'), findsOneWidget);
    });

    testWidgets('PREVIOUS button restores preceding step accurately',
        (tester) async {
      await tester.pumpWidget(buildTestHarness());
      await tester.pumpAndSettle();

      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();

      // Step 1 -> Next -> Step 2
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();
      expect(find.text('STEP 2 OF 14'), findsOneWidget);
      expect(find.text('Shopping Cart icon'), findsOneWidget);
      expect(find.text('PREVIOUS'), findsOneWidget);

      // Step 2 -> Next -> Step 3
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();
      expect(find.text('STEP 3 OF 14'), findsOneWidget);
      expect(find.text('Notification Bell icon'), findsOneWidget);

      // Step 3 -> Previous -> Step 2
      await tester.tap(find.text('PREVIOUS'));
      await tester.pumpAndSettle();
      expect(find.text('STEP 2 OF 14'), findsOneWidget);
      expect(find.text('Shopping Cart icon'), findsOneWidget);

      // Step 2 -> Previous -> Step 1
      await tester.tap(find.text('PREVIOUS'));
      await tester.pumpAndSettle();
      expect(find.text('STEP 1 OF 14'), findsOneWidget);
      expect(find.text('Order Now button'), findsOneWidget);
      expect(find.text('PREVIOUS'), findsNothing);
    });

    testWidgets('SKIP immediately dismisses the guided walkthrough',
        (tester) async {
      await tester.pumpWidget(buildTestHarness());
      await tester.pumpAndSettle();

      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();
      expect(find.text('STEP 1 OF 14'), findsOneWidget);

      // Tap SKIP
      await tester.tap(find.text('SKIP'));
      await tester.pumpAndSettle();

      // Overlay dismissed immediately
      expect(find.text('STEP 1 OF 14'), findsNothing);
      expect(find.text('START TUTORIAL'), findsOneWidget);
    });

    testWidgets('reopening tutorial always begins at Step 1',
        (tester) async {
      await tester.pumpWidget(buildTestHarness());
      await tester.pumpAndSettle();

      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();

      // Advance to step 4
      for (int i = 0; i < 3; i++) {
        await tester.tap(find.text('NEXT'));
        await tester.pumpAndSettle();
      }
      expect(find.text('STEP 4 OF 14'), findsOneWidget);

      // Close via SKIP
      await tester.tap(find.text('SKIP'));
      await tester.pumpAndSettle();

      // Reopen
      await tester.tap(find.text('START TUTORIAL'));
      await tester.pumpAndSettle();

      // Starts fresh at Step 1
      expect(find.text('STEP 1 OF 14'), findsOneWidget);
      expect(find.text('Order Now button'), findsOneWidget);
    });
  });
}

