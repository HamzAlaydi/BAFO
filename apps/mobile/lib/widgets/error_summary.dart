import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:material_ui/material_ui.dart';

/// One line of an [FormErrorSummary]: the field, its message and where to go.
final class FormErrorItem {
  const FormErrorItem({
    required this.label,
    required this.message,
    this.onTap,
    this.note,
  });

  /// The field's label as shown on the form.
  final String label;
  final String message;

  /// Scrolls to or focuses the field; null for an error without a control.
  final VoidCallback? onTap;

  /// E.g. «في الخطوة 2» when the field is on another step.
  final String? note;
}

/// FQ8 (RELEASE_SCOPE.md §3): after a failed submit, every field error in
/// one list at the top of the form, each line a link to its field. Announced
/// once as a live region.
class FormErrorSummary extends StatelessWidget {
  const FormErrorSummary({required this.items, super.key});

  final List<FormErrorItem> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final colors = StatusTone.danger.resolve(context.semanticColors);
    return Semantics(
      container: true,
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: BorderRadius.circular(BafoRadii.card),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.error_outline_rounded,
                  size: 20,
                  color: colors.foreground,
                ),
                const SizedBox(width: BafoSpacing.sm),
                Expanded(
                  child: Text(
                    l10n.commonErrorSummaryTitle(items.length),
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: colors.foreground,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: BafoSpacing.xs),
            for (final item in items)
              Material(
                type: MaterialType.transparency,
                child: InkWell(
                  onTap: item.onTap,
                  borderRadius: BorderRadius.circular(BafoRadii.sm),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(
                      minHeight: BafoSizes.minTouchTarget,
                    ),
                    child: Padding(
                      padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: BafoSpacing.xs,
                        vertical: BafoSpacing.xs,
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            child: Text.rich(
                              TextSpan(
                                children: [
                                  TextSpan(
                                    text: item.label,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      decoration: item.onTap == null
                                          ? null
                                          : TextDecoration.underline,
                                    ),
                                  ),
                                  TextSpan(text: ': ${item.message}'),
                                  if (item.note != null)
                                    TextSpan(
                                      text: ' (${item.note})',
                                      style: theme.textTheme.bodySmall
                                          ?.copyWith(color: colors.foreground),
                                    ),
                                ],
                              ),
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: colors.foreground,
                              ),
                            ),
                          ),
                          if (item.onTap != null)
                            Icon(
                              Icons.chevron_right_rounded,
                              size: 18,
                              color: colors.foreground,
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
