import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/widgets/countdown_text.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// What a competition countdown counts down to (SCREENS.md S3 step 5).
enum CountdownTarget {
  /// Scheduled → `bidding_opens_at` («تبدأ خلال»).
  opens,

  /// Live → `effective_close_at` («تُغلق خلال»).
  closes,

  /// Invitee → `invitation_cutoff_at` («الانضمام قبل»).
  joinDeadline,

  /// BAFO round → `bafo.cutoff_at`.
  bafoCutoff,
}

/// A labelled server-time countdown with the extension banner.
///
/// * The time left comes from the [ServerClock] (never the device clock).
/// * At zero a close shows «جارٍ الإغلاق…», not "Closed": an extension can
///   still arrive, and only the server says it is closed (S3 step 9).
/// * [extensionCount] > 0 shows «مُدّد وقت الإغلاق…», and [hardStopAt] the
///   latest possible close («أقصى موعد للإغلاق»).
/// * With [announce], screen readers hear 10, 5 and 1 minute(s) left and
///   "Closing…" (S3 step 10); the countdown itself is never read every
///   second.
class CompetitionCountdown extends StatefulWidget {
  const CompetitionCountdown({
    required this.target,
    required this.deadline,
    this.extensionCount = 0,
    this.hardStopAt,
    this.announce = false,
    this.clock,
    this.onElapsed,
    super.key,
  });

  final CountdownTarget target;
  final DateTime deadline;
  final int extensionCount;
  final DateTime? hardStopAt;
  final bool announce;
  final ServerClock? clock;
  final VoidCallback? onElapsed;

  /// Minutes-left marks that are announced.
  static const List<int> announcedMinutes = [10, 5, 1];

  @override
  State<CompetitionCountdown> createState() => _CompetitionCountdownState();
}

class _CompetitionCountdownState extends State<CompetitionCountdown> {
  Timer? _mark;
  late ServerClock _clock;

  /// The last crossed mark (minutes left), or 0 at the close.
  int? _crossed;

  bool get _isClose =>
      widget.target == CountdownTarget.closes ||
      widget.target == CountdownTarget.bafoCutoff;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clock = widget.clock ?? context.read<ServerClock>();
    _scheduleMark(announceNow: false);
  }

  @override
  void didUpdateWidget(CompetitionCountdown oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.deadline != widget.deadline) _scheduleMark(announceNow: false);
  }

  @override
  void dispose() {
    _mark?.cancel();
    super.dispose();
  }

  /// Wakes at the next announced mark instead of every second.
  void _scheduleMark({required bool announceNow}) {
    _mark?.cancel();
    if (!widget.announce || !_isClose) return;
    final remaining = _clock.remainingUntil(widget.deadline);
    final crossed = _currentMark(remaining);
    if (announceNow && crossed != null && crossed != _crossed) {
      _announce(crossed);
    }
    _crossed = crossed;
    if (remaining == Duration.zero) return;
    final next = CompetitionCountdown.announcedMinutes
        .map((minutes) => Duration(minutes: minutes))
        .where((mark) => mark < remaining)
        .fold<Duration>(Duration.zero, (a, b) => b > a ? b : a);
    _mark = Timer(remaining - next + const Duration(milliseconds: 20), () {
      if (mounted) setState(() => _scheduleMark(announceNow: true));
    });
  }

  static int? _currentMark(Duration remaining) {
    if (remaining == Duration.zero) return 0;
    int? mark;
    for (final minutes in CompetitionCountdown.announcedMinutes) {
      if (remaining <= Duration(minutes: minutes)) mark = minutes;
    }
    return mark;
  }

  void _announce(int mark) {
    final l10n = context.l10n;
    final message = mark == 0
        ? l10n.competitionsCountdownClosing
        : l10n.competitionsCountdownAnnounceMinutes(mark);
    // "1 minute" and "closing" are assertive (S9); the live region below
    // covers platforms without imperative announcements (Android).
    if (MediaQuery.supportsAnnounceOf(context)) {
      unawaited(
        SemanticsService.sendAnnouncement(
          View.of(context),
          message,
          Directionality.of(context),
          assertiveness: mark <= 1
              ? Assertiveness.assertive
              : Assertiveness.polite,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final label = switch (widget.target) {
      CountdownTarget.opens => l10n.competitionsCountdownOpensIn,
      CountdownTarget.closes => l10n.competitionsCountdownClosesIn,
      CountdownTarget.joinDeadline => l10n.competitionsCountdownJoinBefore,
      CountdownTarget.bafoCutoff => l10n.competitionsCountdownBafoEndsIn,
    };
    final crossed = _crossed;
    final hardStop = widget.hardStopAt;
    final info = context.semanticColors.info;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: BafoSpacing.sm,
          children: [
            Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            CountdownText(
              deadline: widget.deadline,
              clock: _clock,
              style: BafoTypography.tabular(
                theme.textTheme.titleMedium ?? const TextStyle(),
              ),
              elapsedLabel: _isClose ? l10n.competitionsCountdownClosing : null,
              onElapsed: widget.onElapsed,
            ),
          ],
        ),
        if (widget.announce && crossed != null)
          // An invisible polite live region that changes only at the marks.
          Semantics(
            liveRegion: true,
            label: crossed == 0
                ? l10n.competitionsCountdownClosing
                : l10n.competitionsCountdownAnnounceMinutes(crossed),
            child: const SizedBox.shrink(),
          ),
        if (widget.extensionCount > 0) ...[
          const SizedBox(height: BafoSpacing.sm),
          Container(
            padding: const EdgeInsetsDirectional.symmetric(
              horizontal: BafoSpacing.md,
              vertical: BafoSpacing.sm,
            ),
            decoration: BoxDecoration(
              color: info.background,
              borderRadius: BorderRadius.circular(BafoRadii.button),
            ),
            child: Row(
              children: [
                Icon(Icons.update_rounded, size: 18, color: info.foreground),
                const SizedBox(width: BafoSpacing.sm),
                Expanded(
                  child: Text(
                    l10n.competitionsExtensionBanner(widget.extensionCount),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: info.foreground,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        if (hardStop != null) ...[
          const SizedBox(height: BafoSpacing.xs),
          Text(
            l10n.competitionsLatestPossibleClose(
              BafoDateFormat.dateTime(hardStop, context.languageCode),
            ),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ],
    );
  }
}
