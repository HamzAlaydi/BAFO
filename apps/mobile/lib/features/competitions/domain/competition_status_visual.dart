import 'package:bafo/core/models/competition_enums.dart';
import 'package:equatable/equatable.dart';

/// Which label a status chip shows (`competitions.status.*`).
enum StatusVisualKey {
  draft,
  scheduled,
  live,
  finalWindow,
  liveSealed,
  closed,
  bafoRound,
  awarded,
  notAwarded,
  cancelled,
  unknown,
}

/// The colour family of a status chip (SCREENS.md S2).
enum StatusVisualTone { neutral, info, primarySoft, inverse, primarySolid, dangerSoft }

/// The chip icon.
enum StatusVisualIcon {
  pencil,
  calendarClock,
  circleDot,
  timer,
  lock,
  clipboardCheck,
  brandMark,
  trophy,
  circleSlash,
  ban,
}

/// The status chip of a competition plus its two overlay pills, as one pure
/// function of server values (SCREENS.md S2). Unit-tested against the S2
/// table; the widget only renders it.
final class CompetitionStatusVisual extends Equatable {
  const CompetitionStatusVisual({
    required this.key,
    required this.tone,
    required this.icon,
    this.pulsing = false,
    this.closingSoon = false,
    this.extended = false,
  });

  /// Maps the server state. [serverNow] comes from `ServerClock.now()`.
  factory CompetitionStatusVisual.of({
    required CompetitionStatus status,
    CompetitionPhase? phase,
    DateTime? effectiveCloseAt,
    int extensionCount = 0,
    DateTime? serverNow,
  }) {
    final isLive = status == CompetitionStatus.live;
    final closeAt = effectiveCloseAt;
    final remaining = closeAt != null && serverNow != null
        ? closeAt.difference(serverNow)
        : null;
    final closingSoon =
        isLive &&
        remaining != null &&
        remaining > Duration.zero &&
        remaining < closingSoonThreshold;
    final extended = isLive && extensionCount > 0;

    CompetitionStatusVisual chip(
      StatusVisualKey key,
      StatusVisualTone tone,
      StatusVisualIcon icon, {
      bool pulsing = false,
    }) => CompetitionStatusVisual(
      key: key,
      tone: tone,
      icon: icon,
      pulsing: pulsing,
      closingSoon: closingSoon,
      extended: extended,
    );

    return switch (status) {
      CompetitionStatus.draft => chip(
        StatusVisualKey.draft,
        StatusVisualTone.neutral,
        StatusVisualIcon.pencil,
      ),
      CompetitionStatus.scheduled => chip(
        StatusVisualKey.scheduled,
        StatusVisualTone.info,
        StatusVisualIcon.calendarClock,
      ),
      CompetitionStatus.live => switch (phase) {
        CompetitionPhase.finalWindow => chip(
          StatusVisualKey.finalWindow,
          StatusVisualTone.primarySoft,
          StatusVisualIcon.timer,
          pulsing: true,
        ),
        CompetitionPhase.sealed => chip(
          StatusVisualKey.liveSealed,
          StatusVisualTone.primarySoft,
          StatusVisualIcon.lock,
        ),
        _ => chip(
          StatusVisualKey.live,
          StatusVisualTone.primarySoft,
          StatusVisualIcon.circleDot,
        ),
      },
      CompetitionStatus.closed => chip(
        StatusVisualKey.closed,
        StatusVisualTone.neutral,
        StatusVisualIcon.clipboardCheck,
      ),
      CompetitionStatus.bafoRound => chip(
        StatusVisualKey.bafoRound,
        StatusVisualTone.inverse,
        StatusVisualIcon.brandMark,
      ),
      CompetitionStatus.awarded => chip(
        StatusVisualKey.awarded,
        StatusVisualTone.primarySolid,
        StatusVisualIcon.trophy,
      ),
      CompetitionStatus.notAwarded => chip(
        StatusVisualKey.notAwarded,
        StatusVisualTone.neutral,
        StatusVisualIcon.circleSlash,
      ),
      CompetitionStatus.cancelled => chip(
        StatusVisualKey.cancelled,
        StatusVisualTone.dangerSoft,
        StatusVisualIcon.ban,
      ),
      CompetitionStatus.unknown => chip(
        StatusVisualKey.unknown,
        StatusVisualTone.neutral,
        StatusVisualIcon.circleSlash,
      ),
    };
  }

  /// `CLOSING_SOON_SECONDS`: the first value of `bidding.closing_soon_minutes`.
  static const Duration closingSoonThreshold = Duration(minutes: 10);

  final StatusVisualKey key;
  final StatusVisualTone tone;
  final StatusVisualIcon icon;

  /// A pulsing dot (static under reduced motion).
  final bool pulsing;

  /// Overlay «تُغلق قريباً»: live and under 10 minutes to the close.
  final bool closingSoon;

  /// Overlay «مُدّد الإغلاق»: live and extended at least once.
  final bool extended;

  @override
  List<Object?> get props => [key, tone, icon, pulsing, closingSoon, extended];
}
