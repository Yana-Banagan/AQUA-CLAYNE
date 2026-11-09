import 'dart:io';

import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

import '../screens/live_navigation_screen.dart';

class NavigationLauncher {
  NavigationLauncher._();

  /// Launch in-app live navigation screen.
  static Future<bool> launchNavigation({
    required BuildContext context,
    double? latitude,
    double? longitude,
    required String address,
    String? customerName,
    String? deliveryId,
    String? deliveryStatus,
  }) async {
    final hasCoordinates = latitude != null &&
        longitude != null &&
        latitude != 0 &&
        longitude != 0;

    final destinationAddress = address.trim();

    if (!hasCoordinates &&
        (destinationAddress.isEmpty ||
            destinationAddress.toLowerCase() == 'address unavailable')) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No coordinates or address available for navigation.'),
          ),
        );
      }
      return false;
    }

    try {
      if (hasCoordinates) {
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => LiveNavigationScreen(
              latitude: latitude,
              longitude: longitude,
              address: destinationAddress,
              customerName: customerName,
              deliveryId: deliveryId,
              deliveryStatus: deliveryStatus,
            ),
          ),
        );
        return true;
      }

      return await launchExternalNavigation(
        context: context,
        latitude: latitude,
        longitude: longitude,
        address: destinationAddress,
      );
    } catch (e) {
      debugPrint('[NavigationLauncher] Error launching map: $e');
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Could not open maps application: $e'),
          ),
        );
      }
      return false;
    }
  }

  /// Launch external navigation (Google Maps) strictly using the official
  /// Aqua Clayne Station origin (Batisan, Gatid, Santa Cruz, Laguna).
  static Future<bool> launchExternalNavigation({
    required BuildContext context,
    double? latitude,
    double? longitude,
    required String address,
  }) async {
    final hasCoordinates = latitude != null &&
        longitude != null &&
        latitude != 0 &&
        longitude != 0;

    final destination = hasCoordinates
        ? '$latitude,$longitude'
        : address.trim();

    if (destination.isEmpty) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No valid delivery destination available.')),
        );
      }
      return false;
    }

    // Origin is ALWAYS the registered Aqua Clayne Station
    const originLat = 14.265111;
    const originLng = 121.405602;

    final uri = Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'origin': '$originLat,$originLng',
      'destination': destination,
      'travelmode': 'driving',
      'dir_action': 'navigate',
    });

    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open Google Maps navigation.')),
        );
      }
      return opened;
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not launch maps application: $e')),
        );
      }
      return false;
    }
  }

  /// Launch phone dialer for customer phone
  static Future<bool> launchCall(BuildContext context, String? phone) async {
    if (phone == null || phone.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No phone number available.')),
      );
      return false;
    }

    // Android requires an explicit runtime approval before the app can use
    // phone functionality. Opening the dialer on iOS is handed to the system
    // dialer and does not have an equivalent app-level phone permission.
    if (Platform.isAndroid) {
      var phonePermission = await Permission.phone.status;
      if (phonePermission.isDenied && context.mounted) {
        final continueToPermission = await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Allow phone access?'),
            content: const Text(
              'AQUA CLAYNE needs phone access to open a call to this customer.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Not now'),
              ),
              ElevatedButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Continue'),
              ),
            ],
          ),
        );

        if (continueToPermission != true) return false;
        phonePermission = await Permission.phone.request();
      }

      if (!phonePermission.isGranted) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Phone permission is required to place a customer call.'),
              action: phonePermission.isPermanentlyDenied
                  ? SnackBarAction(
                      label: 'Settings',
                      onPressed: () {
                        openAppSettings();
                      },
                    )
                  : null,
            ),
          );
        }
        return false;
      }
    }

    final cleanPhone = phone.replaceAll(RegExp(r'[^\d+]'), '');
    final uri = Uri.parse('tel:$cleanPhone');
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No phone dialer is available on this device.')),
        );
      }
      return opened;
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open phone dialer.')),
        );
      }
      return false;
    }
  }
}
