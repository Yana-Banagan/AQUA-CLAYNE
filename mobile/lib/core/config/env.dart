import 'dart:io' show Platform;
import 'package:flutter/foundation.dart';

/// Centralized environment and API configuration for Aqua Clayne.
///
/// Supports:
/// - Production: Live backend hosted on Railway (`https://aqua-clayne-production.up.railway.app/api/v1`)
/// - Local Development: `--dart-define=APP_ENV=local` or `--dart-define=API_BASE_URL=...`
/// - Android Physical Device over Wi-Fi: `http://192.168.1.104:8000/api/v1`
/// - Android Emulator: `http://10.0.2.2:8000/api/v1`
class Env {
  /// The official live production Laravel API URL hosted on Railway.
  static const String productionApiBaseUrl =
      'https://aqua-clayne-production.up.railway.app/api/v1';

  /// Explicit API Base URL override via `--dart-define=API_BASE_URL=...`
  static const String _configuredApiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
  );

  /// Target environment via `--dart-define=APP_ENV=...` or `--dart-define=ENVIRONMENT=...`
  /// Supported: 'production' (default) | 'local' | 'development'
  static const String _configuredEnv = String.fromEnvironment(
    'APP_ENV',
    defaultValue: String.fromEnvironment('ENVIRONMENT'),
  );

  /// Current development computer local Wi-Fi IPv4 address.
  /// (Verified from Windows ipconfig for physical Android devices on same subnet).
  static const String localDevWifiIp = '192.168.1.104';

  /// Centralized API base URL used across all screens and repositories.
  static String get apiBaseUrl {
    // 1. Explicit CLI argument takes top priority
    if (_configuredApiBaseUrl.isNotEmpty) {
      final url = _configuredApiBaseUrl.trim();
      if (kReleaseMode && !url.startsWith('https://')) {
        throw StateError(
          'Release builds require an HTTPS API_BASE_URL. Configured: $url',
        );
      }
      return url;
    }

    final env = _configuredEnv.toLowerCase().trim();
    final isExplicitLocal = env == 'local' || env == 'development' || env == 'dev';

    // 2. Explicit local development mode
    if (!kReleaseMode && isExplicitLocal) {
      if (!kIsWeb && Platform.isAndroid) {
        const isEmulator = bool.fromEnvironment('EMULATOR', defaultValue: false);
        return isEmulator
            ? 'http://10.0.2.2:8000/api/v1'
            : 'http://$localDevWifiIp:8000/api/v1';
      }
      return 'http://127.0.0.1:8000/api/v1';
    }

    // 3. Production live backend (default for release & standard debug runs)
    return productionApiBaseUrl;
  }

  static const String supabaseUrl = 'https://evnhwmmwsnxizcoejqhz.supabase.co';

  static const String supabaseAnonKey =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2bmh3bW13c254aXpjb2VqcWh6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODczMjQxNjYsImV4cCI6MjEwMjkwMDE2Nn0.-IDDlk5-cEvzKuyWC0JAQ5oHtUS5_ue0F0Hcmycwk7k';
}
