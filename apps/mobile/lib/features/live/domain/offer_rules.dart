import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';

/// What the composer asks for, read from the latest server snapshot only
/// (SCREENS.md CD13). The client never recomputes a step or a rule: every
/// bound here is a value the server sent.
enum OfferMode {
  /// Live format, stage `initial` or `live`: `required_next_amount_minor`.
  live,

  /// Sealed format: the start price is the only bound; revisions may move
  /// either way until the close.
  sealed,

  /// BAFO round, shortlisted: one final offer, not worse than the reference.
  bafo,
}

/// Why an amount would be refused before sending it. The server still
/// decides; a server rejection always wins over these pre-checks.
enum OfferBoundIssue {
  /// Not a multiple of `amount_granularity_minor`.
  granularity,

  /// On the wrong side of `start_price_minor` (ceiling or opening price).
  startPrice,

  /// On the wrong side of `required_next_amount_minor`.
  stepNotMet,

  /// Worse than the BAFO reference (the participant's last offer).
  bafoReference,
}

/// A failed pre-check with the server amount it refers to.
final class OfferBoundViolation extends Equatable {
  const OfferBoundViolation(this.issue, [this.boundMinor]);

  final OfferBoundIssue issue;

  /// The server amount the offer must respect (null for granularity).
  final int? boundMinor;

  @override
  List<Object?> get props => [issue, boundMinor];
}

/// The bounds of the next offer, from a [ParticipantLiveSnapshot].
final class OfferBounds extends Equatable {
  const OfferBounds({
    required this.direction,
    required this.mode,
    required this.granularityMinor,
    this.startPriceMinor,
    this.requiredNextMinor,
    this.bafoReferenceMinor,
  });

  factory OfferBounds.of(
    ParticipantLiveSnapshot snapshot, {
    required CompetitionFormat format,
  }) {
    final bafo = snapshot.bafo;
    final mode = snapshot.status == CompetitionStatus.bafoRound
        ? OfferMode.bafo
        : (format == CompetitionFormat.sealed ||
                  snapshot.phase == CompetitionPhase.sealed
              ? OfferMode.sealed
              : OfferMode.live);
    return OfferBounds(
      direction: snapshot.direction,
      mode: mode,
      granularityMinor: snapshot.amountGranularityMinor,
      startPriceMinor: snapshot.startPriceMinor,
      // The bound only applies to live stages; the server sends null
      // otherwise (ARCHITECTURE.md §7.9).
      requiredNextMinor: mode == OfferMode.live
          ? snapshot.requiredNextAmountMinor
          : null,
      bafoReferenceMinor: mode == OfferMode.bafo
          ? bafo?.referenceAmountMinor
          : null,
    );
  }

  final Direction direction;
  final OfferMode mode;
  final int granularityMinor;
  final int? startPriceMinor;
  final int? requiredNextMinor;
  final int? bafoReferenceMinor;

  /// Whether halalas may be typed (granularity 1) or whole riyals only (100).
  bool get wholeRiyals => granularityMinor >= 100;

  /// The first pre-check [amountMinor] fails, or null when it looks valid.
  /// Every comparison follows the server bound rule of API.md §2.8
  /// (tender ≤ bound, auction ≥ bound) through [Direction.satisfiesBound].
  OfferBoundViolation? check(int amountMinor) {
    if (granularityMinor > 1 && amountMinor % granularityMinor != 0) {
      return const OfferBoundViolation(OfferBoundIssue.granularity);
    }
    final start = startPriceMinor;
    if (start != null && !direction.satisfiesBound(amountMinor, start)) {
      return OfferBoundViolation(OfferBoundIssue.startPrice, start);
    }
    final reference = bafoReferenceMinor;
    if (reference != null &&
        !direction.satisfiesBound(amountMinor, reference)) {
      return OfferBoundViolation(OfferBoundIssue.bafoReference, reference);
    }
    final next = requiredNextMinor;
    if (next != null && !direction.satisfiesBound(amountMinor, next)) {
      return OfferBoundViolation(OfferBoundIssue.stepNotMet, next);
    }
    return null;
  }

  @override
  List<Object?> get props => [
    direction,
    mode,
    granularityMinor,
    startPriceMinor,
    requiredNextMinor,
    bafoReferenceMinor,
  ];
}

/// Basis points as a percentage (SCREENS.md S6): `bps / 100`, up to two
/// decimals, trailing zeros trimmed, Western digits (`50` → `0.5%`,
/// `6080` → `60.8%`). The sign is left to the caller.
String formatBps(int bps) {
  final value = bps.abs();
  final whole = value ~/ 100;
  final fraction = value % 100;
  if (fraction == 0) return '$whole%';
  final decimals = fraction
      .toString()
      .padLeft(2, '0')
      .replaceFirst(RegExp(r'0$'), '');
  return '$whole.$decimals%';
}

/// The relative change from [previousMinor] to [currentMinor] in basis
/// points (own offers only, for the neutral history arrows of M28).
int? changeBps(int currentMinor, int? previousMinor) {
  if (previousMinor == null || previousMinor == 0) return null;
  return ((currentMinor - previousMinor) * 10000 / previousMinor).round();
}
