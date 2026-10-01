import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/login_cubit.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum OtpStatus { idle, verifying, resending, verified }

/// E-mail verification (M10). Also reused as the resend half of the reset
/// screen through [purpose].
final class OtpState extends Equatable {
  const OtpState({
    this.status = OtpStatus.idle,
    this.expiresAt,
    this.resendAvailableAt,
    this.error,
    this.resent = false,
  });

  final OtpStatus status;

  /// Server time the current code stops working (`otp_expires_at`).
  final DateTime? expiresAt;

  /// Device time the resend button unlocks (60 s cooldown, or the server's
  /// `retry_after_seconds`).
  final DateTime? resendAvailableAt;

  /// The last failure (`otp_invalid`, `otp_expired`, …).
  final ApiException? error;

  /// A new code was just sent (toast once).
  final bool resent;

  bool get isBusy =>
      status == OtpStatus.verifying || status == OtpStatus.resending;

  OtpState copyWith({
    OtpStatus? status,
    DateTime? Function()? expiresAt,
    DateTime? Function()? resendAvailableAt,
    ApiException? Function()? error,
    bool? resent,
  }) => OtpState(
    status: status ?? this.status,
    expiresAt: expiresAt == null ? this.expiresAt : expiresAt(),
    resendAvailableAt: resendAvailableAt == null
        ? this.resendAvailableAt
        : resendAvailableAt(),
    error: error == null ? this.error : error(),
    resent: resent ?? false,
  );

  @override
  List<Object?> get props => [status, expiresAt, resendAvailableAt, error, resent];
}

class OtpCubit extends Cubit<OtpState> {
  OtpCubit({
    required this._auth,
    required this._session,
    required this.email,
    DateTime? expiresAt,
    bool codeJustSent = true,
    this.purpose = OtpPurpose.emailVerification,
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now,
       super(
         OtpState(
           expiresAt: expiresAt,
           resendAvailableAt: codeJustSent
               ? (now ?? DateTime.now)().add(resendCooldown)
               : null,
         ),
       );

  /// ARCHITECTURE.md §13.9: 60 s between sends.
  static const Duration resendCooldown = Duration(seconds: 60);

  final AuthRepository _auth;
  final SessionCubit _session;
  final DateTime Function() _now;
  final String email;
  final OtpPurpose purpose;

  /// `POST /auth/otp/verify` → signed in (the router moves to home).
  Future<void> verify(String code) async {
    if (state.isBusy || state.status == OtpStatus.verified) return;
    emit(state.copyWith(status: OtpStatus.verifying, error: () => null));
    try {
      final payload = await _auth.verifyEmail(email: email, code: code);
      await _session.signIn(payload);
      emit(state.copyWith(status: OtpStatus.verified));
    } on ApiException catch (error) {
      emit(state.copyWith(status: OtpStatus.idle, error: () => error));
    }
  }

  /// `POST /auth/otp/send` for [purpose].
  Future<void> resend() async {
    if (state.isBusy) return;
    final available = state.resendAvailableAt;
    if (available != null && _now().isBefore(available)) return;
    emit(state.copyWith(status: OtpStatus.resending, error: () => null));
    try {
      final sent = await _auth.sendOtp(email: email, purpose: purpose);
      emit(
        state.copyWith(
          status: OtpStatus.idle,
          expiresAt: () => sent.expiresAt ?? state.expiresAt,
          resendAvailableAt: () => _now().add(resendCooldown),
          resent: true,
        ),
      );
    } on ApiException catch (error) {
      emit(
        state.copyWith(
          status: OtpStatus.idle,
          error: () => error,
          resendAvailableAt: () => retryAtFor(error, _now()) ?? state.resendAvailableAt,
        ),
      );
    }
  }

  void edited() {
    if (state.error != null) emit(state.copyWith(error: () => null));
  }
}
