import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/config/env.dart';
import 'package:mobile/core/api/api_client.dart';
import 'package:mobile/core/services/token_storage.dart';

void main() {
  group('Env Configuration Tests', () {
    test('apiBaseUrl defaults to live production Railway API', () {
      expect(
        Env.apiBaseUrl,
        equals('https://aqua-clayne-production.up.railway.app/api/v1'),
      );
      expect(Env.apiBaseUrl, isNot(contains('192.168.1.108')));
      expect(Env.apiBaseUrl, isNot(contains('192.168.1.9')));
    });

    test('productionApiBaseUrl is valid HTTPS Railway endpoint', () {
      expect(
        Env.productionApiBaseUrl,
        equals('https://aqua-clayne-production.up.railway.app/api/v1'),
      );
      expect(Env.productionApiBaseUrl.startsWith('https://'), isTrue);
    });

    test('localDevWifiIp reflects current development computer Wi-Fi', () {
      expect(Env.localDevWifiIp, equals('192.168.1.104'));
    });
  });

  group('ApiClient Error Handling Tests', () {
    test('ApiClient contains user-friendly connection and timeout errors', () {
      expect(
        ApiClient.userFriendlyConnectionError,
        equals('Unable to connect to Aqua Clayne. Please check your internet connection and try again.'),
      );
      expect(
        ApiClient.userFriendlyTimeoutError,
        equals('Connection timed out. Please check your internet connection and try again.'),
      );
      expect(
        ApiClient.userFriendlyUploadTimeoutError,
        equals('Upload timed out. Please check your internet connection and try again.'),
      );
    });

    test('ApiClient connects to Env.apiBaseUrl by default', () {
      final client = ApiClient(TokenStorage());
      expect(client.baseUrl, equals(Env.apiBaseUrl));
    });
  });
}

