import 'dart:io';

import '../api/api_client.dart';
import '../services/token_storage.dart';

import 'package:firebase_messaging/firebase_messaging.dart';

class AuthRepository {
  final ApiClient api;
  final TokenStorage tokenStorage;

  AuthRepository(this.api, this.tokenStorage);

  Future<bool> hasStoredToken() async {
    final token = await tokenStorage.readToken();
    return token != null && token.isNotEmpty;
  }

  // ================================================================
  // LOGIN — returns the full response map {token, user}
  // ================================================================
  Future<Map<String, dynamic>> login(String email, String password) async {
    final data = await api.post('/auth/login', {
      'email': email,
      'username': email,
      'password': password,
    });

    final token = data['token']?.toString();
    if (token != null && token.isNotEmpty) {
      await tokenStorage.saveToken(token);
    }

    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // REGISTER — returns the full response map {token, user}
  // ================================================================
  Future<Map<String, dynamic>> register(Map<String, dynamic> payload) async {
    final data = await api.post('/auth/register', payload);

    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // ME — returns user map
  // ================================================================
  Future<Map<String, dynamic>> me() async {
    final data = await api.get('/auth/me');
    if (data is Map<String, dynamic>) {
      return data['user'] as Map<String, dynamic>? ?? data;
    }
    return {};
  }

  // ================================================================
  // LOGOUT
  // ================================================================
  Future<void> logout() async {
    try {
      await api.post('/auth/logout', {}, auth: true);
    } finally {
      await tokenStorage.deleteToken();
    }
  }

  /// Removes a token locally after it has already failed validation. This
  /// avoids issuing a second authenticated request while recovering a session.
  Future<void> clearStoredSession() => tokenStorage.deleteToken();

  // ================================================================
  // UPDATE PROFILE
  // ================================================================
  Future<Map<String, dynamic>> updateProfile(
    Map<String, dynamic> payload,
  ) async {
    final data = await api.patch('/auth/profile', payload);
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  Future<Map<String, dynamic>> uploadProfilePhoto(File photo) async {
    final data = await api.postMultipart('/auth/profile/photo', {
      'photo': photo,
    });
    if (data is Map<String, dynamic>) return data;
    return {};
  }

  // ================================================================
  // CHANGE PASSWORD
  // ================================================================
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    await api.post('/auth/change-password', {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    }, auth: true);
  }

  Future<void> sendPasswordOtp(String email) async {
    await api.post('/auth/password/otp', {'email': email}, auth: false);
  }

  Future<String> verifyPasswordOtp(String email, String otp) async {
    final data = await api.post('/auth/password/verify-otp', {
      'email': email,
      'otp': otp,
    }, auth: false);
    return data['reset_token']?.toString() ?? '';
  }

  Future<void> resetPassword({
    required String email,
    required String resetToken,
    required String password,
  }) async {
    await api.post('/auth/password/reset', {
      'email': email,
      'reset_token': resetToken,
      'password': password,
      'password_confirmation': password,
    }, auth: false);
  }

  // ================================================================
  // FCM TOKEN
  // ================================================================
  Future<void> registerFcmToken() async {
    final token = await FirebaseMessaging.instance.getToken();
    if (token != null && token.isNotEmpty) {
      await api.post('/auth/fcm-token', {'fcm_token': token}, auth: true);
    }
  }

  // ================================================================
  // SOCIAL AUTHENTICATION (GOOGLE & FACEBOOK)
  // ================================================================

  /// Request the official OAuth authorization URL for Google or Facebook from the backend.
  Future<String?> getSocialRedirectUrl(String provider) async {
    final data = await api.post(
      '/auth/social/$provider/mobile/redirect',
      {},
      auth: false,
    );
    if (data is Map<String, dynamic>) {
      return data['redirect_url']?.toString();
    }
    return null;
  }

  Future<Map<String, dynamic>> exchangeSocialAuthCode(String code) async {
    final data = await api.post('/auth/social/exchange', {
      'code': code,
    }, auth: false);
    if (data is! Map<String, dynamic>) {
      throw ApiException('The sign-in response was invalid.');
    }

    final token = data['token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException(
        'The sign-in response did not contain an access token.',
      );
    }
    if (data['user'] is! Map<String, dynamic>) {
      throw ApiException(
        'The sign-in response did not contain a customer profile.',
      );
    }

    await tokenStorage.saveToken(token);
    return data;
  }

  /// Verify and sign in using a social OAuth token or credentials.
  Future<Map<String, dynamic>> loginWithSocialToken(
    String provider, {
    String? token,
    Map<String, dynamic>? mockUser,
  }) async {
    final payload = <String, dynamic>{};
    if (token != null) payload['access_token'] = token;
    if (mockUser != null) payload['mock_user'] = mockUser;

    final data = await api.post(
      '/auth/social/$provider/token',
      payload,
      auth: false,
    );

    if (data is Map<String, dynamic>) {
      final authToken = data['token']?.toString();
      if (authToken != null && authToken.isNotEmpty) {
        await tokenStorage.saveToken(authToken);
      }
      return data;
    }
    return {};
  }

  /// Complete verified account linking when an email already exists.
  Future<Map<String, dynamic>> linkSocialAccount({
    required String linkToken,
    required String password,
  }) async {
    final data = await api.post('/auth/social/link', {
      'link_token': linkToken,
      'password': password,
    }, auth: false);

    if (data is Map<String, dynamic>) {
      final authToken = data['token']?.toString();
      if (authToken != null && authToken.isNotEmpty) {
        await tokenStorage.saveToken(authToken);
      }
      return data;
    }
    return {};
  }

  /// Link social identity for an already authenticated customer.
  Future<Map<String, dynamic>> linkCurrentAccount(
    String provider, {
    required String token,
  }) async {
    final data = await api.post('/auth/social/$provider/link-current', {
      'access_token': token,
    }, auth: true);

    if (data is Map<String, dynamic>) return data;
    return {};
  }

  /// Unlink social identity for an authenticated customer.
  Future<void> unlinkSocialAccount(String provider) async {
    await api.delete('/auth/social/$provider', auth: true);
  }

  /// List linked social accounts for the authenticated customer.
  Future<Map<String, dynamic>> getLinkedSocialAccounts() async {
    final data = await api.get('/auth/social/accounts', auth: true);
    if (data is Map<String, dynamic>) return data;
    return {};
  }
}
