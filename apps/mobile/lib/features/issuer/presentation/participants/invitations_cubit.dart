import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// What the last row action did (for the toast).
enum InvitationAction { resent, revoked, removed }

sealed class InvitationsState extends Equatable {
  const InvitationsState();

  @override
  List<Object?> get props => [];
}

final class InvitationsLoading extends InvitationsState {
  const InvitationsLoading();
}

final class InvitationsFailure extends InvitationsState {
  const InvitationsFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class InvitationsNotFound extends InvitationsState {
  const InvitationsNotFound();
}

/// Not the issuer: back to the shared detail (role guard).
final class InvitationsNotIssuer extends InvitationsState {
  const InvitationsNotIssuer(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class InvitationsLoaded extends InvitationsState {
  const InvitationsLoaded({
    required this.competition,
    required this.invitations,
    required this.counts,
    this.filter,
    this.sponsorship,
    this.busy = const {},
    this.actionError,
    this.lastAction,
  });

  final Competition competition;

  /// Every invitation (≤ 200, not paginated), in server order.
  final List<Invitation> invitations;

  /// `meta.counts` per status.
  final Map<InvitationStatus, int> counts;

  /// Null shows every status.
  final InvitationStatus? filter;

  /// Read-only counters; null when the mode is none.
  final Sponsorship? sponsorship;

  /// Invitation ids with a request in flight.
  final Set<String> busy;
  final ApiException? actionError;
  final ({InvitationAction action, String invitationId})? lastAction;

  List<Invitation> get visible => filter == null
      ? invitations
      : invitations.where((row) => row.status == filter).toList();

  int get total => counts.values.fold(0, (sum, count) => sum + count);

  InvitationsLoaded copyWith({
    Competition? competition,
    List<Invitation>? invitations,
    Map<InvitationStatus, int>? counts,
    InvitationStatus? Function()? filter,
    Sponsorship? Function()? sponsorship,
    Set<String>? busy,
    ApiException? Function()? actionError,
    ({InvitationAction action, String invitationId})? Function()? lastAction,
  }) => InvitationsLoaded(
    competition: competition ?? this.competition,
    invitations: invitations ?? this.invitations,
    counts: counts ?? this.counts,
    filter: filter == null ? this.filter : filter(),
    sponsorship: sponsorship == null ? this.sponsorship : sponsorship(),
    busy: busy ?? this.busy,
    actionError: actionError == null ? this.actionError : actionError(),
    lastAction: lastAction == null ? this.lastAction : lastAction(),
  );

  @override
  List<Object?> get props => [
    competition,
    invitations,
    counts,
    filter,
    sponsorship,
    busy,
    actionError,
    lastAction,
  ];
}

/// M41: the invitations of a competition with their statuses, resend and
/// revoke (with confirmation), and the read-only sponsorship counters.
/// `invitation.updated` upserts the row at once and refetches the counts
/// and sponsorship at most once per [refetchWindow] (SCREENS.md S4).
class InvitationsCubit extends Cubit<InvitationsState> {
  InvitationsCubit({
    required this._competitions,
    required this._invitations,
    required this._hub,
    required this.competitionId,
    this.refetchWindow = const Duration(seconds: 1),
  }) : super(const InvitationsLoading());

  final CompetitionsRepository _competitions;
  final InvitationsRepository _invitations;
  final CompetitionChannelHub _hub;
  final String competitionId;
  final Duration refetchWindow;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];
  Timer? _refetchTimer;

  Future<void> load() async {
    emit(const InvitationsLoading());
    await _fetch();
  }

  Future<void> refresh() => _fetch();

  Future<void> _fetch() async {
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      if (competition.viewerRole != ViewerRole.issuer) {
        emit(InvitationsNotIssuer(competition));
        return;
      }
      final list = await _invitations.list(competitionId);
      final sponsorship = await _sponsorship(competition);
      if (isClosed) return;
      _follow();
      final current = state;
      emit(
        InvitationsLoaded(
          competition: competition,
          invitations: list.invitations,
          counts: list.counts,
          filter: current is InvitationsLoaded ? current.filter : null,
          sponsorship: sponsorship,
          busy: current is InvitationsLoaded ? current.busy : const {},
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      final current = state;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const InvitationsNotFound());
      } else if (current is! InvitationsLoaded) {
        emit(InvitationsFailure(error));
      }
    }
  }

  Future<Sponsorship?> _sponsorship(Competition competition) async {
    if (competition.sponsorship == null) return null;
    try {
      final value = await _competitions.sponsorship(competitionId);
      return value.isNone ? null : value;
    } on ApiException {
      return null;
    }
  }

  void _follow() {
    if (_channel != null) return;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: ViewerRole.issuer,
    );
    _channel = channel;
    _subscriptions
      ..add(channel.invitationUpdates.listen(_onInvitationUpdated))
      ..add(channel.resyncRequests.listen((_) => refresh()));
  }

  void _onInvitationUpdated(Map<String, dynamic> json) {
    final current = state;
    if (current is! InvitationsLoaded) return;
    final Invitation updated;
    try {
      updated = Invitation.fromJson(json);
    } on FormatException {
      return;
    }
    final exists = current.invitations.any((row) => row.id == updated.id);
    emit(
      current.copyWith(
        invitations: exists
            ? [
                for (final row in current.invitations)
                  row.id == updated.id ? updated : row,
              ]
            : [...current.invitations, updated],
      ),
    );
    _scheduleRefetch();
  }

  void _scheduleRefetch() {
    if (_refetchTimer?.isActive ?? false) return;
    _refetchTimer = Timer(refetchWindow, () {
      if (!isClosed) unawaited(_fetch());
    });
  }

  void setFilter(InvitationStatus? status) {
    final current = state;
    if (current is InvitationsLoaded) {
      emit(current.copyWith(filter: () => status));
    }
  }

  /// `POST …/resend` (sent or viewed, before the cutoff; 3 per day → 429).
  Future<void> resend(Invitation invitation) =>
      _run(invitation, InvitationAction.resent, () async {
        await _invitations.resend(competitionId, invitation.id);
        return null;
      });

  /// `DELETE …/invitations/{id}` of a sent or viewed invitation → revoked.
  Future<void> revoke(Invitation invitation) => _run(
    invitation,
    InvitationAction.revoked,
    () => _invitations.remove(competitionId, invitation.id),
  );

  /// `DELETE …/invitations/{id}` of a draft invitation → deleted.
  Future<void> removeDraft(Invitation invitation) => _run(
    invitation,
    InvitationAction.removed,
    () => _invitations.remove(competitionId, invitation.id),
  );

  Future<void> _run(
    Invitation invitation,
    InvitationAction action,
    Future<Invitation?> Function() call,
  ) async {
    final current = state;
    if (current is! InvitationsLoaded || current.busy.contains(invitation.id)) {
      return;
    }
    emit(
      current.copyWith(
        busy: {...current.busy, invitation.id},
        actionError: () => null,
        lastAction: () => null,
      ),
    );
    try {
      final updated = await call();
      if (isClosed) return;
      final latest = state;
      if (latest is! InvitationsLoaded) return;
      final List<Invitation> rows;
      if (action == InvitationAction.resent) {
        rows = latest.invitations;
      } else if (updated == null) {
        rows = [
          for (final row in latest.invitations)
            if (row.id != invitation.id) row,
        ];
      } else {
        rows = [
          for (final row in latest.invitations)
            row.id == updated.id ? updated : row,
        ];
      }
      emit(
        latest.copyWith(
          invitations: rows,
          busy: {...latest.busy}..remove(invitation.id),
          lastAction: () => (action: action, invitationId: invitation.id),
        ),
      );
      if (action != InvitationAction.resent) _scheduleRefetch();
    } on ApiException catch (error) {
      if (isClosed) return;
      final latest = state;
      if (latest is! InvitationsLoaded) return;
      emit(
        latest.copyWith(
          busy: {...latest.busy}..remove(invitation.id),
          actionError: () => error,
        ),
      );
    }
  }

  @override
  Future<void> close() async {
    _refetchTimer?.cancel();
    for (final subscription in _subscriptions) {
      await subscription.cancel();
    }
    await _channel?.release();
    return super.close();
  }
}
