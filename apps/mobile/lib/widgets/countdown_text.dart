import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// CONVENTIONS.md §9.1: `HH:MM:SS` under 24 hours, "N days HH:MM" from
/// 24 hours (day count pluralised per language). Western digits.
String formatCountdown(Duration remaining, AppLocalizations l10n) {
  final clamped = remaining.isNegative ? Duration.zero : remaining;
  final days = clamped.inDays;
  final hours = clamped.inHours % 24;
  final minutes = clamped.inMinutes % 60;
  final seconds = clamped.inSeconds % 60;
  String two(int value) => value.toString().padLeft(2, '0');

  if (days > 0) {
    return '${l10n.commonCountdownDays(days)} ${two(hours)}:${two(minutes)}';
  }
  return '${two(hours)}:${two(minutes)}:${two(seconds)}';
}

/// Remaining time from which a countdown turns to the warning colour.
const Duration countdownWarningThreshold = Duration(minutes: 5);

/// Live time left until [deadline], computed from the [ServerClock] (never the
/// device clock alone). Ticks on whole server seconds and stops at zero.
/// The last five minutes use the warning colour (never flashing red).
///
/// The clock comes from [clock] or, by default, the nearest
/// `RepositoryProvider<ServerClock>`.
class CountdownText extends StatefulWidget {
  const CountdownText({
    required this.deadline,
    this.clock,
    this.style,
    this.onElapsed,
    this.elapsedLabel,
    this.template,
    super.key,
  });

  /// UTC instant (e.g. `effective_close_at`).
  final DateTime deadline;
  final ServerClock? clock;
  final TextStyle? style;

  /// Shown at zero instead of «انتهى الوقت», e.g. «جارٍ الإغلاق…» for a close
  /// that the server has not confirmed yet (SCREENS.md S3 step 9).
  final String? elapsedLabel;

  /// Wraps the running time, e.g. `l10n.authOtpExpiresIn` («تنتهي صلاحية
  /// الرمز خلال {time}»).
  final String Function(String time)? template;

  /// Called once when the countdown reaches zero while on screen.
  final VoidCallback? onElapsed;

  @override
  State<CountdownText> createState() => _CountdownTextState();
}

class _CountdownTextState extends State<CountdownText> {
  Timer? _timer;
  late ServerClock _clock;
  bool _elapsedReported = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clock = widget.clock ?? context.read<ServerClock>();
    _schedule();
  }

  @override
  void didUpdateWidget(CountdownText oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.deadline != widget.deadline ||
        oldWidget.clock != widget.clock) {
      _clock = widget.clock ?? context.read<ServerClock>();
      _elapsedReported = false;
      _schedule();
    }
  }

  void _schedule() {
    _timer?.cancel();
    final remaining = _clock.remainingUntil(widget.deadline);
    if (remaining == Duration.zero) {
      _reportElapsed();
      return;
    }
    // Wake when the remaining time crosses the next whole second, so the
    // display flips exactly when the server-side second changes.
    final toNextSecond = Duration(
      microseconds: remaining.inMicroseconds % Duration.microsecondsPerSecond,
    );
    final delay = toNextSecond == Duration.zero
        ? const Duration(seconds: 1)
        : toNextSecond;
    _timer = Timer(delay, () {
      if (!mounted) return;
      setState(() {});
      _schedule();
    });
  }

  void _reportElapsed() {
    if (_elapsedReported) return;
    _elapsedReported = true;
    final callback = widget.onElapsed;
    if (callback != null) scheduleMicrotask(callback);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final left = _clock.remainingUntil(widget.deadline);
    // Round up: "00:01" shows until the deadline, "00:00" never shows.
    final remaining = Duration(
      seconds:
          (left.inMicroseconds + Duration.microsecondsPerSecond - 1) ~/
          Duration.microsecondsPerSecond,
    );
    final base = widget.style ?? DefaultTextStyle.of(context).style;
    final warn =
        remaining > Duration.zero && remaining <= countdownWarningThreshold;
    final style = warn
        ? base.copyWith(color: context.semanticColors.warning.foreground)
        : base;
    final ended = widget.elapsedLabel ?? l10n.commonCountdownEnded;
    final formatted = formatCountdown(remaining, l10n);
    final text = remaining == Duration.zero
        ? ended
        : (widget.template?.call(formatted) ?? formatted);

    return Semantics(
      label: remaining == Duration.zero
          ? ended
          : (widget.template == null
                ? l10n.commonCountdownRemaining(text)
                : text),
      excludeSemantics: true,
      child: Text(text, style: BafoTypography.tabular(style)),
    );
  }
}
