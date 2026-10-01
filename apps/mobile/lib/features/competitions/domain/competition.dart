import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';

/// `invitations.status` (ARCHITECTURE.md §6.2).
enum InvitationStatus implements WireEnum {
  draft('draft'),
  sent('sent'),
  viewed('viewed'),
  joined('joined'),
  declined('declined'),
  revoked('revoked'),
  expired('expired'),
  unknown('unknown');

  const InvitationStatus(this.wire);

  @override
  final String wire;

  static InvitationStatus parse(Object? raw) => parseWire(values, raw, unknown);
}

/// `schedule` of a competition. Invitee and list projections carry a subset;
/// drafts have no derived times until publish (SCREENS.md G3).
final class CompetitionSchedule extends Equatable {
  const CompetitionSchedule({
    this.biddingOpensAt,
    this.scheduledCloseAt,
    this.effectiveCloseAt,
    this.hardStopAt,
    this.finalWindowStartsAt,
    this.invitationCutoffAt,
    this.extensionCount = 0,
    this.publishedAt,
    this.openedAt,
    this.closedAt,
    this.offersOpenedAt,
    this.awardedAt,
    this.notAwardedAt,
    this.cancelledAt,
  });

  factory CompetitionSchedule.fromJson(Json json) => CompetitionSchedule(
    biddingOpensAt: json.dateOrNull('bidding_opens_at'),
    scheduledCloseAt: json.dateOrNull('scheduled_close_at'),
    effectiveCloseAt: json.dateOrNull('effective_close_at'),
    hardStopAt: json.dateOrNull('hard_stop_at'),
    finalWindowStartsAt: json.dateOrNull('final_window_starts_at'),
    invitationCutoffAt: json.dateOrNull('invitation_cutoff_at'),
    extensionCount: json.intOrNull('extension_count') ?? 0,
    publishedAt: json.dateOrNull('published_at'),
    openedAt: json.dateOrNull('opened_at'),
    closedAt: json.dateOrNull('closed_at'),
    offersOpenedAt: json.dateOrNull('offers_opened_at'),
    awardedAt: json.dateOrNull('awarded_at'),
    notAwardedAt: json.dateOrNull('not_awarded_at'),
    cancelledAt: json.dateOrNull('cancelled_at'),
  );

  final DateTime? biddingOpensAt;
  final DateTime? scheduledCloseAt;

  /// The close, including extensions. Countdown target while live.
  final DateTime? effectiveCloseAt;

  /// Latest possible close when auto-extend is on.
  final DateTime? hardStopAt;
  final DateTime? finalWindowStartsAt;

  /// Join deadline of invitees.
  final DateTime? invitationCutoffAt;
  final int extensionCount;
  final DateTime? publishedAt;
  final DateTime? openedAt;
  final DateTime? closedAt;
  final DateTime? offersOpenedAt;
  final DateTime? awardedAt;
  final DateTime? notAwardedAt;
  final DateTime? cancelledAt;

  @override
  List<Object?> get props => [
    biddingOpensAt,
    scheduledCloseAt,
    effectiveCloseAt,
    hardStopAt,
    finalWindowStartsAt,
    invitationCutoffAt,
    extensionCount,
    publishedAt,
    openedAt,
    closedAt,
    offersOpenedAt,
    awardedAt,
    notAwardedAt,
    cancelledAt,
  ];
}

/// Issuer-only counters.
final class CompetitionCounts extends Equatable {
  const CompetitionCounts({
    this.invitations = 0,
    this.joined = 0,
    this.declined = 0,
    this.participantsWithOffers = 0,
    this.offers = 0,
    this.comments = 0,
    this.attachments = 0,
  });

  factory CompetitionCounts.fromJson(Json json) => CompetitionCounts(
    invitations: json.intOrNull('invitations') ?? 0,
    joined: json.intOrNull('joined') ?? 0,
    declined: json.intOrNull('declined') ?? 0,
    participantsWithOffers: json.intOrNull('participants_with_offers') ?? 0,
    offers: json.intOrNull('offers') ?? 0,
    comments: json.intOrNull('comments') ?? 0,
    attachments: json.intOrNull('attachments') ?? 0,
  );

  final int invitations;
  final int joined;
  final int declined;
  final int participantsWithOffers;
  final int offers;
  final int comments;
  final int attachments;

  @override
  List<Object?> get props => [
    invitations,
    joined,
    declined,
    participantsWithOffers,
    offers,
    comments,
    attachments,
  ];
}

/// Server decisions on what the viewer may do (ARCHITECTURE.md §8.3). The UI
/// shows or hides controls from these flags only (SCREENS.md S10).
final class CompetitionPermissions extends Equatable {
  const CompetitionPermissions({
    this.canEdit = false,
    this.canDelete = false,
    this.canPublish = false,
    this.canInvite = false,
    this.canExtend = false,
    this.canCancel = false,
    this.canStartBafo = false,
    this.canAward = false,
    this.canRevokeAward = false,
    this.canCloseWithoutAward = false,
    this.canManageSponsorship = false,
    this.canComment = false,
    this.canJoin = false,
    this.canDecline = false,
    this.canSubmitOffer = false,
  });

  factory CompetitionPermissions.fromJson(Json json) => CompetitionPermissions(
    canEdit: json.flag('can_edit'),
    canDelete: json.flag('can_delete'),
    canPublish: json.flag('can_publish'),
    canInvite: json.flag('can_invite'),
    canExtend: json.flag('can_extend'),
    canCancel: json.flag('can_cancel'),
    canStartBafo: json.flag('can_start_bafo'),
    canAward: json.flag('can_award'),
    canRevokeAward: json.flag('can_revoke_award'),
    canCloseWithoutAward: json.flag('can_close_without_award'),
    canManageSponsorship: json.flag('can_manage_sponsorship'),
    canComment: json.flag('can_comment'),
    canJoin: json.flag('can_join'),
    canDecline: json.flag('can_decline'),
    canSubmitOffer: json.flag('can_submit_offer'),
  );

  final bool canEdit;
  final bool canDelete;
  final bool canPublish;
  final bool canInvite;
  final bool canExtend;
  final bool canCancel;
  final bool canStartBafo;
  final bool canAward;
  final bool canRevokeAward;
  final bool canCloseWithoutAward;
  final bool canManageSponsorship;
  final bool canComment;
  final bool canJoin;
  final bool canDecline;
  final bool canSubmitOffer;

  @override
  List<Object?> get props => [
    canEdit,
    canDelete,
    canPublish,
    canInvite,
    canExtend,
    canCancel,
    canStartBafo,
    canAward,
    canRevokeAward,
    canCloseWithoutAward,
    canManageSponsorship,
    canComment,
    canJoin,
    canDecline,
    canSubmitOffer,
  ];
}

/// `access` of an invitee or participant (ARCHITECTURE.md §8.4). Render it;
/// never recompute entitlement on the client.
final class ParticipationAccess extends Equatable {
  const ParticipationAccess({
    required this.state,
    required this.coverage,
    this.sponsorName,
    this.joinDeadline,
  });

  factory ParticipationAccess.fromJson(Json json) => ParticipationAccess(
    state: AccessState.parse(json['state']),
    coverage: Coverage.parse(json['coverage']),
    sponsorName: json.strOrNull('sponsor_name'),
    joinDeadline: json.dateOrNull('join_deadline'),
  );

  final AccessState state;
  final Coverage coverage;

  /// The issuer's name when the fees are covered.
  final String? sponsorName;
  final DateTime? joinDeadline;

  /// "Fees covered" badge: only for the covered participant (S2).
  bool get feesCovered => coverage == Coverage.sponsored;

  @override
  List<Object?> get props => [state, coverage, sponsorName, joinDeadline];
}

/// The viewer organisation's participation (participant projection).
final class ParticipationInfo extends Equatable {
  const ParticipationInfo({
    required this.participantId,
    required this.aliasNo,
    this.joinedAt,
    this.termsAcceptedAt,
  });

  factory ParticipationInfo.fromJson(Json json) => ParticipationInfo(
    participantId: json.str('participant_id'),
    aliasNo: json.integer('alias_no'),
    joinedAt: json.dateOrNull('joined_at'),
    termsAcceptedAt: json.dateOrNull('terms_accepted_at'),
  );

  final String participantId;
  final int aliasNo;
  final DateTime? joinedAt;
  final DateTime? termsAcceptedAt;

  @override
  List<Object?> get props => [participantId, aliasNo, joinedAt, termsAcceptedAt];
}

/// `sponsorship` summary of the issuer projection (null when mode is none).
final class SponsorshipSummary extends Equatable {
  const SponsorshipSummary({
    required this.mode,
    this.status,
    this.fundedPasses = 0,
    this.freeSlots = 0,
  });

  factory SponsorshipSummary.fromJson(Json json) => SponsorshipSummary(
    mode: json.str('mode'),
    status: json.strOrNull('status'),
    fundedPasses: json.intOrNull('funded_passes') ?? 0,
    freeSlots: json.intOrNull('free_slots') ?? 0,
  );

  /// `all` or `selected`.
  final String mode;

  /// `draft`, `active` or `settled`.
  final String? status;
  final int fundedPasses;
  final int freeSlots;

  @override
  List<Object?> get props => [mode, status, fundedPasses, freeSlots];
}

/// `bafo_round` of a competition. The issuer gets the full round
/// ([id], counts); a participant gets [shortlisted] and [submitted].
final class BafoRoundInfo extends Equatable {
  const BafoRoundInfo({
    required this.status,
    this.id,
    this.startsAt,
    this.cutoffAt,
    this.endedAt,
    this.shortlistCount,
    this.submittedCount,
    this.shortlisted,
    this.submitted,
  });

  factory BafoRoundInfo.fromJson(Json json) => BafoRoundInfo(
    id: json.strOrNull('id'),
    status: json.strOrNull('status') ?? '',
    startsAt: json.dateOrNull('starts_at'),
    cutoffAt: json.dateOrNull('cutoff_at'),
    endedAt: json.dateOrNull('ended_at'),
    shortlistCount: json.intOrNull('shortlist_count'),
    submittedCount: json.intOrNull('submitted_count'),
    shortlisted: json.boolOrNull('shortlisted'),
    submitted: json.boolOrNull('submitted'),
  );

  final String? id;

  /// `running` or `ended`.
  final String status;
  final DateTime? startsAt;
  final DateTime? cutoffAt;
  final DateTime? endedAt;
  final int? shortlistCount;
  final int? submittedCount;
  final bool? shortlisted;
  final bool? submitted;

  @override
  List<Object?> get props => [
    id,
    status,
    startsAt,
    cutoffAt,
    endedAt,
    shortlistCount,
    submittedCount,
    shortlisted,
    submitted,
  ];
}

/// `AwardSummary` of the issuer projection.
final class AwardSummary extends Equatable {
  const AwardSummary({
    required this.id,
    required this.status,
    required this.participantId,
    required this.aliasNo,
    required this.organization,
    required this.amountMinor,
    this.awardedAt,
  });

  factory AwardSummary.fromJson(Json json) {
    final participant = json.obj('participant');
    return AwardSummary(
      id: json.str('id'),
      status: json.str('status'),
      participantId: participant.str('id'),
      aliasNo: participant.integer('alias_no'),
      organization: OrganizationSummary.fromJson(
        participant.obj('organization'),
      ),
      amountMinor: json.integer('amount_minor'),
      awardedAt: json.dateOrNull('awarded_at'),
    );
  }

  final String id;

  /// `issued` or `revoked`.
  final String status;
  final String participantId;
  final int aliasNo;
  final OrganizationSummary organization;
  final int amountMinor;
  final DateTime? awardedAt;

  @override
  List<Object?> get props => [
    id,
    status,
    participantId,
    aliasNo,
    organization,
    amountMinor,
    awardedAt,
  ];
}

/// `cancellation` and `not_awarded` blocks: the reason, a note and when.
final class ClosureInfo extends Equatable {
  const ClosureInfo({required this.reason, this.note, this.at});

  factory ClosureInfo.fromJson(Json json, String atKey) => ClosureInfo(
    reason: CloseReason.fromJson(json.obj('reason')),
    note: json.strOrNull('note'),
    at: json.dateOrNull(atKey),
  );

  final CloseReason reason;
  final String? note;
  final DateTime? at;

  @override
  List<Object?> get props => [reason, note, at];
}

/// The invitee's invitation in the teaser.
final class TeaserInvitation extends Equatable {
  const TeaserInvitation({
    required this.id,
    required this.status,
    this.joinDeadline,
    this.sentAt,
  });

  factory TeaserInvitation.fromJson(Json json) => TeaserInvitation(
    id: json.str('id'),
    status: InvitationStatus.parse(json['status']),
    joinDeadline: json.dateOrNull('join_deadline'),
    sentAt: json.dateOrNull('sent_at'),
  );

  final String id;
  final InvitationStatus status;
  final DateTime? joinDeadline;
  final DateTime? sentAt;

  @override
  List<Object?> get props => [id, status, joinDeadline, sentAt];
}

/// `Competition` (API.md §2.6), projected for the viewer by the server.
///
/// One class covers the three projections; fields another projection omits
/// are null (issuer only: [counts], [leadingAmountMinor], [sponsorship],
/// [award], [createdByName], [source], `rules.reserve_price_minor`;
/// participant only: [participation], [result]; invitee: [invitation],
/// [invitationDocuments], and no [description] or live block).
final class Competition extends Equatable {
  const Competition({
    required this.id,
    required this.title,
    required this.direction,
    required this.format,
    required this.status,
    required this.viewerRole,
    required this.permissions,
    this.referenceNo,
    this.description,
    this.phase,
    this.currency = 'SAR',
    this.priceBasis = 'excl_vat',
    this.category,
    this.categoryOtherText,
    this.region,
    this.presetCode,
    this.rules = const Rules(),
    this.rulesSummary = const [],
    this.schedule = const CompetitionSchedule(),
    this.issuer,
    this.counts,
    this.leadingAmountMinor,
    this.sponsorship,
    this.bafoRound,
    this.award,
    this.cancellation,
    this.notAwarded,
    this.createdByName,
    this.source,
    this.participation,
    this.access,
    this.result,
    this.invitation,
    this.invitationDocuments = const [],
    this.issuerLive,
    this.participantLive,
    this.liveJson,
    this.serverTime,
    this.createdAt,
    this.updatedAt,
  });

  factory Competition.fromJson(Json json) {
    final role = ViewerRole.parse(json['viewer_role']);
    final live = json.objOrNull('live');
    final createdBy = json.objOrNull('created_by');
    return Competition(
      id: json.str('id'),
      referenceNo: json.strOrNull('reference_no'),
      title: json.str('title'),
      description: json.strOrNull('description'),
      direction: Direction.parse(json['direction']),
      format: CompetitionFormat.parse(json['format']),
      status: CompetitionStatus.parse(json['status']),
      phase: CompetitionPhase.parse(json['phase']),
      currency: json.strOrNull('currency') ?? 'SAR',
      priceBasis: json.strOrNull('price_basis') ?? 'excl_vat',
      category: json.parse('category', Category.fromJson),
      categoryOtherText: json.strOrNull('category_other_text'),
      region: json.parse('region', Region.fromJson),
      presetCode: json.strOrNull('preset_code'),
      rules: json.parse('rules', Rules.fromJson) ?? const Rules(),
      rulesSummary: json.strings('rules_summary'),
      schedule:
          json.parse('schedule', CompetitionSchedule.fromJson) ??
          const CompetitionSchedule(),
      issuer: json.parse('issuer', OrganizationSummary.fromJson),
      counts: json.parse('counts', CompetitionCounts.fromJson),
      leadingAmountMinor: json.intOrNull('leading_amount_minor'),
      sponsorship: json.parse('sponsorship', SponsorshipSummary.fromJson),
      bafoRound: json.parse('bafo_round', BafoRoundInfo.fromJson),
      award: json.parse('award', AwardSummary.fromJson),
      cancellation: json.parse(
        'cancellation',
        (value) => ClosureInfo.fromJson(value, 'cancelled_at'),
      ),
      notAwarded: json.parse(
        'not_awarded',
        (value) => ClosureInfo.fromJson(value, 'not_awarded_at'),
      ),
      createdByName: createdBy?.strOrNull('name'),
      source: json.strOrNull('source'),
      permissions:
          json.parse('permissions', CompetitionPermissions.fromJson) ??
          const CompetitionPermissions(),
      viewerRole: role,
      participation: json.parse('participation', ParticipationInfo.fromJson),
      access: json.parse('access', ParticipationAccess.fromJson),
      result: json.parse('result', ParticipantResult.fromJson),
      invitation: json.parse('invitation', TeaserInvitation.fromJson),
      invitationDocuments: json.list('invitation_documents', Attachment.fromJson),
      issuerLive: live != null && role == ViewerRole.issuer
          ? IssuerLiveSnapshot.fromJson(live)
          : null,
      participantLive: live != null && role == ViewerRole.participant
          ? ParticipantLiveSnapshot.fromJson(live)
          : null,
      liveJson: live,
      serverTime: json.dateOrNull('server_time'),
      createdAt: json.dateOrNull('created_at'),
      updatedAt: json.dateOrNull('updated_at'),
    );
  }

  final String id;

  /// `BAFO-T-2026-000123`; null for drafts.
  final String? referenceNo;
  final String title;
  final String? description;
  final Direction direction;
  final CompetitionFormat format;
  final CompetitionStatus status;

  /// Only while [status] is live.
  final CompetitionPhase? phase;
  final String currency;

  /// Always `excl_vat`.
  final String priceBasis;
  final Category? category;
  final String? categoryOtherText;
  final Region? region;
  final String? presetCode;
  final Rules rules;

  /// Localised sentences generated by the server (ARCHITECTURE.md §7.16).
  final List<String> rulesSummary;
  final CompetitionSchedule schedule;
  final OrganizationSummary? issuer;
  final CompetitionCounts? counts;
  final int? leadingAmountMinor;
  final SponsorshipSummary? sponsorship;
  final BafoRoundInfo? bafoRound;
  final AwardSummary? award;
  final ClosureInfo? cancellation;
  final ClosureInfo? notAwarded;
  final String? createdByName;

  /// `web`, `ios`, `android` or `api`.
  final String? source;
  final CompetitionPermissions permissions;
  final ViewerRole viewerRole;
  final ParticipationInfo? participation;
  final ParticipationAccess? access;
  final ParticipantResult? result;
  final TeaserInvitation? invitation;
  final List<Attachment> invitationDocuments;
  final IssuerLiveSnapshot? issuerLive;
  final ParticipantLiveSnapshot? participantLive;

  /// The raw `live` block, for `CompetitionChannel.acceptSnapshot`.
  final Json? liveJson;
  final DateTime? serverTime;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  bool get isIssuerView => viewerRole == ViewerRole.issuer;

  bool get isParticipantView => viewerRole == ViewerRole.participant;

  bool get isInviteeView => viewerRole == ViewerRole.invitee;

  /// The live `extension_count`, else the schedule's.
  int get extensionCount =>
      participantLive?.extensionCount ??
      issuerLive?.extensionCount ??
      schedule.extensionCount;

  @override
  List<Object?> get props => [
    id,
    referenceNo,
    title,
    description,
    direction,
    format,
    status,
    phase,
    currency,
    priceBasis,
    category,
    categoryOtherText,
    region,
    presetCode,
    rules,
    rulesSummary,
    schedule,
    issuer,
    counts,
    leadingAmountMinor,
    sponsorship,
    bafoRound,
    award,
    cancellation,
    notAwarded,
    createdByName,
    source,
    permissions,
    viewerRole,
    participation,
    access,
    result,
    invitation,
    invitationDocuments,
    issuerLive,
    participantLive,
    serverTime,
    createdAt,
    updatedAt,
  ];
}

/// A row of `GET /competitions` (API.md §2.6 `CompetitionListItem`): the
/// issuer variant ([counts], [leadingAmountMinor]) or the participant variant
/// ([issuer], [invitation], [access], [myOfferAmountMinor], [isLeading],
/// [resultOutcome]).
final class CompetitionListItem extends Equatable {
  const CompetitionListItem({
    required this.id,
    required this.title,
    required this.direction,
    required this.format,
    required this.status,
    this.referenceNo,
    this.phase,
    this.category,
    this.region,
    this.biddingOpensAt,
    this.effectiveCloseAt,
    this.invitationCutoffAt,
    this.counts,
    this.leadingAmountMinor,
    this.issuer,
    this.invitationId,
    this.invitationStatus,
    this.joinDeadline,
    this.access,
    this.myOfferAmountMinor,
    this.isLeading,
    this.resultOutcome,
    this.createdAt,
    this.updatedAt,
  });

  factory CompetitionListItem.fromJson(Json json) {
    final schedule = json.objOrNull('schedule') ?? const {};
    final invitation = json.objOrNull('invitation');
    final result = json.objOrNull('result');
    return CompetitionListItem(
      id: json.str('id'),
      referenceNo: json.strOrNull('reference_no'),
      title: json.str('title'),
      direction: Direction.parse(json['direction']),
      format: CompetitionFormat.parse(json['format']),
      status: CompetitionStatus.parse(json['status']),
      phase: CompetitionPhase.parse(json['phase']),
      category: json.parse('category', Category.fromJson),
      region: json.parse('region', Region.fromJson),
      biddingOpensAt: schedule.dateOrNull('bidding_opens_at'),
      effectiveCloseAt: schedule.dateOrNull('effective_close_at'),
      invitationCutoffAt: schedule.dateOrNull('invitation_cutoff_at'),
      counts: json.parse('counts', CompetitionCounts.fromJson),
      leadingAmountMinor: json.intOrNull('leading_amount_minor'),
      issuer: json.parse('issuer', OrganizationSummary.fromJson),
      invitationId: invitation?.strOrNull('id'),
      invitationStatus: invitation == null
          ? null
          : InvitationStatus.parse(invitation['status']),
      joinDeadline: invitation?.dateOrNull('join_deadline'),
      access: json.parse('access', ParticipationAccess.fromJson),
      myOfferAmountMinor: json.intOrNull('my_offer_amount_minor'),
      isLeading: json.boolOrNull('is_leading'),
      resultOutcome: result == null ? null : AwardOutcome.parse(result['outcome']),
      createdAt: json.dateOrNull('created_at'),
      updatedAt: json.dateOrNull('updated_at'),
    );
  }

  final String id;
  final String? referenceNo;
  final String title;
  final Direction direction;
  final CompetitionFormat format;
  final CompetitionStatus status;
  final CompetitionPhase? phase;
  final Category? category;
  final Region? region;
  final DateTime? biddingOpensAt;
  final DateTime? effectiveCloseAt;
  final DateTime? invitationCutoffAt;
  final CompetitionCounts? counts;
  final int? leadingAmountMinor;
  final OrganizationSummary? issuer;
  final String? invitationId;
  final InvitationStatus? invitationStatus;
  final DateTime? joinDeadline;
  final ParticipationAccess? access;

  /// Null without an offer or before joining.
  final int? myOfferAmountMinor;

  /// Only when the competition projects it (ARCHITECTURE.md §7.9).
  final bool? isLeading;
  final AwardOutcome? resultOutcome;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// SCREENS.md G1: cards needing the viewer's action sort first.
  bool get needsAction =>
      access?.state == AccessState.joinRequired ||
      access?.state == AccessState.planRequired;

  @override
  List<Object?> get props => [
    id,
    referenceNo,
    title,
    direction,
    format,
    status,
    phase,
    category,
    region,
    biddingOpensAt,
    effectiveCloseAt,
    invitationCutoffAt,
    counts,
    leadingAmountMinor,
    issuer,
    invitationId,
    invitationStatus,
    joinDeadline,
    access,
    myOfferAmountMinor,
    isLeading,
    resultOutcome,
    createdAt,
    updatedAt,
  ];
}
