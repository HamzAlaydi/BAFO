import 'package:bafo/core/api/api_client.dart';

/// `GET /time` (API.md §1.1): a clock sample for the [ServerClock]
/// (ARCHITECTURE.md §9.6). The response goes through the API client's
/// server-time interceptor, which records the round trip and the offset;
/// callers only trigger it (when a live screen opens, every 60 s while a
/// countdown is on screen, and on resume).
abstract interface class ClockSyncRepository {
  Future<void> sync();
}

final class ApiClockSyncRepository implements ClockSyncRepository {
  ApiClockSyncRepository(this._api);

  final ApiClient _api;

  @override
  Future<void> sync() => _api.get('time');
}
