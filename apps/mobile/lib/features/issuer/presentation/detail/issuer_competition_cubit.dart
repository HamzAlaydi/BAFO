import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class IssuerCompetitionState extends Equatable {
  const IssuerCompetitionState();

  @override
  List<Object?> get props => [];
}

final class IssuerCompetitionInitial extends IssuerCompetitionState {
  const IssuerCompetitionInitial();
}

final class IssuerCompetitionLoading extends IssuerCompetitionState {
  const IssuerCompetitionLoading();
}

final class IssuerCompetitionLoaded extends IssuerCompetitionState {
  const IssuerCompetitionLoaded({
    required this.competition,
    this.live,
    this.attachments = const [],
    this.attachmentsError,
    this.sponsorship,
    this.refreshing = false,
    this.deleting = false,
    this.actionError,
  });

  final Competition competition;

  /// The newest issuer snapshot (REST or channel, `v`-gated); null before
  /// publish.
  final IssuerLiveSnapshot? live;
  final List<Attachment> attachments;
  final ApiException? attachmentsError;

  /// Read-only counters (M38); null when the mode is none.
  final Sponsorship? sponsorship;
  final bool refreshing;
  final bool deleting;

  /// A failed delete (the other actions have their own cubits).
  final ApiException? actionError;

  /// The leading amount: the live snapshot's leader first (projection).
  int? get leadingAmountMinor =>
      live?.leader?.amountMinor ?? competition.leadingAmountMinor;

  IssuerCompetitionLoaded copyWith({
    Competition? competition,
    IssuerLiveSnapshot? Function()? live,
    List<Attachment>? attachments,
    ApiException? Function()? attachmentsError,
    Sponsorship? Function()? sponsorship,
    bool? refreshing,
    bool? deleting,
    ApiException? Function()? actionError,
  }) => IssuerCompetitionLoaded(
    competition: competition ?? this.competition,
    live: live == null ? this.live : live(),
    attachments: attachments ?? this.attachments,
    attachmentsError: attachmentsError == null
        ? this.attachmentsError
        : attachmentsError(),
    sponsorship: sponsorship == null ? this.sponsorship : sponsorship(),
    refreshing: refreshing ?? this.refreshing,
    deleting: deleting ?? this.deleting,
    actionError: actionError == null ? this.actionError : actionError(),
  );

  @override
  List<Object?> get props => [
    competition,
    live,
    attachments,
    attachmentsError,
    sponsorship,
    refreshing,
    deleting,
    actionError,
  ];
}

/// 404: never reveals whether the competition exists (S8 **N**).
final class IssuerCompetitionNotFound extends IssuerCompetitionState {
  const IssuerCompetitionNotFound();
}

final class IssuerCompetitionFailure extends IssuerCompetitionState {
  const IssuerCompetitionFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// The caller is not the issuer (participant or invitee projection): the
/// issuer screen shows `ForbiddenState` (SCREENS.md §3.1 role guards).
final class IssuerCompetitionNotIssuer extends IssuerCompetitionState {
  const IssuerCompetitionNotIssuer(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

/// The draft was deleted (M45): back to M33.
final class IssuerCompetitionDeleted extends IssuerCompetitionState {
  const IssuerCompetitionDeleted();
}

/// M38, the issuer's competition detail: the issuer projection, its
/// documents and the read-only sponsorship counters, kept current by the
/// issuer channel `competition.{id}` (SCREENS.md S4):
/// * `competition.updated` → refetch;
/// * `invitation.updated` → refetch counts and sponsorship, at most once
///   per [invitationRefetchWindow];
/// * `live.updated` → applied (`v`-gated); a status change or a `status`,
///   `award` or `bafo` change refetches, because `permissions` change;
/// * reconnect or resume → refetch.
class IssuerCompetitionCubit extends Cubit<IssuerCompetitionState> {
  IssuerCompetitionCubit({
    required this._competitions,
    required this._attachments,
    required this._hub,
    required this.competitionId,
    this.invitationRefetchWindow = const Duration(seconds: 1),
  }) : super(const IssuerCompetitionInitial());

  final CompetitionsRepository _competitions;
  final AttachmentsRepository _attachments;
  final CompetitionChannelHub _hub;
  final String competitionId;
  final Duration invitationRefetchWindow;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];
  Timer? _invitationTimer;
  Future<void>? _inFlight;
  bool _refetchAgain = false;

  Future<void> load() async {
    emit(const IssuerCompetitionLoading());
    await _fetch();
  }

  /// Pull to refresh and channel events: the data stays visible. Calls that
  /// arrive while a fetch runs are coalesced into one more fetch.
  Future<void> refresh() {
    final running = _inFlight;
    if (running != null) {
      _refetchAgain = true;
      return running;
    }
    final current = state;
    if (current is IssuerCompetitionLoaded) {
      emit(current.copyWith(refreshing: true));
    }
    return _inFlight = _fetch().whenComplete(() {
      _inFlight = null;
      if (_refetchAgain && !isClosed) {
        _refetchAgain = false;
        unawaited(refresh());
      }
    });
  }

  /// Applies a competition the server returned after an action (publish,
  /// cancel, edit): no optimistic state (CD9).
  void applyCompetition(Competition competition) {
    final current = state;
    if (current is IssuerCompetitionLoaded) {
      emit(current.copyWith(competition: competition));
      unawaited(refresh());
    }
  }

  Future<void> _fetch() async {
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      if (competition.viewerRole != ViewerRole.issuer) {
        emit(IssuerCompetitionNotIssuer(competition));
        return;
      }
      _follow();
      final live = competition.liveJson;
      final channel = _channel;
      IssuerLiveSnapshot? snapshot;
      if (live != null && channel != null && channel.acceptSnapshot(live)) {
        snapshot = competition.issuerLive;
      }
      final (attachments, attachmentsError) = await _documents();
      final sponsorship = await _sponsorship(competition);
      if (isClosed) return;
      final previous = state;
      emit(
        IssuerCompetitionLoaded(
          competition: competition,
          live:
              snapshot ??
              (previous is IssuerCompetitionLoaded ? previous.live : null),
          attachments: attachments,
          attachmentsError: attachmentsError,
          sponsorship: sponsorship,
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      final current = state;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const IssuerCompetitionNotFound());
      } else if (current is IssuerCompetitionLoaded) {
        // A failed refresh keeps the stale data visible (S8 X).
        emit(current.copyWith(refreshing: false));
      } else {
        emit(IssuerCompetitionFailure(error));
      }
    }
  }

  Future<(List<Attachment>, ApiException?)> _documents() async {
    try {
      return (await _attachments.list(competitionId), null);
    } on ApiException catch (error) {
      return (const <Attachment>[], error);
    }
  }

  /// Only when a sponsorship exists (the summary is null for mode none).
  Future<Sponsorship?> _sponsorship(Competition competition) async {
    if (competition.sponsorship == null) return null;
    try {
      final sponsorship = await _competitions.sponsorship(competitionId);
      return sponsorship.isNone ? null : sponsorship;
    } on ApiException {
      final current = state;
      return current is IssuerCompetitionLoaded ? current.sponsorship : null;
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
      ..add(channel.competitionUpdates.listen((_) => refresh()))
      ..add(channel.resyncRequests.listen((_) => refresh()))
      ..add(channel.invitationUpdates.listen((_) => _onInvitationUpdated()))
      ..add(channel.liveSnapshots.listen(_onSnapshot));
  }

  void _onInvitationUpdated() {
    if (_invitationTimer?.isActive ?? false) return;
    _invitationTimer = Timer(invitationRefetchWindow, () {
      if (!isClosed) unawaited(refresh());
    });
  }

  void _onSnapshot(Map<String, dynamic> json) {
    final current = state;
    if (current is! IssuerCompetitionLoaded) return;
    final IssuerLiveSnapshot snapshot;
    try {
      snapshot = IssuerLiveSnapshot.fromJson(json);
    } on FormatException {
      return;
    }
    emit(current.copyWith(live: () => snapshot));
    if (snapshot.status != current.competition.status ||
        snapshot.lastChange.kind.requiresCompetitionRefetch) {
      unawaited(refresh());
    }
  }

  /// M45: `DELETE /competitions/{id}` (drafts only).
  Future<void> deleteDraft() async {
    final current = state;
    if (current is! IssuerCompetitionLoaded || current.deleting) return;
    emit(current.copyWith(deleting: true, actionError: () => null));
    try {
      await _competitions.deleteDraft(competitionId);
      if (!isClosed) emit(const IssuerCompetitionDeleted());
    } on ApiException catch (error) {
      if (!isClosed) {
        emit(current.copyWith(deleting: false, actionError: () => error));
      }
    }
  }

  @override
  Future<void> close() async {
    _invitationTimer?.cancel();
    for (final subscription in _subscriptions) {
      await subscription.cancel();
    }
    await _channel?.release();
    return super.close();
  }
}
