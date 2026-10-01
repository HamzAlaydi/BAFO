import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:material_ui/material_ui.dart';

/// Asks the user to confirm an action. Resolves to true only on confirm.
///
/// A themed Material dialog on both platforms (the legacy app used an
/// unthemed Cupertino alert). Set [destructive] for delete / reject / cancel.
Future<bool> showConfirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  String? confirmLabel,
  String? cancelLabel,
  bool destructive = false,
}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (_) => ConfirmDialog(
      title: title,
      message: message,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
      destructive: destructive,
    ),
  );
  return confirmed ?? false;
}

class ConfirmDialog extends StatelessWidget {
  const ConfirmDialog({
    required this.title,
    required this.message,
    this.confirmLabel,
    this.cancelLabel,
    this.destructive = false,
    super.key,
  });

  final String title;
  final String message;
  final String? confirmLabel;
  final String? cancelLabel;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final confirm = confirmLabel ?? l10n.commonActionsConfirm;
    void close(bool result) => Navigator.of(context).pop(result);

    return AlertDialog(
      title: Text(title),
      content: Text(message),
      actionsAlignment: MainAxisAlignment.end,
      actions: [
        BafoButton.text(
          label: cancelLabel ?? l10n.commonActionsCancel,
          onPressed: () => close(false),
        ),
        if (destructive)
          BafoButton.destructive(label: confirm, onPressed: () => close(true))
        else
          BafoButton(label: confirm, onPressed: () => close(true)),
      ],
    );
  }
}
