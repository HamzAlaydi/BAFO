import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';

/// The signed-in user (API.md §1.3 "Current user").
abstract interface class AccountRepository {
  /// `GET /me`.
  Future<Me> me();

  /// `PATCH /me`: only the given fields change.
  Future<Me> updateMe({String? name, String? phone, String? locale});

  /// `PUT /me/password` (204). Revokes every other token.
  /// Error: `password_incorrect` (422).
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });

  /// `POST /me/avatar` (png, jpg, jpeg, webp; ≤ 2 MB).
  Future<Me> uploadAvatar(String filePath);

  /// `DELETE /me/avatar`.
  Future<Me> deleteAvatar();
}

final class ApiAccountRepository implements AccountRepository {
  ApiAccountRepository(this._api);

  final ApiClient _api;

  @override
  Future<Me> me() async => Me.fromJson((await _api.get('me')).dataMap);

  @override
  Future<Me> updateMe({String? name, String? phone, String? locale}) async {
    final response = await _api.patch(
      'me',
      body: {
        'name': ?name,
        'phone': ?phone,
        'locale': ?locale,
      },
    );
    return Me.fromJson(response.dataMap);
  }

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) => _api.put(
    'me/password',
    body: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    },
  );

  @override
  Future<Me> uploadAvatar(String filePath) async =>
      Me.fromJson((await _api.upload('me/avatar', filePath: filePath)).dataMap);

  @override
  Future<Me> deleteAvatar() async =>
      Me.fromJson((await _api.delete('me/avatar')).dataMap);
}

/// The caller's organisation (API.md §1.3). Writes need
/// `organization.update`.
abstract interface class OrganizationRepository {
  /// `GET /organization`.
  Future<Organization> organization();

  /// `PATCH /organization`.
  Future<Organization> update(OrganizationUpdate update);

  /// `POST /organization/logo` (images ≤ 2 MB).
  Future<Organization> uploadLogo(String filePath);

  /// `DELETE /organization/logo`.
  Future<Organization> deleteLogo();
}

final class ApiOrganizationRepository implements OrganizationRepository {
  ApiOrganizationRepository(this._api);

  final ApiClient _api;

  @override
  Future<Organization> organization() async =>
      Organization.fromJson((await _api.get('organization')).dataMap);

  @override
  Future<Organization> update(OrganizationUpdate update) async =>
      Organization.fromJson(
        (await _api.patch('organization', body: update.toJson())).dataMap,
      );

  @override
  Future<Organization> uploadLogo(String filePath) async =>
      Organization.fromJson(
        (await _api.upload('organization/logo', filePath: filePath)).dataMap,
      );

  @override
  Future<Organization> deleteLogo() async =>
      Organization.fromJson((await _api.delete('organization/logo')).dataMap);
}

/// Account deletion (API.md §1.3, a store requirement).
abstract interface class AccountDeletionRepository {
  /// `GET /account/deletion`: the pending request, or null.
  Future<AccountDeletionRequest?> current();

  /// `POST /account/deletion`. Errors: `password_incorrect`,
  /// `account_deletion_blocked` (`details.blockers`, see
  /// [DeletionBlocker.fromDetails]), `account_deletion_pending`.
  Future<AccountDeletionRequest> request({
    required String password,
    String? reason,
  });

  /// `DELETE /account/deletion`: cancels the pending request.
  Future<void> cancel();
}

final class ApiAccountDeletionRepository implements AccountDeletionRepository {
  ApiAccountDeletionRepository(this._api);

  final ApiClient _api;

  @override
  Future<AccountDeletionRequest?> current() async {
    final data = (await _api.get('account/deletion')).data;
    return data is Map<String, dynamic>
        ? AccountDeletionRequest.fromJson(data)
        : null;
  }

  @override
  Future<AccountDeletionRequest> request({
    required String password,
    String? reason,
  }) async {
    final response = await _api.post(
      'account/deletion',
      body: {'password': password, 'reason': reason},
    );
    return AccountDeletionRequest.fromJson(response.dataMap);
  }

  @override
  Future<void> cancel() => _api.delete('account/deletion');
}
