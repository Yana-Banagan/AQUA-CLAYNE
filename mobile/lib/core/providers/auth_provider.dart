import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_client.dart';
import '../models/user_model.dart';
import '../repositories/auth_repository.dart';
import '../services/realtime_service.dart';

enum AuthStatus { initial, authenticated, unauthenticated, loading }

enum SocialAuthStatus { success, needsLinking, unconfigured, canceled, error }

class SocialAuthResult {
  final SocialAuthStatus status;
  final String? message;
  final Map<String, dynamic>? linkData;
  final UserModel? user;

  const SocialAuthResult({
    required this.status,
    this.message,
    this.linkData,
    this.user,
  });
}

class LogoutResult {
  final bool serverLogoutConfirmed;
  final bool localCleanupComplete;

  const LogoutResult({
    required this.serverLogoutConfirmed,
    required this.localCleanupComplete,
  });

  String? get noticeMessage {
    if (!localCleanupComplete) {
      return 'You are signed out, but some local session data could not be cleared. Please restart the app.';
    }
    if (!serverLogoutConfirmed) {
      return 'You are signed out on this device, but the server could not confirm logout. Sign in again when you have a connection.';
    }
    return null;
  }
}

/// Manages authentication state for both Customer and Driver roles.
///
/// On logout               → stops all Realtime subscriptions.
class AuthProvider extends ChangeNotifier {
  final AuthRepository _repo;
  final RealtimeService _realtime;

  AuthProvider(this._repo, this._realtime);

  AuthStatus _status = AuthStatus.initial;
  UserModel? _user;
  String? _error;
  String? _socialLoadingProvider;
  Future<LogoutResult>? _logoutInProgress;

  AuthStatus get status => _status;
  UserModel? get user => _user;
  String? get error => _error;
  String? get socialLoadingProvider => _socialLoadingProvider;
  bool get isSocialLoading => _socialLoadingProvider != null;
  bool get isAuthenticated => _status == AuthStatus.authenticated;
  bool get isLoading => _status == AuthStatus.loading || isSocialLoading;

  void _onLoggedOut() => _realtime.unsubscribeAll();

  // ================================================================
  // RESTORE SESSION
  // ================================================================
  Future<void> restoreSession() async {
    _status = AuthStatus.loading;
    notifyListeners();
    if (!await _repo.hasStoredToken()) {
      await _repo.tokenStorage.clearOfflineUser();
      _repo.api.setOfflineOwner(null);
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    try {
      final data = await _repo.me();
      _user = UserModel.fromMap(data);
      _repo.api.setOfflineOwner(_user!.id);
      try {
        await _repo.tokenStorage.saveOfflineUser(_user!.toMap());
      } catch (_) {}
      _status = AuthStatus.authenticated;
    } catch (error) {
      final isNetworkFailure =
          error is ApiException &&
          (error.statusCode == null || error.statusCode! >= 500);
      final offlineUser = isNetworkFailure
          ? await _repo.tokenStorage.readOfflineUser()
          : null;
      if (offlineUser != null && offlineUser['id'] != null) {
        _user = UserModel.fromMap(offlineUser);
        _repo.api.setOfflineOwner(_user!.id);
        _status = AuthStatus.authenticated;
        notifyListeners();
        return;
      }
      // A stale token must never leave the preceding user's identity in
      // memory while the storefront is rendered for a guest.
      await _repo.clearStoredSession();
      await _repo.api.clearCachedData();
      _repo.api.setOfflineOwner(null);
      await _repo.tokenStorage.clearOfflineUser();
      _onLoggedOut();
      _user = null;
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  // ================================================================
  // LOGIN
  // ================================================================
  Future<bool> login(String email, String password) async {
    _status = AuthStatus.loading;
    _error = null;
    notifyListeners();
    try {
      final data = await _repo.login(email, password);
      _user = UserModel.fromMap(data['user'] ?? data);
      _repo.api.setOfflineOwner(_user!.id);
      try {
        await _repo.tokenStorage.saveOfflineUser(_user!.toMap());
      } catch (_) {}
      _status = AuthStatus.authenticated;
      notifyListeners();
      // Register FCM token silently — errors are swallowed
      try {
        await _repo.registerFcmToken();
      } catch (_) {}
      return true;
    } catch (e) {
      _error = _formatError(e);
      _repo.api.setOfflineOwner(null);
      await _repo.tokenStorage.clearOfflineUser();
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  // ================================================================
  // REGISTER
  // ================================================================
  Future<bool> register(Map<String, dynamic> payload) async {
    _status = AuthStatus.loading;
    _error = null;
    notifyListeners();
    try {
      await _repo.register(payload);
      _user = null;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return true;
    } catch (e) {
      _error = _formatError(e);
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  String _formatError(dynamic e) {
    final msg = e.toString().toLowerCase();
    if (msg.contains('invalid credentials') || msg.contains('unauthorized')) {
      return 'Invalid username/email or password.';
    }
    if (msg.contains('email has already been taken') ||
        (msg.contains('duplicate') && msg.contains('email'))) {
      return 'Email is already registered.';
    }
    if (msg.contains('username has already been taken') ||
        (msg.contains('duplicate') && msg.contains('username'))) {
      return 'Username is already in use.';
    }
    if (msg.contains('cannot reach the server') ||
        msg.contains('no internet') ||
        msg.contains('socketexception') ||
        msg.contains('failed host lookup')) {
      return 'Unable to reach the server. Verify that the API is running and that this device can access its network address.';
    }
    if (msg.contains('timed out')) {
      return 'The server took too long to respond. Please try again.';
    }
    if (msg.contains('inactive')) {
      return 'Your account is inactive. Please contact the administrator.';
    }
    if (e is ApiException) {
      return e.message;
    }
    return 'Something went wrong. Please try again.';
  }

  // ================================================================
  // SOCIAL AUTHENTICATION (GOOGLE & FACEBOOK)
  // ================================================================

  /// Start the official OAuth redirect flow for Google or Facebook.
  Future<SocialAuthResult> startSocialAuth(String provider) async {
    _socialLoadingProvider = provider;
    _error = null;
    notifyListeners();

    try {
      final redirectUrl = await _repo.getSocialRedirectUrl(provider);
      if (redirectUrl != null && redirectUrl.isNotEmpty) {
        final uri = Uri.parse(redirectUrl);
        final launched = await launchUrl(
          uri,
          mode: LaunchMode.externalApplication,
        );
        if (!launched) {
          _socialLoadingProvider = null;
          _error = 'Could not open browser for $provider sign in.';
          notifyListeners();
          return SocialAuthResult(
            status: SocialAuthStatus.error,
            message: _error,
          );
        }
        _socialLoadingProvider = null;
        notifyListeners();
        return const SocialAuthResult(status: SocialAuthStatus.success);
      }

      _socialLoadingProvider = null;
      notifyListeners();
      return SocialAuthResult(
        status: SocialAuthStatus.error,
        message: 'Could not generate $provider authorization URL.',
      );
    } catch (e) {
      _socialLoadingProvider = null;
      notifyListeners();

      if (e is ApiException) {
        if (e.statusCode == 503 ||
            e.message.toLowerCase().contains('not configured')) {
          return SocialAuthResult(
            status: SocialAuthStatus.unconfigured,
            message: e.message,
          );
        }
        if (e.statusCode == 409 &&
            e.errors != null &&
            e.errors!['needs_linking'] == true) {
          return SocialAuthResult(
            status: SocialAuthStatus.needsLinking,
            message: e.message,
            linkData: e.errors,
          );
        }
        return SocialAuthResult(
          status: SocialAuthStatus.error,
          message: e.message,
        );
      }

      return SocialAuthResult(
        status: SocialAuthStatus.error,
        message: _formatError(e),
      );
    }
  }

  /// Verify and sign in using a social OAuth token or credentials.
  Future<SocialAuthResult> loginWithSocialToken(
    String provider, {
    String? token,
    Map<String, dynamic>? mockUser,
  }) async {
    _socialLoadingProvider = provider;
    _error = null;
    notifyListeners();

    try {
      final data = await _repo.loginWithSocialToken(
        provider,
        token: token,
        mockUser: mockUser,
      );

      _user = UserModel.fromMap(data['user'] ?? data);
      _repo.api.setOfflineOwner(_user!.id);
      try {
        await _repo.tokenStorage.saveOfflineUser(_user!.toMap());
      } catch (_) {}

      _status = AuthStatus.authenticated;
      _socialLoadingProvider = null;
      notifyListeners();

      try {
        await _repo.registerFcmToken();
      } catch (_) {}

      return SocialAuthResult(status: SocialAuthStatus.success, user: _user);
    } catch (e) {
      _socialLoadingProvider = null;
      notifyListeners();

      if (e is ApiException) {
        if (e.statusCode == 503 ||
            e.message.toLowerCase().contains('not configured')) {
          return SocialAuthResult(
            status: SocialAuthStatus.unconfigured,
            message: e.message,
          );
        }
        if (e.statusCode == 409 &&
            e.errors != null &&
            e.errors!['needs_linking'] == true) {
          return SocialAuthResult(
            status: SocialAuthStatus.needsLinking,
            message: e.message,
            linkData: e.errors,
          );
        }
        return SocialAuthResult(
          status: SocialAuthStatus.error,
          message: e.message,
        );
      }

      return SocialAuthResult(
        status: SocialAuthStatus.error,
        message: _formatError(e),
      );
    }
  }

  Future<SocialAuthResult> completeMobileSocialAuth(String code) async {
    _socialLoadingProvider = 'social';
    _error = null;
    notifyListeners();

    try {
      final data = await _repo.exchangeSocialAuthCode(code);
      final user = UserModel.fromMap(data['user'] as Map<String, dynamic>);
      if (user.id.isEmpty || !user.isCustomer) {
        throw ApiException(
          'This account is not permitted to sign in to the customer app.',
          statusCode: 403,
        );
      }

      _user = user;
      _repo.api.setOfflineOwner(user.id);
      try {
        await _repo.tokenStorage.saveOfflineUser(user.toMap());
      } catch (_) {}
      _status = AuthStatus.authenticated;
      _socialLoadingProvider = null;
      notifyListeners();

      try {
        await _repo.registerFcmToken();
      } catch (_) {}

      return SocialAuthResult(status: SocialAuthStatus.success, user: user);
    } catch (e) {
      _socialLoadingProvider = null;
      _error = _formatError(e);
      notifyListeners();
      return SocialAuthResult(status: SocialAuthStatus.error, message: _error);
    }
  }

  /// Complete verified account linking when email matches an existing user.
  Future<bool> verifyAndLinkAccount(String linkToken, String password) async {
    _status = AuthStatus.loading;
    _error = null;
    notifyListeners();

    try {
      final data = await _repo.linkSocialAccount(
        linkToken: linkToken,
        password: password,
      );

      _user = UserModel.fromMap(data['user'] ?? data);
      _repo.api.setOfflineOwner(_user!.id);
      try {
        await _repo.tokenStorage.saveOfflineUser(_user!.toMap());
      } catch (_) {}

      _status = AuthStatus.authenticated;
      notifyListeners();

      try {
        await _repo.registerFcmToken();
      } catch (_) {}

      return true;
    } catch (e) {
      _error = _formatError(e);
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  // ================================================================
  // LOGOUT
  // ================================================================
  Future<LogoutResult> logout() {
    final inProgress = _logoutInProgress;
    if (inProgress != null) return inProgress;

    final logout = _performLogout();
    _logoutInProgress = logout;
    return logout.whenComplete(() {
      if (identical(_logoutInProgress, logout)) _logoutInProgress = null;
    });
  }

  Future<LogoutResult> _performLogout() async {
    var serverLogoutConfirmed = false;
    try {
      await _repo.logout();
      serverLogoutConfirmed = true;
    } catch (error) {
      debugPrint('[AuthProvider] Server logout could not be confirmed: $error');
    }

    final localCleanupComplete = await _clearLocalSession();
    return LogoutResult(
      serverLogoutConfirmed: serverLogoutConfirmed,
      localCleanupComplete: localCleanupComplete,
    );
  }

  /// Clears local state after logout or when the API rejects the token.
  Future<bool> _clearLocalSession() async {
    var complete = true;
    try {
      await _repo.api.clearCachedData();
    } catch (error) {
      complete = false;
      debugPrint('[AuthProvider] Could not clear cached API data: $error');
    }
    _user = null;
    _repo.api.setOfflineOwner(null);
    try {
      await _repo.clearStoredSession();
    } catch (error) {
      complete = false;
      debugPrint(
        '[AuthProvider] Could not clear the stored auth token: $error',
      );
    }
    try {
      await _repo.tokenStorage.clearOfflineUser();
    } catch (error) {
      complete = false;
      debugPrint(
        '[AuthProvider] Could not clear the offline user snapshot: $error',
      );
    }
    try {
      _onLoggedOut();
    } catch (error) {
      complete = false;
      debugPrint(
        '[AuthProvider] Could not stop realtime subscriptions: $error',
      );
    }
    _status = AuthStatus.unauthenticated;
    _error = null;
    notifyListeners();
    return complete;
  }

  /// Clears local state after the API has already rejected the token.
  Future<bool> expireSession() => _clearLocalSession();

  // ================================================================
  // UPDATE USER
  // ================================================================
  void updateUser(UserModel updated) {
    _user = updated;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
