import 'package:flutter/material.dart';

/// Centralized color palette for AQUA CLAYNE.
/// Deep water blue primary, sky blue secondary, aqua/cyan accents.
class AppColors {
  AppColors._();

  // ================================================================
  // AQUA CLAYNE STOREFRONT BRAND PALETTE
  // ================================================================
  static const Color aquaBlue = Color(0xFF0878C9);
  static const Color deepBlue = Color(0xFF064B87);
  static const Color lightAqua = Color(0xFFEAF7FF);
  static const Color accentBlue = Color(0xFF2EA9E8);
  static const Color darkText = Color(0xFF12344D);
  static const Color mutedText = Color(0xFF6F8BA3);
  static const Color border = Color(0xFFD5E7F4);

  // ================================================================
  // PRIMARY
  // ================================================================
  static const Color primary = Color(0xFF0878C9); // Aqua Blue
  static const Color primaryDark = Color(0xFF064B87); // Deep Blue
  static const Color primaryLight = Color(0xFF2EA9E8); // Accent Blue

  // ================================================================
  // SECONDARY
  // ================================================================
  static const Color secondary = Color(0xFF1E88E5); // sky blue
  static const Color secondaryLight = Color(0xFF42A5F5);

  // ================================================================
  // ACCENT
  // ================================================================
  static const Color accent = Color(0xFF00BCD4); // aqua/cyan
  static const Color accentLight = Color(0xFF4DD0E1);

  // ================================================================
  // BACKGROUND / SURFACE
  // ================================================================
  static const Color background = Color(0xFFF0F7FF); // very light blue
  static const Color surface = Color(0xFFFFFFFF);
  static const Color cardBackground = Color(0xFFFFFFFF);

  // ================================================================
  // STATUS
  // ================================================================
  static const Color success = Color(0xFF2E7D32); // green
  static const Color successLight = Color(0xFFE8F5E9);
  static const Color warning = Color(0xFFF57F17); // amber
  static const Color warningLight = Color(0xFFFFF8E1);
  static const Color error = Color(0xFFC62828); // red
  static const Color errorLight = Color(0xFFFFEBEE);
  static const Color info = Color(0xFF1565C0);
  static const Color infoLight = Color(0xFFE3F2FD);

  // ================================================================
  // TEXT
  // ================================================================
  static const Color textPrimary = Color(0xFF0A1929); // dark navy
  static const Color textSecondary = Color(0xFF546E7A); // muted blue-gray
  static const Color textHint = Color(0xFF90A4AE);
  static const Color textOnPrimary = Color(0xFFFFFFFF);

  // ================================================================
  // MISC
  // ================================================================
  static const Color divider = Color(0xFFE3EEF9);
  static const Color disabled = Color(0xFFB0BEC5);
  static const Color shadow = Color(0x1A0D47A1);
  static const Color overlay = Color(0x800D47A1);
  static const Color inputBorder = Color(0xFFBBDEFB);
  static const Color inputFill = Color(0xFFF8FBFF);

  // ================================================================
  // ORDER STATUS COLORS
  // ================================================================
  static Color statusColor(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return warning;
      case 'CONFIRMED':
        return info;
      case 'ASSIGNED':
        return secondary;
      case 'PICKED_UP':
        return const Color(0xFF7B1FA2);
      case 'RECEIVED_AT_STATION':
      case 'WAITING_IN_QUEUE':
        return const Color(0xFF00838F);
      case 'REFILLING':
        return accent;
      case 'READY_FOR_DELIVERY':
        return const Color(0xFF558B2F);
      case 'READY_FOR_PICKUP':
        return const Color(0xFF00897B);
      case 'OUT_FOR_DELIVERY':
        return secondary;
      case 'DELIVERED':
        return success;
      case 'COMPLETED':
        return success;
      case 'REJECTED':
        return error;
      case 'CANCELLED':
        return error;
      case 'PICKUP_FAILED':
        return error;
      case 'DELIVERY_FAILED':
        return error;
      default:
        return textSecondary;
    }
  }

  static Color statusBg(String status) {
    final color = statusColor(status);
    return color.withValues(alpha: 0.12);
  }
}
