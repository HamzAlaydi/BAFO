import 'package:bafo/core/money/money.dart';
import 'package:intl/intl.dart';

/// Formats [Money] for display (CONVENTIONS.md §9.2, the same algorithm as
/// the web `formatMoney`).
///
/// Always two decimals, `,` grouping and Western digits (0-9), in both
/// languages.
///
/// * Arabic: `1,250.50 ر.س`
/// * English: `SAR 1,250.50`
abstract final class MoneyFormat {
  static final NumberFormat _grouped = NumberFormat('#,##0', 'en');

  static const Map<String, Map<String, String>> _labels = {
    Money.sar: {'ar': 'ر.س', 'en': 'SAR'},
  };

  /// Amount with its currency label for [languageCode] (`ar` or `en`).
  static String format(Money money, {required String languageCode}) {
    final amount = amountOnly(money);
    final label = currencyLabel(money.currency, languageCode);
    return languageCode == 'ar' ? '$amount $label' : '$label $amount';
  }

  /// Amount without a currency label: `1,250.50`.
  static String amountOnly(Money money) {
    final minor = money.amountMinor.abs();
    final major = minor ~/ Money.minorPerMajor;
    final fraction = minor % Money.minorPerMajor;
    final sign = money.isNegative ? '-' : '';
    final grouped = _grouped.format(major);
    return '$sign$grouped.${fraction.toString().padLeft(2, '0')}';
  }

  /// `ر.س` / `SAR`; other currencies fall back to their ISO code.
  static String currencyLabel(String currency, String languageCode) =>
      _labels[currency]?[languageCode] ?? currency;
}
