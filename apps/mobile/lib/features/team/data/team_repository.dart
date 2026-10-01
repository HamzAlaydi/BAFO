import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/features/team/domain/team_models.dart';

/// Team members (API.md §1.3 "Team"); every call needs `team.manage`.
abstract interface class TeamRepository {
  /// `GET /team/members`, optionally filtered by [status].
  Future<TeamRoster> members({MembershipStatus? status});

  /// `POST /team/members` → the invited member. Error:
  /// `seat_limit_reached` (409, `details.seats`).
  Future<TeamMember> invite(TeamMemberInvite invite);

  /// `PATCH /team/members/{id}`: only the given fields change. Errors:
  /// `cannot_modify_owner`, `cannot_modify_self`, `seat_limit_reached`.
  Future<TeamMember> update(
    String membershipId, {
    MembershipRole? role,
    bool? canAward,
    bool? canPurchase,
    MembershipStatus? status,
  });

  /// `DELETE /team/members/{id}` (204).
  Future<void> remove(String membershipId);

  /// `POST /team/members/{id}/resend-invitation` (204).
  Future<void> resendInvitation(String membershipId);
}

final class ApiTeamRepository implements TeamRepository {
  ApiTeamRepository(this._api);

  final ApiClient _api;

  @override
  Future<TeamRoster> members({MembershipStatus? status}) async {
    final response = await _api.get(
      'team/members',
      query: status == null ? null : {'status': status.wire},
    );
    final seats = response.meta['seats'];
    return TeamRoster(
      members: response.dataList.map(TeamMember.fromJson).toList(),
      seats: seats is Json ? Seats.fromJson(seats) : const Seats(used: 0, total: 0),
    );
  }

  @override
  Future<TeamMember> invite(TeamMemberInvite invite) async => TeamMember.fromJson(
    (await _api.post('team/members', body: invite.toJson())).dataMap,
  );

  @override
  Future<TeamMember> update(
    String membershipId, {
    MembershipRole? role,
    bool? canAward,
    bool? canPurchase,
    MembershipStatus? status,
  }) async {
    final response = await _api.patch(
      'team/members/$membershipId',
      body: {
        'role': ?role?.wire,
        'can_award': ?canAward,
        'can_purchase': ?canPurchase,
        'status': ?status?.wire,
      },
    );
    return TeamMember.fromJson(response.dataMap);
  }

  @override
  Future<void> remove(String membershipId) =>
      _api.delete('team/members/$membershipId');

  @override
  Future<void> resendInvitation(String membershipId) =>
      _api.post('team/members/$membershipId/resend-invitation');
}
