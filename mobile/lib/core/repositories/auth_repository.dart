import '../api/api_client.dart';
import '../services/token_storage.dart';

import 'package:firebase_messaging/firebase_messaging.dart';

class AuthRepository {
  final ApiClient api;
  final TokenStorage tokenStorage;

  AuthRepository(this.api, this.tokenStorage);

  Future<Map<String, dynamic>> login(String email, String password) async {
    final data = await api.post('/auth/login', {
      'email': email,
      'password': password,
    });

    await tokenStorage.saveToken(data['token']);

    return Map<String, dynamic>.from(data['user']);
  }

  Future<Map<String, dynamic>> register(Map<String, dynamic> payload) async {
    final data = await api.post('/auth/register', payload);

    await tokenStorage.saveToken(data['token']);

    return Map<String, dynamic>.from(data['user']);
  }

  Future<Map<String, dynamic>> me() async {
    final data = await api.get('/auth/me');

    return Map<String, dynamic>.from(data['user']);
  }

  Future<void> logout() async {
    await api.post('/auth/logout', {}, auth: true);

    await tokenStorage.deleteToken();
  }

  Future<void> registerFcmToken() async {
    final token = await FirebaseMessaging.instance.getToken();

    if (token != null && token.isNotEmpty) {
      await api.post('/auth/fcm-token', {'fcm_token': token});
    }
  }

  Future<void> registerFcmTokenValue(String token) async {
    if (token.isEmpty) {
      return;
    }

    await api.post('/auth/fcm-token', {'fcm_token': token});
  }
}
