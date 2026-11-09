import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

/// Network reachability state. This intentionally never stores credentials.
class OfflineService extends ChangeNotifier {
  final Connectivity _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _subscription;
  bool _offline = false;
  int _pendingSyncCount = 0;
  String? _syncError;

  bool get isOffline => _offline;
  int get pendingSyncCount => _pendingSyncCount;
  String? get syncError => _syncError;

  void updateSyncState({required int pendingCount, String? error}) {
    if (_pendingSyncCount == pendingCount && _syncError == error) return;
    _pendingSyncCount = pendingCount;
    _syncError = error;
    notifyListeners();
  }

  Future<void> initialize() async {
    _setOffline(await _connectivity.checkConnectivity());
    _subscription = _connectivity.onConnectivityChanged.listen(_setOffline);
  }

  void _setOffline(List<ConnectivityResult> results) {
    final wasOffline = _offline;
    _offline = results.isEmpty || results.every((result) => result == ConnectivityResult.none);
    if (wasOffline != _offline) notifyListeners();
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }
}
