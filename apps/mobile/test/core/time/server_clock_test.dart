import 'package:bafo/core/time/server_clock.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late DateTime deviceNow;
  late ServerClock clock;

  setUp(() {
    deviceNow = DateTime.utc(2026, 10, 1, 12);
    clock = ServerClock(deviceNow: () => deviceNow);
  });

  test('uses the device clock until the first sync', () {
    expect(clock.isSynced, isFalse);
    expect(clock.offset, Duration.zero);
    expect(clock.now(), deviceNow);
  });

  test('applies the offset from a server timestamp', () {
    // The device runs 90 s behind the server.
    clock.sync(DateTime.utc(2026, 10, 1, 12, 1, 30));

    expect(clock.isSynced, isTrue);
    expect(clock.offset, const Duration(seconds: 90));
    deviceNow = deviceNow.add(const Duration(seconds: 10));
    expect(clock.now(), DateTime.utc(2026, 10, 1, 12, 1, 40));
  });

  test('assumes the server stamped at the round-trip midpoint', () {
    final sent = deviceNow;
    final received = deviceNow.add(const Duration(milliseconds: 800));
    // Server time equals device time at the midpoint (sent + 400 ms).
    clock.sync(
      sent.add(const Duration(milliseconds: 400)),
      requestSentAt: sent,
      responseReceivedAt: received,
    );

    expect(clock.offset, Duration.zero);
    expect(clock.lastRoundTrip, const Duration(milliseconds: 800));
    expect(clock.isSlow, isFalse);
  });

  test('flags round trips over two seconds as slow', () {
    clock.sync(
      deviceNow,
      requestSentAt: deviceNow.subtract(const Duration(seconds: 3)),
      responseReceivedAt: deviceNow,
    );
    expect(clock.isSlow, isTrue);
  });

  test('uses the median of the last three samples', () {
    clock
      ..sync(deviceNow.add(const Duration(seconds: 10)))
      ..sync(deviceNow.add(const Duration(seconds: 12)))
      // One outlier (a slow, stale response) does not move the clock.
      ..sync(deviceNow.add(const Duration(seconds: 60)));
    expect(clock.offset, const Duration(seconds: 12));

    // The oldest sample (10 s) drops out: median of 12, 60, 14.
    clock.sync(deviceNow.add(const Duration(seconds: 14)));
    expect(clock.offset, const Duration(seconds: 14));
  });

  test('parses ISO timestamps and ignores invalid ones', () {
    expect(clock.syncFromIso('2026-10-01T12:00:05.000Z'), isTrue);
    expect(clock.offset, const Duration(seconds: 5));

    expect(clock.syncFromIso('not a date'), isFalse);
    expect(clock.syncFromIso(null), isFalse);
    expect(clock.offset, const Duration(seconds: 5));
  });

  test('remaining time is measured on the server clock and never negative', () {
    // The device clock is 30 s fast; the competition closes at 12:01 server.
    clock.sync(deviceNow.subtract(const Duration(seconds: 30)));
    final closeAt = DateTime.utc(2026, 10, 1, 12, 1);

    expect(clock.remainingUntil(closeAt), const Duration(seconds: 90));
    expect(clock.hasPassed(closeAt), isFalse);

    deviceNow = deviceNow.add(const Duration(minutes: 5));
    expect(clock.remainingUntil(closeAt), Duration.zero);
    expect(clock.hasPassed(closeAt), isTrue);
  });

  test('treats local times as instants', () {
    final local = DateTime.utc(2026, 10, 1, 12, 0, 10).toLocal();
    expect(clock.remainingUntil(local), const Duration(seconds: 10));
  });
}
