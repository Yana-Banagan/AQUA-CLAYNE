import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// Wraps Supabase Realtime subscriptions.
///
/// The mobile app uses the signal-table pattern:
///   Laravel inserts a row into `realtime_events`
///     → Supabase CDC broadcasts it
///       → Flutter receives the signal
///         → Provider re-fetches from Laravel API
///
/// No sensitive data travels over Supabase — only the signal.
/// All real data comes from the authenticated Laravel API.
class RealtimeService with WidgetsBindingObserver {
  final _channels = <String, RealtimeChannel>{};
  final _listeners = <String, Set<VoidCallback>>{};
  final _connected = <String, bool>{};
  Timer? _fallbackTimer;
  Timer? _dispatchTimer;
  final Set<String> _pendingTopics = {};

  RealtimeService() {
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    _queueDispatch(_listeners.keys);
  }

  // ================================================================
  // SUBSCRIBE
  //
  // [topic]    — unique key for this subscription (e.g. 'orders', 'deliveries')
  // [table]    — Supabase table to watch (default: 'realtime_events')
  // [filter]   — optional equality filter column:value
  // [onSignal] — callback fired on any INSERT into the table/filter
  // ================================================================
  void subscribe({
    required String topic,
    String table = 'realtime_events',
    String? filterColumn,
    String? filterValue,
    required VoidCallback onSignal,
  }) {
    _listeners.putIfAbsent(topic, () => <VoidCallback>{}).add(onSignal);

    // Prevent duplicate subscriptions for the same topic
    if (_channels.containsKey(topic)) {
      debugPrint('[Realtime] Added listener to existing $topic channel.');
      return;
    }

    final client = Supabase.instance.client;
    _connected[topic] = false;

    // Build unique channel name
    final channelName =
        'rt-$topic-${DateTime.now().millisecondsSinceEpoch}';

    RealtimeChannel channel = client.channel(channelName);

    // Apply filter if provided
    PostgresChangeFilter? filter;
    if (filterColumn != null && filterValue != null) {
      filter = PostgresChangeFilter(
        type: PostgresChangeFilterType.eq,
        column: filterColumn,
        value: filterValue,
      );
    }

    channel = channel.onPostgresChanges(
      event: PostgresChangeEvent.insert,
      schema: 'public',
      table: table,
      filter: filter,
      callback: (payload) {
        debugPrint('[Realtime] Signal on $topic: ${payload.newRecord}');
        _queueDispatch([topic]);
      },
    );

    channel.subscribe((status, [error]) {
      _connected[topic] = status == RealtimeSubscribeStatus.subscribed;
      if (_connected[topic] == true) {
        _stopFallbackIfConnected();
      } else {
        _startFallback();
      }
      debugPrint('[Realtime] $topic status: $status'
          '${error != null ? " error: $error" : ""}');
    });

    _channels[topic] = channel;
    debugPrint('[Realtime] Subscribed to $topic');
  }

  // ================================================================
  // UNSUBSCRIBE
  // ================================================================
  void unsubscribe(String topic) {
    _listeners.remove(topic);
    _connected.remove(topic);
    _pendingTopics.remove(topic);
    final channel = _channels.remove(topic);
    if (channel != null) {
      Supabase.instance.client.removeChannel(channel);
      debugPrint('[Realtime] Unsubscribed from $topic');
    }
    _stopFallbackIfConnected();
  }

  void unsubscribeListener(String topic, VoidCallback onSignal) {
    final listeners = _listeners[topic];
    listeners?.remove(onSignal);
    if (listeners == null || listeners.isEmpty) unsubscribe(topic);
  }

  // ================================================================
  // UNSUBSCRIBE ALL — call on logout
  // ================================================================
  void unsubscribeAll() {
    final topics = List<String>.from(_channels.keys);
    for (final topic in topics) {
      unsubscribe(topic);
    }
    _fallbackTimer?.cancel();
    _fallbackTimer = null;
    _dispatchTimer?.cancel();
    _dispatchTimer = null;
    _pendingTopics.clear();
    debugPrint('[Realtime] All subscriptions cleared.');
  }

  // ================================================================
  // CONVENIENCE — subscribe filtered by entity_type column
  // ================================================================
  void subscribeToEntity({
    required String entityType,
    required VoidCallback onSignal,
  }) {
    subscribe(
      topic: entityType,
      table: 'realtime_events',
      filterColumn: 'entity_type',
      filterValue: entityType,
      onSignal: onSignal,
    );
  }

  /// Subscribe a listener to an entity and return a cancellation callback.
  /// Multiple providers share one channel and remove only their own listener.
  VoidCallback listenToEntity({
    required String entityType,
    required VoidCallback onSignal,
  }) {
    subscribeToEntity(entityType: entityType, onSignal: onSignal);
    return () => unsubscribeListener(entityType, onSignal);
  }

  void _startFallback() {
    _fallbackTimer ??= Timer.periodic(const Duration(seconds: 30), (_) {
      for (final entry in _listeners.entries) {
        if (_connected[entry.key] == true) continue;
        for (final listener in List<VoidCallback>.from(entry.value)) {
          listener();
        }
      }
    });
  }

  void _queueDispatch(Iterable<String> topics) {
    _pendingTopics.addAll(topics);
    _dispatchTimer ??= Timer(const Duration(milliseconds: 100), () {
      _dispatchTimer = null;
      final topicsToDispatch = Set<String>.from(_pendingTopics);
      _pendingTopics.clear();
      for (final topic in topicsToDispatch) {
        for (final listener in List<VoidCallback>.from(_listeners[topic] ?? {})) {
          listener();
        }
      }
    });
  }

  void _stopFallbackIfConnected() {
    if (_connected.isEmpty || _connected.values.every((connected) => connected)) {
      _fallbackTimer?.cancel();
      _fallbackTimer = null;
    }
  }
}
