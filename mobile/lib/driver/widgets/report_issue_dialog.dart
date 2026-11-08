import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/driver_colors.dart';
import '../providers/driver_delivery_provider.dart';

class ReportIssueDialog extends StatefulWidget {
  final String deliveryId;
  final String orderNumber;

  const ReportIssueDialog({
    super.key,
    required this.deliveryId,
    required this.orderNumber,
  });

  static Future<bool?> show(
    BuildContext context, {
    required String deliveryId,
    required String orderNumber,
  }) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ReportIssueDialog(
        deliveryId: deliveryId,
        orderNumber: orderNumber,
      ),
    );
  }

  @override
  State<ReportIssueDialog> createState() => _ReportIssueDialogState();
}

class _ReportIssueDialogState extends State<ReportIssueDialog> {
  String _issueType = 'wrong_address';
  final _descController = TextEditingController();
  bool _markFailed = false;
  bool _submitting = false;

  final Map<String, String> _issueOptions = const {
    'wrong_address': 'Wrong or unreachable address',
    'customer_unavailable': 'Customer unavailable / no response',
    'vehicle_problem': 'Vehicle breakdown / transport issue',
    'gallon_issue': 'Container / gallon discrepancy',
    'other': 'Other delivery problem',
  };

  @override
  void dispose() {
    _descController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final desc = _descController.text.trim();
    if (desc.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter a description of the issue.'),
          backgroundColor: DriverColors.error,
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    final prov = context.read<DriverDeliveryProvider>();
    final ok = await prov.reportIssue(
      widget.deliveryId,
      issueType: _issueType,
      description: desc,
      markAsFailed: _markFailed,
    );

    if (!mounted) return;
    setState(() => _submitting = false);

    if (ok) {
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _markFailed
                ? 'Delivery marked as failed with reported issue.'
                : 'Delivery issue submitted successfully.',
          ),
          backgroundColor: DriverColors.primary,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(prov.error ?? 'Failed to submit delivery issue.'),
          backgroundColor: DriverColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: bottomInset + 20,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Handle bar
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: DriverColors.border,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Title
            Row(
              children: [
                const Icon(
                  Icons.report_problem_outlined,
                  color: DriverColors.warning,
                  size: 24,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Report Delivery Issue',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: DriverColors.textPrimary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Order ${widget.orderNumber}',
              style: const TextStyle(
                fontSize: 13,
                color: DriverColors.textSecondary,
              ),
            ),
            const SizedBox(height: 16),

            // Issue Type Selector
            const Text(
              'Issue Type',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: DriverColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: DriverColors.mintSurface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: DriverColors.border),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _issueType,
                  isExpanded: true,
                  icon: const Icon(Icons.arrow_drop_down, color: DriverColors.primary),
                  onChanged: (val) {
                    if (val != null) setState(() => _issueType = val);
                  },
                  items: _issueOptions.entries.map((entry) {
                    return DropdownMenuItem<String>(
                      value: entry.key,
                      child: Text(
                        entry.value,
                        style: const TextStyle(
                          fontSize: 14,
                          color: DriverColors.textPrimary,
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Description
            const Text(
              'Description',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: DriverColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _descController,
              maxLines: 4,
              decoration: InputDecoration(
                hintText: 'Describe the issue encountered...',
                hintStyle: const TextStyle(color: DriverColors.textHint, fontSize: 13),
                filled: true,
                fillColor: DriverColors.mintSurface,
                contentPadding: const EdgeInsets.all(12),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: DriverColors.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: DriverColors.primary, width: 1.5),
                ),
              ),
            ),
            const SizedBox(height: 14),

            // Mark as Failed Checkbox
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _markFailed ? DriverColors.errorLight : DriverColors.mintSoft,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _markFailed ? DriverColors.error.withValues(alpha: 0.3) : DriverColors.borderLight,
                ),
              ),
              child: Row(
                children: [
                  Checkbox(
                    value: _markFailed,
                    activeColor: DriverColors.error,
                    onChanged: (val) => setState(() => _markFailed = val ?? false),
                  ),
                  Expanded(
                    child: Text(
                      'Mark this delivery as failed / unable to deliver',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: _markFailed ? DriverColors.error : DriverColors.textPrimary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Buttons
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _submitting ? null : () => Navigator.pop(context),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: DriverColors.textSecondary,
                      side: const BorderSide(color: DriverColors.border),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _markFailed ? DriverColors.error : DriverColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                    ),
                    child: _submitting
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text(
                            'Submit Issue',
                            style: TextStyle(fontWeight: FontWeight.bold),
                          ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
