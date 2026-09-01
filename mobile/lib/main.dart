import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/repositories/auth_repository.dart';
import 'core/services/token_storage.dart';

import 'customer/data/product_repository.dart';
import 'customer/data/address_repository.dart';
import 'customer/data/order_repository.dart';

import 'customer/screens/catalog_screen.dart';
import 'driver/screens/driver_home_screen.dart';

import 'customer/state/cart_provider.dart';

void main() {
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
    setState(() {
      loading = true;
      message = 'Logging in...';
    });

    try {
      final authRepo = context.read<AuthRepository>();

      final user = await authRepo.login(email, password);

      if (!mounted) return;

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
        message = 'Unsupported account role: $role';
      });
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        loading = false;
        message = e.message;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        loading = false;
        message = 'Login failed.';
      });
    }
  }

  // ==================================================================
  // LOGOUT
  // ==================================================================

  Future<void> logout() async {
    try {
      await context.read<AuthRepository>().logout();

      if (!mounted) return;

      setState(() {
        message = 'Logged out successfully.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        message = e.message;
      });
    } catch (e) {
      if (!mounted) return;

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

      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),

          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,

            children: [
              const Icon(Icons.water_drop, size: 70),

              const SizedBox(height: 20),

              const Text(
                'Aqua Clayne',
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
              ),

              const SizedBox(height: 20),

              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 16),
              ),

              const SizedBox(height: 30),

              // ------------------------------------------------------
              // Loading
              // ------------------------------------------------------
              if (loading) const CircularProgressIndicator(),

              // ------------------------------------------------------
              // Login buttons
              // ------------------------------------------------------
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
            ],
          ),
        ),
      ),
    );
  }
}
