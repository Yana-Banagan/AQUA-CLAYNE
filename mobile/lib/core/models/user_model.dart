class UserModel {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final String? status;
  final String? createdAt;
  final String? avatarUrl;
  final String? fcmToken;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    this.status,
    this.createdAt,
    this.avatarUrl,
    this.fcmToken,
  });

  factory UserModel.fromMap(Map<String, dynamic> m) {
    return UserModel(
      id: m['id']?.toString() ?? '',
      name: m['name']?.toString() ?? '',
      email: m['email']?.toString() ?? '',
      phone: m['phone']?.toString(),
      role: m['role']?.toString() ?? 'customer',
      status: m['status']?.toString() ?? 'active',
      createdAt: m['created_at']?.toString(),
      avatarUrl: m['avatar_url']?.toString(),
      fcmToken: m['fcm_token']?.toString(),
    );
  }

  bool get isCustomer => role.toLowerCase() == 'customer';
  bool get isDriver => role.toLowerCase() == 'driver';
  bool get isAdmin => role.toLowerCase() == 'admin';

  UserModel copyWith({
    String? id,
    String? name,
    String? email,
    String? phone,
    String? role,
    String? status,
    String? createdAt,
    String? avatarUrl,
    String? fcmToken,
  }) {
    return UserModel(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      role: role ?? this.role,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      fcmToken: fcmToken ?? this.fcmToken,
    );
  }

  Map<String, dynamic> toMap() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
        'status': status,
        'created_at': createdAt,
        'avatar_url': avatarUrl,
      };
}
