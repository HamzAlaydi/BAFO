import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';

/// `Award` as the issuer sees it (API.md §2.9). Mobile shows it read-only
/// (M48); awarding happens on the web.
final class Award extends Equatable {
  const Award({
    required this.id,
    required this.status,
    required this.participantId,
    required this.aliasNo,
    required this.organization,
    required this.amountMinor,
    this.currency = 'SAR',
    this.isLeadingOffer,
    this.rankAtAward,
    this.reserveMet,
    this.justificationReason,
    this.justificationText,
    this.messageToWinner,
    this.internalNotes,
    this.offerId,
    this.offerSeq,
    this.offerAcceptedAt,
    this.awardedByName,
    this.awardedAt,
    this.revokedAt,
    this.revokeReason,
    this.erpSyncStatus,
    this.ledgerHeadHash,
  });

  factory Award.fromJson(Json json) {
    final participant = json.obj('participant');
    final justification = json.objOrNull('justification');
    final offer = json.objOrNull('offer');
    final awardedBy = json.objOrNull('awarded_by');
    final erpSync = json.objOrNull('erp_sync');
    return Award(
      id: json.str('id'),
      status: json.str('status'),
      participantId: participant.str('id'),
      aliasNo: participant.integer('alias_no'),
      organization: ParticipantOrganization.fromJson(
        participant.obj('organization'),
      ),
      amountMinor: json.integer('amount_minor'),
      currency: json.strOrNull('currency') ?? 'SAR',
      isLeadingOffer: json.boolOrNull('is_leading_offer'),
      rankAtAward: json.intOrNull('rank_at_award'),
      reserveMet: json.boolOrNull('reserve_met'),
      justificationReason: justification?.parse('reason', CloseReason.fromJson),
      justificationText: justification?.strOrNull('text'),
      messageToWinner: json.strOrNull('message_to_winner'),
      internalNotes: json.strOrNull('internal_notes'),
      offerId: offer?.strOrNull('id'),
      offerSeq: offer?.intOrNull('seq'),
      offerAcceptedAt: offer?.dateOrNull('accepted_at'),
      awardedByName: awardedBy?.strOrNull('name'),
      awardedAt: json.dateOrNull('awarded_at'),
      revokedAt: json.dateOrNull('revoked_at'),
      revokeReason: json.strOrNull('revoke_reason'),
      erpSyncStatus: erpSync?.strOrNull('status'),
      ledgerHeadHash: json.strOrNull('ledger_head_hash'),
    );
  }

  final String id;

  /// `issued` or `revoked`.
  final String status;
  final String participantId;
  final int aliasNo;
  final ParticipantOrganization organization;
  final int amountMinor;
  final String currency;
  final bool? isLeadingOffer;
  final int? rankAtAward;
  final bool? reserveMet;
  final CloseReason? justificationReason;
  final String? justificationText;
  final String? messageToWinner;
  final String? internalNotes;
  final String? offerId;
  final int? offerSeq;
  final DateTime? offerAcceptedAt;
  final String? awardedByName;
  final DateTime? awardedAt;
  final DateTime? revokedAt;
  final String? revokeReason;
  final String? erpSyncStatus;
  final String? ledgerHeadHash;

  @override
  List<Object?> get props => [
    id,
    status,
    participantId,
    aliasNo,
    organization,
    amountMinor,
    currency,
    isLeadingOffer,
    rankAtAward,
    reserveMet,
    justificationReason,
    justificationText,
    messageToWinner,
    internalNotes,
    offerId,
    offerSeq,
    offerAcceptedAt,
    awardedByName,
    awardedAt,
    revokedAt,
    revokeReason,
    erpSyncStatus,
    ledgerHeadHash,
  ];
}

/// `GET …/award` as a participant: the outcome, plus the message when this
/// participant won.
final class ParticipantAwardView extends Equatable {
  const ParticipantAwardView({
    this.outcome,
    this.winningAmountMinor,
    this.messageToWinner,
  });

  factory ParticipantAwardView.fromJson(Json json) => ParticipantAwardView(
    outcome: AwardOutcome.parse(json['outcome']),
    winningAmountMinor: json.intOrNull('winning_amount_minor'),
    messageToWinner: json.strOrNull('message_to_winner'),
  );

  final AwardOutcome? outcome;
  final int? winningAmountMinor;
  final String? messageToWinner;

  @override
  List<Object?> get props => [outcome, winningAmountMinor, messageToWinner];
}
