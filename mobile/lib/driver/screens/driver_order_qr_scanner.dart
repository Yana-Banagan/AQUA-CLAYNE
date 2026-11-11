import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';

class DriverOrderQrScanner extends StatefulWidget {
  const DriverOrderQrScanner({super.key});

  @override
  State<DriverOrderQrScanner> createState() => _DriverOrderQrScannerState();
}

class _DriverOrderQrScannerState extends State<DriverOrderQrScanner> {
  final _api = ApiClient(TokenStorage());
  final _seen = <String>{};
  final _results = <String>[];
  bool _busy = false;
  bool _paused = false;
  DateTime _lastScan = DateTime.fromMillisecondsSinceEpoch(0);

  Future<void> _scan(String value) async {
    if (_busy || _paused || DateTime.now().difference(_lastScan).inMilliseconds < 1500 || !_seen.add(value)) return;
    _lastScan = DateTime.now();
    setState(() => _busy = true);
    try {
      final response = await _api.post('/driver/orders/scan-qr', {'qr_code': value}, auth: true);
      final data = response is Map && response['data'] is Map ? response['data'] as Map : const {};
      final name = data['customer_name']?.toString() ?? 'Customer';
      final number = data['order_number']?.toString() ?? 'Order';
      final gallons = data['expected_gallons']?.toString() ?? '0';
      if (!mounted) return;
      setState(() => _results.insert(0, '$number • $name • $gallons gallons'));
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$name • $number picked up')));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Scan Customer Order QR')),
        body: Column(children: [
          Expanded(flex: 3, child: Stack(alignment: Alignment.center, children: [
            if (!_paused) MobileScanner(onDetect: (capture) {
              final raw = capture.barcodes.isEmpty ? null : capture.barcodes.first.rawValue;
              if (raw != null) _scan(raw);
            }),
            Container(width: 250, height: 180, decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(18))),
            if (_busy) const CircularProgressIndicator(color: Colors.white),
          ])),
          Padding(padding: const EdgeInsets.all(12), child: Row(children: [
            Expanded(child: OutlinedButton.icon(onPressed: () => setState(() => _paused = !_paused), icon: Icon(_paused ? Icons.play_arrow : Icons.pause), label: Text(_paused ? 'Resume Scanner' : 'Pause Scanner'))),
            const SizedBox(width: 12),
            Expanded(child: FilledButton.icon(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close), label: const Text('Close'))),
          ])),
          Text('Scanned Orders: ${_results.length}', style: const TextStyle(fontWeight: FontWeight.bold)),
          Expanded(flex: 2, child: ListView.builder(itemCount: _results.length, itemBuilder: (_, i) => ListTile(leading: const Icon(Icons.check_circle, color: Colors.green), title: Text(_results[i])))),
        ]),
      );
}
