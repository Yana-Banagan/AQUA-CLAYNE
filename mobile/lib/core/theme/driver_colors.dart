import 'package:flutter/material.dart';

/// Green / Mint visual identity for the Aqua Clayne Driver app.
/// Complements assets/images/driver_dashboard_bg.png.
class DriverColors {
  DriverColors._();

  // Primary greens
  static const Color primary = Color(0xFF168653);
  static const Color primaryDark = Color(0xFF105C3B);
  static const Color primaryLight = Color(0xFF35A66F);
  static const Color mintAccent = Color(0xFF2E9B62);
  static const Color greenWater = Color(0xFF4CAF50);

  // Background & Surfaces
  static const Color background = Color(0xFFEAF8E8); // Light Green
  static const Color surface = Color(0xFFFFFFFF); // White
  static const Color card = Color(0xFFFFFFFF);
  static const Color cardTranslucent = Color(0xF5FFFFFF);
  static const Color mintSoft = Color(0xFFEAF8E8);
  static const Color mintSurface = Color(0xFFF3FBF7);

  // Borders & Dividers
  static const Color border = Color(0xFFC7EBD9);
  static const Color borderLight = Color(0xFFE0F2E4);
  static const Color divider = Color(0xFFE2F3EB);

  // Text
  static const Color textPrimary = Color(0xFF173B2D);
  static const Color textSecondary = Color(0xFF6F887A);
  static const Color textMuted = Color(0xFF6F887A);
  static const Color textHint = Color(0xFF91A69A);
  static const Color textOnPrimary = Colors.white;

  // Status & Badges
  static const Color success = Color(0xFF3BAA62); // Success
  static const Color successLight = Color(0xFFE8F6ED);
  static const Color warning = Color(0xFFF4A62A); // Warning
  static const Color warningLight = Color(0xFFFEF6E9);
  static const Color error = Color(0xFFD9534F); // Error
  static const Color errorLight = Color(0xFFFCEBEB);
  static const Color info = Color(0xFF168653);
  static const Color infoLight = Color(0xFFEAF8E8);

  // Shadows
  static const Color shadow = Color(0x12168653);

  static Color statusColor(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
      case 'WAITING_IN_QUEUE':
        return warning;
      case 'CONFIRMED':
      case 'ASSIGNED':
      case 'OUT_FOR_DELIVERY':
        return primary;
      case 'PICKED_UP':
      case 'RECEIVED_AT_STATION':
      case 'REFILLING':
      case 'READY_FOR_DELIVERY':
      case 'DELIVERED':
      case 'COMPLETED':
        return success;
      case 'REJECTED':
      case 'CANCELLED':
      case 'PICKUP_FAILED':
      case 'DELIVERY_FAILED':
        return error;
      default:
        return textSecondary;
    }
  }

  static ThemeData theme(ThemeData base) => base.copyWith(
        primaryColor: primary,
        colorScheme: base.colorScheme.copyWith(
          primary: primary,
          onPrimary: Colors.white,
          secondary: mintAccent,
          onSecondary: Colors.white,
          onSurface: textPrimary,
          onSurfaceVariant: textSecondary,
          outline: border,
          error: error,
        ),
        scaffoldBackgroundColor: background,
        appBarTheme: base.appBarTheme.copyWith(
          foregroundColor: textPrimary,
          iconTheme: const IconThemeData(color: primaryDark),
          actionsIconTheme: const IconThemeData(color: primary),
        ),
        progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
        dividerTheme: const DividerThemeData(color: divider),
        textSelectionTheme: const TextSelectionThemeData(cursorColor: primary),
        bottomNavigationBarTheme: base.bottomNavigationBarTheme.copyWith(
          selectedItemColor: primary,
        ),
        floatingActionButtonTheme: base.floatingActionButtonTheme.copyWith(
          backgroundColor: primary,
          foregroundColor: Colors.white,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
          ),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(foregroundColor: primary),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            foregroundColor: primary,
            side: const BorderSide(color: primary),
          ),
        ),
        inputDecorationTheme: base.inputDecorationTheme.copyWith(
          focusedBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: primary, width: 1.5),
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        switchTheme: base.switchTheme.copyWith(
          thumbColor: WidgetStateProperty.resolveWith((states) =>
              states.contains(WidgetState.selected) ? primary : null),
          trackColor: WidgetStateProperty.resolveWith((states) =>
              states.contains(WidgetState.selected) ? primaryLight : null),
        ),
      );
}
