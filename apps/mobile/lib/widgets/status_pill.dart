import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:material_ui/material_ui.dart';

/// Colour family of a [StatusPill] / toast.
enum StatusTone {
  neutral,
  success,
  warning,
  info,
  danger,

  /// "Your offer is leading" (green).
  leading,

  /// "You were outbid" (amber, never red).
  outbid,

  /// "Fees covered" (sponsored participation).
  sponsored,

  /// Charcoal with white text (BAFO round).
  inverse,

  /// Solid primary (awarded, won).
  primary,
}

extension StatusToneColors on StatusTone {
  ToneColors resolve(BafoSemanticColors colors) => switch (this) {
    StatusTone.neutral => colors.neutral,
    StatusTone.success => colors.success,
    StatusTone.warning => colors.warning,
    StatusTone.info => colors.info,
    StatusTone.danger => colors.destructiveContainer,
    StatusTone.leading => colors.leading,
    StatusTone.outbid => colors.outbid,
    StatusTone.sponsored => colors.sponsored,
    StatusTone.inverse => colors.inverse,
    StatusTone.primary => colors.primarySolid,
  };
}

/// A compact status label: competition status, offer standing, fee badge.
///
/// Colour is never the only signal: always pass a meaningful [label], and an
/// [icon] where the state matters (leading / outbid).
class StatusPill extends StatelessWidget {
  const StatusPill({
    required this.label,
    this.tone = StatusTone.neutral,
    this.icon,
    this.leading,
    super.key,
  });

  final String label;
  final StatusTone tone;
  final IconData? icon;

  /// A custom leading widget (e.g. the brand mark, a pulsing dot); wins over
  /// [icon].
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final colors = tone.resolve(context.semanticColors);
    final style = Theme.of(context).textTheme.labelSmall
        ?.copyWith(color: colors.foreground);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: BorderRadius.circular(BafoRadii.pill),
      ),
      child: Padding(
        padding: const EdgeInsetsDirectional.symmetric(
          horizontal: 10,
          vertical: BafoSpacing.xs,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (leading != null) ...[
              leading!,
              const SizedBox(width: BafoSpacing.xs),
            ] else if (icon != null) ...[
              Icon(icon, size: 14, color: colors.foreground),
              const SizedBox(width: BafoSpacing.xs),
            ],
            Flexible(
              child: Text(
                label,
                style: style,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
