import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

// ── Publish (M43) ───────────────────────────────────────────────────────

sealed class PublishState extends Equatable {
  const PublishState();

  @override
  List<Object?> get props => [];
}

final class PublishIdle extends PublishState {
  const PublishIdle();
}

final class PublishInProgress extends PublishState {
  const PublishInProgress();
}

/// Published: `scheduled` or `live`, as the server answered.
final class PublishSucceeded extends PublishState {
  const PublishSucceeded(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

/// `validation_failed`, `min_participants_not_met` (`details.required`,
/// `details.current`), `issuer_plan_required`, `live_event_capacity_reached`,
/// `sponsorship_payment_required` (paid on the web), or
/// `invalid_state_transition` (already published: refetch).
final class PublishFailed extends PublishState {
  const PublishFailed(this.error);

  final ApiException error;

  /// `min_participants_not_met`: how many more invitations are needed.
  int? get missingInvitations {
    if (error.code != 'min_participants_not_met') return null;
    final required = error.details['required'];
    final current = error.details['current'];
    if (required is! int || current is! int) return null;
    final missing = required - current;
    return missing > 0 ? missing : null;
  }

  @override
  List<Object?> get props => [error];
}

/// M43: `POST /competitions/{id}/publish`. The UI waits for the answer; it
/// never shows the competition as published before the server says so
/// (CD9). Mobile never starts a payment: `sponsorship_payment_required`
/// becomes the web notice (SCREENS.md §3.2).
class PublishCubit extends Cubit<PublishState> {
  PublishCubit({required this._competitions, required this.competitionId})
    : super(const PublishIdle());

  final CompetitionsRepository _competitions;
  final String competitionId;

  Future<void> publish() async {
    if (state is PublishInProgress) return;
    emit(const PublishInProgress());
    try {
      final competition = await _competitions.publish(competitionId);
      if (!isClosed) emit(PublishSucceeded(competition));
    } on ApiException catch (error) {
      if (!isClosed) emit(PublishFailed(error));
    }
  }
}

// ── Cancel (M44) ────────────────────────────────────────────────────────

sealed class CancelCompetitionState extends Equatable {
  const CancelCompetitionState();

  @override
  List<Object?> get props => [];
}

final class CancelCompetitionInitial extends CancelCompetitionState {
  const CancelCompetitionInitial();
}

final class CancelCompetitionLoading extends CancelCompetitionState {
  const CancelCompetitionLoading();
}

/// The `cancel` close reasons could not be loaded.
final class CancelCompetitionReasonsFailure extends CancelCompetitionState {
  const CancelCompetitionReasonsFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class CancelCompetitionReady extends CancelCompetitionState {
  const CancelCompetitionReady({
    required this.reasons,
    this.submitting = false,
    this.error,
  });

  final List<CloseReason> reasons;
  final bool submitting;
  final ApiException? error;

  @override
  List<Object?> get props => [reasons, submitting, error];
}

final class CancelCompetitionDone extends CancelCompetitionState {
  const CancelCompetitionDone(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

/// M44 (CD12): cancel from the detail action sheet in `scheduled`, `live`
/// or `bafo_round`, with a `cancel` close reason and the note the reason
/// requires.
class CancelCompetitionCubit extends Cubit<CancelCompetitionState> {
  CancelCompetitionCubit({
    required this._competitions,
    required this._lookups,
    required this.competitionId,
  }) : super(const CancelCompetitionInitial());

  final CompetitionsRepository _competitions;
  final LookupsRepository _lookups;
  final String competitionId;

  Future<void> loadReasons() async {
    emit(const CancelCompetitionLoading());
    try {
      final reasons = await _lookups.closeReasons(CloseReasonKind.cancel);
      if (!isClosed) emit(CancelCompetitionReady(reasons: reasons));
    } on ApiException catch (error) {
      if (!isClosed) emit(CancelCompetitionReasonsFailure(error));
    }
  }

  Future<void> cancel({required CloseReason reason, String? note}) async {
    final current = state;
    if (current is! CancelCompetitionReady || current.submitting) return;
    emit(CancelCompetitionReady(reasons: current.reasons, submitting: true));
    try {
      final competition = await _competitions.cancel(
        competitionId,
        closeReasonId: reason.id,
        note: note,
      );
      if (!isClosed) emit(CancelCompetitionDone(competition));
    } on ApiException catch (error) {
      if (!isClosed) {
        emit(CancelCompetitionReady(reasons: current.reasons, error: error));
      }
    }
  }
}
