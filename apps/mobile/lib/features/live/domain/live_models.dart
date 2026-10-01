import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:equatable/equatable.dart';

/// `last_change.kind` of a live snapshot.
enum LastChangeKind implements WireEnum {
  offer('offer'),
  extension('extension'),
  status('status'),
  bafo('bafo'),
  award('award'),
  voided('void'),

  /// A REST read (not a change).
  snapshot('snapshot'),
  unknown('unknown');

  const LastChangeKind(this.wire);

  @override
  final String wire;

  static LastChangeKind parse(Object? raw) => parseWire(values, raw, unknown);

  /// SCREENS.md S4: these change `permissions`, so the competition is
  /// refetched.
  bool get requiresCompetitionRefetch =>
      this == status || this == award || this == bafo;
}

/// Why the close moved (`last_change.reason` for extensions).
enum ExtensionReason implements WireEnum {
  auto('auto'),
  manual('manual'),
  admin('admin'),
  unknown('unknown');

  const ExtensionReason(this.wire);

  @override
  final String wire;

  static ExtensionReason? parse(Object? raw) =>
      parseWireOrNull(values, raw, unknown);
}

final class LastChange extends Equatable {
  const LastChange({required this.kind, this.reason});

  factory LastChange.fromJson(Json json) => LastChange(
    kind: LastChangeKind.parse(json['kind']),
    reason: ExtensionReason.parse(json['reason']),
  );

  static const LastChange snapshot = LastChange(kind: LastChangeKind.snapshot);

  final LastChangeKind kind;
  final ExtensionReason? reason;

  @override
  List<Object?> get props => [kind, reason];
}

/// The minimum improvement: an amount or basis points (at most one is set).
final class MinStep extends Equatable {
  const MinStep({this.minor, this.bps});

  factory MinStep.fromJson(Json json) =>
      MinStep(minor: json.intOrNull('minor'), bps: json.intOrNull('bps'));

  final int? minor;
  final int? bps;

  @override
  List<Object?> get props => [minor, bps];
}

/// The viewer's own current offer inside a participant snapshot.
final class OwnOffer extends Equatable {
  const OwnOffer({
    required this.id,
    required this.seq,
    required this.amountMinor,
    required this.stage,
    required this.acceptedAt,
  });

  factory OwnOffer.fromJson(Json json) => OwnOffer(
    id: json.str('id'),
    seq: json.integer('seq'),
    amountMinor: json.integer('amount_minor'),
    stage: OfferStage.parse(json['stage']),
    acceptedAt: json.date('accepted_at'),
  );

  final String id;
  final int seq;
  final int amountMinor;
  final OfferStage stage;
  final DateTime acceptedAt;

  @override
  List<Object?> get props => [id, seq, amountMinor, stage, acceptedAt];
}

/// A ladder row (only when `rank_visibility = full` and `show_prices`).
/// Other participants appear by alias only.
final class LadderEntry extends Equatable {
  const LadderEntry({
    required this.aliasNo,
    required this.amountMinor,
    required this.isMe,
  });

  factory LadderEntry.fromJson(Json json) => LadderEntry(
    aliasNo: json.integer('alias_no'),
    amountMinor: json.integer('amount_minor'),
    isMe: json.flag('is_me'),
  );

  final int aliasNo;
  final int amountMinor;
  final bool isMe;

  @override
  List<Object?> get props => [aliasNo, amountMinor, isMe];
}

/// The participant's view of a BAFO round.
final class ParticipantBafo extends Equatable {
  const ParticipantBafo({
    required this.shortlisted,
    required this.submitted,
    this.cutoffAt,
    this.referenceAmountMinor,
  });

  factory ParticipantBafo.fromJson(Json json) => ParticipantBafo(
    shortlisted: json.flag('shortlisted'),
    cutoffAt: json.dateOrNull('cutoff_at'),
    submitted: json.flag('submitted'),
    referenceAmountMinor: json.intOrNull('reference_amount_minor'),
  );

  final bool shortlisted;
  final DateTime? cutoffAt;
  final bool submitted;

  /// The final offer must not be worse than this (own last offer).
  final int? referenceAmountMinor;

  @override
  List<Object?> get props => [
    shortlisted,
    cutoffAt,
    submitted,
    referenceAmountMinor,
  ];
}

/// `result` of a participant snapshot or competition.
final class ParticipantResult extends Equatable {
  const ParticipantResult({this.outcome, this.winningAmountMinor});

  factory ParticipantResult.fromJson(Json json) => ParticipantResult(
    outcome: AwardOutcome.parse(json['outcome']),
    winningAmountMinor: json.intOrNull('winning_amount_minor'),
  );

  /// Null when `result_publication = none`.
  final AwardOutcome? outcome;

  /// Only with `result_publication = outcome_and_amount`.
  final int? winningAmountMinor;

  @override
  List<Object?> get props => [outcome, winningAmountMinor];
}

/// `ParticipantLiveSnapshot` (API.md §2.8). Visibility follows
/// ARCHITECTURE.md §7.9 exactly: null fields are hidden, never "zero".
final class ParticipantLiveSnapshot extends Equatable {
  const ParticipantLiveSnapshot({
    required this.v,
    required this.competitionId,
    required this.direction,
    required this.status,
    required this.acceptingOffers,
    required this.amountGranularityMinor,
    required this.lastChange,
    this.phase,
    this.serverTime,
    this.biddingOpensAt,
    this.effectiveCloseAt,
    this.hardStopAt,
    this.extensionCount = 0,
    this.startPriceMinor,
    this.minStep = const MinStep(),
    this.myOffer,
    this.myOffersCount = 0,
    this.isLeading,
    this.rank,
    this.rankedCount,
    this.leadingAmountMinor,
    this.ladder,
    this.requiredNextAmountMinor,
    this.bafo,
    this.result,
  });

  factory ParticipantLiveSnapshot.fromJson(
    Json json,
  ) => ParticipantLiveSnapshot(
    v: json.integer('v'),
    competitionId: json.str('competition_id'),
    direction: Direction.parse(json['direction']),
    status: CompetitionStatus.parse(json['status']),
    phase: CompetitionPhase.parse(json['phase']),
    serverTime: json.dateOrNull('server_time'),
    biddingOpensAt: json.dateOrNull('bidding_opens_at'),
    effectiveCloseAt: json.dateOrNull('effective_close_at'),
    hardStopAt: json.dateOrNull('hard_stop_at'),
    extensionCount: json.intOrNull('extension_count') ?? 0,
    acceptingOffers: json.flag('accepting_offers'),
    startPriceMinor: json.intOrNull('start_price_minor'),
    minStep: json.parse('min_step', MinStep.fromJson) ?? const MinStep(),
    amountGranularityMinor: json.intOrNull('amount_granularity_minor') ?? 100,
    myOffer: json.parse('my_offer', OwnOffer.fromJson),
    myOffersCount: json.intOrNull('my_offers_count') ?? 0,
    isLeading: json.boolOrNull('is_leading'),
    rank: json.intOrNull('rank'),
    rankedCount: json.intOrNull('ranked_count'),
    leadingAmountMinor: json.intOrNull('leading_amount_minor'),
    ladder: json.listOrNull('ladder', LadderEntry.fromJson),
    requiredNextAmountMinor: json.intOrNull('required_next_amount_minor'),
    bafo: json.parse('bafo', ParticipantBafo.fromJson),
    result: json.parse('result', ParticipantResult.fromJson),
    lastChange:
        json.parse('last_change', LastChange.fromJson) ?? LastChange.snapshot,
  );

  /// Version: apply only when greater than the last applied one.
  final int v;
  final String competitionId;
  final Direction direction;
  final CompetitionStatus status;
  final CompetitionPhase? phase;
  final DateTime? serverTime;
  final DateTime? biddingOpensAt;
  final DateTime? effectiveCloseAt;
  final DateTime? hardStopAt;
  final int extensionCount;

  /// Whether the server would accept an offer from this participant now.
  final bool acceptingOffers;
  final int? startPriceMinor;
  final MinStep minStep;
  final int amountGranularityMinor;
  final OwnOffer? myOffer;
  final int myOffersCount;

  /// Null: the competition does not show it (or not in this phase).
  final bool? isLeading;
  final int? rank;
  final int? rankedCount;
  final int? leadingAmountMinor;
  final List<LadderEntry>? ladder;

  /// A bound: tender → the next offer must be ≤ it; auction → ≥ it.
  final int? requiredNextAmountMinor;
  final ParticipantBafo? bafo;
  final ParticipantResult? result;
  final LastChange lastChange;

  @override
  List<Object?> get props => [
    v,
    competitionId,
    direction,
    status,
    phase,
    serverTime,
    biddingOpensAt,
    effectiveCloseAt,
    hardStopAt,
    extensionCount,
    acceptingOffers,
    startPriceMinor,
    minStep,
    amountGranularityMinor,
    myOffer,
    myOffersCount,
    isLeading,
    rank,
    rankedCount,
    leadingAmountMinor,
    ladder,
    requiredNextAmountMinor,
    bafo,
    result,
    lastChange,
  ];
}

/// A participant organisation as the issuer sees it.
final class ParticipantOrganization extends Equatable {
  const ParticipantOrganization({
    required this.id,
    required this.name,
    this.logoUrl,
    this.email,
    this.phone,
    this.crNumber,
    this.vatNumber,
  });

  factory ParticipantOrganization.fromJson(Json json) =>
      ParticipantOrganization(
        id: json.str('id'),
        name: json.str('name'),
        logoUrl: json.strOrNull('logo_url'),
        email: json.strOrNull('email'),
        phone: json.strOrNull('phone'),
        crNumber: json.strOrNull('cr_number'),
        vatNumber: json.strOrNull('vat_number'),
      );

  final String id;
  final String name;
  final String? logoUrl;
  final String? email;
  final String? phone;
  final String? crNumber;
  final String? vatNumber;

  @override
  List<Object?> get props => [
    id,
    name,
    logoUrl,
    email,
    phone,
    crNumber,
    vatNumber,
  ];
}

/// The current leader (issuer). Null while sealed or without offers.
final class LiveLeader extends Equatable {
  const LiveLeader({
    required this.participantId,
    required this.aliasNo,
    required this.organization,
    required this.amountMinor,
    this.acceptedAt,
  });

  factory LiveLeader.fromJson(Json json) => LiveLeader(
    participantId: json.str('participant_id'),
    aliasNo: json.integer('alias_no'),
    organization: ParticipantOrganization.fromJson(json.obj('organization')),
    amountMinor: json.integer('amount_minor'),
    acceptedAt: json.dateOrNull('accepted_at'),
  );

  final String participantId;
  final int aliasNo;
  final ParticipantOrganization organization;
  final int amountMinor;
  final DateTime? acceptedAt;

  @override
  List<Object?> get props => [
    participantId,
    aliasNo,
    organization,
    amountMinor,
    acceptedAt,
  ];
}

/// BAFO flags of a participant, as the issuer sees them.
final class BafoFlags extends Equatable {
  const BafoFlags({
    this.shortlisted = false,
    this.submitted = false,
    this.referenceAmountMinor,
  });

  factory BafoFlags.fromJson(Json json) => BafoFlags(
    shortlisted: json.flag('shortlisted'),
    submitted: json.flag('submitted'),
    referenceAmountMinor: json.intOrNull('reference_amount_minor'),
  );

  final bool shortlisted;
  final bool submitted;
  final int? referenceAmountMinor;

  @override
  List<Object?> get props => [shortlisted, submitted, referenceAmountMinor];
}

/// A ranking row of the issuer snapshot. While sealed and before unlock,
/// amounts, rank and [isLeader] are null and [submitted] shows who offered.
final class RankingEntry extends Equatable {
  const RankingEntry({
    required this.participantId,
    required this.aliasNo,
    required this.organization,
    required this.offersCount,
    required this.submitted,
    this.currentAmountMinor,
    this.firstAmountMinor,
    this.rank,
    this.isLeader,
    this.lastOfferAt,
    this.bafo = const BafoFlags(),
  });

  factory RankingEntry.fromJson(Json json) => RankingEntry(
    participantId: json.str('participant_id'),
    aliasNo: json.integer('alias_no'),
    organization: ParticipantOrganization.fromJson(json.obj('organization')),
    currentAmountMinor: json.intOrNull('current_amount_minor'),
    firstAmountMinor: json.intOrNull('first_amount_minor'),
    rank: json.intOrNull('rank'),
    isLeader: json.boolOrNull('is_leader'),
    offersCount: json.intOrNull('offers_count') ?? 0,
    lastOfferAt: json.dateOrNull('last_offer_at'),
    submitted: json.flag('submitted'),
    bafo: json.parse('bafo', BafoFlags.fromJson) ?? const BafoFlags(),
  );

  final String participantId;
  final int aliasNo;
  final ParticipantOrganization organization;
  final int? currentAmountMinor;
  final int? firstAmountMinor;
  final int? rank;
  final bool? isLeader;
  final int offersCount;
  final DateTime? lastOfferAt;
  final bool submitted;
  final BafoFlags bafo;

  @override
  List<Object?> get props => [
    participantId,
    aliasNo,
    organization,
    currentAmountMinor,
    firstAmountMinor,
    rank,
    isLeader,
    offersCount,
    lastOfferAt,
    submitted,
    bafo,
  ];
}

final class LiveMetrics extends Equatable {
  const LiveMetrics({
    this.offersCount = 0,
    this.participantsJoined = 0,
    this.participantsWithOffers = 0,
    this.invitationsCount = 0,
    this.improvementVsStartBps,
  });

  factory LiveMetrics.fromJson(Json json) => LiveMetrics(
    offersCount: json.intOrNull('offers_count') ?? 0,
    participantsJoined: json.intOrNull('participants_joined') ?? 0,
    participantsWithOffers: json.intOrNull('participants_with_offers') ?? 0,
    invitationsCount: json.intOrNull('invitations_count') ?? 0,
    improvementVsStartBps: json.intOrNull('improvement_vs_start_bps'),
  );

  final int offersCount;
  final int participantsJoined;
  final int participantsWithOffers;
  final int invitationsCount;

  /// Server-signed: positive is better for the issuer (savings for a tender,
  /// uplift for an auction). Never recompute it.
  final int? improvementVsStartBps;

  @override
  List<Object?> get props => [
    offersCount,
    participantsJoined,
    participantsWithOffers,
    invitationsCount,
    improvementVsStartBps,
  ];
}

/// The issuer's view of a running BAFO round in the snapshot.
final class IssuerBafoProgress extends Equatable {
  const IssuerBafoProgress({
    required this.status,
    this.cutoffAt,
    this.shortlistCount = 0,
    this.submittedCount = 0,
  });

  factory IssuerBafoProgress.fromJson(Json json) => IssuerBafoProgress(
    status: json.strOrNull('status') ?? '',
    cutoffAt: json.dateOrNull('cutoff_at'),
    shortlistCount: json.intOrNull('shortlist_count') ?? 0,
    submittedCount: json.intOrNull('submitted_count') ?? 0,
  );

  /// `running` or `ended`.
  final String status;
  final DateTime? cutoffAt;
  final int shortlistCount;
  final int submittedCount;

  @override
  List<Object?> get props => [status, cutoffAt, shortlistCount, submittedCount];
}

/// `IssuerLiveSnapshot` (API.md §2.8).
final class IssuerLiveSnapshot extends Equatable {
  const IssuerLiveSnapshot({
    required this.v,
    required this.competitionId,
    required this.direction,
    required this.status,
    required this.lastChange,
    this.phase,
    this.serverTime,
    this.biddingOpensAt,
    this.effectiveCloseAt,
    this.hardStopAt,
    this.extensionCount = 0,
    this.leader,
    this.reserveMet,
    this.ranking = const [],
    this.metrics = const LiveMetrics(),
    this.onlineParticipantsCount = 0,
    this.bafo,
  });

  factory IssuerLiveSnapshot.fromJson(Json json) => IssuerLiveSnapshot(
    v: json.integer('v'),
    competitionId: json.str('competition_id'),
    direction: Direction.parse(json['direction']),
    status: CompetitionStatus.parse(json['status']),
    phase: CompetitionPhase.parse(json['phase']),
    serverTime: json.dateOrNull('server_time'),
    biddingOpensAt: json.dateOrNull('bidding_opens_at'),
    effectiveCloseAt: json.dateOrNull('effective_close_at'),
    hardStopAt: json.dateOrNull('hard_stop_at'),
    extensionCount: json.intOrNull('extension_count') ?? 0,
    leader: json.parse('leader', LiveLeader.fromJson),
    reserveMet: json.boolOrNull('reserve_met'),
    ranking: json.list('ranking', RankingEntry.fromJson),
    metrics: json.parse('metrics', LiveMetrics.fromJson) ?? const LiveMetrics(),
    onlineParticipantsCount: json.intOrNull('online_participants_count') ?? 0,
    bafo: json.parse('bafo', IssuerBafoProgress.fromJson),
    lastChange:
        json.parse('last_change', LastChange.fromJson) ?? LastChange.snapshot,
  );

  final int v;
  final String competitionId;
  final Direction direction;
  final CompetitionStatus status;
  final CompetitionPhase? phase;
  final DateTime? serverTime;
  final DateTime? biddingOpensAt;
  final DateTime? effectiveCloseAt;
  final DateTime? hardStopAt;
  final int extensionCount;
  final LiveLeader? leader;

  /// Null without a reserve price (or while sealed).
  final bool? reserveMet;
  final List<RankingEntry> ranking;
  final LiveMetrics metrics;
  final int onlineParticipantsCount;
  final IssuerBafoProgress? bafo;
  final LastChange lastChange;

  @override
  List<Object?> get props => [
    v,
    competitionId,
    direction,
    status,
    phase,
    serverTime,
    biddingOpensAt,
    effectiveCloseAt,
    hardStopAt,
    extensionCount,
    leader,
    reserveMet,
    ranking,
    metrics,
    onlineParticipantsCount,
    bafo,
    lastChange,
  ];
}

/// `MyOffer` (`GET …/my-offers`, and `offer` in a submission response).
final class MyOffer extends Equatable {
  const MyOffer({
    required this.id,
    required this.seq,
    required this.amountMinor,
    required this.stage,
    required this.acceptedAt,
    this.voided = false,
  });

  factory MyOffer.fromJson(Json json) => MyOffer(
    id: json.str('id'),
    seq: json.integer('seq'),
    amountMinor: json.integer('amount_minor'),
    stage: OfferStage.parse(json['stage']),
    acceptedAt: json.date('accepted_at'),
    voided: json.flag('voided'),
  );

  final String id;
  final int seq;
  final int amountMinor;
  final OfferStage stage;
  final DateTime acceptedAt;
  final bool voided;

  @override
  List<Object?> get props => [id, seq, amountMinor, stage, acceptedAt, voided];
}

/// `OfferLogEntry` (issuer `GET …/offers/log`, realtime `offer.accepted`).
final class OfferLogEntry extends Equatable {
  const OfferLogEntry({
    required this.id,
    required this.seq,
    required this.participantId,
    required this.aliasNo,
    required this.stage,
    required this.acceptedAt,
    this.organizationId,
    this.organizationName,
    this.amountMinor,
    this.channel,
    this.voided = false,
  });

  factory OfferLogEntry.fromJson(Json json) {
    final participant = json.obj('participant');
    final organization = participant.objOrNull('organization') ?? const {};
    return OfferLogEntry(
      id: json.str('id'),
      seq: json.integer('seq'),
      participantId: participant.str('id'),
      aliasNo: participant.integer('alias_no'),
      organizationId: organization.strOrNull('id'),
      organizationName: organization.strOrNull('name'),
      amountMinor: json.intOrNull('amount_minor'),
      stage: OfferStage.parse(json['stage']),
      acceptedAt: json.date('accepted_at'),
      channel: json.strOrNull('channel'),
      voided: json.flag('voided'),
    );
  }

  final String id;
  final int seq;
  final String participantId;
  final int aliasNo;
  final String? organizationId;
  final String? organizationName;

  /// Null while sealed and not yet unlocked.
  final int? amountMinor;
  final OfferStage stage;
  final DateTime acceptedAt;

  /// `web`, `ios`, `android` or `api`.
  final String? channel;
  final bool voided;

  @override
  List<Object?> get props => [
    id,
    seq,
    participantId,
    aliasNo,
    organizationId,
    organizationName,
    amountMinor,
    stage,
    acceptedAt,
    channel,
    voided,
  ];
}

/// `GET …/offers/log` page: entries in ascending `seq`.
final class OfferLogPage extends Equatable {
  const OfferLogPage({
    required this.entries,
    required this.lastSeq,
    required this.hasMore,
  });

  final List<OfferLogEntry> entries;
  final int lastSeq;
  final bool hasMore;

  @override
  List<Object?> get props => [entries, lastSeq, hasMore];
}

/// `ParticipantStandingRow` (issuer `GET …/offers`), ordered by rank.
final class ParticipantStandingRow extends Equatable {
  const ParticipantStandingRow({
    required this.participantId,
    required this.aliasNo,
    required this.organization,
    required this.coverage,
    required this.offersCount,
    required this.submitted,
    this.joinedAt,
    this.currentAmountMinor,
    this.firstAmountMinor,
    this.lastOfferAt,
    this.rank,
    this.isLeader,
    this.changeRatioBps,
    this.bafo = const BafoFlags(),
  });

  factory ParticipantStandingRow.fromJson(Json json) {
    final participant = json.obj('participant');
    return ParticipantStandingRow(
      participantId: participant.str('id'),
      aliasNo: participant.integer('alias_no'),
      joinedAt: participant.dateOrNull('joined_at'),
      organization: ParticipantOrganization.fromJson(
        participant.obj('organization'),
      ),
      coverage: Coverage.parse(participant['coverage']),
      currentAmountMinor: json.intOrNull('current_amount_minor'),
      firstAmountMinor: json.intOrNull('first_amount_minor'),
      offersCount: json.intOrNull('offers_count') ?? 0,
      lastOfferAt: json.dateOrNull('last_offer_at'),
      rank: json.intOrNull('rank'),
      isLeader: json.boolOrNull('is_leader'),
      changeRatioBps: json.intOrNull('change_ratio_bps'),
      submitted: json.flag('submitted'),
      bafo: json.parse('bafo', BafoFlags.fromJson) ?? const BafoFlags(),
    );
  }

  final String participantId;
  final int aliasNo;
  final DateTime? joinedAt;
  final ParticipantOrganization organization;
  final Coverage coverage;
  final int? currentAmountMinor;
  final int? firstAmountMinor;
  final int offersCount;
  final DateTime? lastOfferAt;
  final int? rank;
  final bool? isLeader;

  /// Server-signed "first → current" improvement (positive is better).
  final int? changeRatioBps;
  final bool submitted;
  final BafoFlags bafo;

  @override
  List<Object?> get props => [
    participantId,
    aliasNo,
    joinedAt,
    organization,
    coverage,
    currentAmountMinor,
    firstAmountMinor,
    offersCount,
    lastOfferAt,
    rank,
    isLeader,
    changeRatioBps,
    submitted,
    bafo,
  ];
}

/// 201 (or 200 replayed) of `POST …/offers`.
final class OfferSubmission extends Equatable {
  const OfferSubmission({
    required this.offer,
    required this.live,
    required this.liveJson,
    this.replayed = false,
  });

  final MyOffer offer;
  final ParticipantLiveSnapshot live;

  /// The raw snapshot, to pass through `CompetitionChannel.acceptSnapshot`.
  final Json liveJson;

  /// `Idempotent-Replayed: true`: the offer was already accepted earlier.
  final bool replayed;

  @override
  List<Object?> get props => [offer, live, replayed];
}
