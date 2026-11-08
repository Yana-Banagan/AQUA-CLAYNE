import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

class Env {
  // Default for a physical device connected to this development computer's
  // Wi-Fi. Override this whenever the local network address changes.
  // Android emulator: --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
  static const String _configuredApiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
  );

  static String get apiBaseUrl {
    final baseUrl = _configuredApiBaseUrl.isNotEmpty
        ? _configuredApiBaseUrl
        : Platform.isAndroid
        // Development PC Wi-Fi address. Override with
        // --dart-define=API_BASE_URL=... if this address changes.
        ? 'http://192.168.1.9:8000/api/v1'
        : 'http://127.0.0.1:8000/api/v1';

    if (kReleaseMode && !baseUrl.startsWith('https://')) {
      throw StateError('Release builds require an HTTPS API_BASE_URL.');
    }
    return baseUrl;
  }

  static const String supabaseUrl = 'https://evnhwmmwsnxizcoejqhz.supabase.co';

  static const String supabaseAnonKey =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2bmh3bW13c254aXpjb2VqcWh6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODczMjQxNjYsImV4cCI6MjEwMjkwMDE2Nn0.-IDDlk5-cEvzKuyWC0JAQ5oHtUS5_ue0F0Hcmycwk7k';
}
