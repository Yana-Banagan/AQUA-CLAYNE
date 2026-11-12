import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/api/api_client.dart';
import 'package:mobile/core/models/user_model.dart';
import 'package:mobile/core/providers/auth_provider.dart';
import 'package:mobile/core/repositories/auth_repository.dart';
import 'package:mobile/core/services/realtime_service.dart';
import 'package:mobile/core/services/token_storage.dart';
import 'package:mobile/driver/screens/driver_profile_screen.dart';
import 'package:provider/provider.dart';

class _ProfileAuthProvider extends AuthProvider {
  _ProfileAuthProvider(this._user)
    : super(
        AuthRepository(ApiClient(TokenStorage()), TokenStorage()),
        RealtimeService(),
      );

  final UserModel _user;

  @override
  UserModel? get user => _user;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('driver profile cards fit narrow and wide layouts', (
    tester,
  ) async {
    final auth = _ProfileAuthProvider(
      const UserModel(
        id: 'driver-1',
        name: 'Aqua Clayne Delivery Driver',
        email: 'driver.with.a.long.address@example.com',
        phone: '+63 912 345 6789',
        role: 'driver',
      ),
    );
    addTearDown(auth.dispose);
    addTearDown(() => tester.binding.setSurfaceSize(null));

    for (final size in [const Size(320, 740), const Size(1024, 800)]) {
      await tester.binding.setSurfaceSize(size);
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: auth,
          child: MaterialApp(
            home: MediaQuery(
              data: MediaQueryData(textScaler: TextScaler.linear(1.4)),
              child: const DriverProfileScreen(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final exception = tester.takeException();
      expect(exception, isNull);
      expect(find.text('DRIVER AUTHORIZATION'), findsOneWidget);
      expect(find.text('Service Scope'), findsOneWidget);
    }
  });
}
