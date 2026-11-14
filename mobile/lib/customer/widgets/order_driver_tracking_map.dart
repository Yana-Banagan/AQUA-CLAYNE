import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:mobile/core/utils/pht_datetime.dart';

import '../../core/api/api_client.dart';
import '../../core/config/station_config.dart';
import '../../core/models/order_model.dart';
import '../../core/services/realtime_service.dart';
import '../data/order_repository.dart';

/// Shows the customer's saved delivery pin and, after dispatch, the driver's
/// latest GPS location.
class OrderDriverTrackingMap extends StatefulWidget {
  const OrderDriverTrackingMap({
    super.key,
    required this.order,
    this.trackDriver = true,
  });

  final OrderModel order;
  final bool trackDriver;

  @override
  State<OrderDriverTrackingMap> createState() => _OrderDriverTrackingMapState();
}

class _OrderDriverTrackingMapState extends State<OrderDriverTrackingMap> {
  Timer? _timer;
  double? _latitude;
  double? _longitude;
  DateTime? _updatedAt;
  DateTime? _lastRouteAttempt;
  List<LatLng> _route = const [];
  LatLng? _routeStart;
  bool _loading = false;
  bool _loadingRoute = false;
  bool _requestInFlight = false;
  VoidCallback? _removeRealtimeListener;
  double? _routeMeters;
  double? _routeSeconds;
  String? _deliveryStatus;
  String? _deliveryId;
  LatLng _station = StationConfig.point;

  LatLng? get _destination {
    final address = widget.order.address;
    final lat = address?.latitude;
    final lng = address?.longitude;
    if (lat == null || lng == null || lat == 0 || lng == 0) return null;
    return LatLng(lat, lng);
  }

  @override
  void initState() {
    super.initState();
    if (widget.trackDriver) {
      _removeRealtimeListener = context.read<RealtimeService>().listenToEntity(
        entityType: 'delivery',
        onSignal: _refresh,
      );
      _startTracking();
    }
  }

  @override
  void didUpdateWidget(covariant OrderDriverTrackingMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    final orderChanged = oldWidget.order.id != widget.order.id;
    if (orderChanged) {
      _latitude = null;
      _longitude = null;
      _updatedAt = null;
      _route = const [];
      _routeStart = null;
      _routeMeters = null;
      _routeSeconds = null;
      _lastRouteAttempt = null;
      _station = StationConfig.point;
      _deliveryId = null;
    }
    if (orderChanged || oldWidget.trackDriver != widget.trackDriver) {
      _timer?.cancel();
      _removeRealtimeListener?.call();
      _removeRealtimeListener = null;
      if (widget.trackDriver) {
        _removeRealtimeListener = context.read<RealtimeService>().listenToEntity(entityType: 'delivery', onSignal: _refresh);
        _startTracking();
      }
    }
  }

  void _startTracking() {
    _loading = true;
    _refresh();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => _refresh());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _removeRealtimeListener?.call();
    super.dispose();
  }

  Future<void> _refresh() async {
    if (!widget.trackDriver || _requestInFlight) return;
    _requestInFlight = true;
    try {
      final raw = await context.read<OrderRepository>().tracking(widget.order.id);
      if (!mounted) return;
      setState(() {
        _latitude = double.tryParse(raw['driver_latitude']?.toString() ?? '');
        _longitude = double.tryParse(raw['driver_longitude']?.toString() ?? '');
        _updatedAt = PhtDateTime.tryParse(raw['location_updated_at']);
        _deliveryStatus = raw['status']?.toString();
        _deliveryId = raw['delivery_id']?.toString();
        _loading = false;
      });
      final driver = _currentDriver;
      final destination = _destination;
      if (destination != null && _deliveryId != null) {
        final movedKm = _routeStart == null
            ? double.infinity
            : (driver == null ? 0 : const Distance().as(LengthUnit.Kilometer, _routeStart!, driver));
        final routeDue = _route.isEmpty
            ? _lastRouteAttempt == null || DateTime.now().difference(_lastRouteAttempt!) >= const Duration(seconds: 30)
            : movedKm >= 0.05 &&
                DateTime.now().difference(_lastRouteAttempt ?? DateTime.fromMillisecondsSinceEpoch(0)) >= const Duration(seconds: 20);
        if (routeDue) {
          _loadRoute();
        }
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    } finally {
      _requestInFlight = false;
    }
  }

  LatLng? get _currentDriver => _latitude != null && _longitude != null
      ? LatLng(_latitude!, _longitude!)
      : null;

  Future<void> _loadRoute() async {
    if (_loadingRoute) return;
    _loadingRoute = true;
    _lastRouteAttempt = DateTime.now();
    try {
      final deliveryId = _deliveryId;
      if (deliveryId == null) return;
      final data = await context.read<ApiClient>().get('/deliveries/$deliveryId/route');
      final coordinates = data is Map ? data['coordinates'] as List? : null;
      if (coordinates == null || !mounted) return;
      final points = coordinates
          .whereType<List>()
          .where((point) => point.length >= 2)
          .map((point) => LatLng(
                (point[1] as num).toDouble(),
                (point[0] as num).toDouble(),
              ))
          .toList();
      if (points.length >= 2 && mounted) {
        final driver = _currentDriver;
        setState(() {
          _route = points;
          _routeStart = driver;
          _routeMeters = (data['distance'] as num?)?.toDouble();
          _routeSeconds = (data['duration'] as num?)?.toDouble();
          final origin = data['origin'];
          if (origin is Map) _station = LatLng((origin['latitude'] as num).toDouble(), (origin['longitude'] as num).toDouble());
        });
      }
    } catch (_) {
      // Keep the live markers visible if routing is unavailable.
    } finally {
      _loadingRoute = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final driver = _currentDriver;
    final destination = _destination;
    final center = driver ?? destination ?? _station;
    final markers = <Marker>[
      if (destination != null)
        Marker(
          point: destination,
          width: 42,
          height: 42,
          child: const Icon(Icons.location_pin, color: Color(0xFFE53935), size: 42),
        ),
      if (driver != null)
        Marker(
          point: driver,
          width: 44,
          height: 44,
          child: Container(
            decoration: const BoxDecoration(color: Color(0xFF0878C9), shape: BoxShape.circle),
            child: const Icon(Icons.local_shipping_rounded, color: Colors.white, size: 24),
          ),
        ),
      Marker(
        point: _station,
        width: 42,
        height: 42,
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(color: Colors.black26, blurRadius: 4),
            ],
          ),
          padding: const EdgeInsets.all(4),
          child: const Icon(Icons.water_drop, color: Color(0xFF059669), size: 28),
        ),
      ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 14),
        Row(children: [
          Icon(
            widget.trackDriver ? Icons.location_searching_rounded : Icons.location_on_outlined,
            color: const Color(0xFF0878C9),
            size: 18,
          ),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              widget.trackDriver ? 'Live driver tracking' : 'Delivery location',
              style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF12344D)),
            ),
          ),
          if (driver != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFE8F7EF), borderRadius: BorderRadius.circular(20)),
              child: const Text('LIVE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF16804A))),
            ),
        ]),
        const SizedBox(height: 10),
        if (driver == null && destination == null)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF1F7FB),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFDCEAF4)),
            ),
            child: const Row(children: [
              Icon(Icons.location_off_outlined, color: Color(0xFF7890A3), size: 19),
              SizedBox(width: 8),
              Expanded(child: Text('A map pin is not available for this delivery address yet.', style: TextStyle(fontSize: 11.5, color: Color(0xFF4A6B82))),),
            ]),
          )
        else ...[
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: SizedBox(
              height: 190,
              child: FlutterMap(
                  key: ValueKey(widget.order.id),
                options: MapOptions(
                  initialCenter: center,
                  initialZoom: 14,
                  initialCameraFit: _route.length >= 2
                      ? CameraFit.coordinates(
                          coordinates: _route,
                          padding: const EdgeInsets.all(28),
                          maxZoom: 15,
                        )
                      : null,
                ),
                children: [
                  TileLayer(
                    urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.aquaclayne.mobile',
                  ),
                  if (_route.length >= 2)
                    PolylineLayer(
                      polylines: [
                        Polyline(
                          points: _route,
                          color: const Color(0xFF0878C9),
                          strokeWidth: 4,
                        ),
                      ],
                    ),
                  if (markers.isNotEmpty) MarkerLayer(markers: markers),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),
          if (driver != null && _routeMeters != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text('${(_routeMeters! / 1000).toStringAsFixed(1)} km route  ·  ${((_routeSeconds ?? 0) / 60).ceil()} min estimated travel time', style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF0878C9))),
            ),
          if (widget.trackDriver && driver != null && _route.isEmpty && !_loadingRoute)
            const Padding(
              padding: EdgeInsets.only(bottom: 6),
              child: Text('Road route is unavailable right now. Driver location is still updating.', style: TextStyle(fontSize: 11.5, color: Color(0xFF8A5A00))),
            ),
          if (_deliveryStatus != null)
            Padding(padding: const EdgeInsets.only(bottom: 6), child: Text('Status: ${_deliveryStatus!.replaceAll('_', ' ')}', style: const TextStyle(fontSize: 11.5, color: Color(0xFF4A6B82)))),
          Row(children: [
            Icon(
              driver == null ? Icons.location_on_outlined : Icons.local_shipping_outlined,
              size: 16,
              color: driver == null ? const Color(0xFF7890A3) : const Color(0xFF0878C9),
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                driver == null
                    ? (widget.trackDriver
                        ? (_loading ? 'Getting driver location…' : 'Waiting for the driver to start sharing location.')
                        : 'Your saved delivery pin is shown on the map.')
                    : 'Driver location updates automatically${_updatedAt == null ? '' : ' · ${_updatedAt!.hour.toString().padLeft(2, '0')}:${_updatedAt!.minute.toString().padLeft(2, '0')}'}',
                style: const TextStyle(fontSize: 11.5, color: Color(0xFF4A6B82)),
              ),
            ),
            if (widget.trackDriver)
              IconButton(
                tooltip: 'Refresh driver location',
                onPressed: _refresh,
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.refresh_rounded, size: 18, color: Color(0xFF0878C9)),
              ),
          ]),
        ],
      ],
    );
  }
}
