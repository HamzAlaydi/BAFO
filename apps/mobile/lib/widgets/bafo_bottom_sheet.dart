import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:material_ui/material_ui.dart';

/// Opens a modal bottom sheet with a drag handle, an optional [title] row
/// with a close button, safe-area and keyboard insets handled.
Future<T?> showBafoBottomSheet<T>(
  BuildContext context, {
  required WidgetBuilder builder,
  String? title,
  bool isDismissible = true,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    isDismissible: isDismissible,
    enableDrag: isDismissible,
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      return Padding(
        // Keeps inputs above the keyboard.
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsetsDirectional.fromSTEB(
            BafoSpacing.page,
            0,
            BafoSpacing.page,
            BafoSpacing.xl,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (title != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: BafoSpacing.md),
                  child: Row(
                    children: [
                      Expanded(
                        child: Semantics(
                          header: true,
                          child: Text(title, style: theme.textTheme.titleLarge),
                        ),
                      ),
                      if (isDismissible)
                        IconButton(
                          onPressed: () => Navigator.of(sheetContext).pop(),
                          tooltip: sheetContext.l10n.commonActionsClose,
                          icon: const Icon(Icons.close_rounded),
                        ),
                    ],
                  ),
                ),
              builder(sheetContext),
            ],
          ),
        ),
      );
    },
  );
}
