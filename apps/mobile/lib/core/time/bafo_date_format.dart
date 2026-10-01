import 'package:bafo/core/time/ksa_time.dart';
import 'package:bafo/l10n/generated/app_localizations.dart';
import 'package:intl/intl.dart';

/// Every date and time the app shows (CONVENTIONS.md §9.1, SCREENS.md S6):
/// Asia/Riyadh, Gregorian calendar, Arabic month names in Arabic, and always
/// Western digits (0–9).
///
/// | Format        | Arabic                     | English               |
/// |---------------|----------------------------|-----------------------|
/// | [longDate]    | «9 نوفمبر 2026»            | 9 Nov 2026            |
/// | [time]        | «12:59 م»                  | 12:59 PM              |
/// | [dateTime]    | «9 نوفمبر 2026، 12:59 م»   | 9 Nov 2026, 12:59 PM  |
/// | [deadline]    | «… بتوقيت الرياض»          | … Riyadh time         |
/// | [relative]    | «منذ 5 دقائق»              | 5 minutes ago         |
/// | [machine]     | 2026-11-09 14:59:58.412 (KSA) (both)               |
abstract final class BafoDateFormat {
  /// Call once at start-up (SCREENS.md R-M3): `intl` must never switch to
  /// Arabic-Indic digits, even for patterns created elsewhere.
  static void useWesternDigits() {
    DateFormat.useNativeDigitsByDefaultFor('ar', false);
  }

  static String longDate(DateTime instant, String languageCode) =>
      KsaTime.longDate(instant, languageCode);

  static String time(DateTime instant, String languageCode) =>
      KsaTime.time(instant, languageCode);

  static String dateTime(DateTime instant, String languageCode) =>
      KsaTime.dateTime(instant, languageCode);

  /// [dateTime] with the Riyadh-time suffix, for deadlines and schedules.
  static String deadline(
    DateTime instant,
    String languageCode,
    AppLocalizations l10n,
  ) => l10n.commonTimeRiyadh(dateTime(instant, languageCode));

  /// "5 minutes ago" for activity and notification lists; from 7 days on,
  /// the [longDate]. [now] is server time (`ServerClock.now()`).
  static String relative(
    DateTime instant, {
    required DateTime now,
    required String languageCode,
    required AppLocalizations l10n,
  }) {
    final age = now.toUtc().difference(instant.toUtc());
    if (age.inMinutes < 1) return l10n.commonTimeJustNow;
    if (age.inHours < 1) return l10n.commonTimeMinutesAgo(age.inMinutes);
    if (age.inDays < 1) return l10n.commonTimeHoursAgo(age.inHours);
    if (age.inDays < 7) return l10n.commonTimeDaysAgo(age.inDays);
    return longDate(instant, languageCode);
  }

  /// Offer logs and receipts: `2026-11-09 14:59:58.412 (KSA)`.
  static String machine(DateTime instant) {
    final wall = KsaTime.wallClock(instant);
    String two(int value) => value.toString().padLeft(2, '0');
    final millis = wall.millisecond.toString().padLeft(3, '0');
    return '${wall.year}-${two(wall.month)}-${two(wall.day)} '
        '${two(wall.hour)}:${two(wall.minute)}:${two(wall.second)}.$millis '
        '(KSA)';
  }

  /// Time of day with milliseconds, `14:59:58.412` (offer receipts).
  static String timeWithMillis(DateTime instant) {
    final wall = KsaTime.wallClock(instant);
    String two(int value) => value.toString().padLeft(2, '0');
    return '${two(wall.hour)}:${two(wall.minute)}:${two(wall.second)}.'
        '${wall.millisecond.toString().padLeft(3, '0')}';
  }

  /// A Riyadh wall-clock date and time (what the user picked) as the UTC
  /// instant to send to the API (the web's `zonedInputToUtcIso`). Only the
  /// fields of [wallClock] are read; its time-zone flag is ignored.
  static DateTime fromRiyadhWallClock(DateTime wallClock) => DateTime.utc(
    wallClock.year,
    wallClock.month,
    wallClock.day,
    wallClock.hour,
    wallClock.minute,
    wallClock.second,
  ).subtract(KsaTime.utcOffset);

  /// [instant] as Riyadh wall-clock fields for date/time pickers (the web's
  /// `utcIsoToZonedInput`).
  static DateTime toRiyadhWallClock(DateTime instant) =>
      KsaTime.wallClock(instant);
}
