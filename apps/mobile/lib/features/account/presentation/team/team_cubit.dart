import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class TeamState extends Equatable {
  const TeamState();

  @override
  List<Object?> get props => [];
}

final class TeamLoading extends TeamState {
  const TeamLoading();
}

/// S8 **F**: no `team.manage` (from `me.permissions`, or a 403).
final class TeamForbidden extends TeamState {
  const TeamForbidden();
}

final class TeamFailure extends TeamState {
  const TeamFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class TeamLoaded extends TeamState {
  const TeamLoaded(this.roster, {this.refreshError});

  final TeamRoster roster;

  /// A reload failed; the last roster stays on screen.
  final ApiException? refreshError;

  @override
  List<Object?> get props => [roster, refreshError];
}

/// M56: `GET /team/members` (≤ 50, not paginated) with `meta.seats`. Needs
/// `team.manage`; without it the screen is Forbidden and no request is sent.
class TeamCubit extends Cubit<TeamState> {
  TeamCubit({required this._team, required this._canManage})
    : super(const TeamLoading());

  final TeamRepository _team;
  final bool _canManage;

  Future<void> load() async {
    if (!_canManage) {
      emit(const TeamForbidden());
      return;
    }
    final current = state;
    if (current is TeamFailure || current is TeamForbidden) {
      emit(const TeamLoading());
    }
    try {
      emit(TeamLoaded(await _team.members()));
    } on ApiException catch (error) {
      if (error.statusCode == 403 || error.code == 'forbidden') {
        emit(const TeamForbidden());
      } else if (current is TeamLoaded) {
        emit(TeamLoaded(current.roster, refreshError: error));
      } else {
        emit(TeamFailure(error));
      }
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(TeamState state) {
    if (!isClosed) super.emit(state);
  }
}
