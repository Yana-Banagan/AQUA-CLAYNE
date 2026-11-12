import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/api/api_client.dart';
import 'package:mobile/core/navigation/auth_navigation.dart';
import 'package:mobile/core/providers/auth_provider.dart';
import 'package:mobile/core/repositories/auth_repository.dart';
import 'package:mobile/core/screens/login_screen.dart';
import 'package:mobile/core/services/realtime_service.dart';
import 'package:mobile/core/services/token_storage.dart';
import 'package:provider/provider.dart';

class _NavigationAuthProvider extends AuthProvider {
  _NavigationAuthProvider()
    : super(
        AuthRepository(ApiClient(TokenStorage()), TokenStorage()),
        RealtimeService(),
      );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('replaceWithLogin clears previous routes from the root stack', (
    tester,
  ) async {
    final auth = _NavigationAuthProvider();
    final navigatorKey = GlobalKey<NavigatorState>();
    addTearDown(auth.dispose);

    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>.value(
        value: auth,
        child: MaterialApp(
          navigatorKey: navigatorKey,
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (context) => Scaffold(
                      body: TextButton(
                        onPressed: () => replaceWithLogin(context),
                        child: const Text('Reset to Login'),
                      ),
                    ),
                  ),
                ),
                child: const Text('Open previous screen'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Open previous screen'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Reset to Login'));
    await tester.pumpAndSettle();

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Open previous screen'), findsNothing);
    expect(navigatorKey.currentState!.canPop(), isFalse);
  });
}
