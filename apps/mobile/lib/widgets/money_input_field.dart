import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/money/amount_input.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/widgets/bafo_text_field.dart';
import 'package:material_ui/material_ui.dart';

/// An amount input in riyals that yields integer halalas (SCREENS.md S6,
/// `MoneyInputField`).
///
/// * LTR island with tabular figures; the currency label sits on the
///   locale's side (`… ر.س` in Arabic, `SAR …` in English).
/// * Accepts Arabic-Indic digits, commas and at most two decimals; with a
///   granularity of 100 (whole riyals) the decimal key is hidden and halalas
///   are rejected.
/// * [onAmountChanged] gets the halalas, or null while the text is not a
///   usable amount. [validator] adds bound checks (the server still decides).
/// * Shows «الأسعار لا تشمل ضريبة القيمة المضافة» unless [helperText] is set.
class MoneyInputField extends StatelessWidget {
  const MoneyInputField({
    required this.label,
    this.controller,
    this.currency = Money.sar,
    this.granularityMinor = 1,
    this.onAmountChanged,
    this.validator,
    this.errorText,
    this.helperText,
    this.required = true,
    this.enabled = true,
    this.autofocus = false,
    this.textInputAction,
    this.onSubmitted,
    this.focusNode,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final String currency;

  /// `amount_granularity_minor`: 1 (halalas allowed) or 100 (whole riyals).
  final int granularityMinor;
  final ValueChanged<int?>? onAmountChanged;

  /// Extra rule on a well-formed amount (in halalas), e.g. a bound pre-check.
  final String? Function(int amountMinor)? validator;
  final String? errorText;
  final String? helperText;
  final bool required;
  final bool enabled;
  final bool autofocus;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;
  final FocusNode? focusNode;

  /// The localised message for [error], or null.
  static String? messageFor(AmountInputError? error, AppLocalizations l10n) =>
      switch (error) {
        null => null,
        AmountInputError.empty => l10n.validationRequired,
        AmountInputError.invalid => l10n.validationAmount,
        AmountInputError.notPositive => l10n.validationAmountPositive,
        AmountInputError.granularity => l10n.validationAmountWholeRiyals,
      };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final languageCode = context.languageCode;
    final currencyLabel = MoneyFormat.currencyLabel(currency, languageCode);
    final arabic = languageCode == 'ar';
    final wholeRiyals = granularityMinor >= Money.minorPerMajor;

    return BafoTextField(
      label: label,
      controller: controller,
      required: required,
      enabled: enabled,
      autofocus: autofocus,
      focusNode: focusNode,
      errorText: errorText,
      helperText: helperText ?? l10n.commonPricesExcludeVat,
      textDirection: TextDirection.ltr,
      textStyle: BafoTypography.tabular(
        Theme.of(context).textTheme.titleMedium ?? const TextStyle(),
      ),
      keyboardType: TextInputType.numberWithOptions(decimal: !wholeRiyals),
      textInputAction: textInputAction,
      inputFormatters: [AmountInputFormatter()],
      ltrPrefixText: arabic ? null : currencyLabel,
      ltrSuffixText: arabic ? currencyLabel : null,
      onSubmitted: onSubmitted,
      onChanged: (text) {
        final parsed = parseAmountInput(
          text,
          granularityMinor: granularityMinor,
        );
        onAmountChanged?.call(parsed.error == null ? parsed.amountMinor : null);
      },
      validator: (text) {
        final parsed = parseAmountInput(
          text ?? '',
          granularityMinor: granularityMinor,
        );
        if (parsed.error == AmountInputError.empty && !required) return null;
        final message = messageFor(parsed.error, l10n);
        if (message != null) return message;
        return validator?.call(parsed.amountMinor!);
      },
    );
  }
}
