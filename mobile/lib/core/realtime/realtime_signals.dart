import 'package:supabase_flutter/supabase_flutter.dart';

class RealtimeSignals {
  static RealtimeChannel listenForEntity(
    String entityType,
    void Function() onSignal,
  ) {
    final channel = Supabase.instance.client
        .channel('signals-$entityType-${DateTime.now().millisecondsSinceEpoch}')
        .onPostgresChanges(
          event: PostgresChangeEvent.insert,
          schema: 'public',
          table: 'realtime_events',
          filter: PostgresChangeFilter(
            type: PostgresChangeFilterType.eq,
            column: 'entity_type',
            value: entityType,
          ),
          callback: (payload) => onSignal(),
        )
        .subscribe();

    return channel;
  }

  static void stop(RealtimeChannel channel) {
    Supabase.instance.client.removeChannel(channel);
  }
}
