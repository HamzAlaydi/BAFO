import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/bafo_card.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:material_ui/material_ui.dart';

/// The server's `rules_summary` lines (ARCHITECTURE.md §7.16) as a card.
/// The client never writes its own rule text: every surface shows the same
/// server sentences.
class RulesSummaryCard extends StatelessWidget {
  const RulesSummaryCard({required this.lines, this.title, super.key});

  final List<String> lines;

  /// Defaults to «قواعد المنافسة».
  final String? title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(
            header: true,
            child: Text(
              title ?? context.l10n.rulesSummaryTitle,
              style: theme.textTheme.titleSmall,
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          for (final line in lines)
            Padding(
              padding: const EdgeInsetsDirectional.only(top: BafoSpacing.xs),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsetsDirectional.only(
                      top: 7,
                      end: BafoSpacing.sm,
                    ),
                    child: Icon(
                      Icons.circle,
                      size: 6,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  Expanded(
                    child: Text(line, style: theme.textTheme.bodyMedium),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// One row of a [KeyValueList].
class KeyValue {
  const KeyValue(this.label, this.value, {this.ltr = false});

  final String label;
  final String value;

  /// Render the value as an LTR island (codes, numbers, e-mails).
  final bool ltr;
}

/// Label/value rows (award, schedule, plan status). Labels are muted.
class KeyValueList extends StatelessWidget {
  const KeyValueList({required this.items, super.key});

  final List<KeyValue> items;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (index, item) in items.indexed) ...[
          if (index > 0) const Divider(height: BafoSpacing.lg),
          MergeSemantics(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    item.label,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
                const SizedBox(width: BafoSpacing.md),
                Flexible(
                  child: Text(
                    item.value,
                    textAlign: TextAlign.end,
                    textDirection: item.ltr ? TextDirection.ltr : null,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// An informational notice with no action (SCREENS.md M15): entitlement
/// messages such as «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على
/// الويب.». Mobile never pairs these with a purchase link or button.
class InfoNotice extends StatelessWidget {
  const InfoNotice({
    required this.message,
    this.title,
    this.tone = StatusTone.info,
    this.icon = Icons.info_outline_rounded,
    super.key,
  });

  final String message;
  final String? title;
  final StatusTone tone;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final colors = tone.resolve(context.semanticColors);
    final textTheme = Theme.of(context).textTheme;
    return MergeSemantics(
      child: Container(
        width: double.infinity,
        padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: BorderRadius.circular(BafoRadii.card),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: colors.foreground, size: 20),
            const SizedBox(width: BafoSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (title != null)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(
                        bottom: BafoSpacing.xxs,
                      ),
                      child: Text(
                        title!,
                        style: textTheme.titleSmall?.copyWith(
                          color: colors.foreground,
                        ),
                      ),
                    ),
                  Text(
                    message,
                    style: textTheme.bodyMedium?.copyWith(
                      color: colors.foreground,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A number with a label (home stat tiles).
class StatTile extends StatelessWidget {
  const StatTile({
    required this.label,
    required this.value,
    this.icon,
    this.onTap,
    super.key,
  });

  final String label;
  final int value;
  final IconData? icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return MergeSemantics(
      child: BafoCard(
        onTap: onTap,
        padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 20, color: theme.colorScheme.onSurfaceVariant),
              const SizedBox(height: BafoSpacing.sm),
            ],
            Text(
              '$value',
              style: theme.textTheme.headlineSmall?.copyWith(
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
            const SizedBox(height: BafoSpacing.xxs),
            Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A progress meter (seats used, days left). The fill mirrors in RTL.
class MeterBar extends StatelessWidget {
  const MeterBar({
    required this.value,
    required this.max,
    required this.semanticsLabel,
    super.key,
  });

  final int value;
  final int max;
  final String semanticsLabel;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fraction = max <= 0 ? 0.0 : (value / max).clamp(0.0, 1.0);
    return Semantics(
      label: semanticsLabel,
      excludeSemantics: true,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(BafoRadii.pill),
        child: LinearProgressIndicator(
          value: fraction,
          minHeight: 8,
          backgroundColor: scheme.surfaceContainer,
          color: context.semanticColors.brandGreen,
        ),
      ),
    );
  }
}
