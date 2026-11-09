class AddressModel {
  final String id;
  final String label;
  final String addressLine;
  final String? barangay;
  final String? city;
  final String? province;
  final String? zipCode;
  final bool isDefault;
  final double? latitude;
  final double? longitude;

  const AddressModel({
    required this.id,
    required this.label,
    required this.addressLine,
    this.barangay,
    this.city,
    this.province,
    this.zipCode,
    this.isDefault = false,
    this.latitude,
    this.longitude,
  });

  factory AddressModel.fromMap(Map<String, dynamic> m) {
    return AddressModel(
      id: m['id']?.toString() ?? '',
      label: m['label']?.toString() ?? 'Home',
      addressLine: m['address_line']?.toString() ??
          m['street']?.toString() ?? '',
      barangay: m['barangay']?.toString(),
      city: m['city']?.toString(),
      province: m['province']?.toString(),
      zipCode: m['zip_code']?.toString(),
      isDefault: m['is_default'] == true || m['is_default'] == 1,
      latitude: m['latitude'] != null
          ? double.tryParse(m['latitude'].toString())
          : null,
      longitude: m['longitude'] != null
          ? double.tryParse(m['longitude'].toString())
          : null,
    );
  }

  String get fullAddress {
    final parts = [
      addressLine,
      if (barangay != null && barangay!.isNotEmpty) barangay!,
      if (city != null && city!.isNotEmpty) city!,
      if (province != null && province!.isNotEmpty) province!,
      if (zipCode != null && zipCode!.isNotEmpty) zipCode!,
    ];
    return parts.join(', ');
  }

  Map<String, dynamic> toMap() => {
        'id': id,
        'label': label,
        'address_line': addressLine,
        'barangay': barangay,
        'city': city,
        'province': province,
        'zip_code': zipCode,
        'is_default': isDefault,
        'latitude': latitude,
        'longitude': longitude,
      };
}
