import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/widgets/social_auth_section.dart';
import 'package:mobile/core/widgets/social_brand_icons.dart';

void main() {
  group('Social Authentication UI Tests', () {
    testWidgets('renders divider and both social buttons with official logos', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SocialAuthSection(
              onGooglePressed: () {},
              onFacebookPressed: () {},
            ),
          ),
        ),
      );

      // Verify divider text
      expect(find.text('OR CONTINUE WITH'), findsOneWidget);

      // Verify Google button & logo
      expect(find.text('Continue with Google'), findsOneWidget);
      expect(find.byType(GoogleLogo), findsOneWidget);

      // Verify Facebook button & logo
      expect(find.text('Continue with Facebook'), findsOneWidget);
      expect(find.byType(FacebookLogo), findsOneWidget);
    });

    testWidgets('triggers callbacks when social buttons are tapped', (tester) async {
      bool googleTapped = false;
      bool facebookTapped = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SocialAuthSection(
              onGooglePressed: () => googleTapped = true,
              onFacebookPressed: () => facebookTapped = true,
            ),
          ),
        ),
      );

      await tester.tap(find.text('Continue with Google'));
      await tester.pump();
      expect(googleTapped, isTrue);

      await tester.tap(find.text('Continue with Facebook'));
      await tester.pump();
      expect(facebookTapped, isTrue);
    });

    testWidgets('displays loading spinner and disables buttons when provider is loading', (tester) async {
      bool googleTapped = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SocialAuthSection(
              activeLoadingProvider: 'google',
              onGooglePressed: () => googleTapped = true,
              onFacebookPressed: () {},
            ),
          ),
        ),
      );

      // Verify Google loading label and spinner
      expect(find.text('Connecting to Google...'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      // Tapping during loading should NOT trigger callback
      await tester.tap(find.text('Connecting to Google...'));
      await tester.pump();
      expect(googleTapped, isFalse);
    });

    testWidgets('adapts responsively: stacked Column on mobile, side-by-side Row on tablet', (tester) async {
      // Test mobile size (width 400)
      tester.view.physicalSize = const Size(400, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 380,
              child: SocialAuthSection(
                onGooglePressed: () {},
                onFacebookPressed: () {},
              ),
            ),
          ),
        ),
      );

      expect(find.byType(Column), findsWidgets);

      // Test tablet/desktop size (width 700)
      tester.view.physicalSize = const Size(800, 1000);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 600,
              child: SocialAuthSection(
                onGooglePressed: () {},
                onFacebookPressed: () {},
              ),
            ),
          ),
        ),
      );

      expect(find.byType(Row), findsWidgets);
    });
  });
}
