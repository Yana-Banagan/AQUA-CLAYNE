import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../core/models/address_model.dart';
import '../../core/utils/delivery_restriction.dart';
import '../../customer/providers/address_provider.dart';

class AddAddressScreen extends StatefulWidget {
  final AddressModel? address;
  const AddAddressScreen({super.key, this.address});
  @override
  State<AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<AddAddressScreen> {
  static const _center = LatLng(14.2814, 121.4169); // Santa Cruz, Laguna
  final _formKey = GlobalKey<FormState>();
  final _search = TextEditingController();
  late final TextEditingController _label;
  late final TextEditingController _street;
  late final TextEditingController _barangay;
  late final TextEditingController _city;
  late final TextEditingController _province;
  late final TextEditingController _zip;
  final _map = MapController();
  LatLng? _pin;
  bool _saving = false;
  bool _locating = false;
  bool _isDefault = false;
  String? _areaError;
  List<Map<String, dynamic>> _searchResults = [];

  bool get _editing => widget.address != null;
  // Delivery restriction: Gatid, Duhat, and Bubukal in Santa Cruz, Laguna.
  bool _inServiceArea(LatLng point, {String? barangay, String? addressLine}) =>
      DeliveryRestriction.isPointAllowed(
        point,
        barangay: barangay ?? _barangay.text,
        addressLine: addressLine ?? _street.text,
      );

  @override
  void initState() {
    super.initState();
    final a = widget.address;
    _label = TextEditingController(text: a?.label ?? 'Home');
    _street = TextEditingController(text: a?.addressLine ?? '');
    _barangay = TextEditingController(text: a?.barangay ?? '');
    _city = TextEditingController(text: a?.city ?? 'Santa Cruz');
    _province = TextEditingController(text: a?.province ?? 'Laguna');
    _zip = TextEditingController(text: a?.zipCode ?? '4009');
    _isDefault = a?.isDefault ?? false;
    if (a?.latitude != null && a?.longitude != null)
      _pin = LatLng(a!.latitude!, a.longitude!);
  }

  @override
  void dispose() {
    for (final c in [
      _search,
      _label,
      _street,
      _barangay,
      _city,
      _province,
      _zip,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _searchLocation() async {
    final query = _search.text.trim();
    if (query.isEmpty) return;
    setState(() {
      _locating = true;
      _searchResults = [];
    });
    try {
      final uri = Uri.https('nominatim.openstreetmap.org', '/search', {
        // Free-form search supports house numbers, streets, sitios and
        // barangays. The bounding box prevents similarly named places from
        // elsewhere in the Philippines being selected.
        'q': '$query, Santa Cruz, Laguna, Philippines',
        'format': 'jsonv2',
        'limit': '5',
        'addressdetails': '1',
        'countrycodes': 'ph',
        'viewbox': '121.37,14.34,121.47,14.22',
        'bounded': '1',
      });
      final response = await http.get(
        uri,
        headers: {'User-Agent': 'AquaClayneDelivery/1.0'},
      );
      if (response.statusCode != 200) throw Exception('Location search failed');
      final results = (jsonDecode(response.body) as List)
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((item) {
            final point = LatLng(
              double.tryParse(item['lat']?.toString() ?? '') ?? 0,
              double.tryParse(item['lon']?.toString() ?? '') ?? 0,
            );
            return _inServiceArea(point);
          })
          .toList();
      if (results.isEmpty) throw Exception('Location not found');

      // A precise house-number or sitio search commonly returns one match.
      // Select it immediately so the pin and address form are filled without
      // an extra tap. When there are several possible places, show the choices
      // so the customer remains in control of the delivery pin.
      if (results.length == 1) {
        final result = results.first;
        await _select(
          LatLng(
            double.parse(result['lat'].toString()),
            double.parse(result['lon'].toString()),
          ),
          address: result['address'] is Map
              ? Map<String, dynamic>.from(result['address'] as Map)
              : null,
          displayName: result['display_name']?.toString(),
        );
      } else if (mounted) {
        setState(() => _searchResults = results);
      }
    } catch (_) {
      if (mounted)
        _message('Location not found. Try a more specific Santa Cruz address.');
    }
    if (mounted) setState(() => _locating = false);
  }

  Future<void> _select(
    LatLng point, {
    Map<String, dynamic>? address,
    String? displayName,
  }) async {
    final data = address ?? await _reverse(point);
    final b =
        (data['sitio'] ??
                data['hamlet'] ??
                data['village'] ??
                data['suburb'] ??
                data['barangay'] ??
                '')
            .toString();
    final road =
        (data['road'] ?? data['pedestrian'] ?? data['residential'] ?? '')
            .toString();

    if (!_inServiceArea(point, barangay: b, addressLine: road)) {
      setState(() => _areaError = _outsideMessage);
      _message(_outsideMessage);
      return;
    }
    setState(() {
      _pin = point;
      _areaError = null;
    });
    _map.move(point, 16);
    try {
      _fill(data, displayName: displayName);
    } catch (_) {
      if (mounted)
        _message(
          'Pin selected. Please review and complete the address details.',
        );
    }
  }

  /// Pins the map from the form values while preserving the address the
  /// customer entered. This is intentionally separate from [_select], whose
  /// reverse-geocoding flow fills fields from a map/search result.
  Future<void> _pinFromAddressFields() async {
    final street = _street.text.trim();
    final zip = _zip.text.trim();
    if (street.isEmpty || zip.length < 4) {
      _message('Enter the street or house number and ZIP code first.');
      return;
    }

    final query = [
      street,
      _barangay.text.trim(),
      _city.text.trim().isEmpty ? 'Santa Cruz' : _city.text.trim(),
      _province.text.trim().isEmpty ? 'Laguna' : _province.text.trim(),
      zip,
      'Philippines',
    ].where((part) => part.isNotEmpty).join(', ');

    setState(() => _locating = true);
    try {
      final uri = Uri.https('nominatim.openstreetmap.org', '/search', {
        'q': query,
        'format': 'jsonv2',
        'limit': '1',
        'countrycodes': 'ph',
        'viewbox': '121.37,14.34,121.47,14.22',
        'bounded': '1',
      });
      final response = await http.get(
        uri,
        headers: {'User-Agent': 'AquaClayneDelivery/1.0'},
      );
      if (response.statusCode != 200) throw Exception('Address lookup failed');
      final results = jsonDecode(response.body) as List;
      if (results.isEmpty) throw Exception('Address not found');

      final result = results.first as Map;
      final point = LatLng(
        double.parse(result['lat'].toString()),
        double.parse(result['lon'].toString()),
      );
      if (!_inServiceArea(point)) {
        setState(() => _areaError = _outsideMessage);
        _message(_outsideMessage);
        return;
      }

      if (!mounted) return;
      setState(() {
        _pin = point;
        _areaError = null;
        _searchResults = [];
      });
      _map.move(point, 16);
      _message('Map pin updated from your address.');
    } catch (_) {
      if (mounted) {
        _message(
          'We could not find that address. Check the house number, sitio, and ZIP code.',
        );
      }
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<Map<String, dynamic>> _reverse(LatLng point) async {
    final uri = Uri.https('nominatim.openstreetmap.org', '/reverse', {
      'lat': '${point.latitude}',
      'lon': '${point.longitude}',
      'format': 'jsonv2',
      'addressdetails': '1',
    });
    final response = await http.get(
      uri,
      headers: {'User-Agent': 'AquaClayneDelivery/1.0'},
    );
    return (jsonDecode(response.body) as Map<String, dynamic>)['address']
        as Map<String, dynamic>;
  }

  void _fill(Map<String, dynamic> a, {String? displayName}) => setState(() {
    final road =
        a['road'] ??
        a['pedestrian'] ??
        a['residential'] ??
        a['neighbourhood'] ??
        a['building'] ??
        a['amenity'] ??
        '';
    final house = a['house_number'] ?? '';
    final mappedStreet = '$house $road'.trim();
    // Some rural sitios do not have a road tag in OpenStreetMap. In that
    // case, retain the first part of the matched address instead of leaving
    // the required street field blank.
    final searchStreet = displayName?.split(',').first.trim() ?? '';
    _street.text = mappedStreet.isNotEmpty ? mappedStreet : searchStreet;
    // Nominatim labels small localities differently depending on the source
    // data. Include sitio/locality before falling back to the barangay-level
    // village/suburb fields.
    _barangay.text =
        (a['sitio'] ??
                a['hamlet'] ??
                a['village'] ??
                a['suburb'] ??
                a['barangay'] ??
                '')
            .toString();
    _city.text = (a['city'] ?? a['town'] ?? a['municipality'] ?? 'Santa Cruz')
        .toString();
    _province.text = (a['state'] ?? 'Laguna').toString();
    _zip.text = (a['postcode'] ?? '4009').toString();
  });

  Future<void> _save() async {
    if (_pin == null) {
      _message('Select your delivery location on the map first.');
      return;
    }
    if (!_inServiceArea(
      _pin!,
      barangay: _barangay.text,
      addressLine: _street.text,
    )) {
      _message(_outsideMessage);
      return;
    }
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    final payload = {
      'label': _label.text.trim(),
      'address_line': _street.text.trim(),
      'barangay': _barangay.text.trim(),
      'city': _city.text.trim(),
      'province': _province.text.trim(),
      'zip_code': _zip.text.trim(),
      'latitude': _pin!.latitude,
      'longitude': _pin!.longitude,
      'is_default': _isDefault,
    };
    final provider = context.read<AddressProvider>();
    final id = _editing ? widget.address!.id : await provider.add(payload);
    final ok = _editing ? await provider.update(id!, payload) : id != null;
    if (!mounted) return;
    setState(() => _saving = false);
    if (ok) {
      Navigator.pop(context, id);
    } else {
      _message(provider.error ?? 'Failed to save address. Please try again.');
    }
  }

  void _message(String text) => ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(text), backgroundColor: const Color(0xFFC62828)),
  );
  static const _outsideMessage = DeliveryRestriction.restrictionMessage;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Container(
      decoration: const BoxDecoration(
        image: DecorationImage(
          image: AssetImage('assets/images/customer_dashboard_bg.png'),
          fit: BoxFit.cover,
        ),
      ),
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(
                      Icons.arrow_back_rounded,
                      color: Color(0xFF0878C9),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _editing
                              ? 'Edit Delivery Address'
                              : 'Add Delivery Address',
                          style: const TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF12344D),
                          ),
                        ),
                        const Text(
                          'Gatid, Duhat, and Bubukal, Santa Cruz, Laguna',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xFF52718B),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 22),
                  children: [
                    _mapCard(),
                    const SizedBox(height: 16),
                    _addressCard(),
                    const SizedBox(height: 14),
                    _defaultCard(),
                  ],
                ),
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _saving ? null : _save,
                    child: _saving
                        ? const CircularProgressIndicator(color: Colors.white)
                        : Text(
                            _editing
                                ? 'Update Address'
                                : 'Save Delivery Address',
                          ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _mapCard() => Container(
    padding: const EdgeInsets.all(12),
    decoration: _card(),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Pin Your Delivery Location',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: Color(0xFF12344D),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _search,
                onSubmitted: (_) => _searchLocation(),
                onChanged: (_) {
                  if (_searchResults.isNotEmpty)
                    setState(() => _searchResults = []);
                },
                decoration: const InputDecoration(
                  hintText: 'Search house no., street or sitio',
                  prefixIcon: Icon(Icons.search),
                  border: OutlineInputBorder(),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              onPressed: _locating ? null : _searchLocation,
              icon: _locating
                  ? const CircularProgressIndicator()
                  : const Icon(Icons.search, color: Color(0xFF0878C9)),
            ),
          ],
        ),
        if (_searchResults.isNotEmpty) ...[
          const SizedBox(height: 8),
          Container(
            constraints: const BoxConstraints(maxHeight: 150),
            decoration: BoxDecoration(
              color: const Color(0xFFF7FAFD),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFD5EAF8)),
            ),
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: _searchResults.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (_, index) {
                final result = _searchResults[index];
                return ListTile(
                  dense: true,
                  leading: const Icon(
                    Icons.location_on_outlined,
                    color: Color(0xFF0878C9),
                  ),
                  title: Text(
                    result['display_name']?.toString() ?? 'Selected location',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12),
                  ),
                  onTap: () async {
                    setState(() => _searchResults = []);
                    await _select(
                      LatLng(
                        double.parse(result['lat'].toString()),
                        double.parse(result['lon'].toString()),
                      ),
                      address: result['address'] is Map
                          ? Map<String, dynamic>.from(result['address'] as Map)
                          : null,
                      displayName: result['display_name']?.toString(),
                    );
                  },
                );
              },
            ),
          ),
        ],
        const SizedBox(height: 10),
        SizedBox(
          height: 270,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: FlutterMap(
              mapController: _map,
              options: MapOptions(
                initialCenter: _pin ?? _center,
                initialZoom: 13,
                onTap: (_, point) => _select(point),
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.aquaclayne.mobile',
                ),
                MarkerLayer(
                  markers: _pin == null
                      ? []
                      : [
                          Marker(
                            point: _pin!,
                            width: 46,
                            height: 46,
                            child: const Icon(
                              Icons.location_pin,
                              color: Color(0xFFC62828),
                              size: 46,
                            ),
                          ),
                        ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _pin == null
              ? 'Tap the map to drop a pin.'
              : 'Pin selected — review the generated address below.',
          style: TextStyle(
            fontSize: 12,
            color: _areaError == null
                ? const Color(0xFF52718B)
                : const Color(0xFFC62828),
          ),
        ),
      ],
    ),
  );

  Widget _addressCard() => Container(
    padding: const EdgeInsets.all(16),
    decoration: _card(),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Review Address',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: Color(0xFF12344D),
          ),
        ),
        const SizedBox(height: 12),
        _field(_label, 'Address Label', required: true),
        _field(_street, 'Street / House / Building', required: true),
        _field(_barangay, 'Barangay'),
        Row(
          children: [
            Expanded(
              child: _field(_city, 'City / Municipality', required: true),
            ),
            const SizedBox(width: 10),
            Expanded(child: _field(_province, 'Province', required: true)),
          ],
        ),
        _field(
          _zip,
          'ZIP Code',
          number: true,
          onChanged: (value) {
            if (value.trim().length == 4) _pinFromAddressFields();
          },
        ),
      ],
    ),
  );

  Widget _defaultCard() => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
    decoration: _card(),
    child: SwitchListTile(
      value: _isDefault,
      onChanged: (v) => setState(() => _isDefault = v),
      title: const Text(
        'Set as Default Address',
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: const Text('Use this address for future orders'),
      activeThumbColor: const Color(0xFF0878C9),
    ),
  );
  Widget _field(
    TextEditingController c,
    String label, {
    bool required = false,
    bool number = false,
    ValueChanged<String>? onChanged,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextFormField(
      controller: c,
      keyboardType: number ? TextInputType.number : TextInputType.text,
      onChanged: onChanged,
      validator: required
          ? (v) => v?.trim().isEmpty == true ? '$label is required' : null
          : null,
      decoration: InputDecoration(
        labelText: label,
        filled: true,
        fillColor: const Color(0xFFF7FAFD),
        border: const OutlineInputBorder(),
      ),
    ),
  );
  BoxDecoration _card() => BoxDecoration(
    color: Colors.white.withValues(alpha: .96),
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: const Color(0xFFD5EAF8)),
  );
}
