import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/env.dart';
import '../services/token_storage.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, {this.statusCode});

  @override
  String toString() {
    return message;
  }
}

class ApiClient {
  final String baseUrl;
  final TokenStorage tokenStorage;

  ApiClient(this.tokenStorage) : baseUrl = Env.apiBaseUrl;

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
          .timeout(const Duration(seconds: 10));

      return _handle(response);
    } on TimeoutException {
      throw ApiException('Request timed out. Check your connection.');
    } on SocketException {
      throw ApiException('No internet connection.');
    }
  }

  // ================================================================
  // GET
  // ================================================================

  Future<dynamic> get(String path, {bool auth = true}) async {
    try {
      final response = await http
          .get(Uri.parse('$baseUrl$path'), headers: await _headers(auth: auth))
          .timeout(const Duration(seconds: 10));

      return _handle(response);
    } on TimeoutException {
      throw ApiException('Request timed out. Check your connection.');
    } on SocketException {
      throw ApiException('No internet connection.');
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
          .timeout(const Duration(seconds: 10));

      return _handle(response);
    } on TimeoutException {
      throw ApiException('Request timed out. Check your connection.');
    } on SocketException {
      throw ApiException('No internet connection.');
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
          .timeout(const Duration(seconds: 10));

      return _handle(response);
    } on TimeoutException {
      throw ApiException('Request timed out. Check your connection.');
    } on SocketException {
      throw ApiException('No internet connection.');
    }
  }

  // ================================================================
  // RESPONSE HANDLER
  // ================================================================

  dynamic _handle(http.Response response) {
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

    String message = 'Something went wrong.';

    if (body is Map<String, dynamic>) {
      message = body['message']?.toString() ?? 'Something went wrong.';
    }

    throw ApiException(message, statusCode: response.statusCode);
  }
}
