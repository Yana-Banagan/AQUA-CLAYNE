import 'package:flutter/foundation.dart';
import '../../core/api/api_client.dart';
import '../../core/models/address_model.dart';
import '../data/address_repository.dart';

class AddressProvider extends ChangeNotifier {
  final AddressRepository _repo;

  AddressProvider(this._repo);

  List<AddressModel> _addresses = [];
  bool _loading = false;
  String? _error;

  List<AddressModel> get addresses => _addresses;
  bool get loading => _loading;
  String? get error => _error;

  AddressModel? get defaultAddress =>
      _addresses.where((a) => a.isDefault).firstOrNull ??
      (_addresses.isNotEmpty ? _addresses.first : null);

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.list();
      _addresses = raw.map(AddressModel.fromMap).toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  /// Creates an address and returns its ID so checkout can select it.
  Future<String?> add(Map<String, dynamic> payload) async {
    _error = null;
    try {
      final address = await _repo.create(payload);
      await load();
      final id = address['id']?.toString();
      return id != null && id.isNotEmpty ? id : null;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return null;
    }
  }

  Future<bool> update(String id, Map<String, dynamic> payload) async {
    _error = null;
    try {
      final result = await _repo.update(id, payload);
      if (result is OfflineQueuedResult) {
        final index = _addresses.indexWhere((address) => address.id == id);
        if (index >= 0) {
          _addresses[index] = AddressModel.fromMap({..._addresses[index].toMap(), ...payload});
          notifyListeners();
        }
        return true;
      }
      await load();
      return true;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> delete(String id) async {
    _error = null;
    try {
      await _repo.delete(id);
      _addresses.removeWhere((a) => a.id == id);
      notifyListeners();
      return true;
    } catch (e) {
      _error = 'Unable to delete this address. Please try again.';
      notifyListeners();
      return false;
    }
  }

  Future<bool> setDefault(String id) async {
    _error = null;
    try {
      final result = await _repo.setDefault(id);
      if (result is OfflineQueuedResult) {
        _addresses = _addresses.map((address) => AddressModel.fromMap({
          ...address.toMap(),
          'is_default': address.id == id,
        })).toList();
        notifyListeners();
        return true;
      }
      await load();
      return true;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearSessionData() {
    _addresses = [];
    _loading = false;
    _error = null;
    notifyListeners();
  }
}
