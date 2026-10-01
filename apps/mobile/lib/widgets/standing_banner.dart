import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:material_ui/material_ui.dart';

/// The participant's standing, rendered only from server snapshot fields
/// (SCREENS.md S2; never derived on the client):
///
/// * no offer yet → neutral;
/// * `is_leading = true` → leading tone, check icon;
/// * `is_leading = false` → outbid tone (amber, never red), alert icon and the
///   direction hint («خفّض عرضك» / «ارفع عرضك»);
/// * only `rank` / `ranked_count` → neutral rank line;
/// * nothing projected → neutral "standings are not shown".
///
/// The banner is a polite live region whose announced text changes at most
/// once every [announceInterval] (S9); the visual updates at once.
class StandingBanner extends StatefulWidget {
  const StandingBanner({
    required this.direction,
    required this.hasOffer,
    this.isLeading,
    this.rank,
    this.rankedCount,
    this.announceInterval = const Duration(seconds: 10),
    super.key,
  });

  final Direction direction;
  final bool hasOffer;
  final bool? isLeading;
  final int? rank;
  final int? rankedCount;
  final Duration announceInterval;

  @override
  State<StandingBanner> createState() => _StandingBannerState();
}

class _StandingBannerState extends State<StandingBanner> {
  /// The text the live region carries (what screen readers last heard).
  String? _announced;

  /// True for [StandingBanner.announceInterval] after an announcement.
  bool _cooling = false;
  Timer? _cooldown;

  @override
  void dispose() {
    _cooldown?.cancel();
    super.dispose();
  }

  /// The new message once the interval has passed; until then the previous
  /// one. Timer-driven (no wall clock), so it also holds under fake time.
  String _liveLabel(String message) {
    final announced = _announced;
    if (announced == null || (announced != message && !_cooling)) {
      _announced = message;
      _cooling = true;
      _cooldown?.cancel();
      _cooldown = Timer(widget.announceInterval, () {
        _cooling = false;
        // Catch up with a change that arrived while cooling down.
        if (mounted) setState(() {});
      });
      return message;
    }
    return announced;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final rank = widget.rank;
    final count = widget.rankedCount;
    final rankLine = rank != null && count != null
        ? l10n.liveStatusRank(rank, count)
        : null;

    final (StatusTone tone, IconData icon, String message) = !widget.hasOffer
        ? (StatusTone.neutral, Icons.info_outline_rounded, l10n.liveStatusNoOffer)
        : switch (widget.isLeading) {
            true => (
              StatusTone.leading,
              Icons.check_circle_rounded,
              l10n.liveStatusLeading,
            ),
            false => (
              StatusTone.outbid,
              Icons.error_outline_rounded,
              l10n.liveStatusNotLeading(widget.direction.wire),
            ),
            null when rankLine != null => (
              StatusTone.neutral,
              Icons.leaderboard_outlined,
              rankLine,
            ),
            null => (
              StatusTone.neutral,
              Icons.visibility_off_outlined,
              l10n.liveStatusHidden,
            ),
          };
    final secondary = widget.isLeading != null ? rankLine : null;
    final full = secondary == null ? message : '$message. $secondary';
    final colors = tone.resolve(context.semanticColors);
    final textTheme = Theme.of(context).textTheme;

    return Semantics(
      container: true,
      liveRegion: true,
      label: _liveLabel(full),
      excludeSemantics: true,
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
            Icon(icon, color: colors.foreground, size: 22),
            const SizedBox(width: BafoSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    message,
                    style: textTheme.titleSmall?.copyWith(
                      color: colors.foreground,
                    ),
                  ),
                  if (secondary != null)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(
                        top: BafoSpacing.xxs,
                      ),
                      child: Text(
                        secondary,
                        style: textTheme.bodySmall?.copyWith(
                          color: colors.foreground,
                        ),
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
