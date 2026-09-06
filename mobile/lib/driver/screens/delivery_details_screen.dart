import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/services/token_storage.dart';
import '../data/delivery_repository.dart';

class DeliveryDetailsScreen extends StatefulWidget {
  final String deliveryId;

  const DeliveryDetailsScreen({super.key, required this.deliveryId});

  @override
  State<DeliveryDetailsScreen> createState() => _DeliveryDetailsScreenState();
}

class _DeliveryDetailsScreenState extends State<DeliveryDetailsScreen> {
  late final DeliveryRepository repository;

  Map<String, dynamic>? delivery;

  bool loading = true;
  bool updating = false;

  @override
  void initState() {
    super.initState();

    repository = DeliveryRepository(ApiClient(TokenStorage()));

    _loadDetails();
  }

  // ================================================================
  // LOAD DELIVERY
  // ================================================================

  Future<void> _loadDetails() async {
    try {
      final result = await repository.details(widget.deliveryId);

      if (!mounted) return;

      setState(() {
        delivery = result;
        loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        loading = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;

      setState(() {
        loading = false;
      });

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to load delivery.')));
    }
  }

  // ================================================================
  // UPDATE DELIVERY STATUS
  // ================================================================

  Future<void> _updateStatus(String newStatus) async {
    if (updating) return;

    setState(() {
      updating = true;
    });

    try {
      final updated = await repository.updateStatus(
        widget.deliveryId,
        newStatus,
      );

      if (!mounted) return;

      setState(() {
        delivery = updated;
        updating = false;
      });

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Status updated to $newStatus')));
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to update delivery status.')),
      );
    }
  }

  // ================================================================
  // CAPTURE AND UPLOAD PROOF
  // ================================================================

  Future<void> _captureAndUpload() async {
    if (updating) return;

    final picker = ImagePicker();

    final photo = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
    );

    if (photo == null) {
      return;
    }

    try {
      setState(() {
        updating = true;
      });

      // Upload the photo to Laravel
      await repository.uploadProof(widget.deliveryId, File(photo.path));

      // Reload the delivery details.
      // This gets the proof_photo_signed_url generated
      // by the Laravel show() endpoint.
      final refreshed = await repository.details(widget.deliveryId);

      if (!mounted) return;

      setState(() {
        delivery = refreshed;
        updating = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Proof uploaded — you can now mark this delivered.'),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;

      setState(() {
        updating = false;
      });

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to upload proof.')));
    }
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery Details')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery Details')),
        body: const Center(child: Text('Delivery not found.')),
      );
    }

    // ==============================================================
    // DELIVERY DATA
    // ==============================================================

    final order = delivery!['order'] as Map<String, dynamic>?;

    final status = delivery!['status']?.toString() ?? 'unknown';

    final eta = delivery!['eta']?.toString();

    final customer = order?['customer'] as Map<String, dynamic>?;

    final user = customer?['user'] as Map<String, dynamic>?;

    final address = order?['address'] as Map<String, dynamic>?;

    final customerName = user?['name']?.toString() ?? 'Unknown Customer';

    final customerPhone = user?['phone']?.toString() ?? 'No phone number';

    final addressLine = address?['address_line']?.toString() ?? 'No address';

    final items = order?['items'] as List? ?? [];

    // ==============================================================
    // PROOF DATA
    // ==============================================================

    final proofPath = delivery!['proof_photo_url']?.toString();

    final proofUrl = delivery!['proof_photo_signed_url']?.toString();

    final hasProof = proofPath != null && proofPath.isNotEmpty;

    return Scaffold(
      appBar: AppBar(title: const Text('Delivery Details')),

      body: ListView(
        padding: const EdgeInsets.all(16),

        children: [
          // ========================================================
          // CUSTOMER
          // ========================================================

          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    'Customer',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),

                  const SizedBox(height: 12),

                  Text(customerName, style: const TextStyle(fontSize: 16)),

                  const SizedBox(height: 6),

                  Text(customerPhone),

                  const SizedBox(height: 12),

                  const Text(
                    'Delivery Address',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),

                  const SizedBox(height: 6),

                  Text(addressLine),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),

          // ========================================================
          // STATUS
          // ========================================================
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    'Delivery Status',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),

                  const SizedBox(height: 10),

                  Text(
                    status,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),

                  const SizedBox(height: 10),

                  Text('ETA: ${eta ?? 'Not set'}'),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),

          // ========================================================
          // ORDER ITEMS
          // ========================================================
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    'Order Items',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),

                  const SizedBox(height: 10),

                  if (items.isEmpty)
                    const Text('No order items found.')
                  else
                    ...items.map((item) {
                      final itemMap = Map<String, dynamic>.from(item as Map);

                      final product =
                          itemMap['product'] as Map<String, dynamic>?;

                      final name =
                          product?['name']?.toString() ?? 'Unknown Product';

                      final quantity = itemMap['quantity']?.toString() ?? '0';

                      return ListTile(
                        contentPadding: EdgeInsets.zero,

                        title: Text(name),

                        trailing: Text('x$quantity'),
                      );
                    }),
                ],
              ),
            ),
          ),

          const SizedBox(height: 20),

          // ========================================================
          // STEP 29
          // CAPTURE PROOF PHOTO
          // ========================================================
          if (status == 'assigned' ||
              status == 'en_route_for_pickup' ||
              status == 'out_for_delivery') ...[
            SizedBox(
              width: double.infinity,
              height: 50,

              child: ElevatedButton.icon(
                onPressed: updating ? null : _captureAndUpload,

                icon: const Icon(Icons.camera_alt),

                label: Text(
                  hasProof ? 'Replace Proof Photo' : 'Capture Proof Photo',
                ),
              ),
            ),

            const SizedBox(height: 12),
          ],

          // ========================================================
          // STEP 31
          // DISPLAY PROOF PHOTO
          // ========================================================
          if (proofUrl != null && proofUrl.isNotEmpty) ...[
            const SizedBox(height: 4),

            const Text(
              'Proof of Delivery',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 10),

            ClipRRect(
              borderRadius: BorderRadius.circular(12),

              child: Image.network(
                proofUrl,

                height: 200,
                width: double.infinity,

                fit: BoxFit.cover,

                loadingBuilder: (context, child, loadingProgress) {
                  if (loadingProgress == null) {
                    return child;
                  }

                  return Container(
                    height: 200,

                    alignment: Alignment.center,

                    child: const CircularProgressIndicator(),
                  );
                },

                errorBuilder: (context, error, stackTrace) {
                  return Container(
                    height: 200,

                    alignment: Alignment.center,

                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      color: Colors.grey.shade200,
                    ),

                    child: const Text('Unable to load proof photo.'),
                  );
                },
              ),
            ),

            const SizedBox(height: 12),
          ],

          // ========================================================
          // PICKUP LEG
          // ========================================================
          if (status == 'assigned')
            SizedBox(
              height: 50,

              child: ElevatedButton(
                onPressed: updating
                    ? null
                    : () => _updateStatus('en_route_for_pickup'),

                child: updating
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(),
                      )
                    : const Text('Start Pickup'),
              ),
            ),

          // ========================================================
          // PICKUP IN PROGRESS
          // ========================================================
          if (status == 'en_route_for_pickup') ...[
            SizedBox(
              height: 50,

              child: ElevatedButton(
                onPressed: updating ? null : () => _updateStatus('picked_up'),

                child: updating
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(),
                      )
                    : const Text('Confirm Empty Gallons Picked Up'),
              ),
            ),

            const SizedBox(height: 10),

            SizedBox(
              height: 50,

              child: OutlinedButton(
                onPressed: updating ? null : () => _updateStatus('failed'),

                child: const Text('Mark Failed'),
              ),
            ),
          ],

          // ========================================================
          // AT STATION
          // ========================================================
          if (status == 'picked_up')
            SizedBox(
              height: 50,

              child: ElevatedButton(
                onPressed: updating ? null : () => _updateStatus('at_station'),

                child: const Text('Hand Off at Station'),
              ),
            ),

          // ========================================================
          // READY FOR DELIVERY
          // ========================================================
          if (status == 'ready_for_delivery')
            SizedBox(
              height: 50,

              child: ElevatedButton(
                onPressed: updating
                    ? null
                    : () => _updateStatus('out_for_delivery'),

                child: const Text('Start Return Delivery'),
              ),
            ),

          // ========================================================
          // OUT FOR DELIVERY
          // ========================================================
          if (status == 'out_for_delivery') ...[
            const Padding(
              padding: EdgeInsets.only(bottom: 10),

              child: Text(
                'Capture proof of delivery before completing the delivery.',
              ),
            ),

            // ------------------------------------------------------
            // PROOF STATUS MESSAGE
            // ------------------------------------------------------
            if (!hasProof)
              const Padding(
                padding: EdgeInsets.only(bottom: 10),

                child: Text(
                  'Capture proof of delivery before marking this delivery as delivered.',
                  textAlign: TextAlign.center,
                ),
              ),

            // ------------------------------------------------------
            // MARK DELIVERED
            // STEP 30
            // ------------------------------------------------------
            SizedBox(
              height: 50,

              child: ElevatedButton(
                onPressed: updating || !hasProof
                    ? null
                    : () => _updateStatus('delivered'),

                child: const Text('Mark Delivered'),
              ),
            ),

            const SizedBox(height: 10),

            // ------------------------------------------------------
            // REPORT FAILURE
            // ------------------------------------------------------
            SizedBox(
              height: 50,

              child: OutlinedButton(
                onPressed: updating ? null : () => _updateStatus('failed'),

                child: const Text('Report Delivery Failure'),
              ),
            ),
          ],

          // ========================================================
          // COMPLETED
          // ========================================================
          if (status == 'delivered' || status == 'failed')
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),

              child: Center(child: Text('This delivery is completed.')),
            ),
        ],
      ),
    );
  }
}
