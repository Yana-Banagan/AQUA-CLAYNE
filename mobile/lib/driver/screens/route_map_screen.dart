import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/config/station_config.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../shared_widgets/driver_scaffold.dart';
import '../services/navigation_launcher.dart';
import '../widgets/route_point_markers.dart';

class RouteMapScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const RouteMapScreen({super.key, required this.delivery});

  @override
  State<RouteMapScreen> createState() => _RouteMapScreenState();
}

class _RouteMapScreenState extends State<RouteMapScreen> {
  final MapController _mapController = MapController();
  List<LatLng> _routePoints = const [];
  double? _distanceMeters;
  double? _durationSeconds;
  bool _isLoading = false;
  String? _routeError;
  bool _isFallback = false;
  Position? _driverPosition;
  StreamSubscription<Position>? _positionSubscription;

  LatLng get _station => StationConfig.point;
  LatLng? get _destination => widget.delivery.hasCoordinates
      ? LatLng(widget.delivery.latitude!, widget.delivery.longitude!)
      : null;
  LatLng? get _driverLatLng => _driverPosition == null
      ? null
      : LatLng(_driverPosition!.latitude, _driverPosition!.longitude);

  @override
  void initState() {
    super.initState();
    _fetchRoute();
    _trackDriverLocation();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    super.dispose();
  }

  Future<void> _trackDriverLocation() async {
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) return;
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        perm = await Geolocator.requestPermission();
      }
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
        return;
      }
      final initial = await Geolocator.getLastKnownPosition() ?? await Geolocator.getCurrentPosition();
      if (mounted) {
        setState(() => _driverPosition = initial);
      }
      _positionSubscription = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 10),
      ).listen((pos) {
        if (mounted) setState(() => _driverPosition = pos);
      });
    } catch (_) {}
  }

  Future<void> _fetchRoute() async {
    final dest = _destination;
    if (dest == null) return;
    setState(() => _isLoading = true);

    try {
      final res = await context.read<ApiClient>().get('/deliveries/${widget.delivery.id}/route');
      if (!mounted) return;
      if (res is Map) {
        final coords = res['coordinates'] as List?;
        if (coords != null) {
          final pts = coords.map((c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble())).toList();
          setState(() {
            _routePoints = pts;
            _distanceMeters = (res['distance'] as num?)?.toDouble();
            _durationSeconds = (res['duration'] as num?)?.toDouble();
            _isFallback = res['is_fallback'] == true;
            _routeError = null;
          });
          if (pts.isNotEmpty) {
            _mapController.fitCamera(CameraFit.coordinates(
              coordinates: [_station, dest],
              padding: const EdgeInsets.all(40),
              maxZoom: 15,
            ));
          }
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _routeError = 'Road route unavailable. Direct path displayed.';
          // Direct fallback line
          _routePoints = [_station, dest];
        });
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.delivery;
    final dest = _destination;

    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Delivery Navigation',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
        actions: [
          IconButton(
            tooltip: 'Refresh Route',
            icon: const Icon(Icons.refresh),
            onPressed: _fetchRoute,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.standard),
        children: [
          // Point A to Point B Delivery Route Card
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFBCE3FD)),
              boxShadow: const [
                BoxShadow(color: DriverColors.shadow, blurRadius: 6, offset: Offset(0, 2)),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Point A (Official Station Origin)
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFFE8F7EF),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFFA7F3D0)),
                      ),
                      child: const Text(
                        'POINT A',
                        style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: Color(0xFF059669)),
                      ),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Aqua Clayne Water Refilling Station',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF12344D)),
                          ),
                          SizedBox(height: 1),
                          Text(
                            '007 Batisan St., Brgy. Gatid, Sta Cruz Laguna',
                            style: TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.only(left: 24, top: 4, bottom: 4),
                  child: Row(
                    children: [
                      Container(width: 2, height: 16, color: const Color(0xFFCBD5E1)),
                      const SizedBox(width: 8),
                      const Text(
                        'Direct delivery route (Point A ➔ Point B)',
                        style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8), fontStyle: FontStyle.italic),
                      ),
                    ],
                  ),
                ),
                // Point B (Customer Destination)
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFFFECACA)),
                      ),
                      child: const Text(
                        'POINT B',
                        style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: Color(0xFFDC2626)),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            d.customerName,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF12344D)),
                          ),
                          const SizedBox(height: 1),
                          Text(
                            d.fullAddress,
                            style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (_driverPosition != null && dest != null) ...[
                  const SizedBox(height: 10),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  const SizedBox(height: 8),
                  Builder(builder: (context) {
                    final metersToB = const Distance().as(
                      LengthUnit.Meter,
                      LatLng(_driverPosition!.latitude, _driverPosition!.longitude),
                      dest,
                    );
                    final distStr = metersToB >= 1000
                        ? '${(metersToB / 1000).toStringAsFixed(1)} km'
                        : '${metersToB.round()} m';
                    return Row(
                      children: [
                        const Icon(Icons.navigation_rounded, size: 14, color: Color(0xFF2563EB)),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Driver GPS active · You are $distStr away from Point B (Customer)',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF1E40AF),
                            ),
                          ),
                        ),
                      ],
                    );
                  }),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Map Preview Canvas with Point A, Point B, and Live Driver Marker
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 280,
              decoration: BoxDecoration(
                border: Border.all(color: DriverColors.borderLight),
              ),
              child: Stack(
                children: [
                  FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: dest ?? _station,
                      initialZoom: 14,
                      initialCameraFit: dest != null
                          ? CameraFit.coordinates(
                              coordinates: [_station, dest],
                              padding: const EdgeInsets.all(40),
                              maxZoom: 15,
                            )
                          : null,
                    ),
                    children: [
                      TileLayer(
                        urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'com.aquaclayne.mobile',
                      ),
                      if (_routePoints.isNotEmpty)
                        PolylineLayer(
                          polylines: [
                            Polyline(
                              points: _routePoints,
                              color: _isFallback ? const Color(0xFFF59E0B) : DriverColors.primary,
                              strokeWidth: 5,
                            ),
                          ],
                        ),
                      MarkerLayer(
                        markers: [
                          // Point B: Customer Destination
                          if (dest != null)
                            Marker(
                              point: dest,
                              width: 72,
                              height: 64,
                              alignment: Alignment.topCenter,
                              child: const PointBMarker(),
                            ),
                          // Point A: Aqua Clayne Station (007 Batisan St., Brgy. Gatid, Sta Cruz Laguna)
                          Marker(
                            point: _station,
                            width: 72,
                            height: 64,
                            alignment: Alignment.topCenter,
                            child: const PointAMarker(),
                          ),
                          // Live Driver Marker
                          if (_driverLatLng != null)
                            Marker(
                              point: _driverLatLng!,
                              width: 72,
                              height: 64,
                              alignment: Alignment.center,
                              child: const DriverLiveMarker(),
                            ),
                        ],
                      ),
                    ],
                  ),
                  if (_isLoading)
                    Positioned.fill(
                      child: Container(
                        color: Colors.black12,
                        child: const Center(
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                    ),
                  // Map Controls (Recenter on Driver or Fit Route)
                  Positioned(
                    right: 12,
                    bottom: 12,
                    child: MapFloatingControls(
                      hasDriver: _driverLatLng != null,
                      onCenterDriver: () {
                        if (_driverLatLng != null) {
                          _mapController.move(_driverLatLng!, 15);
                        }
                      },
                      onFitRoute: () {
                        if (dest != null) {
                          _mapController.fitCamera(CameraFit.coordinates(
                            coordinates: [_station, dest],
                            padding: const EdgeInsets.all(40),
                            maxZoom: 15,
                          ));
                        }
                      },
                    ),
                  ),
                ],
              ),
            ),
          ),
          // Route Error Banner
          if (_routeError != null) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, color: Color(0xFFB45309), size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _routeError!,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 12.5,
                        color: Color(0xFF92400E),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],

          // Route Metrics Bar
          if (_distanceMeters != null && _durationSeconds != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFEAF7FF),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFBCE3FD)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.route_rounded, color: DriverColors.primary, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${(_distanceMeters! / 1000).toStringAsFixed(1)} km · ~${(_durationSeconds! / 60).ceil()} mins (Estimated Travel Time)',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                        color: DriverColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 12),

          // Destination Details Card
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 10,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: const BoxDecoration(
                        color: Color(0xFFFEE2E2),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.location_on,
                        color: Color(0xFFDC2626),
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            d.orderNumber,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: DriverColors.primaryDark,
                            ),
                          ),
                          Text(
                            d.customerName,
                            style: const TextStyle(
                              fontSize: 13,
                              color: DriverColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                const Text(
                  'DELIVERY DESTINATION',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: DriverColors.primaryDark,
                    letterSpacing: 0.5,
                  ),
                ),
                const SizedBox(height: 6),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: DriverColors.mintSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: DriverColors.borderLight),
                  ),
                  child: Text(
                    d.fullAddress,
                    style: const TextStyle(
                      fontSize: 13.5,
                      color: DriverColors.textPrimary,
                      height: 1.4,
                    ),
                  ),
                ),
                if (d.hasCoordinates) ...[
                  const SizedBox(height: 6),
                  Text(
                    'Coordinates: ${d.latitude!.toStringAsFixed(6)}, ${d.longitude!.toStringAsFixed(6)}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: DriverColors.textHint,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Primary Action: Live In-App Navigation
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => NavigationLauncher.launchNavigation(
                context: context,
                latitude: d.latitude,
                longitude: d.longitude,
                address: d.fullAddress,
                customerName: d.customerName,
                deliveryId: d.id,
              ),
              icon: const Icon(Icons.navigation_rounded, size: 20),
              label: const Text('Start Turn-by-Turn Navigation'),
              style: ElevatedButton.styleFrom(
                backgroundColor: DriverColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
                elevation: 0,
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Secondary Action: External Google Maps with Station Origin
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => NavigationLauncher.launchExternalNavigation(
                context: context,
                latitude: d.latitude,
                longitude: d.longitude,
                address: d.fullAddress,
              ),
              icon: const Icon(Icons.open_in_new_rounded, size: 18),
              label: const Text('Open in Google Maps Navigation'),
              style: OutlinedButton.styleFrom(
                foregroundColor: DriverColors.primary,
                side: const BorderSide(color: DriverColors.primary),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Call Customer Button
          if (d.customerPhone.isNotEmpty)
            SizedBox(
              width: double.infinity,
              child: TextButton.icon(
                onPressed: () => NavigationLauncher.launchCall(
                  context,
                  d.customerPhone,
                ),
                icon: const Icon(Icons.phone_outlined, size: 18),
                label: Text('Call Customer (${d.customerPhone})'),
                style: TextButton.styleFrom(
                  foregroundColor: const Color(0xFF64748B),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

