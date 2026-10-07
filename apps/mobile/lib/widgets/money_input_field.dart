import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/money/amount_input.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/widgets/bafo_text_field.dart';
import 'package:material_ui/material_ui.dart';

/// An amount input in riyals that yields integer halalas (SCREENS.md S6,
/// `MoneyInputField`; RELEASE_SCOPE.md FQ3).
///
/// * LTR island with tabular figures; the currency label sits on the
///   locale's side (`… ر.س` in Arabic, `SAR …` in English).
/// * Accepts Arabic-Indic digits, commas and at most two decimals; with a
///   granularity of 100 (whole riyals) the decimal key is hidden and halalas
///   are rejected.
/// * While the field is not focused a valid amount shows thousands
///   separators and two decimals (`125,000.00`); focusing it strips the
///   grouping so the digits can be edited.
/// * [onAmountChanged] gets the halalas, or null while the text is not a
///   usable amount. [validator] adds bound checks (the server still decides).
/// * Shows «الأسعار لا تشمل ضريبة القيمة المضافة» unless [helperText] is set,
///   and «مثال: 125,000.00» as the placeholder unless [hint] is set.
class MoneyInputField extends StatefulWidget {
  const MoneyInputField({
    required this.label,
    this.controller,
    this.currency = Money.sar,
    this.granularityMinor = 1,
    this.onAmountChanged,
    this.validator,
    this.errorText,
    this.helperText,
    this.hint,
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
  final String? hint;
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

  /// [minor] as the field shows it while not focused: `1,250.50`, or
  /// `1,250` when only whole riyals are allowed.
  static String groupedText(
    int minor, {
    required int granularityMinor,
    String currency = Money.sar,
  }) {
    final full = MoneyFormat.amountOnly(Money(minor, currency: currency));
    final wholeRiyals = granularityMinor >= Money.minorPerMajor;
    return wholeRiyals && full.endsWith('.00')
        ? full.substring(0, full.length - 3)
        : full;
  }

  @override
  State<MoneyInputField> createState() => _MoneyInputFieldState();
}

class _MoneyInputFieldState extends State<MoneyInputField> {
  TextEditingController? _ownController;
  FocusNode? _ownFocus;

  TextEditingController get _controller =>
      widget.controller ?? (_ownController ??= TextEditingController());

  FocusNode get _focus => widget.focusNode ?? (_ownFocus ??= FocusNode());

  @override
  void initState() {
    super.initState();
    _focus.addListener(_onFocusChange);
    if (!_focus.hasFocus) _group();
  }

  @override
  void didUpdateWidget(MoneyInputField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.focusNode != widget.focusNode) {
      (oldWidget.focusNode ?? _ownFocus)?.removeListener(_onFocusChange);
      _focus.addListener(_onFocusChange);
    }
  }

  @override
  void dispose() {
    _focus.removeListener(_onFocusChange);
    _ownFocus?.dispose();
    _ownController?.dispose();
    super.dispose();
  }

  void _onFocusChange() {
    if (_focus.hasFocus) {
      _ungroup();
    } else {
      _group();
    }
  }

  /// Editing: digits and the decimal point only.
  void _ungroup() {
    final text = _controller.text;
    final plain = text.replaceAll(',', '').replaceAll('٬', '');
    if (plain != text) _setText(plain);
  }

  /// Reading: a valid amount gets its separators and decimals.
  void _group() {
    final parsed = parseAmountInput(
      _controller.text,
      granularityMinor: widget.granularityMinor,
    );
    final minor = parsed.amountMinor;
    if (parsed.error != null || minor == null) return;
    final grouped = MoneyInputField.groupedText(
      minor,
      granularityMinor: widget.granularityMinor,
      currency: widget.currency,
    );
    if (grouped != _controller.text) _setText(grouped);
  }

  void _setText(String text) {
    _controller.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final languageCode = context.languageCode;
    final currencyLabel = MoneyFormat.currencyLabel(
      widget.currency,
      languageCode,
    );
    final arabic = languageCode == 'ar';
    final wholeRiyals = widget.granularityMinor >= Money.minorPerMajor;

    return BafoTextField(
      label: widget.label,
      controller: _controller,
      required: widget.required,
      enabled: widget.enabled,
      autofocus: widget.autofocus,
      focusNode: _focus,
      hint: widget.hint ?? l10n.commonAmountExample,
      errorText: widget.errorText,
      helperText: widget.helperText ?? l10n.commonPricesExcludeVat,
      textDirection: TextDirection.ltr,
      textStyle: BafoTypography.tabular(
        Theme.of(context).textTheme.titleMedium ?? const TextStyle(),
      ),
      keyboardType: TextInputType.numberWithOptions(decimal: !wholeRiyals),
      textInputAction: widget.textInputAction,
      inputFormatters: [AmountInputFormatter()],
      ltrPrefixText: arabic ? null : currencyLabel,
      ltrSuffixText: arabic ? currencyLabel : null,
      onSubmitted: widget.onSubmitted,
      onChanged: (text) {
        final parsed = parseAmountInput(
          text,
          granularityMinor: widget.granularityMinor,
        );
        widget.onAmountChanged?.call(
          parsed.error == null ? parsed.amountMinor : null,
        );
      },
      validator: (text) {
        final parsed = parseAmountInput(
          text ?? '',
          granularityMinor: widget.granularityMinor,
        );
        if (parsed.error == AmountInputError.empty && !widget.required) {
          return null;
        }
        final message = MoneyInputField.messageFor(parsed.error, l10n);
        if (message != null) return message;
        return widget.validator?.call(parsed.amountMinor!);
      },
    );
  }
}
