import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/screens/splash_screen.dart';

void main() {
  testWidgets('SplashScreen renders all scenes and branding correctly', (tester) async {
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

    // Initial frame (Scene 1: Dark background & particles)
    expect(find.byType(SplashScreen), findsOneWidget);

    // Advance 1 second (Scene 2 & 3: Light core & water ripples)
    await tester.pump(const Duration(milliseconds: 1000));
    expect(find.byType(SplashScreen), findsOneWidget);

    // Advance to Scene 4 (Logo reveal)
    await tester.pump(const Duration(milliseconds: 1000));
    expect(find.byType(SplashScreen), findsOneWidget);

    // Advance to Scene 6 (Typography & percentage loading appears)
    await tester.pump(const Duration(milliseconds: 1600));
    expect(find.text('AQUA CLAYNE'), findsOneWidget);
    expect(find.text('PURE WATER • HEALTHIER LIFE'), findsOneWidget);
    expect(find.textContaining('%'), findsOneWidget);

    // Complete the remaining splash sequence (hold final frame & transition)
    await tester.pump(const Duration(milliseconds: 1000));
    await tester.pump(const Duration(milliseconds: 500));

    expect(completed, isTrue);
  });
}
