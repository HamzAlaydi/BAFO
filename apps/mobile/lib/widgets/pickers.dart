import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/widgets/bafo_bottom_sheet.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:bafo/widgets/bafo_text_field.dart';
import 'package:bafo/widgets/form_fields.dart';
import 'package:material_ui/material_ui.dart';

/// A chosen close reason and its note.
typedef ReasonChoice = ({CloseReason reason, String? note});

/// Picks a close reason (cancel, close without award, …) with the note the
/// reason requires (M44). The confirm button names the action
/// ([confirmLabel], e.g. «إلغاء المنافسة») and is red when [destructive].
Future<ReasonChoice?> showReasonPickerSheet(
  BuildContext context, {
  required String title,
  required List<CloseReason> reasons,
  required String confirmLabel,
  String? noteLabel,
  bool destructive = true,
}) => showBafoBottomSheet<ReasonChoice>(
  context,
  title: title,
  builder: (_) => _ReasonPicker(
    reasons: reasons,
    confirmLabel: confirmLabel,
    noteLabel: noteLabel,
    destructive: destructive,
  ),
);

class _ReasonPicker extends StatefulWidget {
  const _ReasonPicker({
    required this.reasons,
    required this.confirmLabel,
    required this.destructive,
    this.noteLabel,
  });

  final List<CloseReason> reasons;
  final String confirmLabel;
  final String? noteLabel;
  final bool destructive;

  @override
  State<_ReasonPicker> createState() => _ReasonPickerState();
}

class _ReasonPickerState extends State<_ReasonPicker> {
  final _formKey = GlobalKey<FormState>();
  final _note = TextEditingController();
  CloseReason? _selected;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  void _confirm() {
    final reason = _selected;
    if (reason == null || !(_formKey.currentState?.validate() ?? false)) return;
    final note = _note.text.trim();
    Navigator.of(context).pop<ReasonChoice>((
      reason: reason,
      note: note.isEmpty ? null : note,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final selected = _selected;
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          RadioGroup<String>(
            groupValue: selected?.id,
            onChanged: (id) => setState(
              () => _selected = widget.reasons.firstWhere((r) => r.id == id),
            ),
            child: Column(
              children: [
                for (final reason in widget.reasons)
                  RadioListTile<String>(
                    value: reason.id,
                    title: Text(reason.name),
                    contentPadding: EdgeInsetsDirectional.zero,
                  ),
              ],
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          BafoTextField(
            label: widget.noteLabel ?? l10n.commonNoteLabel,
            controller: _note,
            maxLines: 4,
            minLines: 2,
            maxLength: 1000,
            required: selected?.requiresNote ?? false,
            optional: !(selected?.requiresNote ?? false),
            validator: (value) => (selected?.requiresNote ?? false)
                ? Validators.required(value, l10n)
                : null,
          ),
          const SizedBox(height: BafoSpacing.lg),
          if (widget.destructive)
            BafoButton.destructive(
              label: widget.confirmLabel,
              expand: true,
              onPressed: selected == null ? null : _confirm,
            )
          else
            BafoButton(
              label: widget.confirmLabel,
              expand: true,
              onPressed: selected == null ? null : _confirm,
            ),
        ],
      ),
    );
  }
}

/// What the user picked in the image source sheet (M53).
enum ImageSourceChoice { camera, gallery, remove }

/// Camera / photos / remove, themed (replaces the legacy purple dialog). The
/// caller does the picking.
Future<ImageSourceChoice?> showImageSourceSheet(
  BuildContext context, {
  bool canRemove = false,
}) {
  final l10n = context.l10n;
  return showBafoBottomSheet<ImageSourceChoice>(
    context,
    builder: (sheet) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        ListTile(
          leading: const Icon(Icons.photo_camera_outlined),
          title: Text(l10n.commonActionsCamera),
          onTap: () => Navigator.of(sheet).pop(ImageSourceChoice.camera),
        ),
        ListTile(
          leading: const Icon(Icons.photo_library_outlined),
          title: Text(l10n.commonActionsGallery),
          onTap: () => Navigator.of(sheet).pop(ImageSourceChoice.gallery),
        ),
        if (canRemove)
          ListTile(
            leading: Icon(
              Icons.delete_outline_rounded,
              color: Theme.of(sheet).colorScheme.error,
            ),
            title: Text(
              l10n.commonActionsRemove,
              style: TextStyle(color: Theme.of(sheet).colorScheme.error),
            ),
            onTap: () => Navigator.of(sheet).pop(ImageSourceChoice.remove),
          ),
      ],
    ),
  );
}

/// One option of [showMultiSelectSheet].
class SelectOption<T> {
  const SelectOption({required this.value, required this.label});

  final T value;
  final String label;
}

/// A searchable multi-select sheet (categories). Returns the new selection,
/// or null when dismissed. At most [max] items.
Future<Set<T>?> showMultiSelectSheet<T>(
  BuildContext context, {
  required String title,
  required List<SelectOption<T>> options,
  required Set<T> selected,
  int? max,
  String? maxMessage,
}) => showBafoBottomSheet<Set<T>>(
  context,
  title: title,
  builder: (_) => _MultiSelect<T>(
    options: options,
    initial: selected,
    max: max,
    maxMessage: maxMessage,
  ),
);

class _MultiSelect<T> extends StatefulWidget {
  const _MultiSelect({
    required this.options,
    required this.initial,
    this.max,
    this.maxMessage,
  });

  final List<SelectOption<T>> options;
  final Set<T> initial;
  final int? max;
  final String? maxMessage;

  @override
  State<_MultiSelect<T>> createState() => _MultiSelectState<T>();
}

class _MultiSelectState<T> extends State<_MultiSelect<T>> {
  late final Set<T> _selected = {...widget.initial};
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final max = widget.max;
    final atMax = max != null && _selected.length >= max;
    final visible = widget.options
        .where((option) => option.label.contains(_query))
        .toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        SearchField(
          debounce: Duration.zero,
          onChanged: (value) => setState(() => _query = value),
        ),
        const SizedBox(height: BafoSpacing.sm),
        Text(
          l10n.commonSelectedCount(_selected.length),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (atMax && widget.maxMessage != null)
          Text(
            widget.maxMessage!,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        // A bounded list keeps "Done" in view on long option lists.
        SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.45,
          child: ListView(
            children: [
              for (final option in visible)
                CheckboxListTile(
                  value: _selected.contains(option.value),
                  title: Text(option.label),
                  contentPadding: EdgeInsetsDirectional.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  onChanged: (checked) => setState(() {
                    if (checked ?? false) {
                      if (!atMax) _selected.add(option.value);
                    } else {
                      _selected.remove(option.value);
                    }
                  }),
                ),
            ],
          ),
        ),
        const SizedBox(height: BafoSpacing.md),
        BafoButton(
          label: l10n.commonActionsDone,
          expand: true,
          onPressed: () => Navigator.of(context).pop(_selected),
        ),
      ],
    );
  }
}
