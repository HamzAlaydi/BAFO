import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/account/presentation/action_outcome.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum DeletionBusy { none, requesting, cancelling }

enum DeletionResult { requested, cancelled }

sealed class AccountDeletionState extends Equatable {
  const AccountDeletionState();

  @override
  List<Object?> get props => [];
}

final class AccountDeletionLoading extends AccountDeletionState {
  const AccountDeletionLoading();
}

final class AccountDeletionFailure extends AccountDeletionState {
  const AccountDeletionFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class AccountDeletionLoaded extends AccountDeletionState {
  const AccountDeletionLoaded({
    this.pending,
    this.busy = DeletionBusy.none,
    this.passwordError,
    this.blockers = const [],
    this.error,
    this.outcome,
  });

  /// The pending request, or null when there is none.
  final AccountDeletionRequest? pending;
  final DeletionBusy busy;

  /// `password_incorrect`, or a validation error on `password`.
  final ApiException? passwordError;

  /// `account_deletion_blocked` (`details.blockers`): open competitions and
  /// participations of the organisation.
  final List<DeletionBlocker> blockers;

  /// Other failures (network, …).
  final ApiException? error;
  final ActionOutcome<DeletionResult>? outcome;

  bool get isBusy => busy != DeletionBusy.none;

  @override
  List<Object?> get props => [
    pending,
    busy,
    passwordError,
    blockers,
    error,
    outcome,
  ];
}

/// M61 (a store requirement): `GET /account/deletion`, then request it with
/// the password (`POST`, 14 days; an owner deletes the organisation, others
/// their own user) or cancel the pending one (`DELETE`).
class AccountDeletionCubit extends Cubit<AccountDeletionState> with OutcomeIds {
  AccountDeletionCubit(this._deletion) : super(const AccountDeletionLoading());

  final AccountDeletionRepository _deletion;

  Future<void> load() async {
    if (state is! AccountDeletionLoading) emit(const AccountDeletionLoading());
    try {
      emit(AccountDeletionLoaded(pending: await _deletion.current()));
    } on ApiException catch (error) {
      emit(AccountDeletionFailure(error));
    }
  }

  Future<void> request({required String password, String? reason}) async {
    final current = state;
    if (current is! AccountDeletionLoaded || current.isBusy) return;
    emit(const AccountDeletionLoaded(busy: DeletionBusy.requesting));
    final trimmed = reason?.trim();
    try {
      final pending = await _deletion.request(
        password: password,
        reason: trimmed == null || trimmed.isEmpty ? null : trimmed,
      );
      emit(
        AccountDeletionLoaded(
          pending: pending,
          outcome: outcome(DeletionResult.requested),
        ),
      );
    } on ApiException catch (error) {
      switch (error.code) {
        case 'account_deletion_pending':
          // Someone else (another device) asked already: show that request.
          await load();
        case 'account_deletion_blocked':
          emit(
            AccountDeletionLoaded(
              blockers: DeletionBlocker.fromDetails(error.details),
            ),
          );
        case 'password_incorrect':
          emit(AccountDeletionLoaded(passwordError: error));
        default:
          emit(
            error.fieldError('password') != null
                ? AccountDeletionLoaded(passwordError: error)
                : AccountDeletionLoaded(error: error),
          );
      }
    }
  }

  Future<void> cancel() async {
    final current = state;
    if (current is! AccountDeletionLoaded || current.isBusy) return;
    emit(
      AccountDeletionLoaded(
        pending: current.pending,
        busy: DeletionBusy.cancelling,
      ),
    );
    try {
      await _deletion.cancel();
      emit(AccountDeletionLoaded(outcome: outcome(DeletionResult.cancelled)));
    } on ApiException catch (error) {
      emit(AccountDeletionLoaded(pending: current.pending, error: error));
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(AccountDeletionState state) {
    if (!isClosed) super.emit(state);
  }
}
