import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class LoginState extends Equatable {
  const LoginState();

  bool get isSubmitting => this is LoginSubmitting;

  /// Server validation message for an API field (`email`, `password`).
  String? fieldError(String field) => switch (this) {
    LoginFailure(:final error) => error.fieldError(field),
    _ => null,
  };

  /// An error that belongs to no field (bad credentials, network, server).
  ApiException? get generalError => switch (this) {
    LoginFailure(:final error) when error.fieldErrors.isEmpty => error,
    _ => null,
  };

  @override
  List<Object?> get props => [];
}

final class LoginInitial extends LoginState {
  const LoginInitial();
}

final class LoginSubmitting extends LoginState {
  const LoginSubmitting();
}

final class LoginSuccess extends LoginState {
  const LoginSuccess();
}

/// 403 `email_not_verified`: the API sent a new code; continue on the
/// verification screen (M10).
final class LoginNeedsVerification extends LoginState {
  const LoginNeedsVerification({required this.email, this.otpExpiresAt});

  final String email;
  final DateTime? otpExpiresAt;

  @override
  List<Object?> get props => [email, otpExpiresAt];
}

final class LoginFailure extends LoginState {
  const LoginFailure(this.error, {this.retryAt});

  final ApiException error;

  /// After a 429: the submit stays disabled until then (device time).
  final DateTime? retryAt;

  @override
  List<Object?> get props => [error, retryAt];
}

/// Sign in (M06). Navigation after success is the router's job: the session
/// turns authenticated and the auth redirect moves on.
class LoginCubit extends Cubit<LoginState> {
  LoginCubit({
    required this._auth,
    required this._session,
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now,
       super(const LoginInitial());

  final AuthRepository _auth;
  final SessionCubit _session;
  final DateTime Function() _now;

  Future<void> submit({required String email, required String password}) async {
    if (state.isSubmitting) return;
    final address = email.trim();
    emit(const LoginSubmitting());
    try {
      final payload = await _auth.login(email: address, password: password);
      await _session.signIn(payload);
      emit(const LoginSuccess());
    } on ApiException catch (error) {
      if (error.code == 'email_not_verified') {
        final expires = error.details['otp_expires_at'];
        emit(
          LoginNeedsVerification(
            email: address,
            otpExpiresAt: expires is String ? DateTime.tryParse(expires)?.toUtc() : null,
          ),
        );
        return;
      }
      emit(LoginFailure(error, retryAt: retryAtFor(error, _now())));
    }
  }

  /// Drops stale server errors once the user edits a field.
  void edited() {
    final current = state;
    if (current is LoginFailure && current.retryAt == null) {
      emit(const LoginInitial());
    }
  }
}

/// When a rate-limited action may run again: `details.retry_after_seconds`
/// of a 429 (`too_many_requests`, `otp_resend_cooldown`), else null.
DateTime? retryAtFor(ApiException error, DateTime now) {
  if (error.statusCode != 429) return null;
  final seconds = error.details['retry_after_seconds'];
  return now.add(Duration(seconds: seconds is int && seconds > 0 ? seconds : 60));
}
