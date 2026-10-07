import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum RegisterStatus { editing, submitting, succeeded }

/// The three-step registration (M07 account, M08 company, M09 address and
/// consent). One state object: the step, the lookups for the pickers, and
/// the server's field errors, which stay attached to their fields until the
/// user edits them.
final class RegisterState extends Equatable {
  const RegisterState({
    this.step = 0,
    this.status = RegisterStatus.editing,
    this.lookups,
    this.lookupsError,
    this.fieldErrors = const {},
    this.error,
    this.result,
    this.retryAt,
  });

  static const int stepCount = 3;

  /// 0: account, 1: company, 2: address and consent.
  final int step;
  final RegisterStatus status;

  /// Regions and categories for step 2 and 3 (null while loading).
  final Lookups? lookups;
  final ApiException? lookupsError;

  /// Server validation messages by field path (`organization.cr_number`).
  final Map<String, List<String>> fieldErrors;

  /// A failure that belongs to no field.
  final ApiException? error;
  final RegistrationResult? result;

  /// After a 429 the submit waits until then.
  final DateTime? retryAt;

  bool get isSubmitting => status == RegisterStatus.submitting;

  String? fieldError(String path) {
    final messages = fieldErrors[path];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  RegisterState copyWith({
    int? step,
    RegisterStatus? status,
    Lookups? lookups,
    ApiException? Function()? lookupsError,
    Map<String, List<String>>? fieldErrors,
    ApiException? Function()? error,
    RegistrationResult? result,
    DateTime? Function()? retryAt,
  }) => RegisterState(
    step: step ?? this.step,
    status: status ?? this.status,
    lookups: lookups ?? this.lookups,
    lookupsError: lookupsError == null ? this.lookupsError : lookupsError(),
    fieldErrors: fieldErrors ?? this.fieldErrors,
    error: error == null ? this.error : error(),
    result: result ?? this.result,
    retryAt: retryAt == null ? this.retryAt : retryAt(),
  );

  @override
  List<Object?> get props => [
    step,
    status,
    lookups,
    lookupsError,
    fieldErrors,
    error,
    result,
    retryAt,
  ];
}

class RegisterCubit extends Cubit<RegisterState> {
  RegisterCubit({
    required this._auth,
    required this._lookups,
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now,
       super(const RegisterState());

  final AuthRepository _auth;
  final LookupsRepository _lookups;
  final DateTime Function() _now;

  /// Loads regions and categories (`GET /lookups`, cached per language).
  Future<void> loadLookups({bool refresh = false}) async {
    if (state.lookupsError != null) {
      emit(state.copyWith(lookupsError: () => null));
    }
    try {
      final lookups = await _lookups.lookups(refresh: refresh);
      emit(state.copyWith(lookups: lookups));
    } on ApiException catch (error) {
      emit(state.copyWith(lookupsError: () => error));
    }
  }

  void next() {
    if (state.step < RegisterState.stepCount - 1) {
      emit(state.copyWith(step: state.step + 1, error: () => null));
    }
  }

  void back() {
    if (state.step > 0) emit(state.copyWith(step: state.step - 1));
  }

  /// The error summary (FQ8): open the step that holds a field.
  void goTo(int step) {
    if (step < 0 || step >= RegisterState.stepCount || step == state.step) {
      return;
    }
    emit(state.copyWith(step: step, error: () => null));
  }

  /// Forgets the server error of [path] once the user edits that field.
  void fieldEdited(String path) {
    if (!state.fieldErrors.containsKey(path)) return;
    emit(state.copyWith(fieldErrors: {...state.fieldErrors}..remove(path)));
  }

  Future<void> submit(RegistrationRequest request) async {
    if (state.isSubmitting) return;
    emit(
      state.copyWith(
        status: RegisterStatus.submitting,
        error: () => null,
        fieldErrors: const {},
      ),
    );
    try {
      final result = await _auth.register(request);
      emit(state.copyWith(status: RegisterStatus.succeeded, result: result));
    } on ApiException catch (error) {
      final fieldErrors = {
        ...error.fieldErrors,
        // Registering with an invitation for another address (422).
        if (error.code == 'invitation_email_mismatch' &&
            error.message.isNotEmpty)
          'email': [error.message],
      };
      emit(
        state.copyWith(
          status: RegisterStatus.editing,
          fieldErrors: fieldErrors,
          step: fieldErrors.isEmpty
              ? state.step
              : stepOfFields(fieldErrors.keys),
          error: () => fieldErrors.isEmpty ? error : null,
          retryAt: () => error.statusCode == 429
              ? _now().add(
                  Duration(
                    seconds: switch (error.details['retry_after_seconds']) {
                      final int seconds when seconds > 0 => seconds,
                      _ => 60,
                    },
                  ),
                )
              : null,
        ),
      );
    }
  }

  /// The first step holding one of [paths] (server errors "jump back to
  /// their step", M09).
  static int stepOfFields(Iterable<String> paths) {
    var step = RegisterState.stepCount - 1;
    for (final path in paths) {
      final candidate = stepOf(path);
      if (candidate < step) step = candidate;
    }
    return step;
  }

  static int stepOf(String path) {
    if (!path.startsWith('organization.')) {
      return const {
            'name',
            'email',
            'phone',
            'password',
            'password_confirmation',
            'locale',
          }.contains(path)
          ? 0
          : 2;
    }
    final field = path.substring('organization.'.length);
    if (field.startsWith('national_address') ||
        field.startsWith('category_ids') ||
        field == 'visible_in_suggestions') {
      return 2;
    }
    return 1;
  }
}
