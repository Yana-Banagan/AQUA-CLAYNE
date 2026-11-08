import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api/api_client.dart';
import '../../core/config/station_config.dart';
import '../../core/theme/driver_colors.dart';
import '../data/delivery_repository.dart';
import '../widgets/route_point_markers.dart';

/// In-app live delivery map. The driver marker follows the phone's GPS stream.
class LiveNavigationScreen extends StatefulWidget {
  const LiveNavigationScreen({
    super.key,
    required this.latitude,
    required this.longitude,
    required this.address,
    this.customerName,
    this.deliveryId,
    this.deliveryStatus,
  });

  final double latitude;
  final double longitude;
  final String address;
  final String? customerName;
  final String? deliveryId;
  final String? deliveryStatus;

  @override
  State<LiveNavigationScreen> createState() => _LiveNavigationScreenState();
}

class _LiveNavigationScreenState extends State<LiveNavigationScreen> {
  final MapController _map = MapController();
  StreamSubscription<Position>? _positionSubscription;
  Position? _driverPosition;
  List<LatLng> _route = const [];
  String? _locationIssue;
  String? _locationSharingIssue;
  bool _loadingRoute = false;
  DateTime? _lastLocationUpload;
  LatLng? _routeStart;
  DateTime? _lastRouteAttempt;
  double? _routeMeters;
  double? _routeSeconds;
  List<Map<String, dynamic>> _steps = const [];
  LatLng _station = StationConfig.point;
  String? _routeIssue;
  bool _routeUnavailableNotified = false;
  bool _isFallbackRoute = false;
  bool _showAllSteps = false;

  LatLng get _customer => LatLng(widget.latitude, widget.longitude);

  @override
  void initState() {
    super.initState();
    _enableLiveLocation();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    super.dispose();
  }

  Future<void> _enableLiveLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        if (mounted) setState(() => _locationIssue = 'Location services are turned off. Enable GPS to use live navigation.');
        return;
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        if (mounted) setState(() => _locationIssue = permission == LocationPermission.deniedForever ? 'Location permission is permanently disabled. Enable it in your phone settings.' : 'Location permission is required for live navigation.');
        return;
      }
      if (!mounted) return;
      setState(() => _locationIssue = null);
      await _positionSubscription?.cancel();
      _positionSubscription = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 5),
      ).listen((position) {
        if (!mounted) return;
        setState(() => _driverPosition = position);
        final latest = LatLng(position.latitude, position.longitude);
        final routeDue = _routeStart == null
            ? _lastRouteAttempt == null || DateTime.now().difference(_lastRouteAttempt!) >= const Duration(seconds: 30)
            : const Distance().as(LengthUnit.Meter, _routeStart!, latest) >= 75 &&
                DateTime.now().difference(_lastRouteAttempt ?? DateTime.fromMillisecondsSinceEpoch(0)) >= const Duration(seconds: 20);
        if (routeDue) _loadRoute(position);
        final deliveryId = widget.deliveryId;
        final now = DateTime.now();
        if (deliveryId != null &&
            (_lastLocationUpload == null ||
                now.difference(_lastLocationUpload!) >= const Duration(seconds: 10))) {
          _lastLocationUpload = now;
          _publishLocation(deliveryId, position);
        }
      }, onError: (_) {
        if (mounted) setState(() => _locationIssue = 'Unable to read your live location. Check GPS and location permission.');
      });
    } catch (_) {
      if (mounted) setState(() => _locationIssue = 'Could not start live location. Check GPS permission and try again.');
    }
  }

  Future<void> _publishLocation(String deliveryId, Position position) async {
    try {
      await context.read<DeliveryRepository>().updateLocation(
            deliveryId,
            position.latitude,
            position.longitude,
          );
      if (mounted && _locationSharingIssue != null) {
        setState(() => _locationSharingIssue = null);
      }
    } catch (_) {
      if (mounted && _locationSharingIssue == null) {
        setState(() => _locationSharingIssue = 'Live location is not reaching the customer. Check your connection.');
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Live location could not be shared with the customer.')),
        );
      }
    }
  }

  Future<void> _loadRoute(Position position) async {
    if (_loadingRoute) return;
    _lastRouteAttempt = DateTime.now();
    setState(() => _loadingRoute = true);
    try {
      final deliveryId = widget.deliveryId;
      if (deliveryId == null) throw StateError('Delivery reference is missing.');
      final data = await context.read<ApiClient>().get('/deliveries/$deliveryId/route');
      final coordinates = data is Map ? data['coordinates'] as List? : null;
      if (coordinates != null && mounted) {
        setState(() {
          _route = coordinates.map((point) => LatLng((point[1] as num).toDouble(), (point[0] as num).toDouble())).toList();
          _routeStart = LatLng(position.latitude, position.longitude);
          _routeMeters = (data['distance'] as num?)?.toDouble();
          _routeSeconds = (data['duration'] as num?)?.toDouble();
          _routeIssue = null;
          _isFallbackRoute = data['is_fallback'] == true;
          final origin = data['origin'];
          if (origin is Map && origin['latitude'] != null && origin['longitude'] != null) {
            _station = LatLng((origin['latitude'] as num).toDouble(), (origin['longitude'] as num).toDouble());
          } else {
            _station = StationConfig.point;
          }
          _steps = (data['steps'] as List? ?? []).whereType<Map>().map((s) => Map<String, dynamic>.from(s)).toList();
        });
      }
      if (coordinates != null && mounted) {
        _map.fitCamera(CameraFit.coordinates(coordinates: [_station, _customer], padding: const EdgeInsets.all(56), maxZoom: 15));
      }
    } catch (e) {
      if (mounted) setState(() => _routeIssue = e.toString().contains('422') ? 'This delivery address needs a valid map pin. Ask an administrator to correct it.' : 'Road routing is temporarily unavailable. Check your connection and retry.');
      if (mounted && _route.isEmpty && !_routeUnavailableNotified) {
        _routeUnavailableNotified = true;
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Road routing is temporarily unavailable. Live location will continue to update.')));
      }
    } finally {
      if (mounted) setState(() => _loadingRoute = false);
    }
  }

  Future<void> _openGoogleNavigation() async {
    // Origin is ALWAYS strictly fixed to the official Aqua Clayne Station
    final origin = _station;
    final uri = Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'origin': '${origin.latitude},${origin.longitude}',
      'destination': '${widget.latitude},${widget.longitude}',
      'travelmode': 'driving',
      'dir_action': 'navigate',
    });
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not open external maps navigation.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final driver = _driverPosition == null ? null : LatLng(_driverPosition!.latitude, _driverPosition!.longitude);
    final driverDistanceToB = driver != null ? const Distance().as(LengthUnit.Meter, driver, _customer) : null;
    final driverDistanceToStr = driverDistanceToB == null
        ? null
        : (driverDistanceToB >= 1000
            ? '${(driverDistanceToB / 1000).toStringAsFixed(1)} km'
            : '${driverDistanceToB.round()} m');

    return Theme(
      data: DriverColors.theme(Theme.of(context)),
      child: Scaffold(
      appBar: AppBar(
        title: const Text('Live Navigation'),
        actions: [
          IconButton(
            tooltip: 'Turn-by-turn navigation in Google Maps',
            onPressed: _openGoogleNavigation,
            icon: const Icon(Icons.directions_rounded),
          ),
        ],
      ),
      body: _locationIssue != null ? _LocationRequired(message: _locationIssue!, onRetry: _enableLiveLocation) : Stack(children: [
        FlutterMap(
          mapController: _map,
          options: MapOptions(
            initialCenter: driver ?? _station,
            initialZoom: driver == null ? 14 : 15,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.aquaclayne.mobile',
            ),
            if (_route.isNotEmpty)
              PolylineLayer(
                polylines: [
                  Polyline(
                    points: _route,
                    color: _isFallbackRoute ? const Color(0xFFF59E0B) : DriverColors.primary,
                    strokeWidth: 5,
                  ),
                ],
              ),
            MarkerLayer(markers: [
              // Point B: Customer Destination
              Marker(
                point: _customer,
                width: 72,
                height: 64,
                alignment: Alignment.topCenter,
                child: const PointBMarker(),
              ),
              // Point A: Aqua Clayne Station (Batisan, Gatid, Santa Cruz, Laguna)
              Marker(
                point: _station,
                width: 72,
                height: 64,
                alignment: Alignment.topCenter,
                child: const PointAMarker(),
              ),
              // Driver's Live Location
              if (driver != null)
                Marker(
                  point: driver,
                  width: 72,
                  height: 64,
                  alignment: Alignment.center,
                  child: const DriverLiveMarker(),
                ),
            ]),
          ],
        ),

        // Point A to Point B Route Header with Live Driver GPS Status
        Positioned(
          top: 12,
          left: 14,
          right: 14,
          child: SafeArea(
            bottom: false,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xF7FFFFFF),
                borderRadius: BorderRadius.circular(16),
                boxShadow: const [
                  BoxShadow(color: Color(0x14000000), blurRadius: 10, offset: Offset(0, 2)),
                ],
                border: Border.all(color: const Color(0xFFD5EAF8)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      // Point A tag
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE8F7EF),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: const Color(0xFFA7F3D0)),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'A',
                              style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF059669), fontSize: 11),
                            ),
                            SizedBox(width: 4),
                            Text(
                              '007 Batisan St., Brgy. Gatid',
                              style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF065F46), fontSize: 11),
                            ),
                          ],
                        ),
                      ),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 6),
                        child: Icon(Icons.arrow_forward_rounded, size: 14, color: Color(0xFF94A3B8)),
                      ),
                      // Point B tag
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: const Color(0xFFFECACA)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Text(
                                'B',
                                style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFFDC2626), fontSize: 11),
                              ),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  widget.customerName ?? 'Customer Destination',
                                  style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF991B1B), fontSize: 11),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (_loadingRoute) ...[
                        const SizedBox(width: 8),
                        const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Container(
                        width: 7,
                        height: 7,
                        decoration: BoxDecoration(
                          color: driver != null ? const Color(0xFF2563EB) : const Color(0xFF10B981),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          driverDistanceToStr != null
                              ? 'Point A ➔ Point B Route · Driver is $driverDistanceToStr from Point B'
                              : 'Official Route: Point A (007 Batisan St., Brgy. Gatid) ➔ Point B (Customer)',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                            color: driverDistanceToStr != null ? const Color(0xFF1E40AF) : const Color(0xFF475569),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),

        // Map Floating Controls (Recenter Driver, Fit Route A ➔ B)
        Positioned(
          right: 16,
          bottom: _steps.isNotEmpty && _showAllSteps ? 290 : 230,
          child: MapFloatingControls(
            hasDriver: driver != null,
            onCenterDriver: () {
              if (driver != null) {
                _map.move(driver, 16);
              }
            },
            onFitRoute: () {
              _map.fitCamera(CameraFit.coordinates(
                coordinates: [_station, _customer],
                padding: const EdgeInsets.all(56),
                maxZoom: 15,
              ));
            },
          ),
        ),

        // Bottom Navigation Card
        Positioned(
          left: 16,
          right: 16,
          bottom: 18,
          child: SafeArea(
            top: false,
            child: Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              elevation: 4,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDC2626),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Text(
                            'POINT B',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                              fontSize: 9,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            widget.customerName ?? 'Customer Destination',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.navigation, color: DriverColors.primary),
                          tooltip: 'Open in Google Maps',
                          onPressed: _openGoogleNavigation,
                          visualDensity: VisualDensity.compact,
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(widget.address, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, color: Color(0xFF334155))),
                    if (widget.deliveryStatus != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          'Status: ${widget.deliveryStatus!.replaceAll('_', ' ').toUpperCase()}',
                          style: const TextStyle(color: DriverColors.textSecondary, fontSize: 11, fontWeight: FontWeight.w600),
                        ),
                      ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEAF7FF),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.access_time_rounded, size: 16, color: DriverColors.primary),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              _routeMeters == null
                                  ? (_routeIssue ?? 'Official route starts at Aqua Clayne Station (Point A).')
                                  : 'Route A➔B: ${(_routeMeters! / 1000).toStringAsFixed(1)} km · ~${(_routeSeconds! / 60).ceil()} min${driverDistanceToStr != null ? ' (Driver is $driverDistanceToStr to B)' : ''}',
                              style: const TextStyle(color: DriverColors.primary, fontWeight: FontWeight.w700, fontSize: 12),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (_isFallbackRoute)
                      const Padding(
                        padding: EdgeInsets.only(top: 4),
                        child: Text('ℹ️ Direct route estimate active. Road navigation updating…', style: TextStyle(color: Color(0xFFD97706), fontSize: 11)),
                      ),
                    if (_steps.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      InkWell(
                        onTap: () => setState(() => _showAllSteps = !_showAllSteps),
                        child: Row(
                          children: [
                            Text(
                              'Turn-by-turn instructions (${_steps.length} steps)',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: DriverColors.primary),
                            ),
                            const Spacer(),
                            Icon(_showAllSteps ? Icons.expand_less : Icons.expand_more, size: 18, color: DriverColors.primary),
                          ],
                        ),
                      ),
                      ConstrainedBox(
                        constraints: BoxConstraints(maxHeight: _showAllSteps ? 140 : 45),
                        child: SingleChildScrollView(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: (_showAllSteps ? _steps : _steps.take(2)).map((s) {
                              final dist = (s['distance'] as num?)?.toDouble() ?? 0;
                              final distStr = dist > 1000 ? '${(dist / 1000).toStringAsFixed(1)} km' : '${dist.round()} m';
                              return Padding(
                                padding: const EdgeInsets.only(top: 3),
                                child: Text(
                                  '• ${s['instruction'] ?? 'Proceed'} ($distStr)',
                                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF475569)),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ]),
      ),
    );
  }
}

class _LocationRequired extends StatelessWidget {
  const _LocationRequired({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(child: Padding(padding: const EdgeInsets.all(28), child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.location_off_rounded, size: 56, color: DriverColors.primary), const SizedBox(height: 16), const Text('Enable location for live navigation', textAlign: TextAlign.center, style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold)), const SizedBox(height: 8), Text(message, textAlign: TextAlign.center), const SizedBox(height: 18), ElevatedButton.icon(onPressed: onRetry, icon: const Icon(Icons.my_location), label: const Text('Try again')), TextButton(onPressed: Geolocator.openAppSettings, child: const Text('Open app settings'))])));
}
