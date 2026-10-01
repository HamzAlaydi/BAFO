import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/login_cubit.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class ForgotPasswordState extends Equatable {
  const ForgotPasswordState();

  @override
  List<Object?> get props => [];
}

final class ForgotPasswordInitial extends ForgotPasswordState {
  const ForgotPasswordInitial();
}

final class ForgotPasswordSubmitting extends ForgotPasswordState {
  const ForgotPasswordSubmitting();
}

/// Always reached for a well-formed e-mail: the API never says whether the
/// account exists (202). Continue to the reset screen.
final class ForgotPasswordSent extends ForgotPasswordState {
  const ForgotPasswordSent(this.email);

  final String email;

  @override
  List<Object?> get props => [email];
}

final class ForgotPasswordFailure extends ForgotPasswordState {
  const ForgotPasswordFailure(this.error, {this.retryAt});

  final ApiException error;
  final DateTime? retryAt;

  @override
  List<Object?> get props => [error, retryAt];
}

/// M11: `POST /auth/password/forgot`.
class ForgotPasswordCubit extends Cubit<ForgotPasswordState> {
  ForgotPasswordCubit(this._auth, {DateTime Function()? now})
    : _now = now ?? DateTime.now,
      super(const ForgotPasswordInitial());

  final AuthRepository _auth;
  final DateTime Function() _now;

  Future<void> submit(String email) async {
    if (state is ForgotPasswordSubmitting) return;
    final address = email.trim();
    emit(const ForgotPasswordSubmitting());
    try {
      await _auth.forgotPassword(address);
      emit(ForgotPasswordSent(address));
    } on ApiException catch (error) {
      emit(ForgotPasswordFailure(error, retryAt: retryAtFor(error, _now())));
    }
  }
}

enum ResetStatus { editing, checking, submitting, done }

final class ResetPasswordState extends Equatable {
  const ResetPasswordState({
    this.status = ResetStatus.editing,
    this.codeValid = false,
    this.codeError,
    this.fieldErrors = const {},
    this.error,
    this.resendAvailableAt,
    this.resent = false,
  });

  final ResetStatus status;

  /// The early `otp/check` accepted the code.
  final bool codeValid;

  /// `otp_invalid`, `otp_expired`, `otp_too_many_attempts` for the code.
  final ApiException? codeError;

  /// Server validation messages (`password`, …).
  final Map<String, List<String>> fieldErrors;

  /// Other failures (network, 429, …).
  final ApiException? error;
  final DateTime? resendAvailableAt;
  final bool resent;

  bool get isBusy =>
      status == ResetStatus.checking || status == ResetStatus.submitting;

  String? fieldError(String path) {
    final messages = fieldErrors[path];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  ResetPasswordState copyWith({
    ResetStatus? status,
    bool? codeValid,
    ApiException? Function()? codeError,
    Map<String, List<String>>? fieldErrors,
    ApiException? Function()? error,
    DateTime? Function()? resendAvailableAt,
    bool? resent,
  }) => ResetPasswordState(
    status: status ?? this.status,
    codeValid: codeValid ?? this.codeValid,
    codeError: codeError == null ? this.codeError : codeError(),
    fieldErrors: fieldErrors ?? this.fieldErrors,
    error: error == null ? this.error : error(),
    resendAvailableAt: resendAvailableAt == null
        ? this.resendAvailableAt
        : resendAvailableAt(),
    resent: resent ?? false,
  );

  @override
  List<Object?> get props => [
    status,
    codeValid,
    codeError,
    fieldErrors,
    error,
    resendAvailableAt,
    resent,
  ];
}

/// M12: check the code early (`otp/check`), then `password/reset`, with a
/// resend (`otp/send`, purpose `password_reset`). Success → sign in (M06).
class ResetPasswordCubit extends Cubit<ResetPasswordState> {
  ResetPasswordCubit({
    required this._auth,
    required this.email,
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now,
       super(
         ResetPasswordState(
           resendAvailableAt: (now ?? DateTime.now)().add(
             const Duration(seconds: 60),
           ),
         ),
       );

  final AuthRepository _auth;
  final DateTime Function() _now;
  final String email;

  static const Set<String> _codeErrors = {
    'otp_invalid',
    'otp_expired',
    'otp_too_many_attempts',
  };

  Future<void> checkCode(String code) async {
    if (state.isBusy) return;
    emit(state.copyWith(status: ResetStatus.checking, codeError: () => null));
    try {
      await _auth.checkResetCode(email: email, code: code);
      emit(state.copyWith(status: ResetStatus.editing, codeValid: true));
    } on ApiException catch (error) {
      emit(_failed(error));
    }
  }

  Future<void> submit({
    required String code,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (state.isBusy) return;
    emit(
      state.copyWith(
        status: ResetStatus.submitting,
        error: () => null,
        codeError: () => null,
        fieldErrors: const {},
      ),
    );
    try {
      await _auth.resetPassword(
        email: email,
        code: code,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      emit(state.copyWith(status: ResetStatus.done));
    } on ApiException catch (error) {
      emit(_failed(error));
    }
  }

  Future<void> resend() async {
    if (state.isBusy) return;
    final available = state.resendAvailableAt;
    if (available != null && _now().isBefore(available)) return;
    try {
      await _auth.sendOtp(email: email, purpose: OtpPurpose.passwordReset);
      emit(
        state.copyWith(
          codeValid: false,
          codeError: () => null,
          resendAvailableAt: () => _now().add(const Duration(seconds: 60)),
          resent: true,
        ),
      );
    } on ApiException catch (error) {
      emit(
        state.copyWith(
          error: () => error,
          resendAvailableAt: () =>
              retryAtFor(error, _now()) ?? state.resendAvailableAt,
        ),
      );
    }
  }

  /// The user changed the code: forget the previous verdict.
  void codeEdited() {
    if (state.codeValid || state.codeError != null) {
      emit(state.copyWith(codeValid: false, codeError: () => null));
    }
  }

  ResetPasswordState _failed(ApiException error) {
    if (_codeErrors.contains(error.code)) {
      return state.copyWith(
        status: ResetStatus.editing,
        codeValid: false,
        codeError: () => error,
      );
    }
    return state.copyWith(
      status: ResetStatus.editing,
      fieldErrors: error.fieldErrors,
      error: () => error.fieldErrors.isEmpty ? error : null,
    );
  }
}
