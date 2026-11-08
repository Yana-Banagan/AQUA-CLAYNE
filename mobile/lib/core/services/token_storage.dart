import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStorage {
  final _storage = const FlutterSecureStorage();

  static const _key = 'auth_token';
  static const _offlineUserKey = 'offline_user_snapshot';

  Future<void> saveToken(String token) {
    return _storage.write(key: _key, value: token);
  }

  Future<String?> readToken() {
    return _storage.read(key: _key);
  }

  Future<void> deleteToken() {
    return _storage.delete(key: _key);
  }

  Future<void> saveOfflineUser(Map<String, dynamic> user) =>
      _storage.write(key: _offlineUserKey, value: jsonEncode(user));

  Future<Map<String, dynamic>?> readOfflineUser() async {
    final value = await _storage.read(key: _offlineUserKey);
    if (value == null) return null;
    try {
      final decoded = jsonDecode(value);
      return decoded is Map<String, dynamic> ? decoded : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> clearOfflineUser() => _storage.delete(key: _offlineUserKey);
}
