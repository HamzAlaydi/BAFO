import 'package:intl/intl.dart';

/// Display formats for instants (CONVENTIONS.md §9.1): transported in UTC,
/// always shown in Asia/Riyadh, Gregorian calendar, Western digits.
///
/// Riyadh is UTC+3 all year (no daylight saving), so no time-zone database is
/// needed. Date symbols for `ar`/`en` are loaded by the Material localisation
/// delegates; plain Dart tests call `initializeDateFormatting()` first.
abstract final class KsaTime {
  static const Duration utcOffset = Duration(hours: 3);

  /// [instant] as Riyadh wall-clock time. The result is UTC-flagged; read
  /// its fields (hour, day, ...) as Riyadh time and never convert it again.
  static DateTime wallClock(DateTime instant) => instant.toUtc().add(utcOffset);

  /// «9 نوفمبر 2026» / "9 Nov 2026".
  static String longDate(DateTime instant, String languageCode) => _format(
    languageCode == 'ar' ? 'd MMMM yyyy' : 'd MMM yyyy',
    languageCode,
    instant,
  );

  /// «3:05 م» / "3:05 PM" (12-hour).
  static String time(DateTime instant, String languageCode) =>
      _format('h:mm a', languageCode, instant);

  /// «9 نوفمبر 2026، 3:05 م» / "9 Nov 2026, 3:05 PM".
  static String dateTime(DateTime instant, String languageCode) {
    final separator = languageCode == 'ar' ? '، ' : ', ';
    return '${longDate(instant, languageCode)}$separator'
        '${time(instant, languageCode)}';
  }

  static String _format(String pattern, String languageCode, DateTime at) {
    final format = DateFormat(pattern, languageCode)..useNativeDigits = false;
    return format.format(wallClock(at));
  }
}
