import 'dart:convert';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../customer/screens/order_detail_screen.dart';
import '../../customer/screens/complaint_detail_screen.dart';
import '../../driver/screens/delivery_detail_screen.dart';

// ====================================================================
// GLOBAL NAVIGATOR KEY
// ====================================================================
// FCM callbacks have no BuildContext — this key lets us navigate from
// anywhere without a context.
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

// ====================================================================
// LOCAL NOTIFICATIONS PLUGIN
// ====================================================================
final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
    FlutterLocalNotificationsPlugin();

// ====================================================================
// PUSH HANDLER
// ====================================================================
class PushHandler {
  // ================================================================
  // ANDROID NOTIFICATION CHANNEL
  // ================================================================
  static const AndroidNotificationChannel channel = AndroidNotificationChannel(
    'aqua_clayne_channel',
    'Aqua Clayne Notifications',
    description: 'Order updates, driver assignments, and delivery alerts.',
    importance: Importance.high,
  );

  // ================================================================
  // INITIALIZE
  // ================================================================
  static Future<void> initialize() async {
    // Android init settings
    const androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    const initSettings = InitializationSettings(android: androidSettings);

    await flutterLocalNotificationsPlugin.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload != null && payload.isNotEmpty) {
          _handlePayload(payload);
        }
      },
    );

    // Create the Android notification channel
    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    // Foreground — app is open
    FirebaseMessaging.onMessage.listen(_handleForeground);

    // Background tap — app was in background, user tapped notification
    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);

    // Terminated tap — app was closed, user tapped notification
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      // Small delay to let the app fully mount before navigating
      await Future.delayed(const Duration(milliseconds: 500));
      _handleTap(initial);
    }
  }

  // ================================================================
  // FOREGROUND MESSAGE — show as local notification
  // ================================================================
  static Future<void> _handleForeground(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    // Build a safe navigation payload — only IDs, no PII
    final payload = jsonEncode({
      'entity_type': message.data['entity_type']?.toString() ?? '',
      'entity_id': message.data['entity_id']?.toString() ?? '',
    });

    await flutterLocalNotificationsPlugin.show(
      message.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'aqua_clayne_channel',
          'Aqua Clayne Notifications',
          channelDescription:
              'Order updates, driver assignments, and delivery alerts.',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      payload: payload,
    );
  }

  // ================================================================
  // TAP ON NOTIFICATION (background / terminated)
  // ================================================================
  static void _handleTap(RemoteMessage message) {
    final payload = jsonEncode({
      'entity_type': message.data['entity_type']?.toString() ?? '',
      'entity_id': message.data['entity_id']?.toString() ?? '',
    });
    _handlePayload(payload);
  }

  // ================================================================
  // NAVIGATE BASED ON PAYLOAD
  //
  // entity_type   →  Screen
  // ─────────────────────────────────────────────────────────────────
  // order         →  OrderDetailScreen   (customer)
  // delivery      →  DeliveryDetailScreen (driver)
  // complaint     →  ComplaintDetailScreen (customer)
  // notification  →  no specific screen — just loads notification list
  // ================================================================
  static void _handlePayload(String payload) {
    try {
      final decoded = jsonDecode(payload) as Map;
      final entityType = decoded['entity_type']?.toString() ?? '';
      final entityId = decoded['entity_id']?.toString() ?? '';

      if (entityId.isEmpty) return;

      final navigator = navigatorKey.currentState;
      if (navigator == null) {
        debugPrint(
            '[PushHandler] Navigator not ready for entity_type=$entityType');
        return;
      }

      switch (entityType) {
        // ── Customer: order status update ────────────────────────
        case 'order':
          navigator.push(
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(
                orderId: entityId,
                showBottomNavigation: true,
              ),
            ),
          );
          break;

        // ── Driver: delivery assignment / status change ──────────
        case 'delivery':
          navigator.push(
            MaterialPageRoute(
              builder: (_) => DeliveryDetailScreen(id: entityId),
            ),
          );
          break;

        // ── Customer: complaint response ─────────────────────────
        case 'complaint':
          navigator.push(
            MaterialPageRoute(
              builder: (_) => ComplaintDetailScreen(id: entityId),
            ),
          );
          break;

        // ── Unknown / notification-only ──────────────────────────
        default:
          debugPrint(
              '[PushHandler] Unhandled entity_type: $entityType — no navigation.');
      }
    } catch (e) {
      debugPrint('[PushHandler] Failed to handle payload: $e');
    }
  }
}
