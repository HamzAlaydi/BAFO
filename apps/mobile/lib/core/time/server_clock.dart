/// The server's clock as seen from this device (ARCHITECTURE.md §9.6).
///
/// The server is authoritative for bidding. Every app v1 response carries
/// `meta.server_time` and every live snapshot carries `server_time`;
/// `ServerTimeInterceptor` feeds them here. Deadlines and countdowns use
/// [now], never the device clock alone, so a wrong phone clock cannot make a
/// competition look open or closed.
///
/// Each sample assumes the server stamped its time halfway through the round
/// trip: `offset = server_time − (t_send + t_recv) / 2`. The offset in use is
/// the median of the last [sampleSize] samples, which discards one outlier
/// (a slow response, a clock jump).
class ServerClock {
  ServerClock({DateTime Function()? deviceNow})
    : _deviceNow = deviceNow ?? DateTime.now;

  /// Samples kept for the median.
  static const int sampleSize = 3;

  /// Round trips above this make the offset less reliable (§9.6: show a hint).
  static const Duration slowRoundTrip = Duration(seconds: 2);

  final DateTime Function() _deviceNow;
  final List<Duration> _samples = [];
  Duration _offset = Duration.zero;
  Duration? _lastRoundTrip;

  /// Server time minus device time. Zero until the first sync.
  Duration get offset => _offset;

  /// Whether at least one server timestamp has been applied.
  bool get isSynced => _samples.isNotEmpty;

  /// Round trip of the latest sample, when it was measured.
  Duration? get lastRoundTrip => _lastRoundTrip;

  /// Whether the latest round trip was slow enough to warn the user.
  bool get isSlow {
    final roundTrip = _lastRoundTrip;
    return roundTrip != null && roundTrip > slowRoundTrip;
  }

  /// Current server time (UTC).
  DateTime now() => _deviceNow().toUtc().add(_offset);

  /// Adds a server timestamp sample.
  ///
  /// [requestSentAt] and [responseReceivedAt] are device times around the
  /// request that returned [serverTime]. Without [requestSentAt] the response
  /// time stands in for the midpoint.
  void sync(
    DateTime serverTime, {
    DateTime? requestSentAt,
    DateTime? responseReceivedAt,
  }) {
    final received = (responseReceivedAt ?? _deviceNow()).toUtc();
    final sent = requestSentAt?.toUtc();
    final DateTime midpoint;
    if (sent == null || sent.isAfter(received)) {
      midpoint = received;
      _lastRoundTrip = null;
    } else {
      final roundTrip = received.difference(sent);
      midpoint = sent.add(roundTrip ~/ 2);
      _lastRoundTrip = roundTrip;
    }

    _samples.add(serverTime.toUtc().difference(midpoint));
    if (_samples.length > sampleSize) _samples.removeAt(0);
    _offset = _median(_samples);
  }

  /// [sync] from an ISO-8601 string. Returns false (and changes nothing) when
  /// [value] is not a valid timestamp.
  bool syncFromIso(
    String? value, {
    DateTime? requestSentAt,
    DateTime? responseReceivedAt,
  }) {
    final parsed = value == null ? null : DateTime.tryParse(value);
    if (parsed == null) return false;
    sync(
      parsed,
      requestSentAt: requestSentAt,
      responseReceivedAt: responseReceivedAt,
    );
    return true;
  }

  /// Time left until [deadline]; never negative.
  Duration remainingUntil(DateTime deadline) {
    final left = deadline.toUtc().difference(now());
    return left.isNegative ? Duration.zero : left;
  }

  /// Whether [instant] is at or before the current server time.
  bool hasPassed(DateTime instant) => !now().isBefore(instant.toUtc());

  static Duration _median(List<Duration> samples) {
    final sorted = [...samples]..sort();
    final middle = sorted.length ~/ 2;
    if (sorted.length.isOdd) return sorted[middle];
    return (sorted[middle - 1] + sorted[middle]) ~/ 2;
  }
}
