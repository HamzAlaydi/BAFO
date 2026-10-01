import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';

/// Invitations, both sides (API.md §1.4 issuer, §1.5 invitee). Every method
/// throws `ApiException`.
abstract interface class InvitationsRepository {
  /// Issuer `GET …/invitations` (≤ 200, not paginated).
  Future<InvitationList> list(
    String competitionId, {
    Set<InvitationStatus> statuses = const {},
  });

  /// Issuer `POST …/invitations` (all or nothing). Row errors come as 422
  /// `validation_failed` with `errors."invitations.{i}.<field>"` and
  /// `details.item_codes`; also `invitation_cutoff_passed`,
  /// `max_participants_exceeded`, `sponsorship_payment_required`.
  Future<List<Invitation>> invite(
    String competitionId,
    List<InvitationInput> invitations,
  );

  /// Issuer `PATCH …/invitations/{id}` (draft rows).
  Future<Invitation> update(
    String competitionId,
    String invitationId, {
    String? name,
    bool? sponsored,
  });

  /// Issuer `DELETE …/invitations/{id}`: a draft is deleted (null), a sent
  /// or viewed invitation is revoked (the updated row).
  Future<Invitation?> remove(String competitionId, String invitationId);

  /// Issuer `POST …/invitations/{id}/resend` (at most 3 per day → 429).
  Future<void> resend(String competitionId, String invitationId);

  /// Invitee `POST /invitations/{id}/join` → the participant projection.
  /// Errors: `terms_not_accepted`, `join_deadline_passed`,
  /// `already_participating`, `plan_required` (403, `details.access`; mobile
  /// shows information only).
  Future<Competition> join(String invitationId);

  /// Invitee `POST /invitations/{id}/decline`.
  Future<InviteeInvitation> decline(String invitationId, {String? reason});

  /// `POST /invitations/claim` (tokens open on the web in the MVP).
  Future<ClaimResult> claim(String token, {String? code});
}

final class ApiInvitationsRepository implements InvitationsRepository {
  ApiInvitationsRepository(this._api);

  final ApiClient _api;

  @override
  Future<InvitationList> list(
    String competitionId, {
    Set<InvitationStatus> statuses = const {},
  }) async {
    final response = await _api.get(
      'competitions/$competitionId/invitations',
      query: statuses.isEmpty
          ? null
          : {'status': statuses.map((status) => status.wire).join(',')},
    );
    final rawCounts = response.meta['counts'];
    return InvitationList(
      invitations: response.dataList.map(Invitation.fromJson).toList(),
      counts: {
        if (rawCounts is Json)
          for (final entry in rawCounts.entries)
            if (entry.value is int)
              InvitationStatus.parse(entry.key): entry.value as int,
      },
    );
  }

  @override
  Future<List<Invitation>> invite(
    String competitionId,
    List<InvitationInput> invitations,
  ) async {
    final response = await _api.post(
      'competitions/$competitionId/invitations',
      body: {
        'invitations': [for (final row in invitations) row.toJson()],
      },
    );
    return response.dataList.map(Invitation.fromJson).toList();
  }

  @override
  Future<Invitation> update(
    String competitionId,
    String invitationId, {
    String? name,
    bool? sponsored,
  }) async => Invitation.fromJson(
    (await _api.patch(
      'competitions/$competitionId/invitations/$invitationId',
      body: {'name': ?name, 'sponsored': ?sponsored},
    )).dataMap,
  );

  @override
  Future<Invitation?> remove(String competitionId, String invitationId) async {
    final response = await _api.delete(
      'competitions/$competitionId/invitations/$invitationId',
    );
    final data = response.data;
    return data is Json ? Invitation.fromJson(data) : null;
  }

  @override
  Future<void> resend(String competitionId, String invitationId) => _api.post(
    'competitions/$competitionId/invitations/$invitationId/resend',
  );

  @override
  Future<Competition> join(String invitationId) async => Competition.fromJson(
    (await _api.post(
      'invitations/$invitationId/join',
      body: {'accept_terms': true},
    )).dataMap,
  );

  @override
  Future<InviteeInvitation> decline(String invitationId, {String? reason}) async =>
      InviteeInvitation.fromJson(
        (await _api.post(
          'invitations/$invitationId/decline',
          body: {'reason': reason},
        )).dataMap,
      );

  @override
  Future<ClaimResult> claim(String token, {String? code}) async {
    final response = await _api.post(
      'invitations/claim',
      body: {'token': token, 'code': code},
    );
    final data = response.dataMap;
    if (response.statusCode == 202) {
      return ClaimCodeSent(
        sentTo: data.strOrNull('otp_sent_to') ?? '',
        expiresAt: data.dateOrNull('otp_expires_at'),
      );
    }
    return InvitationClaimed(InviteeInvitation.fromJson(data));
  }
}
