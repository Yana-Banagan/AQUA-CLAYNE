import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Back Button Tab History & Stack Tests', () {
    test('Tab history correctly records sequence and prevents duplicate entries', () {
      final List<int> tabHistory = [0];

      void recordTabSwitch(int newIndex) {
        if (newIndex == 0) {
          tabHistory.clear();
          tabHistory.add(0);
        } else {
          tabHistory.remove(newIndex);
          tabHistory.add(newIndex);
        }
      }

      int handleBack(int currentIndex) {
        if (tabHistory.length > 1) {
          tabHistory.removeLast();
          return tabHistory.last;
        }
        if (currentIndex != 0) {
          tabHistory.clear();
          tabHistory.add(0);
          return 0;
        }
        return 0; // At root
      }

      // Initial state: on Home (0)
      expect(tabHistory, equals([0]));

      // Navigate to Orders (1)
      recordTabSwitch(1);
      expect(tabHistory, equals([0, 1]));

      // Navigate to Chat (3)
      recordTabSwitch(3);
      expect(tabHistory, equals([0, 1, 3]));

      // Navigate to Profile (4)
      recordTabSwitch(4);
      expect(tabHistory, equals([0, 1, 3, 4]));

      // Navigate to Chat (3) again — should move 3 to top without duplicating
      recordTabSwitch(3);
      expect(tabHistory, equals([0, 1, 4, 3]));

      // Back press 1: from 3 -> returns to 4
      int active = handleBack(3);
      expect(active, equals(4));
      expect(tabHistory, equals([0, 1, 4]));

      // Back press 2: from 4 -> returns to 1
      active = handleBack(4);
      expect(active, equals(1));
      expect(tabHistory, equals([0, 1]));

      // Back press 3: from 1 -> returns to 0 (Home)
      active = handleBack(1);
      expect(active, equals(0));
      expect(tabHistory, equals([0]));

      // Back press 4: from 0 -> stays at root
      active = handleBack(0);
      expect(active, equals(0));
      expect(tabHistory, equals([0]));
    });

    test('Double back press exit confirmation window works accurately', () {
      DateTime? lastBackPressTime;
      bool exitTriggered = false;
      bool promptShown = false;

      void onRootBackPress(DateTime now) {
        if (lastBackPressTime == null ||
            now.difference(lastBackPressTime!) > const Duration(seconds: 2)) {
          lastBackPressTime = now;
          promptShown = true;
          exitTriggered = false;
        } else {
          exitTriggered = true;
        }
      }

      final t0 = DateTime(2026, 11, 4, 12, 0, 0);
      onRootBackPress(t0);
      expect(promptShown, isTrue);
      expect(exitTriggered, isFalse);

      // Back press 3 seconds later (> 2s window) -> should prompt again, not exit
      promptShown = false;
      final t1 = t0.add(const Duration(seconds: 3));
      onRootBackPress(t1);
      expect(promptShown, isTrue);
      expect(exitTriggered, isFalse);

      // Back press 1 second later (< 2s window) -> triggers exit
      promptShown = false;
      final t2 = t1.add(const Duration(milliseconds: 1200));
      onRootBackPress(t2);
      expect(exitTriggered, isTrue);
    });

    testWidgets('PopScope intercepts back events when canPop is false', (tester) async {
      bool intercepted = false;

      await tester.pumpWidget(
        MaterialApp(
          home: PopScope(
            canPop: false,
            onPopInvokedWithResult: (didPop, result) {
              if (didPop) return;
              intercepted = true;
            },
            child: const Scaffold(
              body: Text('Protected Root Screen'),
            ),
          ),
        ),
      );

      expect(find.text('Protected Root Screen'), findsOneWidget);

      // Simulate back press via route navigator
      final dynamic widgetsAppState = tester.state(find.byType(WidgetsApp));
      await widgetsAppState.didPopRoute();
      await tester.pump();

      expect(intercepted, isTrue);
    });
  });
}

