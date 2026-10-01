import 'package:material_ui/material_ui.dart';

/// One choice of a [BafoDropdown].
class BafoDropdownItem<T> {
  const BafoDropdownItem({required this.value, required this.label});

  final T value;
  final String label;
}

/// A labelled single-choice dropdown, styled like [BafoTextField].
class BafoDropdown<T> extends StatelessWidget {
  const BafoDropdown({
    required this.label,
    required this.items,
    required this.onChanged,
    this.value,
    this.hint,
    this.errorText,
    this.validator,
    this.enabled = true,
    super.key,
  });

  final String label;
  final List<BafoDropdownItem<T>> items;
  final ValueChanged<T?> onChanged;
  final T? value;
  final String? hint;
  final String? errorText;
  final FormFieldValidator<T>? validator;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.only(bottom: 6),
          child: ExcludeSemantics(
            child: Text(label, style: theme.textTheme.labelMedium),
          ),
        ),
        Semantics(
          label: label,
          child: DropdownButtonFormField<T>(
            // Rebuild the field when the selection is changed from outside.
            key: ValueKey<T?>(value),
            initialValue: value,
            isExpanded: true,
            validator: validator,
            onChanged: enabled ? onChanged : null,
            style: theme.textTheme.bodyLarge,
            borderRadius: BorderRadius.circular(10),
            decoration: InputDecoration(hintText: hint, errorText: errorText),
            items: [
              for (final item in items)
                DropdownMenuItem<T>(
                  value: item.value,
                  child: Text(item.label, overflow: TextOverflow.ellipsis),
                ),
            ],
          ),
        ),
      ],
    );
  }
}
