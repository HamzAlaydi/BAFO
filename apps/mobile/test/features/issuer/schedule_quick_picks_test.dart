import 'package:bafo/features/issuer/domain/schedule_quick_picks.dart';
import 'package:flutter_test/flutter_test.dart';

/// RELEASE_SCOPE.md §2.3: the quick-pick maths.
void main() {
  final now = DateTime.utc(2026, 11, 9, 13, 2, 17, 450);

  test('publish time is rounded up to the next 5 minutes', () {
    expect(roundUpToFiveMinutes(now), DateTime.utc(2026, 11, 9, 13, 5));
    expect(
      roundUpToFiveMinutes(DateTime.utc(2026, 11, 9, 13, 5)),
      DateTime.utc(2026, 11, 9, 13, 5),
    );
    expect(
      roundUpToFiveMinutes(DateTime.utc(2026, 11, 9, 13, 5, 0, 1)),
      DateTime.utc(2026, 11, 9, 13, 10),
    );
    expect(
      roundUpToFiveMinutes(DateTime.utc(2026, 11, 9, 23, 58)),
      DateTime.utc(2026, 11, 10),
    );
  });

  test('each pick adds its duration to the opening', () {
    final opens = DateTime.utc(2026, 11, 9, 16);
    expect(
      quickPickCloseAt(opens, ScheduleQuickPick.hour, now),
      DateTime.utc(2026, 11, 9, 17),
    );
    expect(
      quickPickCloseAt(opens, ScheduleQuickPick.hours3, now),
      DateTime.utc(2026, 11, 9, 19),
    );
    expect(
      quickPickCloseAt(opens, ScheduleQuickPick.day, now),
      DateTime.utc(2026, 11, 10, 16),
    );
    expect(
      quickPickCloseAt(opens, ScheduleQuickPick.days3, now),
      DateTime.utc(2026, 11, 12, 16),
    );
    expect(
      quickPickCloseAt(opens, ScheduleQuickPick.week, now),
      DateTime.utc(2026, 11, 16, 16),
    );
    expect(quickPickCloseAt(opens, ScheduleQuickPick.custom, now), isNull);
  });

  test('«فور النشر» counts from the rounded publish time', () {
    expect(
      quickPickCloseAt(null, ScheduleQuickPick.days3, now),
      DateTime.utc(2026, 11, 12, 13, 5),
    );
  });

  test('the active pick tolerates 60 seconds, otherwise custom', () {
    final opens = DateTime.utc(2026, 11, 9, 16);
    expect(
      activeQuickPick(opens, DateTime.utc(2026, 11, 12, 16), now),
      ScheduleQuickPick.days3,
    );
    expect(
      activeQuickPick(opens, DateTime.utc(2026, 11, 12, 16, 0, 59), now),
      ScheduleQuickPick.days3,
    );
    expect(
      activeQuickPick(opens, DateTime.utc(2026, 11, 12, 16, 1, 1), now),
      ScheduleQuickPick.custom,
    );
    expect(activeQuickPick(opens, null, now), ScheduleQuickPick.custom);
    // On publish: measured from the rounded publish time.
    expect(
      activeQuickPick(null, DateTime.utc(2026, 11, 9, 14, 5), now),
      ScheduleQuickPick.hour,
    );
  });

  test('the fixed picks keep the display order of §2.3', () {
    expect(ScheduleQuickPick.fixed.map((p) => p.duration), [
      const Duration(hours: 1),
      const Duration(hours: 3),
      const Duration(hours: 24),
      const Duration(hours: 72),
      const Duration(days: 7),
    ]);
  });
}
