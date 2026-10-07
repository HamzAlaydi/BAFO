import 'package:bafo/core/l10n/l10n.dart';
import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

/// A labelled text field. The label sits above the field (clearer than a
/// floating label in Arabic, and it never overlaps the value).
///
/// [errorText] shows a server-side validation message; [validator] runs the
/// client-side rules: on blur, on submit, and again on each change once the
/// field is in error, never while the first characters are typed
/// (RELEASE_SCOPE.md FQ2). Password fields ([obscure]) get a show/hide
/// toggle. [required] adds a visible marker and says "required" to screen
/// readers; [optional] marks optional fields instead (S7).
class BafoTextField extends StatefulWidget {
  const BafoTextField({
    required this.label,
    this.controller,
    this.hint,
    this.errorText,
    this.helperText,
    this.validator,
    this.onChanged,
    this.onSubmitted,
    this.onTap,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.inputFormatters,
    this.textDirection,
    this.textStyle,
    this.prefixIcon,
    this.ltrPrefixText,
    this.ltrSuffixText,
    this.suffix,
    this.obscure = false,
    this.enabled = true,
    this.readOnly = false,
    this.autofocus = false,
    this.required = false,
    this.optional = false,
    this.maxLines = 1,
    this.minLines,
    this.maxLength,
    this.textCapitalization = TextCapitalization.none,
    this.focusNode,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final String? errorText;
  final String? helperText;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final VoidCallback? onTap;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final List<TextInputFormatter>? inputFormatters;

  /// Force a direction, e.g. [TextDirection.ltr] for e-mail, URLs, numbers.
  final TextDirection? textDirection;
  final TextStyle? textStyle;
  final IconData? prefixIcon;

  /// Always-visible text on the **left** of LTR content (`+966`, `SAR`),
  /// in either language: the slot is picked from the page direction.
  final String? ltrPrefixText;

  /// Always-visible text on the **right** of LTR content (`ر.س`).
  final String? ltrSuffixText;
  final Widget? suffix;
  final bool obscure;
  final bool enabled;
  final bool readOnly;
  final bool autofocus;
  final bool required;
  final bool optional;
  final int maxLines;
  final int? minLines;

  /// Shows a character counter.
  final int? maxLength;
  final TextCapitalization textCapitalization;
  final FocusNode? focusNode;

  @override
  State<BafoTextField> createState() => _BafoTextFieldState();
}

class _BafoTextFieldState extends State<BafoTextField> {
  late bool _hidden = widget.obscure;
  final GlobalKey<FormFieldState<String>> _field =
      GlobalKey<FormFieldState<String>>();
  FocusNode? _ownFocus;

  FocusNode get _focus => widget.focusNode ?? (_ownFocus ??= FocusNode());

  @override
  void initState() {
    super.initState();
    _focus.addListener(_onFocusChange);
    widget.controller?.addListener(_onControllerChange);
  }

  @override
  void didUpdateWidget(BafoTextField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.focusNode != widget.focusNode) {
      (oldWidget.focusNode ?? _ownFocus)?.removeListener(_onFocusChange);
      _focus.addListener(_onFocusChange);
    }
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller?.removeListener(_onControllerChange);
      widget.controller?.addListener(_onControllerChange);
    }
  }

  @override
  void dispose() {
    _focus.removeListener(_onFocusChange);
    widget.controller?.removeListener(_onControllerChange);
    _ownFocus?.dispose();
    super.dispose();
  }

  /// FQ2: validate when the user leaves the field.
  void _onFocusChange() {
    if (_focus.hasFocus || widget.validator == null || !mounted) return;
    _field.currentState?.validate();
  }

  /// A field in error re-validates as its text changes (also when the text
  /// is set programmatically, e.g. a picked date), so the message clears.
  void _onControllerChange() {
    final field = _field.currentState;
    if (field != null && field.hasError && mounted) field.validate();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l10n = context.l10n;

    Widget? suffix = widget.suffix;
    if (widget.obscure) {
      suffix = IconButton(
        onPressed: () => setState(() => _hidden = !_hidden),
        tooltip: _hidden ? l10n.commonPasswordShow : l10n.commonPasswordHide,
        icon: Icon(
          _hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
        ),
      );
    }
    final marker = widget.required
        ? ' *'
        : widget.optional
        ? ' (${l10n.commonFieldOptional})'
        : '';
    // Visual left/right of LTR content → the decoration's start/end slots.
    final rtl = Directionality.of(context) == TextDirection.rtl;
    Widget? affix(String? text) => text == null
        ? null
        : Padding(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: 14),
            child: Text(
              text,
              textDirection: TextDirection.ltr,
              style: (widget.textStyle ?? theme.textTheme.bodyLarge)?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          );
    final left = affix(widget.ltrPrefixText);
    final right = affix(widget.ltrSuffixText);
    final Widget? startAffix = rtl ? right : left;
    final Widget? endAffix = rtl ? left : right;
    // LTR content sits next to its affix: `+966 5…` on the left, and
    // `1,250 ر.س` on the right in Arabic, instead of the width of the field
    // apart from it. Other fields keep their direction's alignment.
    final textAlign = left != null
        ? TextAlign.left
        : right != null
        ? TextAlign.right
        : TextAlign.start;
    const affixConstraints = BoxConstraints(minWidth: 0, minHeight: 0);

    final semanticsLabel = widget.required
        ? '${widget.label}, ${l10n.commonFieldRequired}'
        : widget.label;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.only(bottom: 6),
          // Announced with the field below, not as a separate node.
          child: ExcludeSemantics(
            child: Text(
              '${widget.label}$marker',
              style: theme.textTheme.labelMedium,
            ),
          ),
        ),
        Semantics(
          label: semanticsLabel,
          child: TextFormField(
            key: _field,
            controller: widget.controller,
            focusNode: _focus,
            validator: widget.validator,
            onChanged: (value) {
              if (widget.controller == null) _onControllerChange();
              widget.onChanged?.call(value);
            },
            onFieldSubmitted: widget.onSubmitted,
            onTap: widget.onTap,
            keyboardType: widget.keyboardType,
            textInputAction: widget.textInputAction,
            autofillHints: widget.autofillHints,
            inputFormatters: widget.inputFormatters,
            textDirection: widget.textDirection,
            textAlign: textAlign,
            textCapitalization: widget.textCapitalization,
            obscureText: _hidden,
            enableSuggestions: !widget.obscure,
            autocorrect: !widget.obscure,
            enabled: widget.enabled,
            readOnly: widget.readOnly,
            autofocus: widget.autofocus,
            maxLines: widget.obscure ? 1 : widget.maxLines,
            minLines: widget.obscure ? null : widget.minLines,
            maxLength: widget.maxLength,
            style: widget.textStyle ?? theme.textTheme.bodyLarge,
            decoration: InputDecoration(
              hintText: widget.hint,
              hintTextDirection: widget.textDirection,
              errorText: widget.errorText,
              helperText: widget.helperText,
              helperMaxLines: 3,
              prefixIcon:
                  startAffix ??
                  (widget.prefixIcon == null ? null : Icon(widget.prefixIcon)),
              prefixIconConstraints: startAffix == null
                  ? null
                  : affixConstraints,
              suffixIcon: suffix ?? endAffix,
              suffixIconConstraints: suffix == null && endAffix != null
                  ? affixConstraints
                  : null,
            ),
          ),
        ),
      ],
    );
  }
}
