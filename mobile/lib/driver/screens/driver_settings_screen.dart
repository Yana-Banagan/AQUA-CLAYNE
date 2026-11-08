import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/driver_colors.dart';

class DriverSettingsScreen extends StatefulWidget {
  const DriverSettingsScreen({super.key});

  @override
  State<DriverSettingsScreen> createState() =>
      _DriverSettingsScreenState();
}

class _DriverSettingsScreenState extends State<DriverSettingsScreen> {
  bool _assignmentNotifs = true;
  bool _pickupNotifs = true;
  bool _deliveryNotifs = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DriverColors.background,
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          _SectionHeader('Notifications'),
          _SwitchTile(
            icon: Icons.local_shipping_outlined,
            title: 'New Assignments',
            subtitle: 'Get notified when a delivery is assigned to you',
            value: _assignmentNotifs,
            onChanged: (v) => setState(() => _assignmentNotifs = v),
          ),
          _SwitchTile(
            icon: Icons.inventory_2_outlined,
            title: 'Pickup Reminders',
            subtitle: 'Reminders for pending gallon pickups',
            value: _pickupNotifs,
            onChanged: (v) => setState(() => _pickupNotifs = v),
          ),
          _SwitchTile(
            icon: Icons.delivery_dining_outlined,
            title: 'Delivery Updates',
            subtitle: 'Order ready and delivery status updates',
            value: _deliveryNotifs,
            onChanged: (v) => setState(() => _deliveryNotifs = v),
          ),
          _SectionHeader('Legal'),
          _NavTile(
            icon: Icons.description_outlined,
            title: 'Terms & Conditions',
            onTap: () => _showText(context, 'Terms & Conditions',
                'Driver terms and conditions apply.'),
          ),
          _NavTile(
            icon: Icons.privacy_tip_outlined,
            title: 'Privacy Policy',
            onTap: () => _showText(context, 'Privacy Policy',
                'We collect and use your data as described in our Privacy Policy.'),
          ),
          _SectionHeader('App Information'),
          Container(
            color: AppColors.surface,
            child: const ListTile(
              leading: Icon(Icons.info_outline,
                  color: DriverColors.primary, size: 22),
              title: Text('App Version',
                  style: TextStyle(fontSize: 14)),
              trailing: Text('1.0.0',
                  style: TextStyle(
                      fontSize: 13,
                      color: DriverColors.textSecondary)),
              dense: true,
              contentPadding: EdgeInsets.symmetric(
                  horizontal: AppSpacing.standard, vertical: 2),
            ),
          ),
          const SizedBox(height: AppSpacing.section),
        ],
      ),
    );
  }

  void _showText(BuildContext context, String title, String body) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(title),
        content: Text(body,
            style:
                const TextStyle(fontSize: 14, height: 1.5)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Close')),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  const _SectionHeader(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.standard, AppSpacing.standard, 0, AppSpacing.small),
      child: Text(title,
          style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: DriverColors.textSecondary,
              letterSpacing: 0.5)),
    );
  }
}

class _SwitchTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.surface,
      child: SwitchListTile(
        secondary: Icon(icon, color: DriverColors.primary, size: 22),
        title: Text(title, style: const TextStyle(fontSize: 14)),
        subtitle: Text(subtitle,
            style: const TextStyle(
                fontSize: 12, color: DriverColors.textSecondary)),
        value: value,
        onChanged: onChanged,
        activeThumbColor: DriverColors.primary,
        dense: true,
        contentPadding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.standard, vertical: 2),
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final VoidCallback onTap;
  const _NavTile(
      {required this.icon, required this.title, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.surface,
      child: ListTile(
        leading: Icon(icon, color: DriverColors.primary, size: 22),
        title: Text(title, style: const TextStyle(fontSize: 14)),
        trailing: const Icon(Icons.chevron_right,
            color: DriverColors.textHint, size: 20),
        onTap: onTap,
        dense: true,
        contentPadding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.standard, vertical: 2),
      ),
    );
  }
}
