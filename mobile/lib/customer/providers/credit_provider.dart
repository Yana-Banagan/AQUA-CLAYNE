import 'package:flutter/foundation.dart';
import '../../core/models/credit_model.dart';
import '../data/credit_repository.dart';

class CreditProvider extends ChangeNotifier {
  final CreditRepository _repo;

  CreditProvider(this._repo);

  CreditAccountModel? _account;
  List<FaqModel> _faqs = [];
  bool _loading = false;
  String? _error;

  CreditAccountModel? get account => _account;
  List<FaqModel> get faqs => _faqs;
  bool get loading => _loading;
  String? get error => _error;

  Future<void> loadCredit() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.getCredit();
      if (raw.isNotEmpty && raw['id'] != null && raw['id'].toString().isNotEmpty) {
        _account = CreditAccountModel.fromMap(raw);
      } else {
        _account = null;
      }
    } catch (e) {
      _error = e.toString();
      _account = null;
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> loadFaqs() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final raw = await _repo.getFaqs();
      _faqs = (raw as List)
          .map((r) => FaqModel.fromMap(r as Map<String, dynamic>))
          .toList();
    } catch (e) {
      _error = e.toString();
    }
    _loading = false;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearSessionData() {
    _account = null;
    _faqs = [];
    _loading = false;
    _error = null;
    notifyListeners();
  }
}
