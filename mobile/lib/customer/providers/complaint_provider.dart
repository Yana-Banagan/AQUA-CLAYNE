import 'package:flutter/foundation.dart';
import '../../core/models/complaint_model.dart';
import '../data/complaint_repository.dart';

class ComplaintProvider extends ChangeNotifier {
  final ComplaintRepository _repo;

  ComplaintProvider(this._repo);

  List<ComplaintModel> _complaints = [];
  List<ComplaintCategoryModel> _categories = [];
  ComplaintModel? _detail;
  bool _loading = false;
  String? _error;

  List<ComplaintModel> get complaints => _complaints;
  List<ComplaintCategoryModel> get categories => _categories;
  ComplaintModel? get detail => _detail;
  bool get loading => _loading;
  String? get error => _error;

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final results = await Future.wait([
        _repo.list(),
        _repo.categories(),
      ]);
      _complaints = (results[0] as List)
          .map((r) => ComplaintModel.fromMap(r as Map<String, dynamic>))
          .toList();
      _categories = (results[1] as List)
          .map((r) => ComplaintCategoryModel.fromMap(r as Map<String, dynamic>))
          .toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.get(id);
      _detail = ComplaintModel.fromMap(raw);
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  Future<bool> submit(Map<String, dynamic> payload) async {
    _error = null;
    try {
      await _repo.create(payload);
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
    _complaints = [];
    _categories = [];
    _detail = null;
    _loading = false;
    _error = null;
    notifyListeners();
  }
}
