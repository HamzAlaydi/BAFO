import 'package:equatable/equatable.dart';

/// An amount in minor units (halalas for SAR), exactly as the API sends it:
/// `{ "amount_minor": 125050, "currency": "SAR" }`.
///
/// Arithmetic stays in integers; never convert money to `double`.
final class Money extends Equatable implements Comparable<Money> {
  const Money(this.amountMinor, {this.currency = sar});

  /// Parses the API shape `{amount_minor, currency}`.
  factory Money.fromJson(Map<String, dynamic> json) {
    final amount = json['amount_minor'];
    final currency = json['currency'];
    if (amount is! int) {
      throw FormatException('amount_minor must be an integer', json);
    }
    return Money(amount, currency: currency is String ? currency : sar);
  }

  static const String sar = 'SAR';

  /// Minor units per major unit (100 halalas = 1 riyal).
  static const int minorPerMajor = 100;

  static const Money zeroSar = Money(0);

  final int amountMinor;

  /// ISO 4217 code.
  final String currency;

  bool get isZero => amountMinor == 0;
  bool get isNegative => amountMinor < 0;

  Money operator +(Money other) {
    _assertSameCurrency(other);
    return Money(amountMinor + other.amountMinor, currency: currency);
  }

  Money operator -(Money other) {
    _assertSameCurrency(other);
    return Money(amountMinor - other.amountMinor, currency: currency);
  }

  Money operator -() => Money(-amountMinor, currency: currency);

  bool operator <(Money other) => compareTo(other) < 0;
  bool operator >(Money other) => compareTo(other) > 0;

  /// [basisPoints] / 10 000 of this amount, rounded half away from zero to a
  /// whole minor unit (1500 bp = 15%).
  Money percent(int basisPoints) {
    final product = amountMinor * basisPoints;
    final rounded = (product.abs() + 5000) ~/ 10000;
    return Money(product < 0 ? -rounded : rounded, currency: currency);
  }

  @override
  int compareTo(Money other) {
    _assertSameCurrency(other);
    return amountMinor.compareTo(other.amountMinor);
  }

  Map<String, Object> toJson() => {
    'amount_minor': amountMinor,
    'currency': currency,
  };

  /// Parses a user-typed amount in major units ("1,250.5", "١٢٥٠٫٥").
  ///
  /// Accepts Arabic-Indic and Eastern Arabic-Indic digits, the Arabic decimal
  /// separator (٫) and thousands separators (, ٬). At most two decimals.
  /// Returns null for anything else, including negatives.
  static Money? tryParse(String input, {String currency = sar}) {
    final normalized = _normalizeDigits(input.trim())
        .replaceAll(RegExp('[,٬\\s]'), '')
        .replaceAll('٫', '.');
    final match = RegExp(r'^(\d+)(?:\.(\d{1,2}))?$').firstMatch(normalized);
    if (match == null) return null;
    final major = int.tryParse(match.group(1)!);
    if (major == null) return null;
    final minorDigits = (match.group(2) ?? '').padRight(2, '0');
    return Money(
      major * minorPerMajor + int.parse(minorDigits),
      currency: currency,
    );
  }

  static String _normalizeDigits(String input) {
    final out = StringBuffer();
    for (final rune in input.runes) {
      if (rune >= 0x0660 && rune <= 0x0669) {
        out.writeCharCode(0x30 + rune - 0x0660); // Arabic-Indic
      } else if (rune >= 0x06F0 && rune <= 0x06F9) {
        out.writeCharCode(0x30 + rune - 0x06F0); // Eastern Arabic-Indic
      } else {
        out.writeCharCode(rune);
      }
    }
    return out.toString();
  }

  void _assertSameCurrency(Money other) {
    if (other.currency != currency) {
      throw ArgumentError('Currency mismatch: $currency vs ${other.currency}');
    }
  }

  @override
  List<Object?> get props => [amountMinor, currency];

  @override
  String toString() => 'Money($amountMinor $currency)';
}

/// Saudi VAT. Prices in BAFO exclude VAT.
abstract final class Vat {
  /// 15%.
  static const int rateBasisPoints = 1500;

  /// The VAT due on a net (VAT-exclusive) amount.
  static Money of(Money net) => net.percent(rateBasisPoints);

  /// Net amount plus VAT.
  static Money gross(Money net) => net + of(net);
}
