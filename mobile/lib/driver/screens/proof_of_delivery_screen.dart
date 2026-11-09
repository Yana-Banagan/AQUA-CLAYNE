import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/models/delivery_model.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';
import '../../driver/providers/driver_delivery_provider.dart';
import '../../shared_widgets/driver_scaffold.dart';

class ProofOfDeliveryScreen extends StatefulWidget {
  final DeliveryModel delivery;
  const ProofOfDeliveryScreen({super.key, required this.delivery});

  @override
  State<ProofOfDeliveryScreen> createState() => _ProofOfDeliveryScreenState();
}

class _ProofOfDeliveryScreenState extends State<ProofOfDeliveryScreen> {
  File? _photo;
  bool _uploading = false;

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: source,
        maxWidth: 1200,
        imageQuality: 80,
      );
      if (picked != null) {
        setState(() => _photo = File(picked.path));
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Could not open camera/gallery: $e'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  Future<void> _upload() async {
    if (_photo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please capture or select a proof photo first.'),
          backgroundColor: DriverColors.error,
        ),
      );
      return;
    }

    setState(() => _uploading = true);
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.uploadProof(widget.delivery.id, _photo!);

    if (!mounted) return;
    setState(() => _uploading = false);

    if (ok) {
      await prov.loadDetail(widget.delivery.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Proof of delivery uploaded successfully!'),
          backgroundColor: DriverColors.primary,
        ),
      );
      Navigator.pop(context, true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to upload proof photo.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return DriverScaffold(
      appBar: AppBar(
        title: const Text(
          'Proof of Delivery',
          style: TextStyle(
            color: DriverColors.primaryDark,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: DriverColors.primaryDark),
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.standard),
        children: [
          const Text(
            'Delivery Completed',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: DriverColors.primaryDark,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Proof of delivery is optional. Add a photo or skip and continue to complete the delivery.',
            style: TextStyle(fontSize: 14, color: DriverColors.textSecondary),
          ),
          const SizedBox(height: AppSpacing.standard),
          // Delivery Header Info
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 8,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: DriverColors.mintSoft,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        widget.delivery.orderNumber,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: DriverColors.primaryDark,
                        ),
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${widget.delivery.expectedGallons} Gallons for Refill',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: DriverColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  widget.delivery.customerName,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: DriverColors.textPrimary,
                  ),
                ),
                Text(
                  widget.delivery.fullAddress,
                  style: const TextStyle(
                    fontSize: 13,
                    color: DriverColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Photo Preview / Picker Area
          Container(
            padding: const EdgeInsets.all(AppSpacing.standard),
            decoration: BoxDecoration(
              color: DriverColors.cardTranslucent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DriverColors.borderLight),
              boxShadow: const [
                BoxShadow(
                  color: DriverColors.shadow,
                  blurRadius: 8,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              children: [
                if (_photo != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.file(
                      _photo!,
                      height: 280,
                      width: double.infinity,
                      fit: BoxFit.cover,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      OutlinedButton.icon(
                        onPressed: _uploading
                            ? null
                            : () => _pickPhoto(ImageSource.camera),
                        icon: const Icon(Icons.refresh, size: 16),
                        label: const Text('Retake Photo'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: DriverColors.textSecondary,
                          side: const BorderSide(color: DriverColors.border),
                        ),
                      ),
                      const SizedBox(width: 12),
                      OutlinedButton.icon(
                        onPressed: _uploading
                            ? null
                            : () => _pickPhoto(ImageSource.gallery),
                        icon: const Icon(Icons.photo_library, size: 16),
                        label: const Text('Change'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: DriverColors.textSecondary,
                          side: const BorderSide(color: DriverColors.border),
                        ),
                      ),
                    ],
                  ),
                ] else ...[
                  Container(
                    height: 220,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: DriverColors.mintSurface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: DriverColors.border,
                        style: BorderStyle.solid,
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.camera_alt_outlined,
                          size: 52,
                          color: DriverColors.primary,
                        ),
                        const SizedBox(height: 10),
                        const Text(
                          'No photo captured yet',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: DriverColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Capture refilled gallons handed over to the customer.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 12,
                            color: DriverColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: () => _pickPhoto(ImageSource.camera),
                          icon: const Icon(Icons.camera_alt, size: 18),
                          label: const Text('Take Photo'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: DriverColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            elevation: 0,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _pickPhoto(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library_outlined, size: 18),
                          label: const Text('From Gallery'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: DriverColors.primary,
                            side: const BorderSide(color: DriverColors.primary),
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.standard),

          // Upload Button
          ElevatedButton.icon(
            onPressed: (_photo == null || _uploading) ? null : _upload,
            icon: _uploading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : const Icon(Icons.cloud_upload_outlined, size: 18),
            label: Text(
              _uploading ? 'Uploading Proof...' : 'Upload Proof of Delivery',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: DriverColors.primaryDark,
              foregroundColor: Colors.white,
              disabledBackgroundColor: Colors.grey.shade300,
              disabledForegroundColor: Colors.grey.shade600,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              elevation: 0,
            ),
          ),
        const SizedBox(height: AppSpacing.small),
        OutlinedButton.icon(
          onPressed: _uploading ? null : () => Navigator.pop(context, false),
          icon: const Icon(Icons.skip_next_rounded),
          label: const Text('Skip / Continue Without Proof'),
          style: OutlinedButton.styleFrom(
            foregroundColor: DriverColors.textSecondary,
            side: const BorderSide(color: DriverColors.border),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
        ],
      ),
    );
  }
}
