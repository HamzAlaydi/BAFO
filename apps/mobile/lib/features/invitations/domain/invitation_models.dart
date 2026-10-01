import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:equatable/equatable.dart';

/// Sponsored-pass states (ARCHITECTURE.md §6.3).
enum PassStatus implements WireEnum {
  pending('pending'),
  reserved('reserved'),
  joined('joined'),
  released('released'),
  unused('unused'),
  voided('void'),
  unknown('unknown');

  const PassStatus(this.wire);

  @override
  final String wire;

  static PassStatus? parse(Object? raw) => parseWireOrNull(values, raw, unknown);
}

/// `Invitation` as the issuer sees it (API.md §2.7).
final class Invitation extends Equatable {
  const Invitation({
    required this.id,
    required this.email,
    required this.status,
    this.name,
    this.organization,
    this.vendorId,
    this.vendorName,
    this.sponsoredRequested = false,
    this.coverage = Coverage.none,
    this.passStatus,
    this.participantId,
    this.aliasNo,
    this.sentAt,
    this.viewedAt,
    this.joinedAt,
    this.declinedAt,
    this.declineReason,
    this.revokedAt,
    this.revokeReason,
    this.expiredAt,
    this.createdAt,
  });

  factory Invitation.fromJson(Json json) {
    final vendor = json.objOrNull('vendor');
    final participant = json.objOrNull('participant');
    return Invitation(
      id: json.str('id'),
      email: json.str('email'),
      name: json.strOrNull('name'),
      status: InvitationStatus.parse(json['status']),
      organization: json.parse('organization', OrganizationSummary.fromJson),
      vendorId: vendor?.strOrNull('id'),
      vendorName: vendor?.strOrNull('name'),
      sponsoredRequested: json.flag('sponsored_requested'),
      coverage: Coverage.parse(json['coverage']),
      passStatus: PassStatus.parse(json['pass_status']),
      participantId: participant?.strOrNull('id'),
      aliasNo: participant?.intOrNull('alias_no'),
      sentAt: json.dateOrNull('sent_at'),
      viewedAt: json.dateOrNull('viewed_at'),
      joinedAt: json.dateOrNull('joined_at'),
      declinedAt: json.dateOrNull('declined_at'),
      declineReason: json.strOrNull('decline_reason'),
      revokedAt: json.dateOrNull('revoked_at'),
      revokeReason: json.strOrNull('revoke_reason'),
      expiredAt: json.dateOrNull('expired_at'),
      createdAt: json.dateOrNull('created_at'),
    );
  }

  final String id;
  final String email;
  final String? name;
  final InvitationStatus status;

  /// Null when the invitee has no BAFO organisation yet.
  final OrganizationSummary? organization;
  final String? vendorId;
  final String? vendorName;
  final bool sponsoredRequested;
  final Coverage coverage;
  final PassStatus? passStatus;

  /// Set once the invitee joined.
  final String? participantId;
  final int? aliasNo;
  final DateTime? sentAt;
  final DateTime? viewedAt;
  final DateTime? joinedAt;
  final DateTime? declinedAt;
  final String? declineReason;
  final DateTime? revokedAt;
  final String? revokeReason;
  final DateTime? expiredAt;
  final DateTime? createdAt;

  /// Resend and revoke are offered for `sent` and `viewed` only.
  bool get isPending =>
      status == InvitationStatus.sent || status == InvitationStatus.viewed;

  @override
  List<Object?> get props => [
    id,
    email,
    name,
    status,
    organization,
    vendorId,
    vendorName,
    sponsoredRequested,
    coverage,
    passStatus,
    participantId,
    aliasNo,
    sentAt,
    viewedAt,
    joinedAt,
    declinedAt,
    declineReason,
    revokedAt,
    revokeReason,
    expiredAt,
    createdAt,
  ];
}

/// `GET …/invitations`: the rows and `meta.counts` per status.
final class InvitationList extends Equatable {
  const InvitationList({required this.invitations, this.counts = const {}});

  final List<Invitation> invitations;
  final Map<InvitationStatus, int> counts;

  int countOf(InvitationStatus status) => counts[status] ?? 0;

  @override
  List<Object?> get props => [invitations, counts];
}

/// One row of `POST …/invitations`: an e-mail, a suggested organisation or
/// a vendor (at least one of them).
final class InvitationInput extends Equatable {
  const InvitationInput({
    this.email,
    this.organizationId,
    this.vendorId,
    this.name,
    this.sponsored = false,
  });

  final String? email;
  final String? organizationId;
  final String? vendorId;
  final String? name;

  /// Counts only in `selected` sponsorship mode.
  final bool sponsored;

  Json toJson() => {
    'email': ?email?.trim(),
    'organization_id': ?organizationId,
    'vendor_id': ?vendorId,
    'name': ?name,
    'sponsored': sponsored,
  };

  @override
  List<Object?> get props => [email, organizationId, vendorId, name, sponsored];
}

/// `Invitation` as the invitee sees it (after decline).
final class InviteeInvitation extends Equatable {
  const InviteeInvitation({
    required this.id,
    required this.status,
    this.joinDeadline,
    this.sentAt,
    this.competition,
  });

  factory InviteeInvitation.fromJson(Json json) => InviteeInvitation(
    id: json.str('id'),
    status: InvitationStatus.parse(json['status']),
    joinDeadline: json.dateOrNull('join_deadline'),
    sentAt: json.dateOrNull('sent_at'),
    competition: json.parse('competition', Competition.fromJson),
  );

  final String id;
  final InvitationStatus status;
  final DateTime? joinDeadline;
  final DateTime? sentAt;
  final Competition? competition;

  @override
  List<Object?> get props => [id, status, joinDeadline, sentAt, competition];
}

/// `POST /invitations/claim`: bound (200) or a code was sent (202).
sealed class ClaimResult extends Equatable {
  const ClaimResult();
}

final class InvitationClaimed extends ClaimResult {
  const InvitationClaimed(this.invitation);

  final InviteeInvitation invitation;

  @override
  List<Object?> get props => [invitation];
}

final class ClaimCodeSent extends ClaimResult {
  const ClaimCodeSent({required this.sentTo, this.expiresAt});

  /// Masked e-mail, e.g. `s***@acme.sa`.
  final String sentTo;
  final DateTime? expiresAt;

  @override
  List<Object?> get props => [sentTo, expiresAt];
}
