import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';

/// The issuer's result report (API.md §1.6 `GET …/report`, §2.9 `Report`).
///
/// The PDF itself is a private [CompetitionReport.file]: it downloads with
/// the bearer header through `FileDownloadService`, never through a URL
/// carrying a token.
abstract interface class IssuerReportRepository {
  /// `GET /competitions/{id}/report?locale=`:
  /// * 200 → `status: ready` with the file;
  /// * 202 → `status: pending` (poll every 3 s);
  /// * 409 `report_not_available` before the first close (`ApiException`).
  Future<CompetitionReport> report(
    String competitionId, {
    required String locale,
  });
}

final class ApiIssuerReportRepository implements IssuerReportRepository {
  ApiIssuerReportRepository(this._api);

  final ApiClient _api;

  @override
  Future<CompetitionReport> report(
    String competitionId, {
    required String locale,
  }) async => CompetitionReport.fromJson(
    (await _api.get(
      'competitions/$competitionId/report',
      query: {'locale': locale},
    )).dataMap,
  );
}
