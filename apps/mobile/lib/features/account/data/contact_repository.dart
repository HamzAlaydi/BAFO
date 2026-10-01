import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/features/account/domain/contact_message.dart';

/// The contact form (API.md §1.1 `POST /contact`, guest, 5 per hour per IP).
abstract interface class ContactRepository {
  /// Sends [message]; returns the id of the stored request. Errors:
  /// `validation_failed` (field paths `name`, `email`, …), `too_many_requests`.
  Future<String> send(ContactMessage message);
}

final class ApiContactRepository implements ContactRepository {
  ApiContactRepository(this._api);

  final ApiClient _api;

  @override
  Future<String> send(ContactMessage message) async {
    final response = await _api.post('contact', body: message.toJson());
    final data = response.data;
    // API.md: 201 `{"id": …}` (as `data`, or at the top level).
    final id = data is Map ? data['id'] : null;
    return id is String ? id : '';
  }
}
