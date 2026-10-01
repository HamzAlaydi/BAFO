import 'package:bafo/core/money/money.dart';

/// Why a typed amount is not usable.
enum AmountInputError {
  empty,

  /// Not a number, a negative number, or more than two decimals.
  invalid,

  /// Zero.
  notPositive,

  /// Not a multiple of the competition's `amount_granularity_minor`
  /// (100 = whole riyals).
  granularity,
}

/// The web `parseAmountToMinor` for Flutter (SCREENS.md S5): Arabic-Indic and
/// Persian digits, commas and at most two decimals are accepted; no floats.
/// Returns the amount in halalas, or the reason it is not usable.
({int? amountMinor, AmountInputError? error}) parseAmountInput(
  String text, {
  int granularityMinor = 1,
}) {
  if (text.trim().isEmpty) {
    return (amountMinor: null, error: AmountInputError.empty);
  }
  final money = Money.tryParse(text);
  if (money == null) return (amountMinor: null, error: AmountInputError.invalid);
  final minor = money.amountMinor;
  if (minor <= 0) {
    return (amountMinor: minor, error: AmountInputError.notPositive);
  }
  if (granularityMinor > 1 && minor % granularityMinor != 0) {
    return (amountMinor: minor, error: AmountInputError.granularity);
  }
  return (amountMinor: minor, error: null);
}
