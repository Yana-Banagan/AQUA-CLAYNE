import 'package:latlong2/latlong.dart';

/// Configured Aqua Clayne station origin and Santa Cruz service bounds.
/// The Aqua Clayne station is the official, non-negotiable origin for all deliveries.
class StationConfig {
  StationConfig._();

  static const String name = 'Aqua Clayne Water Refilling Station';
  static const String address =
      'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Sta Cruz Laguna';
  static const String shortAddress =
      '007 Batisan St., Brgy. Gatid, Sta Cruz Laguna';

  /// Official verified station coordinates
  static const double latitude = 14.265111;
  static const double longitude = 121.405602;

  static LatLng get point => const LatLng(latitude, longitude);

  /// Geographic boundaries for Santa Cruz, Laguna delivery coverage
  static const double minLatitude = 14.22;
  static const double maxLatitude = 14.34;
  static const double minLongitude = 121.37;
  static const double maxLongitude = 121.47;

  /// Check whether the given coordinates fall within the Santa Cruz service bounds
  static bool isWithinBounds(double? lat, double? lng) {
    if (lat == null || lng == null) return false;
    if (lat == 0.0 && lng == 0.0) return false;
    return lat >= minLatitude &&
        lat <= maxLatitude &&
        lng >= minLongitude &&
        lng <= maxLongitude;
  }

  /// Format coordinate string for display
  static const String formattedCoordinates = '14.265111, 121.405602';

  /// Formatted full station string for checkout / order details
  static const String formattedStationCard =
      '$name\n$shortAddress\nCoordinates: $formattedCoordinates';
}
