import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import 'core/api/api_client.dart';
import 'core/repositories/auth_repository.dart';
import 'core/services/token_storage.dart';
import 'core/notifications/push_handler.dart';

import 'customer/data/product_repository.dart';
import 'customer/data/address_repository.dart';
import 'customer/data/order_repository.dart';
import 'driver/data/delivery_repository.dart';

import 'customer/screens/catalog_screen.dart';
import 'driver/screens/driver_home_screen.dart';

import 'customer/state/cart_provider.dart';

// ====================================================================
// FIREBASE BACKGROUND FCM HANDLER
// ====================================================================
//
// Handles Firebase Cloud Messaging notifications when the application
// is running in the background or terminated.
//
// IMPORTANT:
// This must be a top-level function.
//

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Firebase must be initialized before using Firebase services.
  await Firebase.initializeApp();

  debugPrint('Background FCM message received: ${message.messageId}');

  debugPrint('FCM notification title: ${message.notification?.title}');

  debugPrint('FCM notification body: ${message.notification?.body}');

  debugPrint('FCM data: ${message.data}');
}

// ====================================================================
// MAIN
// ====================================================================

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // ================================================================
  // FIREBASE INITIALIZATION
  // ================================================================

  await Firebase.initializeApp();

  // ================================================================
  // REGISTER BACKGROUND FCM HANDLER
  // ================================================================

  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  // ================================================================
  // REQUEST NOTIFICATION PERMISSION
  // ================================================================

  final notificationSettings = await FirebaseMessaging.instance
      .requestPermission(alert: true, badge: true, sound: true);

  debugPrint(
    'FCM permission: '
    '${notificationSettings.authorizationStatus}',
  );

  // ================================================================
  // INITIALIZE PUSH HANDLER
  // ================================================================
  //
  // Registers:
  // - Foreground notification listener
  // - Background notification tap listener
  // - Terminated notification tap handling
  // - Local notification channel
  //

  await PushHandler.initialize();

  // ================================================================
  // SUPABASE INITIALIZATION
  // ================================================================

  await Supabase.initialize(
    url: 'https://evnhwmmwsnxizcoejqhz.supabase.co',
    publishableKey: 'sb_publishable_HVLCXjrvWAJyezYy0qGolQ_Ep--J7Ni',
  );

  // ================================================================
  // RUN APP
  // ================================================================

  runApp(
    MultiProvider(
      providers: [
        // ------------------------------------------------------------
        // Token Storage
        // ------------------------------------------------------------

        Provider<TokenStorage>(create: (_) => TokenStorage()),

        // ------------------------------------------------------------
        // API Client
        // ------------------------------------------------------------
        Provider<ApiClient>(
          create: (context) => ApiClient(context.read<TokenStorage>()),
        ),

        // ------------------------------------------------------------
        // Authentication Repository
        // ------------------------------------------------------------
        Provider<AuthRepository>(
          create: (context) => AuthRepository(
            context.read<ApiClient>(),
            context.read<TokenStorage>(),
          ),
        ),

        // ------------------------------------------------------------
        // Product Repository
        // ------------------------------------------------------------
        Provider<ProductRepository>(
          create: (context) => ProductRepository(context.read<ApiClient>()),
        ),

        // ------------------------------------------------------------
        // Address Repository
        // ------------------------------------------------------------
        Provider<AddressRepository>(
          create: (context) => AddressRepository(context.read<ApiClient>()),
        ),

        // ------------------------------------------------------------
        // Order Repository
        // ------------------------------------------------------------
        Provider<OrderRepository>(
          create: (context) => OrderRepository(context.read<ApiClient>()),
        ),

        // ------------------------------------------------------------
        // Delivery Repository
        // ------------------------------------------------------------
        Provider<DeliveryRepository>(
          create: (context) => DeliveryRepository(context.read<ApiClient>()),
        ),

        // ------------------------------------------------------------
        // Local Cart State
        // ------------------------------------------------------------
        ChangeNotifierProvider<CartProvider>(create: (_) => CartProvider()),
      ],
      child: const MyApp(),
    ),
  );
}

// ====================================================================
// APP
// ====================================================================

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      // ==============================================================
      // GLOBAL NAVIGATOR KEY
      // ==============================================================
      //
      // Push notifications do not have access to a Widget
      // BuildContext. The navigator key allows PushHandler to
      // navigate to screens when the user taps a notification.
      //

      navigatorKey: navigatorKey,

      title: 'Aqua Clayne',

      debugShowCheckedModeBanner: false,

      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
      ),

      home: const HomePage(),
    );
  }
}

// ====================================================================
// HOME PAGE
// ====================================================================

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  String message = 'Please log in.';

  bool loading = false;

  // ==================================================================
  // CUSTOMER LOGIN
  // ==================================================================

  Future<void> loginAsCustomer() async {
    await login(email: 'juan2@example.com', password: 'password123');
  }

  // ==================================================================
  // DRIVER LOGIN
  // ==================================================================

  Future<void> loginAsDriver() async {
    await login(email: 'testdriver123@example.com', password: 'password123');
  }

  // ==================================================================
  // LOGIN
  // ==================================================================

  Future<void> login({required String email, required String password}) async {
    // Get AuthRepository before the async gap.
    //
    // This prevents the "use_build_context_synchronously" warning
    // when accessing Provider after an await.

    final authRepo = context.read<AuthRepository>();

    setState(() {
      loading = true;
      message = 'Logging in...';
    });

    try {
      // ==============================================================
      // STEP 1 — LOGIN THROUGH LARAVEL
      // ==============================================================

      final user = await authRepo.login(email, password);

      // ==============================================================
      // STEP 2 — CHECK THAT SCREEN STILL EXISTS
      // ==============================================================

      if (!mounted) {
        return;
      }

      // ==============================================================
      // STEP 3 — REGISTER FCM TOKEN
      // ==============================================================
      //
      // Login has already succeeded.
      //
      // Firebase
      //     ↓
      // FCM token
      //     ↓
      // Laravel /auth/fcm-token
      //     ↓
      // users.fcm_token
      //
      // If FCM registration fails, the user is still allowed to
      // continue into the application.
      //

      try {
        await authRepo.registerFcmToken();

        debugPrint('FCM token successfully registered with Laravel.');
      } catch (e) {
        debugPrint('FCM token registration failed: $e');
      }

      // ==============================================================
      // STEP 4 — CHECK SCREEN AGAIN
      // ==============================================================

      if (!mounted) {
        return;
      }

      // ==============================================================
      // STEP 5 — DETERMINE USER ROLE
      // ==============================================================

      final role = user['role']?.toString();

      setState(() {
        loading = false;
        message = 'Logged in as ${role ?? 'unknown'}';
      });

      // ==============================================================
      // DRIVER
      // ==============================================================

      if (role == 'driver') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const DriverHomeScreen()),
        );

        return;
      }

      // ==============================================================
      // CUSTOMER
      // ==============================================================

      if (role == 'customer') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const CatalogScreen()),
        );

        return;
      }

      // ==============================================================
      // OTHER ROLES
      // ==============================================================

      setState(() {
        loading = false;
        message = 'Unsupported account role: $role';
      });
    } on ApiException catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        loading = false;

        // Do not expose raw backend/database errors.
        message = _friendlyApiError(e.message);
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        loading = false;
        message = 'Unable to connect to the server. Please try again.';
      });
    }
  }

  // ==================================================================
  // FRIENDLY API ERROR
  // ==================================================================

  String _friendlyApiError(String error) {
    final lowerError = error.toLowerCase();

    // --------------------------------------------------------------
    // Database / PostgreSQL connection errors
    // --------------------------------------------------------------

    if (lowerError.contains('sqlstate') ||
        lowerError.contains('postgres') ||
        lowerError.contains('connection to server') ||
        lowerError.contains('connection refused') ||
        lowerError.contains('permission denied')) {
      return 'Unable to connect to the server. Please try again.';
    }

    // --------------------------------------------------------------
    // Authentication errors
    // --------------------------------------------------------------

    if (lowerError.contains('invalid credentials') ||
        lowerError.contains('unauthorized') ||
        lowerError.contains('authentication')) {
      return 'Invalid email or password.';
    }

    // --------------------------------------------------------------
    // Forbidden
    // --------------------------------------------------------------

    if (lowerError.contains('forbidden')) {
      return 'You do not have permission to perform this action.';
    }

    // --------------------------------------------------------------
    // Not found
    // --------------------------------------------------------------

    if (lowerError.contains('not found')) {
      return 'The requested resource was not found.';
    }

    // --------------------------------------------------------------
    // Generic API error
    // --------------------------------------------------------------

    return error.length > 150
        ? 'Something went wrong. Please try again.'
        : error;
  }

  // ==================================================================
  // LOGOUT
  // ==================================================================

  Future<void> logout() async {
    // Get repository before the async gap.
    final authRepo = context.read<AuthRepository>();

    try {
      await authRepo.logout();

      if (!mounted) {
        return;
      }

      setState(() {
        message = 'Logged out successfully.';
      });
    } on ApiException catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        message = _friendlyApiError(e.message);
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        message = 'Logout failed.';
      });
    }
  }

  // ==================================================================
  // BUILD
  // ==================================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Aqua Clayne')),

      // ================================================================
      // SAFE AREA + SCROLL VIEW
      //
      // Prevents RenderFlex overflow when the screen is small or
      // when the keyboard changes the available height.
      // ================================================================
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.all(24),

              child: ConstrainedBox(
                constraints: BoxConstraints(
                  minHeight: constraints.maxHeight - 48,
                ),

                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.center,

                  children: [
                    // --------------------------------------------------
                    // Logo
                    // --------------------------------------------------

                    const Icon(Icons.water_drop, size: 70),

                    const SizedBox(height: 20),

                    // --------------------------------------------------
                    // Application Name
                    // --------------------------------------------------
                    const Text(
                      'Aqua Clayne',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 20),

                    // --------------------------------------------------
                    // Login Message
                    // --------------------------------------------------
                    Text(
                      message,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 16),
                    ),

                    const SizedBox(height: 30),

                    // --------------------------------------------------
                    // Loading
                    // --------------------------------------------------
                    if (loading) ...[
                      const CircularProgressIndicator(),

                      const SizedBox(height: 20),

                      const Text('Please wait...', textAlign: TextAlign.center),
                    ],

                    // --------------------------------------------------
                    // Login Buttons
                    // --------------------------------------------------
                    if (!loading) ...[
                      // Customer
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: loginAsCustomer,
                          child: const Text('Login as Customer'),
                        ),
                      ),

                      const SizedBox(height: 12),

                      // Driver
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: loginAsDriver,
                          child: const Text('Login as Driver'),
                        ),
                      ),

                      const SizedBox(height: 12),

                      // Logout
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: logout,
                          child: const Text('Clear Login Session'),
                        ),
                      ),
                    ],

                    // Extra bottom spacing for smaller screens.
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
