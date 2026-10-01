import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/idempotency.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/features/live/domain/live_models.dart';

/// Bidding endpoints (API.md §1.6). Every method throws `ApiException`.
///
/// Snapshots returned here must go through the competition's
/// `CompetitionChannel.acceptSnapshot` (the `v` guard) before they are
/// applied; the raw JSON is exposed for that.
abstract interface class LiveRepository {
  /// `GET /competitions/{id}/live` as a participant.
  Future<(ParticipantLiveSnapshot, Json)> participantSnapshot(
    String competitionId,
  );

  /// `GET /competitions/{id}/live` as the issuer.
  Future<(IssuerLiveSnapshot, Json)> issuerSnapshot(String competitionId);

  /// `POST /competitions/{id}/live/heartbeat` (every 20 s while the live
  /// screen is visible).
  Future<void> heartbeat(String competitionId);

  /// `POST /competitions/{id}/offers` with [idempotencyKey] (one per intent,
  /// reused on retries; see `IdempotencyKey`). [confirmOutlier] re-sends
  /// after `offer_outlier_confirm_required`, with a **new** key.
  Future<OfferSubmission> submitOffer(
    String competitionId, {
    required int amountMinor,
    required String idempotencyKey,
    bool confirmOutlier = false,
  });

  /// `GET /competitions/{id}/my-offers`, newest first.
  Future<List<MyOffer>> myOffers(String competitionId);

  /// Issuer `GET /competitions/{id}/offers`, ordered by rank.
  Future<List<ParticipantStandingRow>> standings(String competitionId);

  /// Issuer `GET /competitions/{id}/offers/log?after_seq=&limit=`.
  Future<OfferLogPage> offersLog(
    String competitionId, {
    int afterSeq = 0,
    int limit = 200,
  });
}

final class ApiLiveRepository implements LiveRepository {
  ApiLiveRepository(this._api);

  final ApiClient _api;

  @override
  Future<(ParticipantLiveSnapshot, Json)> participantSnapshot(
    String competitionId,
  ) async {
    final json = (await _api.get('competitions/$competitionId/live')).dataMap;
    return (ParticipantLiveSnapshot.fromJson(json), json);
  }

  @override
  Future<(IssuerLiveSnapshot, Json)> issuerSnapshot(
    String competitionId,
  ) async {
    final json = (await _api.get('competitions/$competitionId/live')).dataMap;
    return (IssuerLiveSnapshot.fromJson(json), json);
  }

  @override
  Future<void> heartbeat(String competitionId) =>
      _api.post('competitions/$competitionId/live/heartbeat');

  @override
  Future<OfferSubmission> submitOffer(
    String competitionId, {
    required int amountMinor,
    required String idempotencyKey,
    bool confirmOutlier = false,
  }) async {
    final response = await _api.post(
      'competitions/$competitionId/offers',
      headers: {IdempotencyKey.header: idempotencyKey},
      body: {'amount_minor': amountMinor, 'confirm_outlier': confirmOutlier},
    );
    final data = response.dataMap;
    final live = data.obj('live');
    return OfferSubmission(
      offer: MyOffer.fromJson(data.obj('offer')),
      live: ParticipantLiveSnapshot.fromJson(live),
      liveJson: live,
      replayed:
          response.header(IdempotencyKey.replayedHeader)?.toLowerCase() ==
          'true',
    );
  }

  @override
  Future<List<MyOffer>> myOffers(String competitionId) async =>
      (await _api.get('competitions/$competitionId/my-offers')).dataList
          .map(MyOffer.fromJson)
          .toList();

  @override
  Future<List<ParticipantStandingRow>> standings(String competitionId) async =>
      (await _api.get('competitions/$competitionId/offers')).dataList
          .map(ParticipantStandingRow.fromJson)
          .toList();

  @override
  Future<OfferLogPage> offersLog(
    String competitionId, {
    int afterSeq = 0,
    int limit = 200,
  }) async {
    final response = await _api.get(
      'competitions/$competitionId/offers/log',
      query: {'after_seq': afterSeq, 'limit': limit},
    );
    final entries = response.dataList.map(OfferLogEntry.fromJson).toList();
    final lastSeq = response.meta['last_seq'];
    final hasMore = response.meta['has_more'];
    return OfferLogPage(
      entries: entries,
      lastSeq: lastSeq is int
          ? lastSeq
          : (entries.isEmpty ? afterSeq : entries.last.seq),
      hasMore: hasMore is bool && hasMore,
    );
  }
}
