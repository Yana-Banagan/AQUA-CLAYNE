import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

/// Safely attempts to open an external web URL (like PayMongo checkout)
/// using multiple fallback launch modes:
/// 1. External application (System web browser)
/// 2. Platform default
/// 3. In-app browser view
Future<bool> launchSafeUrl(String url) async {
  final trimmed = url.trim();
  if (trimmed.isEmpty) return false;
  final uri = Uri.tryParse(trimmed);
  if (uri == null) return false;

  // 1. External Application (standard system browser)
  try {
    if (await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      return true;
    }
  } catch (e) {
    debugPrint('launchSafeUrl: externalApplication mode failed: $e');
  }

  // 2. Platform Default
  try {
    if (await launchUrl(uri, mode: LaunchMode.platformDefault)) {
      return true;
    }
  } catch (e) {
    debugPrint('launchSafeUrl: platformDefault mode failed: $e');
  }

  // 3. In-App Browser View
  try {
    if (await launchUrl(uri, mode: LaunchMode.inAppBrowserView)) {
      return true;
    }
  } catch (e) {
    debugPrint('launchSafeUrl: inAppBrowserView mode failed: $e');
  }

  return false;
}
