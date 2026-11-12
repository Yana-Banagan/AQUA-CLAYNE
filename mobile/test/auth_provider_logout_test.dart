import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/api/api_client.dart';
import 'package:mobile/core/providers/auth_provider.dart';
import 'package:mobile/core/repositories/auth_repository.dart';
import 'package:mobile/core/services/realtime_service.dart';
import 'package:mobile/core/services/token_storage.dart';

class _FakeApiClient extends ApiClient {
  _FakeApiClient(super.tokenStorage);

  bool cacheCleared = false;
  String? offlineOwner;

  @override
  Future<void> clearCachedData() async {
    cacheCleared = true;
  }

  @override
  void setOfflineOwner(String? userId) {
    offlineOwner = userId;
  }
}

class _FakeTokenStorage extends TokenStorage {
  bool offlineUserCleared = false;

  @override
  Future<void> clearOfflineUser() async {
    offlineUserCleared = true;
  }
}

class _FakeAuthRepository extends AuthRepository {
  _FakeAuthRepository(super.api, super.tokenStorage);

  int logoutCalls = 0;
  bool storedSessionCleared = false;
  Object? logoutError;
  Completer<void>? logoutGate;

  @override
  Future<void> logout() async {
    logoutCalls++;
    await logoutGate?.future;
    final error = logoutError;
    if (error != null) throw error;
  }

  @override
  Future<void> clearStoredSession() async {
    storedSessionCleared = true;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
    'logout reports an unconfirmed server revocation after clearing locally',
    () async {
      final storage = _FakeTokenStorage();
      final api = _FakeApiClient(storage);
      final repository = _FakeAuthRepository(api, storage)
        ..logoutError = ApiException('offline');
      final provider = AuthProvider(repository, RealtimeService());
      addTearDown(provider.dispose);

      final result = await provider.logout();

      expect(result.serverLogoutConfirmed, isFalse);
      expect(result.localCleanupComplete, isTrue);
      expect(result.noticeMessage, contains('server could not confirm logout'));
      expect(api.cacheCleared, isTrue);
      expect(api.offlineOwner, isNull);
      expect(repository.storedSessionCleared, isTrue);
      expect(storage.offlineUserCleared, isTrue);
      expect(provider.status, AuthStatus.unauthenticated);
      expect(provider.user, isNull);
    },
  );

  test('simultaneous logout requests issue one backend request', () async {
    final api = _FakeApiClient(_FakeTokenStorage());
    final storage = _FakeTokenStorage();
    final repository = _FakeAuthRepository(api, storage)
      ..logoutGate = Completer<void>();
    final provider = AuthProvider(repository, RealtimeService());
    addTearDown(provider.dispose);

    final first = provider.logout();
    final second = provider.logout();
    repository.logoutGate!.complete();
    final results = await Future.wait([first, second]);

    expect(repository.logoutCalls, 1);
    expect(
      results.map((result) => result.serverLogoutConfirmed),
      everyElement(isTrue),
    );
  });
}
