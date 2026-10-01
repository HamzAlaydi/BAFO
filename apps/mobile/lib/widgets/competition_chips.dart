import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/domain/competition_status_visual.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// Tender ↓ / auction ↑ with the rule («مناقصة · الأقل سعراً يفوز»). Both use
/// the same neutral container: the glyph shows the direction, colour never
/// does (SCREENS.md S2). The vertical arrows are never mirrored.
class DirectionChip extends StatelessWidget {
  const DirectionChip({required this.direction, this.showRule = true, super.key});

  final Direction direction;

  /// False shows only «مناقصة» / «مزايدة» (dense rows).
  final bool showRule;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final label = l10n.competitionsDirectionLabel(direction.wire);
    final known = direction != Direction.unknown;
    final text = showRule && known
        ? '$label · ${l10n.competitionsDirectionRule(direction.wire)}'
        : label;
    return StatusPill(
      label: text,
      icon: switch (direction) {
        Direction.tender => Icons.south_rounded,
        Direction.auction => Icons.north_rounded,
        Direction.unknown => Icons.swap_vert_rounded,
      },
    );
  }
}

/// Live («مباشرة») or sealed (lock, «بظرف مغلق»).
class FormatChip extends StatelessWidget {
  const FormatChip({required this.format, super.key});

  final CompetitionFormat format;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final sealed = format == CompetitionFormat.sealed;
    return StatusPill(
      label: sealed ? l10n.competitionsFormatSealed : l10n.competitionsFormatLive,
      icon: sealed ? Icons.lock_outline_rounded : Icons.bolt_rounded,
    );
  }
}

/// The competition status chip plus the "Closing soon" and "Extended"
/// overlay pills while live (SCREENS.md S2), from
/// [CompetitionStatusVisual.of]. "Closing soon" follows the [ServerClock].
class CompetitionStatusChip extends StatefulWidget {
  const CompetitionStatusChip({
    required this.status,
    this.phase,
    this.effectiveCloseAt,
    this.extensionCount = 0,
    this.showOverlays = true,
    this.clock,
    super.key,
  });

  final CompetitionStatus status;
  final CompetitionPhase? phase;
  final DateTime? effectiveCloseAt;
  final int extensionCount;
  final bool showOverlays;

  /// Defaults to the nearest `RepositoryProvider<ServerClock>`.
  final ServerClock? clock;

  @override
  State<CompetitionStatusChip> createState() => _CompetitionStatusChipState();
}

class _CompetitionStatusChipState extends State<CompetitionStatusChip> {
  Timer? _timer;
  ServerClock? _clock;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clock = widget.clock ?? _maybeClock(context);
    _schedule();
  }

  @override
  void didUpdateWidget(CompetitionStatusChip oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.effectiveCloseAt != widget.effectiveCloseAt ||
        oldWidget.status != widget.status) {
      _schedule();
    }
  }

  static ServerClock? _maybeClock(BuildContext context) {
    try {
      return context.read<ServerClock>();
    } on ProviderNotFoundException {
      return null;
    }
  }

  /// Wakes when "Closing soon" appears (10 minutes left) and when it goes
  /// (the close), instead of ticking every second.
  void _schedule() {
    _timer?.cancel();
    final clock = _clock;
    final closeAt = widget.effectiveCloseAt;
    if (clock == null || closeAt == null || widget.status != CompetitionStatus.live) {
      return;
    }
    final remaining = clock.remainingUntil(closeAt);
    if (remaining == Duration.zero) return;
    const threshold = CompetitionStatusVisual.closingSoonThreshold;
    final wake = remaining > threshold ? remaining - threshold : remaining;
    _timer = Timer(wake + const Duration(milliseconds: 50), () {
      if (!mounted) return;
      setState(() {});
      _schedule();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final visual = CompetitionStatusVisual.of(
      status: widget.status,
      phase: widget.phase,
      effectiveCloseAt: widget.effectiveCloseAt,
      extensionCount: widget.extensionCount,
      serverNow: _clock?.now(),
    );
    final tone = switch (visual.tone) {
      StatusVisualTone.neutral => StatusTone.neutral,
      StatusVisualTone.info => StatusTone.info,
      StatusVisualTone.primarySoft => StatusTone.success,
      StatusVisualTone.inverse => StatusTone.inverse,
      StatusVisualTone.primarySolid => StatusTone.primary,
      StatusVisualTone.dangerSoft => StatusTone.danger,
    };
    final label = switch (visual.key) {
      StatusVisualKey.draft => l10n.competitionsStatusDraft,
      StatusVisualKey.scheduled => l10n.competitionsStatusScheduled,
      StatusVisualKey.live => l10n.competitionsStatusLive,
      StatusVisualKey.finalWindow => l10n.competitionsStatusFinalWindow,
      StatusVisualKey.liveSealed => l10n.competitionsStatusLiveSealed,
      StatusVisualKey.closed => l10n.competitionsStatusClosed,
      StatusVisualKey.bafoRound => l10n.competitionsStatusBafoRound,
      StatusVisualKey.awarded => l10n.competitionsStatusAwarded,
      StatusVisualKey.notAwarded => l10n.competitionsStatusNotAwarded,
      StatusVisualKey.cancelled => l10n.competitionsStatusCancelled,
      StatusVisualKey.unknown => l10n.competitionsStatusUnknown,
    };
    final colors = tone.resolve(context.semanticColors);
    final chip = StatusPill(
      label: label,
      tone: tone,
      leading: visual.pulsing
          ? _PulsingDot(color: colors.foreground)
          : Icon(_icon(visual.icon), size: 14, color: colors.foreground),
    );
    if (!widget.showOverlays || (!visual.closingSoon && !visual.extended)) {
      return chip;
    }
    return Wrap(
      spacing: BafoSpacing.xs,
      runSpacing: BafoSpacing.xs,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        chip,
        if (visual.closingSoon)
          StatusPill(
            label: l10n.competitionsStatusClosingSoon,
            tone: StatusTone.warning,
            icon: Icons.hourglass_bottom_rounded,
          ),
        if (visual.extended)
          StatusPill(
            label: l10n.competitionsStatusExtended,
            tone: StatusTone.info,
            icon: Icons.update_rounded,
          ),
      ],
    );
  }

  static IconData _icon(StatusVisualIcon icon) => switch (icon) {
    StatusVisualIcon.pencil => Icons.edit_outlined,
    StatusVisualIcon.calendarClock => Icons.event_outlined,
    StatusVisualIcon.circleDot => Icons.radio_button_checked_rounded,
    StatusVisualIcon.timer => Icons.timer_outlined,
    StatusVisualIcon.lock => Icons.lock_outline_rounded,
    StatusVisualIcon.clipboardCheck => Icons.assignment_turned_in_outlined,
    // The brand mark has a 24 dp minimum (05_brand.md §2.5): a chip uses a
    // glyph; the BAFO banner shows the mark itself.
    StatusVisualIcon.brandMark => Icons.workspace_premium_outlined,
    StatusVisualIcon.trophy => Icons.emoji_events_outlined,
    StatusVisualIcon.circleSlash => Icons.do_not_disturb_on_outlined,
    StatusVisualIcon.ban => Icons.block_rounded,
  };
}

/// The final-window dot: pulses, static under reduced motion (S9).
class _PulsingDot extends StatefulWidget {
  const _PulsingDot({required this.color});

  final Color color;

  @override
  State<_PulsingDot> createState() => _PulsingDotState();
}

class _PulsingDotState extends State<_PulsingDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
    lowerBound: 0.35,
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller
        ..stop()
        ..value = 1;
    } else if (!_controller.isAnimating) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
    opacity: _controller,
    child: DecoratedBox(
      decoration: BoxDecoration(color: widget.color, shape: BoxShape.circle),
      child: const SizedBox.square(dimension: 8),
    ),
  );
}

/// The invitee or participant access state (`invitations.access.*`).
class AccessStateChip extends StatelessWidget {
  const AccessStateChip({required this.state, super.key});

  final AccessState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final (label, tone, icon) = switch (state) {
      AccessState.joinRequired => (
        l10n.invitationsAccessJoinRequired,
        StatusTone.info,
        Icons.mark_email_unread_outlined,
      ),
      AccessState.planRequired => (
        l10n.invitationsAccessPlanRequired,
        StatusTone.warning,
        Icons.workspace_premium_outlined,
      ),
      AccessState.full => (
        l10n.invitationsAccessFull,
        StatusTone.success,
        Icons.check_circle_outline_rounded,
      ),
      AccessState.readOnly => (
        l10n.invitationsAccessReadOnly,
        StatusTone.neutral,
        Icons.visibility_outlined,
      ),
      AccessState.unavailable || AccessState.unknown => (
        l10n.invitationsAccessUnavailable,
        StatusTone.neutral,
        Icons.do_not_disturb_on_outlined,
      ),
    };
    return StatusPill(label: label, tone: tone, icon: icon);
  }
}

/// A participant's result (`award.outcome.*`).
class ResultChip extends StatelessWidget {
  const ResultChip({required this.outcome, super.key});

  final AwardOutcome outcome;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final (label, tone, icon) = switch (outcome) {
      AwardOutcome.won => (
        l10n.awardOutcomeWon,
        StatusTone.primary,
        Icons.emoji_events_outlined,
      ),
      AwardOutcome.notSelected => (
        l10n.awardOutcomeNotSelected,
        StatusTone.neutral,
        Icons.remove_circle_outline_rounded,
      ),
      AwardOutcome.notAwarded || AwardOutcome.unknown => (
        l10n.awardOutcomeNotAwarded,
        StatusTone.neutral,
        Icons.do_not_disturb_on_outlined,
      ),
    };
    return StatusPill(label: label, tone: tone, icon: icon);
  }
}

/// «رسوم مغطّاة»: shown only to the covered participant, never to others.
class FeesCoveredBadge extends StatelessWidget {
  const FeesCoveredBadge({super.key});

  @override
  Widget build(BuildContext context) => StatusPill(
    label: context.l10n.sponsorshipBadgeFeesCovered,
    tone: StatusTone.sponsored,
    icon: Icons.confirmation_number_outlined,
  );
}

/// Compact standing for list cards: leading (green, check) or not leading
/// (amber, alert; never red). Renders nothing when the competition does not
/// project `is_leading` (null).
class StandingBadge extends StatelessWidget {
  const StandingBadge({required this.isLeading, super.key});

  final bool? isLeading;

  @override
  Widget build(BuildContext context) {
    final leading = isLeading;
    if (leading == null) return const SizedBox.shrink();
    final l10n = context.l10n;
    return leading
        ? StatusPill(
            label: l10n.liveBadgeLeading,
            tone: StatusTone.leading,
            icon: Icons.check_circle_rounded,
          )
        : StatusPill(
            label: l10n.liveBadgeNotLeading,
            tone: StatusTone.outbid,
            icon: Icons.error_outline_rounded,
          );
  }
}
