import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/widgets/bafo_text_field.dart';
import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

/// Saudi mobile number: a fixed `+966` prefix, 9 digits starting with 5, a
/// numeric keypad, LTR (SCREENS.md S6). The controller holds the 9 local
/// digits; send [toE164] to the API.
class PhoneField extends StatelessWidget {
  const PhoneField({
    required this.controller,
    this.label,
    this.errorText,
    this.required = true,
    this.textInputAction,
    this.onChanged,
    super.key,
  });

  final TextEditingController controller;
  final String? label;
  final String? errorText;
  final bool required;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onChanged;

  static const String countryPrefix = '+966';

  /// `+9665XXXXXXXX` from the typed local digits.
  static String toE164(String local) =>
      '$countryPrefix${normalizeDigits(local.trim())}';

  /// The 9 local digits of an E.164 number (to pre-fill the field).
  static String fromE164(String? e164) {
    final value = e164 ?? '';
    return value.startsWith(countryPrefix)
        ? value.substring(countryPrefix.length)
        : value;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BafoTextField(
      label: label ?? l10n.authFieldsPhoneLabel,
      controller: controller,
      hint: l10n.authFieldsPhoneHint,
      errorText: errorText,
      required: required,
      keyboardType: TextInputType.phone,
      textInputAction: textInputAction,
      autofillHints: const [AutofillHints.telephoneNumberNational],
      inputFormatters: [DigitsOnlyFormatter(maxLength: 9)],
      textDirection: TextDirection.ltr,
      ltrPrefixText: countryPrefix,
      onChanged: onChanged,
      validator: (value) => (value ?? '').isEmpty && !required
          ? null
          : Validators.localPhone(value, l10n),
    );
  }
}

/// A fixed-length numeric field (CR: 10, VAT: 15), LTR with a numeric
/// keypad; Arabic-Indic digits are normalised as they are typed.
class DigitsField extends StatelessWidget {
  const DigitsField({
    required this.label,
    required this.controller,
    required this.maxLength,
    this.validator,
    this.errorText,
    this.helperText,
    this.hint,
    this.required = false,
    this.optional = false,
    this.enabled = true,
    this.textInputAction,
    this.onChanged,
    super.key,
  });

  final String label;
  final TextEditingController controller;
  final int maxLength;
  final FormFieldValidator<String>? validator;
  final String? errorText;
  final String? helperText;
  final String? hint;
  final bool required;
  final bool optional;
  final bool enabled;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) => BafoTextField(
    label: label,
    controller: controller,
    hint: hint,
    errorText: errorText,
    helperText: helperText,
    required: required,
    optional: optional,
    enabled: enabled,
    keyboardType: TextInputType.number,
    textInputAction: textInputAction,
    inputFormatters: [DigitsOnlyFormatter(maxLength: maxLength)],
    textDirection: TextDirection.ltr,
    validator: validator,
    onChanged: onChanged,
  );
}

/// The one-time code: one input (autofill `oneTimeCode`, numeric, LTR)
/// drawn as six boxes (SCREENS.md S9). [onCompleted] fires at 6 digits.
class OtpField extends StatefulWidget {
  const OtpField({
    required this.controller,
    this.onCompleted,
    this.onChanged,
    this.errorText,
    this.enabled = true,
    this.autofocus = true,
    this.length = 6,
    super.key,
  });

  final TextEditingController controller;
  final ValueChanged<String>? onCompleted;
  final ValueChanged<String>? onChanged;
  final String? errorText;
  final bool enabled;
  final bool autofocus;
  final int length;

  @override
  State<OtpField> createState() => _OtpFieldState();
}

class _OtpFieldState extends State<OtpField> {
  final FocusNode _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_refresh);
    _focus.addListener(_refresh);
  }

  @override
  void didUpdateWidget(OtpField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_refresh);
      widget.controller.addListener(_refresh);
    }
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.controller.removeListener(_refresh);
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final code = widget.controller.text;
    final error = widget.errorText;
    final l10n = context.l10n;

    final boxes = Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < widget.length; i++) ...[
          if (i > 0) const SizedBox(width: BafoSpacing.sm),
          Flexible(
            child: AspectRatio(
              aspectRatio: 0.85,
              child: Container(
                constraints: const BoxConstraints(maxWidth: 52),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(BafoRadii.input),
                  border: Border.all(
                    color: error != null
                        ? scheme.error
                        : _focus.hasFocus && i == code.length.clamp(0, widget.length - 1)
                        ? scheme.primary
                        : scheme.outline,
                    width: _focus.hasFocus && i == code.length ? 2 : 1,
                  ),
                ),
                child: Text(
                  i < code.length ? code[i] : '',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Directionality(
          textDirection: TextDirection.ltr,
          child: Stack(
            alignment: Alignment.center,
            children: [
              ExcludeSemantics(child: boxes),
              Positioned.fill(
                child: Opacity(
                  opacity: 0,
                  alwaysIncludeSemantics: true,
                  child: Semantics(
                    label: l10n.authOtpLabel,
                    child: TextField(
                      key: const Key('otp.input'),
                      controller: widget.controller,
                      focusNode: _focus,
                      enabled: widget.enabled,
                      autofocus: widget.autofocus,
                      keyboardType: TextInputType.number,
                      autofillHints: const [AutofillHints.oneTimeCode],
                      inputFormatters: [
                        DigitsOnlyFormatter(maxLength: widget.length),
                      ],
                      showCursor: false,
                      enableInteractiveSelection: false,
                      decoration: const InputDecoration.collapsed(hintText: ''),
                      onChanged: (value) {
                        widget.onChanged?.call(value);
                        if (value.length == widget.length) {
                          widget.onCompleted?.call(value);
                        }
                      },
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsetsDirectional.only(top: BafoSpacing.sm),
            child: Text(
              error,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(color: scheme.error),
            ),
          ),
      ],
    );
  }
}

/// A password with show/hide and, with [showRules], the live rule checklist
/// (≥ 8, lower, upper, digit, symbol; ARCHITECTURE.md §13.9).
class PasswordField extends StatefulWidget {
  const PasswordField({
    required this.controller,
    this.label,
    this.errorText,
    this.showRules = false,
    this.validator,
    this.textInputAction,
    this.autofillHints = const [AutofillHints.password],
    this.onChanged,
    this.onSubmitted,
    super.key,
  });

  final TextEditingController controller;
  final String? label;
  final String? errorText;
  final bool showRules;
  final FormFieldValidator<String>? validator;
  final TextInputAction? textInputAction;
  final Iterable<String> autofillHints;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;

  @override
  State<PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<PasswordField> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_refresh);
  }

  void _refresh() {
    if (mounted && widget.showRules) setState(() {});
  }

  @override
  void dispose() {
    widget.controller.removeListener(_refresh);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final rules = PasswordRules.of(widget.controller.text);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        BafoTextField(
          label: widget.label ?? l10n.authFieldsPasswordLabel,
          controller: widget.controller,
          errorText: widget.errorText,
          obscure: true,
          required: true,
          textInputAction: widget.textInputAction,
          autofillHints: widget.autofillHints,
          textDirection: TextDirection.ltr,
          validator: widget.validator,
          onChanged: widget.onChanged,
          onSubmitted: widget.onSubmitted,
        ),
        if (widget.showRules) ...[
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.authPasswordRulesTitle,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          for (final (met, text) in [
            (rules.length, l10n.authPasswordRuleLength),
            (rules.lowercase, l10n.authPasswordRuleLower),
            (rules.uppercase, l10n.authPasswordRuleUpper),
            (rules.digit, l10n.authPasswordRuleDigit),
            (rules.symbol, l10n.authPasswordRuleSymbol),
          ])
            _RuleRow(met: met, text: text),
        ],
      ],
    );
  }
}

class _RuleRow extends StatelessWidget {
  const _RuleRow({required this.met, required this.text});

  final bool met;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l10n = context.l10n;
    final color = met
        ? context.semanticColors.success.foreground
        : theme.colorScheme.onSurfaceVariant;
    return Semantics(
      label: '$text, ${met ? l10n.commonRuleMet : l10n.commonRuleNotMet}',
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsetsDirectional.only(top: BafoSpacing.xs),
        child: Row(
          children: [
            Icon(
              met ? Icons.check_circle_rounded : Icons.radio_button_unchecked,
              size: 16,
              color: color,
            ),
            const SizedBox(width: BafoSpacing.sm),
            Expanded(
              child: Text(
                text,
                style: theme.textTheme.bodySmall?.copyWith(color: color),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A search box that reports after 300 ms without typing (CONVENTIONS.md
/// §9.3), with a clear button.
class SearchField extends StatefulWidget {
  const SearchField({
    required this.onChanged,
    this.hint,
    this.initialValue = '',
    this.debounce = const Duration(milliseconds: 300),
    super.key,
  });

  final ValueChanged<String> onChanged;
  final String? hint;
  final String initialValue;
  final Duration debounce;

  @override
  State<SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<SearchField> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialValue,
  );
  Timer? _timer;

  void _changed(String value) {
    setState(() {});
    _timer?.cancel();
    _timer = Timer(widget.debounce, () => widget.onChanged(value.trim()));
  }

  void _clear() {
    _controller.clear();
    _timer?.cancel();
    setState(() {});
    widget.onChanged('');
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Semantics(
      label: l10n.commonActionsSearch,
      textField: true,
      child: TextField(
        controller: _controller,
        onChanged: _changed,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: widget.hint ?? l10n.commonActionsSearch,
          prefixIcon: const Icon(Icons.search_rounded),
          suffixIcon: _controller.text.isEmpty
              ? null
              : IconButton(
                  tooltip: l10n.commonActionsClear,
                  onPressed: _clear,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
      ),
    );
  }
}

/// A date and time picked and shown in Riyadh time; [value] and
/// [onChanged] use UTC instants (the web `zonedInputToUtcIso` pair).
class DateTimeField extends StatefulWidget {
  const DateTimeField({
    required this.label,
    required this.onChanged,
    this.value,
    this.firstDate,
    this.lastDate,
    this.errorText,
    this.helperText,
    this.required = false,
    this.enabled = true,
    super.key,
  });

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final DateTime? firstDate;
  final DateTime? lastDate;
  final String? errorText;
  final String? helperText;
  final bool required;
  final bool enabled;

  @override
  State<DateTimeField> createState() => _DateTimeFieldState();
}

class _DateTimeFieldState extends State<DateTimeField> {
  final TextEditingController _text = TextEditingController();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncText();
  }

  @override
  void didUpdateWidget(DateTimeField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) _syncText();
  }

  void _syncText() {
    final current = widget.value;
    _text.text = current == null
        ? ''
        : BafoDateFormat.deadline(current, context.languageCode, context.l10n);
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _pick(BuildContext context) async {
    final now = DateTime.now().toUtc();
    final initial = BafoDateFormat.toRiyadhWallClock(widget.value ?? now);
    final first = BafoDateFormat.toRiyadhWallClock(widget.firstDate ?? now);
    final last = BafoDateFormat.toRiyadhWallClock(
      widget.lastDate ?? now.add(const Duration(days: 365)),
    );
    final date = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(first) ? first : initial,
      firstDate: DateTime(first.year, first.month, first.day),
      lastDate: DateTime(last.year, last.month, last.day),
    );
    if (date == null || !context.mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: initial.hour, minute: initial.minute),
    );
    if (time == null) return;
    widget.onChanged(
      BafoDateFormat.fromRiyadhWallClock(
        DateTime(date.year, date.month, date.day, time.hour, time.minute),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BafoTextField(
      label: widget.label,
      controller: _text,
      hint: l10n.commonDateTimePick,
      readOnly: true,
      enabled: widget.enabled,
      required: widget.required,
      errorText: widget.errorText,
      helperText: widget.helperText,
      suffix: const Icon(Icons.event_outlined),
      onTap: widget.enabled ? () => _pick(context) : null,
      validator: (_) => widget.required && widget.value == null
          ? l10n.validationRequired
          : null,
    );
  }
}

/// A required consent checkbox with a "Read" link to the document.
class ConsentCheckbox extends StatelessWidget {
  const ConsentCheckbox({
    required this.value,
    required this.label,
    required this.onChanged,
    this.onRead,
    this.errorText,
    super.key,
  });

  final bool value;
  final String label;
  final ValueChanged<bool> onChanged;
  final VoidCallback? onRead;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final error = errorText;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Expanded(
              child: MergeSemantics(
                child: InkWell(
                  onTap: () => onChanged(!value),
                  child: Row(
                    children: [
                      Checkbox(
                        value: value,
                        isError: error != null,
                        onChanged: (checked) => onChanged(checked ?? false),
                      ),
                      Expanded(
                        child: Text(label, style: theme.textTheme.bodyMedium),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            if (onRead != null)
              TextButton(
                onPressed: onRead,
                child: Text(context.l10n.authRegisterReadDocument),
              ),
          ],
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsetsDirectional.only(start: BafoSpacing.xxxl),
            child: Text(
              error,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.error,
              ),
            ),
          ),
      ],
    );
  }
}

/// Upper-cases the national short address as it is typed (`ABCD1234`).
class UpperCaseFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final text = normalizeDigits(newValue.text).toUpperCase();
    return newValue.copyWith(text: text);
  }
}
