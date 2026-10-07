/// Duration quick picks of the schedule step (RELEASE_SCOPE.md §2.3), as pure
/// functions shared by the wizard (M36) and its tests. Every instant is UTC.
library;

/// The chips of M36: fixed durations plus «مخصص».
enum ScheduleQuickPick {
  hour(Duration(hours: 1)),
  hours3(Duration(hours: 3)),
  day(Duration(hours: 24)),
  days3(Duration(hours: 72)),
  week(Duration(days: 7)),

  /// The date-time picker decides.
  custom(null);

  const ScheduleQuickPick(this.duration);

  /// Null for [custom].
  final Duration? duration;

  /// The fixed-duration picks, in display order.
  static const List<ScheduleQuickPick> fixed = [hour, hours3, day, days3, week];
}

/// A pick counts as selected while `close − opens` matches its duration
/// within this tolerance.
const Duration quickPickTolerance = Duration(seconds: 60);

/// [instant] rounded **up** to the next 5 minutes (seconds dropped). An
/// instant already on a 5-minute mark is returned unchanged.
DateTime roundUpToFiveMinutes(DateTime instant) {
  final utc = instant.toUtc();
  final floor = DateTime.utc(
    utc.year,
    utc.month,
    utc.day,
    utc.hour,
    utc.minute,
  );
  final onMark = floor.minute % 5 == 0 && floor == utc;
  if (onMark) return floor;
  final remainder = floor.minute % 5;
  return floor.add(Duration(minutes: 5 - remainder));
}

/// The opening the picks count from: [opensAt], or the server time rounded
/// up to the next 5 minutes when offers open on publish («فور النشر»).
DateTime quickPickOpens(DateTime? opensAt, DateTime now) =>
    opensAt?.toUtc() ?? roundUpToFiveMinutes(now);

/// The closing time [pick] produces from [opensAt] (or [now] when offers
/// open on publish); null for [ScheduleQuickPick.custom].
DateTime? quickPickCloseAt(
  DateTime? opensAt,
  ScheduleQuickPick pick,
  DateTime now,
) {
  final duration = pick.duration;
  if (duration == null) return null;
  return quickPickOpens(opensAt, now).add(duration);
}

/// The pick whose duration equals `closeAt − opens` within
/// [quickPickTolerance]; [ScheduleQuickPick.custom] otherwise (also while
/// there is no closing time yet).
ScheduleQuickPick activeQuickPick(
  DateTime? opensAt,
  DateTime? closeAt,
  DateTime now,
) {
  if (closeAt == null) return ScheduleQuickPick.custom;
  final actual = closeAt.toUtc().difference(quickPickOpens(opensAt, now));
  for (final pick in ScheduleQuickPick.fixed) {
    final delta = (actual - pick.duration!).abs();
    if (delta <= quickPickTolerance) return pick;
  }
  return ScheduleQuickPick.custom;
}
