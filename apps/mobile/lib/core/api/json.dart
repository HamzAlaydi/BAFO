/// Typed readers for API JSON (API.md §0.6).
///
/// Models are hand-written `fromJson` factories built on these helpers:
/// * required fields throw [FormatException] when missing or mistyped, so a
///   contract break fails loudly in tests instead of rendering nonsense;
/// * optional fields (`…OrNull`) accept `null` and absent keys (fields that
///   API.md marks "omitted" in some projections);
/// * lists default to empty when null or absent;
/// * timestamps are parsed as UTC instants;
/// * enums go through [WireEnum] and map unknown values to an `unknown` case
///   (CONVENTIONS.md §5.1: they never crash).
library;

typedef Json = Map<String, dynamic>;

/// An enum with the API's snake_case value in [wire].
abstract interface class WireEnum {
  String get wire;
}

/// The case of [values] whose [WireEnum.wire] equals [raw], else [fallback].
T parseWire<T extends WireEnum>(List<T> values, Object? raw, T fallback) {
  if (raw is String) {
    for (final value in values) {
      if (value.wire == raw) return value;
    }
  }
  return fallback;
}

/// Like [parseWire], but null stays null (the field is nullable).
T? parseWireOrNull<T extends WireEnum>(
  List<T> values,
  Object? raw,
  T fallback,
) => raw == null ? null : parseWire(values, raw, fallback);

extension JsonRead on Json {
  Never _bad(String key, String expected) => throw FormatException(
    'Expected $expected at "$key", got ${this[key]?.runtimeType ?? 'null'}',
  );

  String str(String key) {
    final value = this[key];
    if (value is String) return value;
    _bad(key, 'String');
  }

  String? strOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    if (value is String) return value;
    _bad(key, 'String?');
  }

  int integer(String key) {
    final value = this[key];
    if (value is int) return value;
    _bad(key, 'int');
  }

  int? intOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    if (value is int) return value;
    _bad(key, 'int?');
  }

  bool boolean(String key) {
    final value = this[key];
    if (value is bool) return value;
    _bad(key, 'bool');
  }

  bool? boolOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    if (value is bool) return value;
    _bad(key, 'bool?');
  }

  /// A boolean that defaults to [fallback] when absent or null.
  bool flag(String key, {bool fallback = false}) =>
      boolOrNull(key) ?? fallback;

  DateTime date(String key) {
    final value = this[key];
    if (value is String) {
      final parsed = DateTime.tryParse(value);
      if (parsed != null) return parsed.toUtc();
    }
    _bad(key, 'RFC 3339 timestamp');
  }

  DateTime? dateOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    if (value is String) {
      final parsed = DateTime.tryParse(value);
      if (parsed != null) return parsed.toUtc();
    }
    _bad(key, 'RFC 3339 timestamp?');
  }

  Json obj(String key) {
    final value = this[key];
    if (value is Map<String, dynamic>) return value;
    _bad(key, 'object');
  }

  Json? objOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    if (value is Map<String, dynamic>) return value;
    // PHP encodes an empty associative array as `[]`.
    if (value is List && value.isEmpty) return const {};
    _bad(key, 'object?');
  }

  /// The object at [key] parsed with [parse], or null.
  T? parse<T>(String key, T Function(Json json) parse) {
    final value = objOrNull(key);
    return value == null ? null : parse(value);
  }

  /// A list of objects parsed with [parse]; empty when null or absent.
  List<T> list<T>(String key, T Function(Json json) parse) {
    final value = this[key];
    if (value == null) return const [];
    if (value is List) {
      return List.unmodifiable(value.whereType<Json>().map(parse));
    }
    _bad(key, 'list');
  }

  /// A list of objects, or null when the field is null or absent (used where
  /// null and empty mean different things, e.g. a hidden ladder).
  List<T>? listOrNull<T>(String key, T Function(Json json) parse) =>
      this[key] == null ? null : list(key, parse);

  List<String> strings(String key) {
    final value = this[key];
    if (value == null) return const [];
    if (value is List) return List.unmodifiable(value.whereType<String>());
    _bad(key, 'list of strings');
  }

  /// A free-form map (`params`, `details`); `[]` from PHP reads as empty.
  Map<String, Object?> looseMap(String key) {
    final value = this[key];
    if (value is Map<String, dynamic>) return Map.unmodifiable(value);
    return const {};
  }
}

/// Serialises a UTC instant the way the API expects (RFC 3339, `Z`).
String toApiTime(DateTime instant) => instant.toUtc().toIso8601String();
