import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/env.dart';
import '../services/token_storage.dart';
import '../services/offline_cache.dart';

class _CachedGet {
  final dynamic value;
  final DateTime loadedAt;

  const _CachedGet(this.value, this.loadedAt);
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException(this.message, {this.statusCode, this.errors});

  @override
  String toString() {
    return message;
  }
}

/// Returned only when a safe, repeatable write was durably stored for later sync.
class OfflineQueuedResult {
  final String path;
  const OfflineQueuedResult(this.path);
}

class OfflineSyncResult {
  final int synced;
  final int pending;
  final String? error;
  const OfflineSyncResult({
    required this.synced,
    required this.pending,
    this.error,
  });
}

class ApiClient {
  // Two seconds is a UI responsiveness target, not a safe network timeout:
  // mobile connections and a cold Laravel server can legitimately take longer.
  // Keep requests alive long enough to complete instead of showing a false
  // “server took too long” error.
  static const Duration _requestTimeout = Duration(seconds: 30);
  static const Duration _uploadTimeout = Duration(seconds: 45);
  // Long enough to cover a dashboard tab transition, but short enough that
  // status changes still appear promptly if a realtime signal is missed.
  static const Duration _dashboardCacheLifetime = Duration(seconds: 2);

  static const String userFriendlyConnectionError =
      'Unable to connect to Aqua Clayne. Please check your internet connection and try again.';
  static const String userFriendlyTimeoutError =
      'Connection timed out. Please check your internet connection and try again.';
  static const String userFriendlyUploadTimeoutError =
      'Upload timed out. Please check your internet connection and try again.';

  final String baseUrl;
  final TokenStorage tokenStorage;
  void Function()? onSessionExpired;
  final Map<String, _CachedGet> _getCache = <String, _CachedGet>{};
  final Map<String, Future<dynamic>> _pendingGets = <String, Future<dynamic>>{};
  final OfflineCache _offlineCache = OfflineCache();
  String? _offlineOwnerId;
  bool _syncInProgress = false;
  void Function()? onOutboxChanged;

  ApiClient(this.tokenStorage) : baseUrl = Env.apiBaseUrl;

  void setOfflineOwner(String? userId) {
    userId = userId?.trim();
    if (userId?.isEmpty == true) userId = null;
    if (_offlineOwnerId == userId) return;
    _offlineOwnerId = userId;
    _getCache.clear();
    _pendingGets.clear();
  }

  // ================================================================
  // MULTIPART POST
  // ================================================================

  Future<dynamic> postMultipart(String path, Map<String, File> files) async {
    final uri = Uri.parse('$baseUrl$path');

    final request = http.MultipartRequest('POST', uri);

    final headers = await _headers(auth: true);

    // MultipartRequest creates its own multipart Content-Type.
    headers.remove('Content-Type');

    request.headers.addAll(headers);

    for (final entry in files.entries) {
      request.files.add(
        await http.MultipartFile.fromPath(entry.key, entry.value.path),
      );
    }

    try {
      final streamed = await request.send().timeout(_uploadTimeout);

      final response = await http.Response.fromStream(streamed);

      return _handle(response, path: path, auth: true);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] Upload TimeoutException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyUploadTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] Upload SocketException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] Upload ClientException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // HEADERS
  // ================================================================

  Future<Map<String, String>> _headers({bool auth = true}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    if (auth) {
      final token = await tokenStorage.readToken();

      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }

    return headers;
  }

  // ================================================================
  // POST
  // ================================================================

  Future<dynamic> post(
    String path,
    Map<String, dynamic> body, {
    bool auth = false,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$path'),
            headers: await _headers(auth: auth),
            body: jsonEncode(body),
          )
          .timeout(_requestTimeout);

      return _handle(response, path: path, auth: auth);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] POST TimeoutException on $path ($baseUrl): $e');
      if (await _queueIfSafe('POST', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] POST SocketException on $path ($baseUrl): $e');
      if (await _queueIfSafe('POST', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] POST ClientException on $path ($baseUrl): $e');
      if (await _queueIfSafe('POST', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // GET
  // ================================================================

  Future<dynamic> get(String path, {bool auth = true}) async {
    // Scope entries to the signed-in user, then reuse a recent response or an
    // already-running request. Dashboard tabs frequently request the same
    // resources as each other on web.
    final headers = await _headers(auth: auth);
    final cacheKey = '$auth:${headers['Authorization'] ?? ''}:$path';
    final cached = _getCache[cacheKey];
    if (cached != null &&
        DateTime.now().difference(cached.loadedAt) < _dashboardCacheLifetime) {
      return _applyPendingWrites(path, cached.value);
    }

    final pending = _pendingGets[cacheKey];
    if (pending != null) return pending;

    final request = _performGet(path, auth: auth, headers: headers);
    _pendingGets[cacheKey] = request;
    try {
      final value = await request;
      _getCache[cacheKey] = _CachedGet(value, DateTime.now());
      if (_isCacheable(path)) {
        await _offlineCache.write(
          _offlineKey(path),
          value,
          ttl: _offlineCacheLifetime(path),
        );
      }
      return value;
    } on ApiException {
      var cachedOffline = await _offlineCache.read(
        _offlineKey(path),
        allowExpired: true,
      );
      cachedOffline ??= await _offlineCache.read(
        _legacyOfflineKey(path, headers),
        allowExpired: true,
      );
      if (cachedOffline != null)
        return _applyPendingWrites(path, cachedOffline);
      rethrow;
    } finally {
      _pendingGets.remove(cacheKey);
    }
  }

  String _pathOnly(String path) => Uri.tryParse(path)?.path ?? path;

  bool _isCacheable(String path) {
    final normalized = _pathOnly(path);
    if (const {
      '/products',
      '/faqs',
      '/customer/addresses',
      '/customer/orders',
      '/customer/notifications',
      '/driver/notifications',
      '/notifications',
      '/driver/deliveries',
      '/driver/deliveries/history',
      '/driver/routes',
      '/driver/pickups',
    }.contains(normalized)) {
      return true;
    }
    return RegExp(r'^/customer/orders/[^/]+$').hasMatch(normalized) ||
        RegExp(r'^/driver/deliveries/[^/]+$').hasMatch(normalized) ||
        RegExp(r'^/driver/routes/[^/]+$').hasMatch(normalized) ||
        RegExp(r'^/driver/pickups/[^/]+$').hasMatch(normalized);
  }

  Duration _offlineCacheLifetime(String path) =>
      const {'/products', '/faqs'}.contains(_pathOnly(path))
      ? const Duration(hours: 24)
      : const Duration(hours: 4);

  /// Clear all locally persisted read data on logout/session expiry. This
  /// prevents a shared device from exposing a prior customer's records.
  Future<void> clearCachedData() async {
    _getCache.clear();
    _pendingGets.clear();
    await _offlineCache.clear();
  }

  String _offlineKey(String path) {
    return '${_offlineOwnerId ?? 'guest'}_${base64Url.encode(utf8.encode(path))}';
  }

  String _legacyOfflineKey(String path, Map<String, String> headers) =>
      '${headers['Authorization']?.hashCode ?? 'guest'}_${path.hashCode}';

  Future<bool> _queueIfSafe(
    String method,
    String path,
    Map<String, dynamic> payload, {
    required bool authenticated,
  }) async {
    if (!authenticated ||
        _offlineOwnerId == null ||
        !_isSafeToQueue(method, path))
      return false;
    final coalesceKey = _coalesceKey(method, path);
    await _offlineCache.enqueueMutation(
      ownerId: _offlineOwnerId!,
      method: method,
      path: path,
      payload: payload,
      coalesceKey: coalesceKey,
      replacePayload: path.endsWith('/location'),
    );
    onOutboxChanged?.call();
    return true;
  }

  bool _isSafeToQueue(String method, String path) {
    if (method == 'PATCH' &&
        RegExp(r'^/customer/addresses/[A-Za-z0-9-]+$').hasMatch(path))
      return true;
    if (method == 'POST' &&
        RegExp(r'^/customer/addresses/[A-Za-z0-9-]+/set-default$')
            .hasMatch(path))
      return true;
    if (method == 'PATCH' &&
        RegExp(r'^/customer/notifications/[A-Za-z0-9-]+/read$').hasMatch(path))
      return true;
    if (method == 'POST' && path == '/customer/notifications/read-all')
      return true;
    if (method == 'POST' && path == '/driver/notifications/read-all')
      return true;
    if (method == 'PATCH' &&
        RegExp(r'^/driver/notifications/[A-Za-z0-9-]+/read$').hasMatch(path))
      return true;
    if (method == 'POST' &&
        RegExp(r'^/driver/deliveries/[A-Za-z0-9-]+/location$').hasMatch(path))
      return true;
    return false;
  }

  String? _coalesceKey(String method, String path) {
    if (path.endsWith('/location')) return 'location:$path';
    if (path.endsWith('/read-all')) return 'read-all:$path';
    return '$method:$path';
  }

  Future<dynamic> _applyPendingWrites(String path, dynamic value) async {
    final owner = _offlineOwnerId;
    if (owner == null || value is! List) return value;
    final rows = (await _offlineCache.pendingMutations(owner)).where((row) {
      final queuedPath = row['path'] as String;
      return path == '/customer/addresses' &&
              (queuedPath.startsWith('/customer/addresses/') ||
                  queuedPath.endsWith('/set-default')) ||
          path == '/customer/notifications' &&
              queuedPath.startsWith('/customer/notifications/') ||
          (path == '/notifications' || path == '/driver/notifications') &&
              queuedPath.startsWith('/driver/notifications/');
    });
    var result = value
        .map((item) => item is Map ? Map<String, dynamic>.from(item) : item)
        .toList();
    for (final row in rows) {
      final queuedPath = row['path'] as String;
      final payload = row['payload'] as Map<String, dynamic>;
      if (queuedPath.endsWith('/set-default')) {
        final selectedId = queuedPath.split('/')[3];
        result = result
            .map(
              (item) => item is Map<String, dynamic>
                  ? {
                      ...item,
                      'is_default': item['id']?.toString() == selectedId,
                    }
                  : item,
            )
            .toList();
      } else if (queuedPath.endsWith('/read-all')) {
        result = result
            .map(
              (item) => item is Map<String, dynamic>
                  ? {
                      ...item,
                      'is_read': true,
                      'read_at': DateTime.now().toIso8601String(),
                    }
                  : item,
            )
            .toList();
      } else if (queuedPath.endsWith('/read')) {
        final id = queuedPath.split('/')[3];
        result = result
            .map(
              (item) =>
                  item is Map<String, dynamic> && item['id']?.toString() == id
                  ? {
                      ...item,
                      'is_read': true,
                      'read_at': DateTime.now().toIso8601String(),
                    }
                  : item,
            )
            .toList();
      } else if (queuedPath.startsWith('/customer/addresses/')) {
        final id = queuedPath.split('/')[3];
        result = result
            .map(
              (item) =>
                  item is Map<String, dynamic> && item['id']?.toString() == id
                  ? {...item, ...payload}
                  : item,
            )
            .toList();
      }
    }
    return result;
  }

  Future<int> pendingMutationCount() async {
    final owner = _offlineOwnerId;
    return owner == null ? 0 : _offlineCache.pendingMutationCount(owner);
  }

  Future<OfflineSyncResult> syncPendingMutations() async {
    final owner = _offlineOwnerId;
    if (owner == null || _syncInProgress)
      return OfflineSyncResult(
        synced: 0,
        pending: await pendingMutationCount(),
      );
    _syncInProgress = true;
    var synced = 0;
    String? error;
    try {
      final mutations = await _offlineCache.pendingMutations(owner);
      for (final mutation in mutations) {
        final id = mutation['id'] as int;
        final method = mutation['method'] as String;
        final path = mutation['path'] as String;
        final payload = mutation['payload'] as Map<String, dynamic>;
        try {
          final headers = await _headers(auth: true);
          final uri = Uri.parse('$baseUrl$path');
          late final http.Response response;
          switch (method) {
            case 'POST':
              response = await http
                  .post(uri, headers: headers, body: jsonEncode(payload))
                  .timeout(_requestTimeout);
              break;
            case 'PATCH':
              response = await http
                  .patch(uri, headers: headers, body: jsonEncode(payload))
                  .timeout(_requestTimeout);
              break;
            default:
              throw ApiException('Unsupported queued request: $method');
          }
          _handle(response, path: path, auth: true);
          await _offlineCache.removeMutation(id);
          synced++;
        } on TimeoutException {
          error = 'Sync paused: the server did not respond.';
          await _offlineCache.recordMutationFailure(id, error);
          break;
        } on SocketException {
          error = 'Sync paused: the server cannot be reached.';
          await _offlineCache.recordMutationFailure(id, error);
          break;
        } on http.ClientException {
          error = 'Sync paused: the server cannot be reached.';
          await _offlineCache.recordMutationFailure(id, error);
          break;
        } on ApiException catch (e) {
          error = e.message;
          await _offlineCache.recordMutationFailure(id, e.message);
          break;
        }
      }
    } finally {
      _syncInProgress = false;
    }
    final pending = await pendingMutationCount();
    return OfflineSyncResult(synced: synced, pending: pending, error: error);
  }

  Future<dynamic> _performGet(
    String path, {
    required bool auth,
    required Map<String, String> headers,
  }) async {
    try {
      final response = await http
          .get(Uri.parse('$baseUrl$path'), headers: headers)
          .timeout(_requestTimeout);

      return _handle(response, path: path, auth: auth);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] GET TimeoutException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] GET SocketException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] GET ClientException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // PUT
  // ================================================================

  Future<dynamic> put(
    String path,
    Map<String, dynamic> body, {
    bool auth = true,
  }) async {
    try {
      final response = await http
          .put(
            Uri.parse('$baseUrl$path'),
            headers: await _headers(auth: auth),
            body: jsonEncode(body),
          )
          .timeout(_requestTimeout);

      return _handle(response, path: path, auth: auth);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] PUT TimeoutException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] PUT SocketException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] PUT ClientException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // PATCH
  // ================================================================

  Future<dynamic> patch(
    String path,
    Map<String, dynamic> body, {
    bool auth = true,
  }) async {
    try {
      final response = await http
          .patch(
            Uri.parse('$baseUrl$path'),
            headers: await _headers(auth: auth),
            body: jsonEncode(body),
          )
          .timeout(_requestTimeout);

      return _handle(response, path: path, auth: auth);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] PATCH TimeoutException on $path ($baseUrl): $e');
      if (await _queueIfSafe('PATCH', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] PATCH SocketException on $path ($baseUrl): $e');
      if (await _queueIfSafe('PATCH', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] PATCH ClientException on $path ($baseUrl): $e');
      if (await _queueIfSafe('PATCH', path, body, authenticated: auth))
        return OfflineQueuedResult(path);
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // DELETE
  // ================================================================

  Future<dynamic> delete(String path, {bool auth = true}) async {
    try {
      final response = await http
          .delete(
            Uri.parse('$baseUrl$path'),
            headers: await _headers(auth: auth),
          )
          .timeout(_requestTimeout);

      return _handle(response, path: path, auth: auth);
    } on TimeoutException catch (e) {
      debugPrint('[ApiClient] DELETE TimeoutException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyTimeoutError);
    } on SocketException catch (e) {
      debugPrint('[ApiClient] DELETE SocketException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    } on http.ClientException catch (e) {
      debugPrint('[ApiClient] DELETE ClientException on $path ($baseUrl): $e');
      throw ApiException(userFriendlyConnectionError);
    }
  }

  // ================================================================
  // RESPONSE HANDLER
  // ================================================================

  dynamic _handle(
    http.Response response, {
    String path = '',
    bool auth = false,
  }) {
    dynamic body;

    if (response.body.isNotEmpty) {
      try {
        body = jsonDecode(response.body);
      } catch (_) {
        body = {'message': response.body};
      }
    } else {
      body = {};
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (body is Map<String, dynamic>) {
        return body['data'] ?? body;
      }

      return body;
    }

    // Logout handles its own rejected token so it cannot race session expiry.
    final normalizedPath = _pathOnly(path);
    if (response.statusCode == 401 &&
        auth &&
        normalizedPath != '/auth/login' &&
        normalizedPath != '/auth/logout') {
      onSessionExpired?.call();
      throw ApiException(
        'Your session has expired. Please log in again.',
        statusCode: 401,
      );
    }

    String message = 'Something went wrong.';
    Map<String, dynamic>? errors;

    if (body is Map<String, dynamic>) {
      message = body['message']?.toString() ?? 'Something went wrong.';
      if (body['errors'] is Map<String, dynamic>) {
        errors = body['errors'] as Map<String, dynamic>;
        if (errors.isNotEmpty) {
          final firstEntry = errors.values.first;
          if (firstEntry is List && firstEntry.isNotEmpty) {
            message = firstEntry.first.toString();
          }
        }
      }
    }

    throw ApiException(
      message,
      statusCode: response.statusCode,
      errors:
          errors ??
          (body is Map<String, dynamic> && body['data'] is Map<String, dynamic>
              ? body['data'] as Map<String, dynamic>
              : null),
    );
  }
}
