import 'package:latlong2/latlong.dart';
import '../models/address_model.dart';

class DeliveryRestriction {
  DeliveryRestriction._();

  /// Permitted barangays for home delivery in Santa Cruz, Laguna
  static const List<String> allowedBarangays = ['gatid', 'duhat', 'bubukal'];

  /// Non-negotiable exact restriction message required by business rules
  static const String restrictionMessage =
      'Sorry! Aqua Clayne currently provides home delivery only in Gatid, Duhat, and Bubukal, Santa Cruz, Laguna. Please select an address within our delivery area.';

  /// Bounding box for Gatid, Duhat, and Bubukal, Santa Cruz, Laguna
  static const double minLat = 14.250;
  static const double maxLat = 14.295;
  static const double minLng = 121.385;
  static const double maxLng = 121.435;

  /// Santa Cruz municipality bounds
  static const double scMinLat = 14.22;
  static const double scMaxLat = 14.34;
  static const double scMinLng = 121.37;
  static const double scMaxLng = 121.47;

  /// Check whether a given barangay or address text matches Gatid, Duhat, or Bubukal
  static bool isAllowedBarangay(String? barangay, [String? addressLine]) {
    final b = _normalize(barangay);
    for (final allowed in allowedBarangays) {
      if (b.isNotEmpty && (b.contains(allowed) || b == allowed)) {
        return true;
      }
    }

    if (addressLine != null) {
      final line = _normalize(addressLine);
      for (final allowed in allowedBarangays) {
        if (line.contains(allowed)) {
          return true;
        }
      }
    }

    return false;
  }

  /// Check whether a coordinate falls inside the Gatid, Duhat, Bubukal geofence
  static bool isWithinDeliveryCoordinates(double? lat, double? lng) {
    if (lat == null || lng == null) return false;
    if (lat == 0.0 && lng == 0.0) return false;
    return lat >= minLat && lat <= maxLat && lng >= minLng && lng <= maxLng;
  }

  /// Check whether a coordinate point falls inside Santa Cruz municipality
  static bool isWithinSantaCruz(double? lat, double? lng) {
    if (lat == null || lng == null) return false;
    if (lat == 0.0 && lng == 0.0) return false;
    return lat >= scMinLat && lat <= scMaxLat && lng >= scMinLng && lng <= scMaxLng;
  }

  /// Check whether a LatLng point is within the allowed delivery coverage
  static bool isPointAllowed(LatLng point, {String? barangay, String? addressLine}) {
    if (isAllowedBarangay(barangay, addressLine) && isWithinSantaCruz(point.latitude, point.longitude)) {
      return true;
    }
    return isWithinDeliveryCoordinates(point.latitude, point.longitude);
  }

  /// Validate an existing AddressModel
  static bool isAddressAllowed(AddressModel address) {
    if (isAllowedBarangay(address.barangay, address.addressLine)) {
      if (address.latitude != null && address.longitude != null) {
        return isWithinSantaCruz(address.latitude, address.longitude);
      }
      return true;
    }

    if (address.latitude != null && address.longitude != null) {
      return isWithinDeliveryCoordinates(address.latitude, address.longitude);
    }

    return false;
  }

  static String _normalize(String? s) {
    if (s == null) return '';
    return s
        .toLowerCase()
        .replaceAll(RegExp(r'brgy\.?|barangay'), ' ')
        .replaceAll(RegExp(r'[,.\-_]'), ' ')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }
}

