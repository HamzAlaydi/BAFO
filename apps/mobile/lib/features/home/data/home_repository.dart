import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/features/home/domain/home_models.dart';

/// The dashboard home (`GET /home`, API.md §1.4).
abstract interface class HomeRepository {
  Future<Home> home();
}

final class ApiHomeRepository implements HomeRepository {
  ApiHomeRepository(this._api);

  final ApiClient _api;

  @override
  Future<Home> home() async => Home.fromJson((await _api.get('home')).dataMap);
}
