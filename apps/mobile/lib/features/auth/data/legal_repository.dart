import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';

/// Published legal documents (`GET /legal/{code}`, API.md §1.1), in the
/// request language. 404 `not_found` when no version is published.
abstract interface class LegalRepository {
  Future<LegalDocument> document(LegalCode code);
}

final class ApiLegalRepository implements LegalRepository {
  ApiLegalRepository(this._api);

  final ApiClient _api;

  @override
  Future<LegalDocument> document(LegalCode code) async {
    final response = await _api.get('legal/${code.wire}');
    return LegalDocument.fromJson(response.dataMap);
  }
}
