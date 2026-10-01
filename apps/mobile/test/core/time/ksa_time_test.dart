import 'package:bafo/core/time/ksa_time.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  setUpAll(() => initializeDateFormatting());

  // 12:05 UTC = 15:05 in Riyadh.
  final instant = DateTime.utc(2026, 11, 9, 12, 5);

  test('shows Riyadh wall-clock time whatever the device zone', () {
    expect(KsaTime.wallClock(instant).hour, 15);
    expect(KsaTime.wallClock(instant.toLocal()).hour, 15);
  });

  test('Arabic: Arabic month names, Western digits, ص/م', () {
    expect(KsaTime.longDate(instant, 'ar'), '9 نوفمبر 2026');
    expect(KsaTime.time(instant, 'ar'), '3:05 م');
    expect(KsaTime.dateTime(instant, 'ar'), '9 نوفمبر 2026، 3:05 م');
  });

  test('English: short month, 12-hour clock', () {
    expect(KsaTime.longDate(instant, 'en'), '9 Nov 2026');
    expect(KsaTime.dateTime(instant, 'en'), '9 Nov 2026, 3:05 PM');
  });

  test('the date rolls over at Riyadh midnight, not UTC midnight', () {
    final lateUtc = DateTime.utc(2026, 11, 9, 22, 30); // 01:30 on the 10th
    expect(KsaTime.longDate(lateUtc, 'en'), '10 Nov 2026');
  });
}
