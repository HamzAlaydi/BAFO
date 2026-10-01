import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';

/// Authentication against the first-party API (API.md §1.3, Sanctum personal
/// access tokens). Every method throws `ApiException` on failure.
abstract interface class AuthRepository {
  /// `POST /auth/login`. Errors: `invalid_credentials` (401),
  /// `email_not_verified` (403, `details.otp_expires_at`; a code was sent),
  /// `account_inactive`, `organization_suspended`.
  Future<AuthTokenPayload> login({
    required String email,
    required String password,
  });

  /// `POST /auth/register` → the e-mail to verify. No token yet.
  Future<RegistrationResult> register(RegistrationRequest request);

  /// `POST /auth/otp/send` (always 202; `otp_resend_cooldown` 429).
  Future<OtpSent> sendOtp({required String email, required OtpPurpose purpose});

  /// `POST /auth/otp/verify` (e-mail verification) → signed in.
  Future<AuthTokenPayload> verifyEmail({
    required String email,
    required String code,
  });

  /// `POST /auth/otp/check` (password reset, does not consume the code).
  /// Completes when the code is valid, throws `otp_invalid` / `otp_expired`
  /// otherwise.
  Future<void> checkResetCode({required String email, required String code});

  /// `POST /auth/password/forgot` (always 202, never reveals the account).
  Future<void> forgotPassword(String email);

  /// `POST /auth/password/reset` (204; every token of the user is revoked).
  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  });

  /// `POST /auth/logout`: revokes the current token.
  Future<void> logout();

  /// `POST /auth/team-invitations/lookup` (team invitations open on the web
  /// in the MVP, SCREENS.md §3.3).
  Future<TeamInvitationLookup> lookupTeamInvitation(String token);

  /// `POST /auth/team-invitations/accept` → signed in.
  Future<AuthTokenPayload> acceptTeamInvitation({
    required String token,
    required String password,
    required String passwordConfirmation,
  });
}

final class ApiAuthRepository implements AuthRepository {
  ApiAuthRepository(this._api, {required this._deviceName});

  final ApiClient _api;

  /// Stored with the token so users can recognise their sessions, e.g.
  /// "Pixel 8 · android" (≤ 120 chars).
  final String _deviceName;

  @override
  Future<AuthTokenPayload> login({
    required String email,
    required String password,
  }) async {
    final response = await _api.post(
      'auth/login',
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': _deviceName,
      },
    );
    return AuthTokenPayload.fromJson(response.dataMap);
  }

  @override
  Future<RegistrationResult> register(RegistrationRequest request) async {
    final response = await _api.post('auth/register', body: request.toJson());
    return RegistrationResult.fromJson(response.dataMap);
  }

  @override
  Future<OtpSent> sendOtp({
    required String email,
    required OtpPurpose purpose,
  }) async {
    final response = await _api.post(
      'auth/otp/send',
      body: {'email': email.trim(), 'purpose': purpose.wire},
    );
    return OtpSent.fromJson(response.dataMap);
  }

  @override
  Future<AuthTokenPayload> verifyEmail({
    required String email,
    required String code,
  }) async {
    final response = await _api.post(
      'auth/otp/verify',
      body: {
        'email': email.trim(),
        'code': code,
        'purpose': OtpPurpose.emailVerification.wire,
        'device_name': _deviceName,
      },
    );
    return AuthTokenPayload.fromJson(response.dataMap);
  }

  @override
  Future<void> checkResetCode({
    required String email,
    required String code,
  }) => _api.post(
    'auth/otp/check',
    body: {
      'email': email.trim(),
      'code': code,
      'purpose': OtpPurpose.passwordReset.wire,
    },
  );

  @override
  Future<void> forgotPassword(String email) =>
      _api.post('auth/password/forgot', body: {'email': email.trim()});

  @override
  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) => _api.post(
    'auth/password/reset',
    body: {
      'email': email.trim(),
      'code': code,
      'password': password,
      'password_confirmation': passwordConfirmation,
    },
  );

  @override
  Future<void> logout() => _api.post('auth/logout');

  @override
  Future<TeamInvitationLookup> lookupTeamInvitation(String token) async {
    final response = await _api.post(
      'auth/team-invitations/lookup',
      body: {'token': token},
    );
    return TeamInvitationLookup.fromJson(response.dataMap);
  }

  @override
  Future<AuthTokenPayload> acceptTeamInvitation({
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _api.post(
      'auth/team-invitations/accept',
      body: {
        'token': token,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'device_name': _deviceName,
        'accept_terms': true,
      },
    );
    return AuthTokenPayload.fromJson(response.dataMap);
  }
}
