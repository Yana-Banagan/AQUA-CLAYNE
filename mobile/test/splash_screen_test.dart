import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/screens/splash_screen.dart';

void main() {
  testWidgets('splash logo scales in during its intro animation', (tester) async {
    var animationCompleted = false;
    final splashScale = find
        .descendant(
          of: find.byType(SplashScreen),
          matching: find.byType(ScaleTransition),
        )
        .first;

    await tester.pumpWidget(
      MaterialApp(
        home: SplashScreen(
          onAnimationComplete: () => animationCompleted = true,
        ),
      ),
    );

    final initialScale = tester.widget<ScaleTransition>(splashScale).scale.value;
    expect(initialScale, closeTo(0.94, 0.001));

    await tester.pump(const Duration(milliseconds: 350));
    final midAnimationScale =
        tester.widget<ScaleTransition>(splashScale).scale.value;
    expect(midAnimationScale, greaterThan(initialScale));
    expect(midAnimationScale, lessThan(1.0));

    await tester.pump(const Duration(milliseconds: 350));
    final finalScale = tester.widget<ScaleTransition>(splashScale).scale.value;
    expect(finalScale, closeTo(1.0, 0.001));

    await tester.pump(const Duration(milliseconds: 1100));
    expect(animationCompleted, isTrue);
  });

  testWidgets('SplashScreen renders clean branded design with Aqua Clayne logo and typography', (tester) async {
    bool completed = false;

    await tester.pumpWidget(
      MaterialApp(
        home: SplashScreen(
          onAnimationComplete: () {
            completed = true;
          },
        ),
      ),
    );

    // Initial frame
    expect(find.byType(SplashScreen), findsOneWidget);
    expect(find.text('AQUA CLAYNE'), findsOneWidget);
    expect(find.text('Purified Drinking Water'), findsOneWidget);

    // Let animation and minimum display duration advance
    await tester.pump(const Duration(milliseconds: 1000));
    expect(find.byType(SplashScreen), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 1000));
    // Settle transition
    await tester.pumpAndSettle();

    expect(completed, isTrue);
  });
}
