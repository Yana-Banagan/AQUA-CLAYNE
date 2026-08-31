import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/address_repository.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();

    _future = context.read<AddressRepository>().list();
  }

  void _reload() {
    setState(() {
      _future = context.read<AddressRepository>().list();
    });
  }

  Future<void> _showAddressForm({Map<String, dynamic>? address}) async {
    final labelController = TextEditingController(
      text: address?['label']?.toString() ?? '',
    );

    final addressLineController = TextEditingController(
      text: address?['address_line']?.toString() ?? '',
    );

    final isEditing = address != null;

    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        bool saving = false;

        return StatefulBuilder(
          builder: (context, setDialogState) {
            Future<void> save() async {
              final label = labelController.text.trim();
              final addressLine = addressLineController.text.trim();

              if (addressLine.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Please enter your address.')),
                );
                return;
              }

              setDialogState(() {
                saving = true;
              });

              try {
                final repository = context.read<AddressRepository>();

                if (isEditing) {
                  final id = address['id']?.toString();

                  if (id == null || id.isEmpty) {
                    throw Exception('Address ID is missing.');
                  }

                  await repository.update(id, {
                    'label': label.isEmpty ? null : label,
                    'address_line': addressLine,
                  });
                } else {
                  await repository.create({
                    'label': label.isEmpty ? null : label,
                    'address_line': addressLine,
                  });
                }

                if (!context.mounted) return;

                Navigator.of(context).pop(true);
              } catch (e) {
                setDialogState(() {
                  saving = false;
                });

                if (!context.mounted) return;

                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      'Failed to ${isEditing ? 'update' : 'add'} address: $e',
                    ),
                  ),
                );
              }
            }

            return AlertDialog(
              title: Text(isEditing ? 'Edit Address' : 'Add Address'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: labelController,
                      enabled: !saving,
                      decoration: const InputDecoration(
                        labelText: 'Label',
                        hintText: 'Home',
                        border: OutlineInputBorder(),
                      ),
                    ),

                    const SizedBox(height: 16),

                    TextField(
                      controller: addressLineController,
                      enabled: !saving,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        labelText: 'Address Line',
                        hintText: '123 Sample Street...',
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving
                      ? null
                      : () {
                          Navigator.of(context).pop(false);
                        },
                  child: const Text('Cancel'),
                ),
                ElevatedButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(isEditing ? 'Update' : 'Save'),
                ),
              ],
            );
          },
        );
      },
    );

    labelController.dispose();
    addressLineController.dispose();

    if (result == true && mounted) {
      _reload();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isEditing
                ? 'Address updated successfully.'
                : 'Address added successfully.',
          ),
        ),
      );
    }
  }

  Future<void> _deleteAddress(Map<String, dynamic> address) async {
    final id = address['id']?.toString();

    if (id == null || id.isEmpty) {
      if (!mounted) return;

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Address ID is missing.')));
      return;
    }

    // Get the repository BEFORE the async gap.
    final repository = context.read<AddressRepository>();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Delete Address'),
          content: const Text('Are you sure you want to delete this address?'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(true);
              },
              child: const Text('Delete'),
            ),
          ],
        );
      },
    );

    if (confirmed != true) {
      return;
    }

    try {
      await repository.delete(id);

      if (!mounted) return;

      _reload();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Address deleted successfully.')),
      );
    } catch (e) {
      if (!mounted) return;

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Failed to delete address: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Addresses')),

      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          // Loading
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          // Error
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline, size: 60),

                    const SizedBox(height: 16),

                    const Text(
                      'Failed to load addresses.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      snapshot.error.toString(),
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 20),

                    ElevatedButton(
                      onPressed: _reload,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final addresses = snapshot.data ?? [];

          // Empty
          if (addresses.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.location_on_outlined, size: 70),

                    SizedBox(height: 16),

                    Text(
                      'No addresses yet.',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    SizedBox(height: 8),

                    Text(
                      'Tap the + button to add your first address.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            );
          }

          // Address list
          return RefreshIndicator(
            onRefresh: () async {
              _reload();
              await _future;
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: addresses.length,
              itemBuilder: (context, index) {
                final address = addresses[index];

                final label = address['label']?.toString().trim() ?? '';

                final addressLine = address['address_line']?.toString() ?? '';

                final isDefault = address['is_default'] == true;

                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.location_on),

                            const SizedBox(width: 8),

                            Expanded(
                              child: Text(
                                label.isEmpty ? 'Address' : label,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),

                            if (isDefault) const Chip(label: Text('Default')),
                          ],
                        ),

                        const SizedBox(height: 12),

                        Text(addressLine, style: const TextStyle(fontSize: 16)),

                        const SizedBox(height: 12),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            TextButton.icon(
                              onPressed: () {
                                _showAddressForm(address: address);
                              },
                              icon: const Icon(Icons.edit),
                              label: const Text('Edit'),
                            ),

                            const SizedBox(width: 8),

                            TextButton.icon(
                              onPressed: () {
                                _deleteAddress(address);
                              },
                              icon: const Icon(Icons.delete),
                              label: const Text('Delete'),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),

      floatingActionButton: FloatingActionButton(
        onPressed: () {
          _showAddressForm();
        },
        child: const Icon(Icons.add),
      ),
    );
  }
}
