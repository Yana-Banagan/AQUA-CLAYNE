import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

// Core
import 'core/api/api_client.dart';
import 'core/repositories/auth_repository.dart';
import 'core/services/token_storage.dart';
import 'core/services/realtime_service.dart';
import 'core/services/offline_service.dart';
import 'core/notifications/push_handler.dart';
import 'core/config/env.dart';
import 'core/theme/app_theme.dart';
import 'core/providers/auth_provider.dart';
import 'core/screens/splash_screen.dart';
import 'core/navigation/auth_navigation.dart';
import 'core/widgets/social_auth_link_handler.dart';

// Customer data
import 'customer/data/product_repository.dart';
import 'customer/data/address_repository.dart';
import 'customer/data/order_repository.dart';
import 'customer/data/notification_repository.dart';
import 'customer/data/complaint_repository.dart';
import 'customer/data/credit_repository.dart';
import 'customer/data/payment_repository.dart';

// Customer providers
import 'customer/providers/cart_provider.dart';
import 'customer/providers/order_provider.dart';
import 'customer/providers/address_provider.dart';
import 'customer/providers/notification_provider.dart';
import 'customer/providers/complaint_provider.dart';
import 'customer/providers/credit_provider.dart';
import 'customer/providers/payment_provider.dart';

// Driver data
import 'driver/data/delivery_repository.dart';

// Driver providers
import 'driver/providers/driver_delivery_provider.dart';

// ======================================================================
// FIREBASE BACKGROUND HANDLER — must be a top-level function
// ======================================================================
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  debugPrint('[FCM Background] messageId: ${message.messageId}');
}

// ======================================================================
// MAIN
// ======================================================================
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // ── Firebase ──────────────────────────────────────────────────────
  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  await FirebaseMessaging.instance.requestPermission(
    alert: true,
    badge: true,
    sound: true,
  );

  // ── Push notifications (local channel + tap handling) ─────────────
  await PushHandler.initialize();

  // ── Supabase — publishable key only, service-role stays server-side
  await Supabase.initialize(
    url: Env.supabaseUrl,
    publishableKey: Env.supabaseAnonKey,
  );

  runApp(const AquaClayneApp());
}

// ======================================================================
// APP ROOT
// ======================================================================
class AquaClayneApp extends StatelessWidget {
  const AquaClayneApp({super.key});

  static final RealtimeService _realtimeService = RealtimeService();

  @override
  Widget build(BuildContext context) {
    // ── Infrastructure singletons (created once) ───────────────────
    final tokenStorage = TokenStorage();
    final apiClient = ApiClient(tokenStorage);
    final realtimeService = _realtimeService;
    final offlineService = OfflineService();
    offlineService.initialize();
    final authRepo = AuthRepository(apiClient, tokenStorage);

    var sessionExpirationInProgress = false;
    apiClient.onSessionExpired = () {
      if (sessionExpirationInProgress) return;
      sessionExpirationInProgress = true;
      final appContext = navigatorKey.currentContext;
      if (appContext == null) {
        sessionExpirationInProgress = false;
        return;
      }

      unawaited(() async {
        final expiredUser = appContext.read<AuthProvider>().user;
        appContext.read<NotificationProvider>().clearSessionData();
        appContext.read<OrderProvider>().clearSessionData();
        appContext.read<PaymentProvider>().clearSessionData();
        appContext.read<AddressProvider>().clearSessionData();
        appContext.read<CreditProvider>().clearSessionData();
        appContext.read<ComplaintProvider>().clearSessionData();
        if (expiredUser?.isCustomer == true) {
          appContext.read<CartProvider>().clearCart();
        }
        appContext.read<DriverDeliveryProvider>().clearSessionData();

        var cleanupComplete = false;
        try {
          cleanupComplete = await appContext
              .read<AuthProvider>()
              .expireSession();
        } catch (error) {
          debugPrint('[AquaClayneApp] Session cleanup failed: $error');
        }

        if (appContext.mounted) {
          replaceWithLogin(
            appContext,
            noticeMessage: cleanupComplete
                ? 'Your session has expired. Please sign in again.'
                : 'Your session has expired, but some local session data could not be cleared. Please restart the app.',
          );
        }
        sessionExpirationInProgress = false;
      }());
    };

    // ── Repositories ───────────────────────────────────────────────
    final productRepo = ProductRepository(apiClient);
    final addressRepo = AddressRepository(apiClient);
    final orderRepo = OrderRepository(apiClient);
    final notificationRepo = NotificationRepository(apiClient);
    final complaintRepo = ComplaintRepository(apiClient);
    final creditRepo = CreditRepository(apiClient);
    final paymentRepo = PaymentRepository(apiClient);
    final deliveryRepo = DeliveryRepository(apiClient);

    return MultiProvider(
      providers: [
        // ── Infrastructure ─────────────────────────────────────────
        Provider<TokenStorage>.value(value: tokenStorage),
        Provider<ApiClient>.value(value: apiClient),
        Provider<RealtimeService>.value(value: realtimeService),
        ChangeNotifierProvider<OfflineService>.value(value: offlineService),
        Provider<AuthRepository>.value(value: authRepo),

        // ── Auth state ─────────────────────────────────────────────
        ChangeNotifierProvider<AuthProvider>(
          create: (_) => AuthProvider(authRepo, realtimeService),
        ),

        // ── Customer repositories ───────────────────────────────────
        Provider<ProductRepository>.value(value: productRepo),
        Provider<AddressRepository>.value(value: addressRepo),
        Provider<OrderRepository>.value(value: orderRepo),
        Provider<NotificationRepository>.value(value: notificationRepo),
        Provider<ComplaintRepository>.value(value: complaintRepo),
        Provider<CreditRepository>.value(value: creditRepo),
        Provider<PaymentRepository>.value(value: paymentRepo),

        // ── Customer state ──────────────────────────────────────────
        ChangeNotifierProvider<CartProvider>(create: (_) => CartProvider()),
        ChangeNotifierProvider<OrderProvider>(
          create: (_) => OrderProvider(orderRepo, realtimeService),
        ),
        ChangeNotifierProvider<AddressProvider>(
          create: (_) => AddressProvider(addressRepo),
        ),
        ChangeNotifierProvider<NotificationProvider>(
          create: (_) =>
              NotificationProvider(notificationRepo, realtimeService),
        ),
        ChangeNotifierProvider<ComplaintProvider>(
          create: (_) => ComplaintProvider(complaintRepo),
        ),
        ChangeNotifierProvider<CreditProvider>(
          create: (_) => CreditProvider(creditRepo),
        ),
        ChangeNotifierProvider<PaymentProvider>(
          create: (_) => PaymentProvider(paymentRepo, realtimeService),
        ),

        // ── Driver ─────────────────────────────────────────────────
        Provider<DeliveryRepository>.value(value: deliveryRepo),
        ChangeNotifierProvider<DriverDeliveryProvider>(
          create: (_) => DriverDeliveryProvider(deliveryRepo, realtimeService),
        ),
      ],
      child: MaterialApp(
        title: 'Aqua Clayne',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        // navigatorKey lets PushHandler navigate from FCM callbacks
        navigatorKey: navigatorKey,
        builder: (context, child) => Column(
          children: [
            Builder(
              builder: (context) {
                final offline = context.watch<OfflineService>();
                if (!offline.isOffline && offline.pendingSyncCount == 0)
                  return const SizedBox.shrink();
                final message = offline.isOffline
                    ? offline.pendingSyncCount > 0
                          ? 'Offline · ${offline.pendingSyncCount} safe change(s) saved on this device and waiting to sync.'
                          : 'Offline · Showing saved information where available. Some actions require a connection.'
                    : offline.syncError != null
                    ? '${offline.pendingSyncCount} saved change(s) still need to sync: ${offline.syncError}'
                    : '${offline.pendingSyncCount} saved change(s) are queued for sync when the server is available.';
                return Material(
                  color: offline.isOffline
                      ? const Color(0xFF7C2D12)
                      : const Color(0xFF075985),
                  child: SafeArea(
                    bottom: false,
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: Row(
                        children: [
                          Icon(
                            offline.isOffline
                                ? Icons.cloud_off_rounded
                                : Icons.sync_rounded,
                            color: Colors.white,
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              message,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                              ),
                            ),
                          ),
                          if (!offline.isOffline &&
                              offline.pendingSyncCount > 0)
                            TextButton(
                              onPressed: () async {
                                final result = await context
                                    .read<ApiClient>()
                                    .syncPendingMutations();
                                if (!context.mounted) return;
                                context.read<OfflineService>().updateSyncState(
                                  pendingCount: result.pending,
                                  error: result.error,
                                );
                                if (result.synced > 0) {
                                  await Future.wait([
                                    context.read<OrderProvider>().loadOrders(),
                                    context.read<AddressProvider>().load(),
                                    context.read<NotificationProvider>().load(),
                                    context
                                        .read<PaymentProvider>()
                                        .loadPayments(),
                                  ]);
                                }
                              },
                              child: const Text(
                                'Retry',
                                style: TextStyle(color: Colors.white),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
            Expanded(
              child: SocialAuthLinkHandler(
                child: OfflineSyncGate(child: child ?? const SizedBox.shrink()),
              ),
            ),
          ],
        ),
        home: const SplashScreen(),
      ),
    );
  }
}

/// Refreshes server-authoritative data after connectivity returns. Order and
/// payment creation are never replayed from local storage; they remain server
/// validated to prevent duplicate transactions.
class OfflineSyncGate extends StatefulWidget {
  final Widget child;
  const OfflineSyncGate({super.key, required this.child});

  @override
  State<OfflineSyncGate> createState() => _OfflineSyncGateState();
}

class _OfflineSyncGateState extends State<OfflineSyncGate> {
  bool _wasOffline = false;
  bool _callbackBound = false;
  bool _syncing = false;
  String? _lastOwnerId;
  Timer? _retryTimer;
  ApiClient? _api;

  @override
  void dispose() {
    _retryTimer?.cancel();
    _api?.onOutboxChanged = null;
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final offline = context.watch<OfflineService>().isOffline;
    final auth = context.watch<AuthProvider>();
    final ownerId = auth.user?.id;
    if (!_callbackBound) {
      _callbackBound = true;
      _api = context.read<ApiClient>();
      _api!.onOutboxChanged = () {
        _updatePendingCount();
      };
      _retryTimer = Timer.periodic(const Duration(seconds: 30), (_) {
        if (!mounted ||
            context.read<OfflineService>().isOffline ||
            !context.read<AuthProvider>().isAuthenticated)
          return;
        context.read<ApiClient>().pendingMutationCount().then((count) {
          if (mounted && count > 0)
            _syncAndRefresh(refreshAllOnReconnect: false);
        });
      });
    }
    final shouldSync =
        auth.isAuthenticated &&
        !offline &&
        (_wasOffline || ownerId != _lastOwnerId);
    final shouldRefreshOfflineCount =
        auth.isAuthenticated && offline && ownerId != _lastOwnerId;
    final reconnected = _wasOffline && !offline;
    _wasOffline = offline;
    _lastOwnerId = ownerId;
    if (shouldSync)
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _syncAndRefresh(refreshAllOnReconnect: reconnected),
      );
    if (shouldRefreshOfflineCount)
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _updatePendingCount(),
      );
  }

  Future<void> _updatePendingCount() async {
    if (!mounted) return;
    final api = context.read<ApiClient>();
    final count = await api.pendingMutationCount();
    if (mounted)
      context.read<OfflineService>().updateSyncState(
        pendingCount: count,
        error: null,
      );
  }

  Future<void> _syncAndRefresh({required bool refreshAllOnReconnect}) async {
    if (!mounted || _syncing) return;
    _syncing = true;
    try {
      final api = context.read<ApiClient>();
      final result = await api.syncPendingMutations();
      if (!mounted) return;
      context.read<OfflineService>().updateSyncState(
        pendingCount: result.pending,
        error: result.error,
      );
      if (!context.read<AuthProvider>().isAuthenticated) return;
      if (refreshAllOnReconnect || result.synced > 0) {
        await Future.wait([
          context.read<OrderProvider>().loadOrders(),
          context.read<AddressProvider>().load(),
          context.read<NotificationProvider>().load(),
          context.read<PaymentProvider>().loadPayments(),
        ]);
      }
    } finally {
      _syncing = false;
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
