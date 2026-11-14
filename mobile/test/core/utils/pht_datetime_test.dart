import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/utils/pht_datetime.dart';

void main() {
  group('PhtDateTime.parse', () {
    test('converts UTC instants to Philippine time across midnight', () {
      final result = PhtDateTime.parse('2026-11-14T16:30:00Z');

      expect(result.year, 2026);
      expect(result.month, 11);
      expect(result.day, 15);
      expect(result.hour, 0);
      expect(result.minute, 30);
    });

    test('preserves ISO timestamps already carrying the Manila offset', () {
      final result = PhtDateTime.parse('2026-11-15T00:30:00+08:00');

      expect(result.hour, 0);
      expect(result.minute, 30);
      expect(result.day, 15);
    });

    test('treats legacy timezone-less API timestamps as UTC', () {
      final result = PhtDateTime.parse('2026-11-14 16:30:00');

      expect(result.day, 15);
      expect(result.hour, 0);
      expect(result.minute, 30);
    });

    test('returns null for absent or invalid optional timestamps', () {
      expect(PhtDateTime.tryParse(null), isNull);
      expect(PhtDateTime.tryParse('not-a-timestamp'), isNull);
    });
  });
}
