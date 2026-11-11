import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/screens/splash_screen.dart';

void main() {
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
