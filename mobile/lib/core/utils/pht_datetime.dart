import 'package:timezone/data/latest.dart' as timezone_data;
import 'package:timezone/timezone.dart' as timezone;

class PhtDateTime {
  static final timezone.Location _manila = _initializeTimezone();

  static timezone.Location _initializeTimezone() {
    timezone_data.initializeTimeZones();
    return timezone.getLocation('Asia/Manila');
  }

  static DateTime now() =>
      timezone.TZDateTime.from(DateTime.now().toUtc(), _manila);

  static DateTime parse(Object value) {
    if (value is DateTime) {
      return timezone.TZDateTime.from(value.toUtc(), _manila);
    }

    var input = value.toString().trim();
    if (input.isEmpty) {
      throw const FormatException('Timestamp cannot be empty.');
    }

    if (input.contains(' ') && !input.contains('T')) {
      input = input.replaceFirst(' ', 'T');
    }

    final hasOffset = RegExp(
      r'(?:Z|[+-]\d{2}:?\d{2})$',
      caseSensitive: false,
    ).hasMatch(input);
    final parsed = DateTime.parse(hasOffset ? input : '${input}Z');

    return timezone.TZDateTime.from(parsed.toUtc(), _manila);
  }

  static DateTime? tryParse(Object? value) {
    if (value == null || value.toString().trim().isEmpty) {
      return null;
    }

    try {
      return parse(value);
    } on FormatException {
      return null;
    }
  }
}
