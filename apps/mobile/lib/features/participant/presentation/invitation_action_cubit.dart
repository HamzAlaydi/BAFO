import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum InvitationAction { join, decline }

sealed class InvitationActionState extends Equatable {
  const InvitationActionState();

  @override
  List<Object?> get props => [];
}

final class InvitationActionIdle extends InvitationActionState {
  const InvitationActionIdle();
}

final class InvitationActionInProgress extends InvitationActionState {
  const InvitationActionInProgress(this.action);

  final InvitationAction action;

  @override
  List<Object?> get props => [action];
}

/// `POST /invitations/{id}/join` → the participant projection.
final class InvitationJoined extends InvitationActionState {
  const InvitationJoined(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class InvitationDeclined extends InvitationActionState {
  const InvitationDeclined(this.invitation);

  final InviteeInvitation invitation;

  @override
  List<Object?> get props => [invitation];
}

/// A refused join or decline. `plan_required` carries the access state in
/// [ApiException.details] (`details.access`); mobile explains it and never
/// offers a purchase (SCREENS.md §3.2, M20).
final class InvitationActionFailed extends InvitationActionState {
  const InvitationActionFailed(this.action, this.error);

  final InvitationAction action;
  final ApiException error;

  bool get planRequired => error.code == 'plan_required';

  /// The state changed on the server (deadline, already joined, …): the
  /// page should reload.
  bool get meansStale => switch (error.code) {
    'join_deadline_passed' ||
    'already_participating' ||
    'invalid_state_transition' ||
    'plan_required' => true,
    _ => false,
  };

  @override
  List<Object?> get props => [action, error];
}

/// Join (M18) and decline (M19). No optimistic state: the page changes
/// from the server's answer only (CD9).
class InvitationActionCubit extends Cubit<InvitationActionState> {
  InvitationActionCubit({required this._invitations})
    : super(const InvitationActionIdle());

  final InvitationsRepository _invitations;

  /// The terms checkbox is required by the sheet before this is called;
  /// the request always sends `accept_terms: true`.
  Future<void> join(String invitationId) async {
    if (state is InvitationActionInProgress) return;
    emit(const InvitationActionInProgress(InvitationAction.join));
    try {
      final competition = await _invitations.join(invitationId);
      if (!isClosed) emit(InvitationJoined(competition));
    } on ApiException catch (error) {
      if (!isClosed) {
        emit(InvitationActionFailed(InvitationAction.join, error));
      }
    }
  }

  /// [reason] is optional (≤ 500 characters).
  Future<void> decline(String invitationId, {String? reason}) async {
    if (state is InvitationActionInProgress) return;
    emit(const InvitationActionInProgress(InvitationAction.decline));
    final text = reason?.trim();
    try {
      final invitation = await _invitations.decline(
        invitationId,
        reason: text == null || text.isEmpty ? null : text,
      );
      if (!isClosed) emit(InvitationDeclined(invitation));
    } on ApiException catch (error) {
      if (!isClosed) {
        emit(InvitationActionFailed(InvitationAction.decline, error));
      }
    }
  }

  void reset() => emit(const InvitationActionIdle());
}
