import 'package:bafo/core/api/json.dart';

/// Tender (reverse, the lowest offer wins) or auction (forward, the highest
/// offer wins). Copy and comparisons are chosen from this value, never by
/// comparing numbers (ARCHITECTURE.md §7.1).
enum Direction implements WireEnum {
  tender('tender'),
  auction('auction'),
  unknown('unknown');

  const Direction(this.wire);

  @override
  final String wire;

  static Direction parse(Object? raw) => parseWire(values, raw, unknown);

  /// ARCHITECTURE.md §7.1 sign: tender −1, auction +1 (0 when unknown).
  int get sign => switch (this) {
    tender => -1,
    auction => 1,
    unknown => 0,
  };

  /// Whether [amountMinor] respects a server bound such as
  /// `required_next_amount_minor` or `start_price_minor`: tender ≤ bound,
  /// auction ≥ bound (API.md §2.8). Pre-check only; the server decides.
  bool satisfiesBound(int amountMinor, int boundMinor) => switch (this) {
    tender => amountMinor <= boundMinor,
    auction => amountMinor >= boundMinor,
    unknown => true,
  };
}

enum CompetitionFormat implements WireEnum {
  live('live'),
  sealed('sealed'),
  unknown('unknown');

  const CompetitionFormat(this.wire);

  @override
  final String wire;

  static CompetitionFormat parse(Object? raw) =>
      parseWire(values, raw, unknown);
}

/// `competitions.status` (ARCHITECTURE.md §6.1).
enum CompetitionStatus implements WireEnum {
  draft('draft'),
  scheduled('scheduled'),
  live('live'),
  closed('closed'),
  bafoRound('bafo_round'),
  awarded('awarded'),
  notAwarded('not_awarded'),
  cancelled('cancelled'),
  unknown('unknown');

  const CompetitionStatus(this.wire);

  @override
  final String wire;

  static CompetitionStatus parse(Object? raw) =>
      parseWire(values, raw, unknown);

  bool get isTerminal => this == awarded || this == notAwarded || this == cancelled;

  /// `status_group=active` of `GET /competitions`.
  bool get isActive =>
      this == scheduled || this == live || this == closed || this == bafoRound;
}

/// Derived phase while `status = live` (null otherwise).
enum CompetitionPhase implements WireEnum {
  initial('initial'),
  open('open'),
  finalWindow('final_window'),
  sealed('sealed'),
  unknown('unknown');

  const CompetitionPhase(this.wire);

  @override
  final String wire;

  static CompetitionPhase? parse(Object? raw) =>
      parseWireOrNull(values, raw, unknown);
}

/// Which projection of a competition the caller received (API.md §2.6).
enum ViewerRole implements WireEnum {
  issuer('issuer'),
  participant('participant'),
  invitee('invitee'),
  unknown('unknown');

  const ViewerRole(this.wire);

  @override
  final String wire;

  static ViewerRole parse(Object? raw) => parseWire(values, raw, unknown);
}

enum MustBeat implements WireEnum {
  own('own'),
  best('best'),
  unknown('unknown');

  const MustBeat(this.wire);

  @override
  final String wire;

  /// Null for sealed competitions.
  static MustBeat? parse(Object? raw) => parseWireOrNull(values, raw, unknown);
}

enum RankVisibility implements WireEnum {
  full('full'),
  leadingFlag('leading_flag'),
  none('none'),
  unknown('unknown');

  const RankVisibility(this.wire);

  @override
  final String wire;

  static RankVisibility parse(Object? raw) => parseWire(values, raw, unknown);
}

enum ResultPublication implements WireEnum {
  none('none'),
  outcomeOnly('outcome_only'),
  outcomeAndAmount('outcome_and_amount'),
  unknown('unknown');

  const ResultPublication(this.wire);

  @override
  final String wire;

  static ResultPublication parse(Object? raw) =>
      parseWire(values, raw, unknown);
}

/// `access.state` of an invitee or participant (ARCHITECTURE.md §8.4).
enum AccessState implements WireEnum {
  joinRequired('join_required'),
  planRequired('plan_required'),
  full('full'),
  readOnly('read_only'),
  unavailable('unavailable'),
  unknown('unknown');

  const AccessState(this.wire);

  @override
  final String wire;

  static AccessState parse(Object? raw) => parseWire(values, raw, unknown);
}

/// Who pays for a participation (R4).
enum Coverage implements WireEnum {
  sponsored('sponsored'),
  ownPlan('own_plan'),
  none('none'),
  unknown('unknown');

  const Coverage(this.wire);

  @override
  final String wire;

  static Coverage parse(Object? raw) => parseWire(values, raw, unknown);
}

/// A participant's result (ARCHITECTURE.md §7.9).
enum AwardOutcome implements WireEnum {
  won('won'),
  notSelected('not_selected'),
  notAwarded('not_awarded'),
  unknown('unknown');

  const AwardOutcome(this.wire);

  @override
  final String wire;

  /// Null when `result_publication = none` or before a result exists.
  static AwardOutcome? parse(Object? raw) =>
      parseWireOrNull(values, raw, unknown);
}

/// `offers.stage` (ARCHITECTURE.md §7.3).
enum OfferStage implements WireEnum {
  sealed('sealed'),
  initial('initial'),
  live('live'),
  bafo('bafo'),
  unknown('unknown');

  const OfferStage(this.wire);

  @override
  final String wire;

  static OfferStage parse(Object? raw) => parseWire(values, raw, unknown);
}
