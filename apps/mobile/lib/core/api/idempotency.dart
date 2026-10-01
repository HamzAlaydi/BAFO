import 'package:uuid/uuid.dart';

/// `Idempotency-Key` values (API.md §0.2): 8–64 chars of `[A-Za-z0-9_-]`,
/// one per user intent.
///
/// Create one when the user opens the confirm step and reuse it for every
/// retry of that intent (timeout, network error, 5xx). A new intent (another
/// amount, a reopened sheet, the outlier re-send) gets a new key
/// (CONVENTIONS.md §4.2, SCREENS.md S5).
abstract final class IdempotencyKey {
  static const String header = 'Idempotency-Key';

  /// Response header set on a replayed request.
  static const String replayedHeader = 'Idempotent-Replayed';

  static const Uuid _uuid = Uuid();

  /// A UUID v4, e.g. `3f2b8c1e-…` (36 chars, allowed by the API pattern).
  static String generate() => _uuid.v4();

  static final RegExp _pattern = RegExp(r'^[A-Za-z0-9_-]{8,64}$');

  static bool isValid(String key) => _pattern.hasMatch(key);
}
