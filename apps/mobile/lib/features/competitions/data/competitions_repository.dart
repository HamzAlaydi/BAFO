import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';

/// Which side of `GET /competitions` to list.
enum CompetitionListRole {
  issuer('issuer'),
  participant('participant');

  const CompetitionListRole(this.wire);

  final String wire;
}

/// `status_group` of `GET /competitions`.
enum CompetitionStatusGroup {
  /// scheduled, live, closed, bafo_round.
  active('active'),
  draft('draft'),

  /// awarded, not_awarded, cancelled.
  ended('ended'),
  all('all');

  const CompetitionStatusGroup(this.wire);

  final String wire;
}

enum CompetitionSort {
  recentlyUpdated('-updated_at'),
  closingSoonest('effective_close_at'),
  recentlyCreated('-created_at');

  const CompetitionSort(this.wire);

  final String wire;
}

/// Competitions (API.md §1.4) and the bidding reads the competition screens
/// share (award). Every method throws `ApiException`.
abstract interface class CompetitionsRepository {
  /// `GET /competitions`. [query] searches titles (debounce 300 ms).
  Future<Paged<CompetitionListItem>> list({
    required CompetitionListRole role,
    CompetitionStatusGroup group = CompetitionStatusGroup.all,
    Direction? direction,
    String? query,
    CompetitionSort sort = CompetitionSort.recentlyUpdated,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  });

  /// `GET /competitions/{id}`, projected for the caller (`viewer_role`).
  /// 404 when the caller may not see it. An invitee's first view marks the
  /// invitation `viewed`.
  Future<Competition> show(String id);

  /// `POST /competitions` → a draft. Errors: `issuer_plan_required`,
  /// `auction_not_enabled`, `validation_failed`.
  Future<Competition> create(CompetitionDraftInput input);

  /// `PATCH /competitions/{id}` with only the fields the status allows
  /// (API.md §1.4); others → 409 `competition_not_editable`
  /// (`details.fields`). Use [CompetitionDraftInput.toJson] for drafts.
  Future<Competition> update(String id, Map<String, Object?> fields);

  /// `DELETE /competitions/{id}` (drafts only).
  Future<void> deleteDraft(String id);

  /// `POST /competitions/{id}/publish`. Errors: `min_participants_not_met`,
  /// `issuer_plan_required`, `live_event_capacity_reached`,
  /// `sponsorship_payment_required` (mobile shows the web notice),
  /// `validation_failed`.
  Future<Competition> publish(String id);

  /// `POST /competitions/{id}/cancel` with a `cancel` close reason.
  Future<Competition> cancel(
    String id, {
    required String closeReasonId,
    String? note,
  });

  /// `GET /competitions/{id}/suggestions`.
  Future<List<Suggestion>> suggestions(String id, {String? query, int limit = 20});

  /// `GET /competitions/{id}/sponsorship` (issuer, read-only on mobile).
  Future<Sponsorship> sponsorship(String id);

  /// Issuer `GET /competitions/{id}/award`: null when there is no award.
  Future<Award?> issuerAward(String id);

  /// Participant `GET /competitions/{id}/award`: null when there is none.
  Future<ParticipantAwardView?> participantAward(String id);
}

final class ApiCompetitionsRepository implements CompetitionsRepository {
  ApiCompetitionsRepository(this._api);

  final ApiClient _api;

  @override
  Future<Paged<CompetitionListItem>> list({
    required CompetitionListRole role,
    CompetitionStatusGroup group = CompetitionStatusGroup.all,
    Direction? direction,
    String? query,
    CompetitionSort sort = CompetitionSort.recentlyUpdated,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async {
    final search = query?.trim();
    final response = await _api.get(
      'competitions',
      query: {
        'role': role.wire,
        'status_group': group.wire,
        if (direction != null && direction != Direction.unknown)
          'direction': direction.wire,
        if (search != null && search.isNotEmpty) 'q': search,
        'sort': sort.wire,
        ...pageQuery(page, perPage),
      },
    );
    return Paged.fromResponse(response, CompetitionListItem.fromJson);
  }

  @override
  Future<Competition> show(String id) async =>
      Competition.fromJson((await _api.get('competitions/$id')).dataMap);

  @override
  Future<Competition> create(CompetitionDraftInput input) async =>
      Competition.fromJson(
        (await _api.post('competitions', body: input.toJson())).dataMap,
      );

  @override
  Future<Competition> update(String id, Map<String, Object?> fields) async =>
      Competition.fromJson(
        (await _api.patch('competitions/$id', body: fields)).dataMap,
      );

  @override
  Future<void> deleteDraft(String id) => _api.delete('competitions/$id');

  @override
  Future<Competition> publish(String id) async => Competition.fromJson(
    (await _api.post('competitions/$id/publish', body: const {})).dataMap,
  );

  @override
  Future<Competition> cancel(
    String id, {
    required String closeReasonId,
    String? note,
  }) async => Competition.fromJson(
    (await _api.post(
      'competitions/$id/cancel',
      body: {'close_reason_id': closeReasonId, 'note': note},
    )).dataMap,
  );

  @override
  Future<List<Suggestion>> suggestions(
    String id, {
    String? query,
    int limit = 20,
  }) async {
    final search = query?.trim();
    final response = await _api.get(
      'competitions/$id/suggestions',
      query: {
        if (search != null && search.isNotEmpty) 'q': search,
        'limit': limit,
      },
    );
    return response.dataList.map(Suggestion.fromJson).toList();
  }

  @override
  Future<Sponsorship> sponsorship(String id) async => Sponsorship.fromJson(
    (await _api.get('competitions/$id/sponsorship')).dataMap,
  );

  @override
  Future<Award?> issuerAward(String id) async {
    final data = (await _api.get('competitions/$id/award')).data;
    return data is Map<String, dynamic> ? Award.fromJson(data) : null;
  }

  @override
  Future<ParticipantAwardView?> participantAward(String id) async {
    final data = (await _api.get('competitions/$id/award')).data;
    return data is Map<String, dynamic>
        ? ParticipantAwardView.fromJson(data)
        : null;
  }
}
