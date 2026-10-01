import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:material_ui/material_ui.dart';

/// Short, non-blocking feedback as a floating snack bar with a tone icon.
/// Replaces any toast already showing.
abstract final class BafoToast {
  static void show(
    BuildContext context,
    String message, {
    StatusTone tone = StatusTone.neutral,
  }) {
    final messenger = ScaffoldMessenger.maybeOf(context);
    if (messenger == null) return;
    final scheme = Theme.of(context).colorScheme;
    final accent = switch (tone) {
      StatusTone.neutral || StatusTone.inverse => scheme.onInverseSurface,
      // On the inverse (dark) surface the light-mode foregrounds lack
      // contrast, so tones use their container colour as the accent.
      _ => tone.resolve(context.semanticColors).background,
    };
    final icon = switch (tone) {
      StatusTone.success || StatusTone.leading => Icons.check_circle_rounded,
      StatusTone.warning || StatusTone.outbid => Icons.warning_amber_rounded,
      StatusTone.danger => Icons.error_rounded,
      StatusTone.info || StatusTone.sponsored => Icons.info_rounded,
      StatusTone.primary => Icons.check_circle_rounded,
      StatusTone.neutral || StatusTone.inverse => Icons.info_outline_rounded,
    };

    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              Icon(icon, color: accent, size: 20),
              const SizedBox(width: 12),
              Expanded(child: Text(message)),
            ],
          ),
        ),
      );
  }

  static void success(BuildContext context, String message) =>
      show(context, message, tone: StatusTone.success);

  static void error(BuildContext context, String message) =>
      show(context, message, tone: StatusTone.danger);
}
