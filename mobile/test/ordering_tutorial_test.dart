import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/customer/widgets/ordering_tutorial_modal.dart';

void main() {
  group('OrderingTutorialModal Tests', () {
    testWidgets('renders Step 1 on open and shows proper controls', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => OrderingTutorialModal.show(context),
                child: const Text('Open Tutorial'),
              ),
            ),
          ),
        ),
      );

      // Open tutorial modal
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      // Check header
      expect(find.text('HOW TO ORDER'), findsOneWidget);
      expect(find.text('Step 1 of 8'), findsOneWidget);
      expect(find.text('SKIP'), findsOneWidget);

      // Check Step 1 content
      expect(find.text('Welcome to Aqua Clayne'), findsOneWidget);
      expect(find.text('STEP 1 OF 8 • WELCOME'), findsOneWidget);

      // Verify PREVIOUS button is NOT visible on Step 1
      expect(find.text('PREVIOUS'), findsNothing);

      // Verify NEXT button is present
      expect(find.text('NEXT'), findsOneWidget);
    });

    testWidgets('navigates through steps with NEXT and PREVIOUS buttons', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => OrderingTutorialModal.show(context),
                child: const Text('Open Tutorial'),
              ),
            ),
          ),
        ),
      );

      // Open tutorial modal
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      // Step 1 -> Click NEXT
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();

      // Should now be on Step 2
      expect(find.text('Step 2 of 8'), findsOneWidget);
      expect(find.text('Explore the Home Screen'), findsOneWidget);
      expect(find.text('PREVIOUS'), findsOneWidget);

      // Step 2 -> Click NEXT
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();

      // Should now be on Step 3
      expect(find.text('Step 3 of 8'), findsOneWidget);
      expect(find.text('Browse Water Refilling Services'), findsOneWidget);

      // Step 3 -> Click PREVIOUS
      await tester.tap(find.text('PREVIOUS'));
      await tester.pumpAndSettle();

      // Returns to Step 2
      expect(find.text('Step 2 of 8'), findsOneWidget);
      expect(find.text('Explore the Home Screen'), findsOneWidget);

      // Step 2 -> Click PREVIOUS
      await tester.tap(find.text('PREVIOUS'));
      await tester.pumpAndSettle();

      // Returns to Step 1
      expect(find.text('Step 1 of 8'), findsOneWidget);
      expect(find.text('Welcome to Aqua Clayne'), findsOneWidget);
      expect(find.text('PREVIOUS'), findsNothing);
    });

    testWidgets('SKIP immediately dismisses the tutorial modal', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => OrderingTutorialModal.show(context),
                child: const Text('Open Tutorial'),
              ),
            ),
          ),
        ),
      );

      // Open tutorial
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      expect(find.text('HOW TO ORDER'), findsOneWidget);

      // Tap SKIP
      await tester.tap(find.text('SKIP'));
      await tester.pumpAndSettle();

      // Modal is dismissed, returning to base screen
      expect(find.text('HOW TO ORDER'), findsNothing);
      expect(find.text('Open Tutorial'), findsOneWidget);
    });

    testWidgets('final Step 8 displays GET STARTED and closes on tap', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => OrderingTutorialModal.show(context),
                child: const Text('Open Tutorial'),
              ),
            ),
          ),
        ),
      );

      // Open tutorial
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      // Advance through Steps 1 to 7
      for (int i = 1; i <= 7; i++) {
        await tester.tap(find.text('NEXT'));
        await tester.pumpAndSettle();
      }

      // Now on Step 8
      expect(find.text('Step 8 of 8'), findsOneWidget);
      expect(find.text("You're Ready to Order!"), findsOneWidget);
      expect(find.text('GET STARTED'), findsOneWidget);
      expect(find.text('NEXT'), findsNothing);

      // Tap GET STARTED
      await tester.tap(find.text('GET STARTED'));
      await tester.pumpAndSettle();

      // Modal is closed
      expect(find.text('HOW TO ORDER'), findsNothing);
      expect(find.text('Open Tutorial'), findsOneWidget);
    });

    testWidgets('reopening tutorial always begins at Step 1', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => OrderingTutorialModal.show(context),
                child: const Text('Open Tutorial'),
              ),
            ),
          ),
        ),
      );

      // Open tutorial
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      // Advance to Step 3
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('NEXT'));
      await tester.pumpAndSettle();
      expect(find.text('Step 3 of 8'), findsOneWidget);

      // Close via SKIP
      await tester.tap(find.text('SKIP'));
      await tester.pumpAndSettle();

      // Reopen tutorial
      await tester.tap(find.text('Open Tutorial'));
      await tester.pumpAndSettle();

      // Must be fresh at Step 1
      expect(find.text('Step 1 of 8'), findsOneWidget);
      expect(find.text('Welcome to Aqua Clayne'), findsOneWidget);
    });
  });
}

