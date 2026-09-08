import 'dart:convert';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../driver/screens/delivery_details_screen.dart';

// ====================================================================
// GLOBAL NAVIGATOR KEY
// ====================================================================
//
// FCM callbacks do not have access to a Widget BuildContext.
// This key allows notifications to navigate through the application.
//

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

// ====================================================================
// FLUTTER LOCAL NOTIFICATIONS
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
    'default_channel',
    'Notifications',
    description: 'Aqua Clayne notifications',
    importance: Importance.high,
  );

  // ================================================================
  // INITIALIZE PUSH NOTIFICATIONS
  // ================================================================

  static Future<void> initialize() async {
    // --------------------------------------------------------------
    // Android initialization
    // --------------------------------------------------------------

    const androidSettings = AndroidInitializationSettings(
      '@mipmap/ic_launcher',
    );

    const initializationSettings = InitializationSettings(
      android: androidSettings,
    );

    // --------------------------------------------------------------
    // Initialize local notifications
    // --------------------------------------------------------------

    await flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;

        if (payload == null || payload.isEmpty) {
          return;
        }

        _handleLocalNotificationTap(payload);
      },
    );

    // --------------------------------------------------------------
    // Create Android notification channel
    // --------------------------------------------------------------

    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(channel);

    // --------------------------------------------------------------
    // FOREGROUND
    // --------------------------------------------------------------

    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // --------------------------------------------------------------
    // BACKGROUND → USER TAPS NOTIFICATION
    // --------------------------------------------------------------

    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);

    // --------------------------------------------------------------
    // TERMINATED → USER TAPS NOTIFICATION
    // --------------------------------------------------------------

    final initial = await FirebaseMessaging.instance.getInitialMessage();

    if (initial != null) {
      _handleTap(initial);
    }
  }

  // ================================================================
  // FOREGROUND MESSAGE
  // ================================================================

  static Future<void> _handleForegroundMessage(RemoteMessage message) async {
    final notification = message.notification;

    if (notification == null) {
      return;
    }

    // --------------------------------------------------------------
    // Keep only the data required for navigation.
    //
    // This follows the Phase 11 payload design:
    //
    // entity_type
    // entity_id
    //
    // We do NOT put customer information, address, payment,
    // credit/debt, or order financial information in the payload.
    // --------------------------------------------------------------

    final payloadData = <String, String>{
      'entity_type': message.data['entity_type']?.toString() ?? '',
      'entity_id': message.data['entity_id']?.toString() ?? '',
    };

    await flutterLocalNotificationsPlugin.show(
      message.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'default_channel',
          'Notifications',
          channelDescription: 'Aqua Clayne notifications',
          importance: Importance.high,
          priority: Priority.high,
        ),
      ),
      payload: jsonEncode(payloadData),
    );
  }

  // ================================================================
  // LOCAL NOTIFICATION TAP
  // ================================================================

  static void _handleLocalNotificationTap(String payload) {
    try {
      final decoded = jsonDecode(payload);

      if (decoded is! Map) {
        return;
      }

      final entityType = decoded['entity_type']?.toString();

      final entityId = decoded['entity_id']?.toString();

      if (entityType == 'delivery' && entityId != null && entityId.isNotEmpty) {
        _openDelivery(entityId);
      }
    } catch (e) {
      debugPrint('Unable to process local notification payload: $e');
    }
  }

  // ================================================================
  // FCM NOTIFICATION TAP
  // ================================================================

  static void _handleTap(RemoteMessage message) {
    final entityType = message.data['entity_type']?.toString();

    final entityId = message.data['entity_id']?.toString();

    if (entityType == 'delivery' && entityId != null && entityId.isNotEmpty) {
      _openDelivery(entityId);
    }
  }

  // ================================================================
  // OPEN DELIVERY DETAILS
  // ================================================================

  static void _openDelivery(String deliveryId) {
    final navigator = navigatorKey.currentState;

    if (navigator == null) {
      debugPrint('Navigator is not ready. Unable to open delivery.');
      return;
    }

    navigator.push(
      MaterialPageRoute(
        builder: (_) => DeliveryDetailsScreen(deliveryId: deliveryId),
      ),
    );
  }
}
