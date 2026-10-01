import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class ChangePasswordState extends Equatable {
  const ChangePasswordState();

  @override
  List<Object?> get props => [];
}

final class ChangePasswordInitial extends ChangePasswordState {
  const ChangePasswordInitial();
}

final class ChangePasswordSubmitting extends ChangePasswordState {
  const ChangePasswordSubmitting();
}

/// `PUT /me/password` answered 204: the other devices are signed out.
final class ChangePasswordDone extends ChangePasswordState {
  const ChangePasswordDone();
}

final class ChangePasswordFailure extends ChangePasswordState {
  const ChangePasswordFailure(this.error);

  final ApiException error;

  /// `password_incorrect` belongs to the current-password field.
  bool get currentPasswordWrong => error.code == 'password_incorrect';

  String? fieldError(String path) => error.fieldError(path);

  /// Shown above the form: anything that is not a field error.
  bool get isFormLevel => !currentPasswordWrong && error.fieldErrors.isEmpty;

  @override
  List<Object?> get props => [error];
}

/// M54: `PUT /me/password`. Revokes every other token of the user.
class ChangePasswordCubit extends Cubit<ChangePasswordState> {
  ChangePasswordCubit(this._account) : super(const ChangePasswordInitial());

  final AccountRepository _account;

  Future<void> submit({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (state is ChangePasswordSubmitting || state is ChangePasswordDone) {
      return;
    }
    emit(const ChangePasswordSubmitting());
    try {
      await _account.changePassword(
        currentPassword: currentPassword,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      emit(const ChangePasswordDone());
    } on ApiException catch (error) {
      emit(ChangePasswordFailure(error));
    }
  }

  /// Clears a server error once the user edits a field.
  void edited() {
    if (state is ChangePasswordFailure) emit(const ChangePasswordInitial());
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(ChangePasswordState state) {
    if (!isClosed) super.emit(state);
  }
}
