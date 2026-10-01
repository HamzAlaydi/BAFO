import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:equatable/equatable.dart';

/// `rules.auto_extend` (anti-sniping, ARCHITECTURE.md §7.7).
final class AutoExtendRule extends Equatable {
  const AutoExtendRule({
    required this.enabled,
    this.windowSeconds,
    this.bySeconds,
    this.maxExtensions,
  });

  factory AutoExtendRule.fromJson(Json json) => AutoExtendRule(
    enabled: json.flag('enabled'),
    windowSeconds: json.intOrNull('window_seconds'),
    bySeconds: json.intOrNull('by_seconds'),
    maxExtensions: json.intOrNull('max_extensions'),
  );

  static const AutoExtendRule off = AutoExtendRule(enabled: false);

  final bool enabled;
  final int? windowSeconds;
  final int? bySeconds;
  final int? maxExtensions;

  Json toJson() => {
    'enabled': enabled,
    'window_seconds': windowSeconds,
    'by_seconds': bySeconds,
    'max_extensions': maxExtensions,
  };

  @override
  List<Object?> get props => [enabled, windowSeconds, bySeconds, maxExtensions];
}

/// `rules.bafo_round`: whether a best-and-final-offer round may follow.
final class BafoRoundRule extends Equatable {
  const BafoRoundRule({required this.enabled, this.durationMinutes});

  factory BafoRoundRule.fromJson(Json json) => BafoRoundRule(
    enabled: json.flag('enabled'),
    durationMinutes: json.intOrNull('duration_minutes'),
  );

  static const BafoRoundRule off = BafoRoundRule(enabled: false);

  final bool enabled;
  final int? durationMinutes;

  Json toJson() => {'enabled': enabled, 'duration_minutes': durationMinutes};

  @override
  List<Object?> get props => [enabled, durationMinutes];
}

/// `RulesInput` / `Rules` (API.md §2.6): the same keys for input and output.
///
/// [reservePriceMinor] is issuer-only: participant and invitee projections
/// omit it (it reads as null). Presets carry no prices.
final class Rules extends Equatable {
  const Rules({
    this.startPriceMinor,
    this.reservePriceMinor,
    this.minStepMinor,
    this.minStepBps,
    this.amountGranularityMinor = 100,
    this.mustBeat,
    this.rankVisibility = RankVisibility.leadingFlag,
    this.showPrices = false,
    this.autoExtend = AutoExtendRule.off,
    this.finalWindowMinutes,
    this.bafoRound = BafoRoundRule.off,
    this.minParticipants = 1,
    this.resultPublication = ResultPublication.outcomeOnly,
  });

  factory Rules.fromJson(Json json) => Rules(
    startPriceMinor: json.intOrNull('start_price_minor'),
    reservePriceMinor: json.intOrNull('reserve_price_minor'),
    minStepMinor: json.intOrNull('min_step_minor'),
    minStepBps: json.intOrNull('min_step_bps'),
    amountGranularityMinor: json.intOrNull('amount_granularity_minor') ?? 100,
    mustBeat: MustBeat.parse(json['must_beat']),
    rankVisibility: RankVisibility.parse(json['rank_visibility']),
    showPrices: json.flag('show_prices'),
    autoExtend: json.parse('auto_extend', AutoExtendRule.fromJson) ??
        AutoExtendRule.off,
    finalWindowMinutes: json.intOrNull('final_window_minutes'),
    bafoRound: json.parse('bafo_round', BafoRoundRule.fromJson) ??
        BafoRoundRule.off,
    minParticipants: json.intOrNull('min_participants') ?? 1,
    resultPublication: ResultPublication.parse(json['result_publication']),
  );

  final int? startPriceMinor;

  /// Issuer only; never shown to participants.
  final int? reservePriceMinor;
  final int? minStepMinor;
  final int? minStepBps;

  /// 1 (halalas allowed) or 100 (whole riyals).
  final int amountGranularityMinor;

  /// Null for sealed competitions.
  final MustBeat? mustBeat;
  final RankVisibility rankVisibility;
  final bool showPrices;
  final AutoExtendRule autoExtend;
  final int? finalWindowMinutes;
  final BafoRoundRule bafoRound;
  final int minParticipants;
  final ResultPublication resultPublication;

  /// Whole riyals only (no halalas) in offers and prices.
  bool get wholeRiyalsOnly => amountGranularityMinor == 100;

  /// The full input object (the client always sends every key, SCREENS.md
  /// G4). Unknown enum values are not sent.
  Json toJson() => {
    'start_price_minor': startPriceMinor,
    'reserve_price_minor': reservePriceMinor,
    'min_step_minor': minStepMinor,
    'min_step_bps': minStepBps,
    'amount_granularity_minor': amountGranularityMinor,
    'must_beat': mustBeat == MustBeat.unknown ? null : mustBeat?.wire,
    if (rankVisibility != RankVisibility.unknown)
      'rank_visibility': rankVisibility.wire,
    'show_prices': showPrices,
    'auto_extend': autoExtend.toJson(),
    'final_window_minutes': finalWindowMinutes,
    'bafo_round': bafoRound.toJson(),
    'min_participants': minParticipants,
    if (resultPublication != ResultPublication.unknown)
      'result_publication': resultPublication.wire,
  };

  Rules copyWith({
    int? Function()? startPriceMinor,
    int? Function()? reservePriceMinor,
  }) => Rules(
    startPriceMinor: startPriceMinor == null
        ? this.startPriceMinor
        : startPriceMinor(),
    reservePriceMinor: reservePriceMinor == null
        ? this.reservePriceMinor
        : reservePriceMinor(),
    minStepMinor: minStepMinor,
    minStepBps: minStepBps,
    amountGranularityMinor: amountGranularityMinor,
    mustBeat: mustBeat,
    rankVisibility: rankVisibility,
    showPrices: showPrices,
    autoExtend: autoExtend,
    finalWindowMinutes: finalWindowMinutes,
    bafoRound: bafoRound,
    minParticipants: minParticipants,
    resultPublication: resultPublication,
  );

  @override
  List<Object?> get props => [
    startPriceMinor,
    reservePriceMinor,
    minStepMinor,
    minStepBps,
    amountGranularityMinor,
    mustBeat,
    rankVisibility,
    showPrices,
    autoExtend,
    finalWindowMinutes,
    bafoRound,
    minParticipants,
    resultPublication,
  ];
}
