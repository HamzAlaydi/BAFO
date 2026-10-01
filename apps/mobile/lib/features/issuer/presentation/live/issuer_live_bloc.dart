import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Connection states of a live screen (SCREENS.md S4). The short `grace`
/// period (< 3 s) keeps showing [connected].
enum LiveConnection { connecting, connected, reconnecting, polling }

// ── Events ──────────────────────────────────────────────────────────────

sealed class IssuerLiveEvent extends Equatable {
  const IssuerLiveEvent();

  @override
  List<Object?> get props => [];
}

final class IssuerLiveOpened extends IssuerLiveEvent {
  const IssuerLiveOpened();
}

/// A `live.updated` payload from the channel (already `v`-gated), or a
/// REST snapshot passed through the gate.
final class IssuerLiveSnapshotReceived extends IssuerLiveEvent {
  const IssuerLiveSnapshotReceived(this.snapshot);

  final Map<String, dynamic> snapshot;

  @override
  List<Object?> get props => [snapshot];
}

final class IssuerLiveConnectionChanged extends IssuerLiveEvent {
  const IssuerLiveConnectionChanged(this.state);

  final RealtimeConnectionState state;

  @override
  List<Object?> get props => [state];
}

/// Reconnected or the app resumed: fetch `GET …/live` again (S4 resync).
final class IssuerLiveResumed extends IssuerLiveEvent {
  const IssuerLiveResumed();
}

/// `competition.updated`, or a snapshot that changes the status.
final class IssuerLiveCompetitionChanged extends IssuerLiveEvent {
  const IssuerLiveCompetitionChanged();
}

final class _GraceElapsed extends IssuerLiveEvent {
  const _GraceElapsed();
}

final class _FallbackElapsed extends IssuerLiveEvent {
  const _FallbackElapsed();
}

final class _PollTick extends IssuerLiveEvent {
  const _PollTick();
}

// ── States ──────────────────────────────────────────────────────────────

sealed class IssuerLiveState extends Equatable {
  const IssuerLiveState();

  @override
  List<Object?> get props => [];
}

final class IssuerLiveLoading extends IssuerLiveState {
  const IssuerLiveLoading();
}

final class IssuerLiveFailure extends IssuerLiveState {
  const IssuerLiveFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class IssuerLiveNotFound extends IssuerLiveState {
  const IssuerLiveNotFound();
}

/// Not the issuer (the participant live room is another screen).
final class IssuerLiveNotIssuer extends IssuerLiveState {
  const IssuerLiveNotIssuer(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class IssuerLiveLoaded extends IssuerLiveState {
  const IssuerLiveLoaded({
    required this.competition,
    required this.connection,
    this.snapshot,
    this.snapshotError,
  });

  final Competition competition;

  /// Null in draft (no live state before publish).
  final IssuerLiveSnapshot? snapshot;
  final LiveConnection connection;

  /// The last `GET …/live` failed (stale data stays visible).
  final ApiException? snapshotError;

  IssuerLiveLoaded copyWith({
    Competition? competition,
    IssuerLiveSnapshot? Function()? snapshot,
    LiveConnection? connection,
    ApiException? Function()? snapshotError,
  }) => IssuerLiveLoaded(
    competition: competition ?? this.competition,
    snapshot: snapshot == null ? this.snapshot : snapshot(),
    connection: connection ?? this.connection,
    snapshotError: snapshotError == null ? this.snapshotError : snapshotError(),
  );

  @override
  List<Object?> get props => [competition, snapshot, connection, snapshotError];
}

// ── Bloc ────────────────────────────────────────────────────────────────

/// M46, the read-only live monitor on the issuer channel
/// `competition.{id}`. Snapshots are applied only when newer (`v`, via the
/// channel handle's gate); the client never derives a leader, a rank or a
/// visibility (CD10).
///
/// Connection (S4): without a socket for [graceDelay] the screen shows
/// "Reconnecting…"; after [fallbackDelay] it polls `GET …/live` every
/// [pollFast] in the last 5 minutes, otherwise every [pollSlow].
class IssuerLiveBloc extends Bloc<IssuerLiveEvent, IssuerLiveState> {
  IssuerLiveBloc({
    required this._competitions,
    required this._live,
    required this._hub,
    required this._now,
    required this.competitionId,
    this.graceDelay = const Duration(seconds: 3),
    this.fallbackDelay = const Duration(seconds: 10),
    this.pollFast = const Duration(seconds: 3),
    this.pollSlow = const Duration(seconds: 10),
  }) : super(const IssuerLiveLoading()) {
    on<IssuerLiveOpened>(_onOpened);
    on<IssuerLiveSnapshotReceived>(_onSnapshot);
    on<IssuerLiveConnectionChanged>(_onConnection);
    on<IssuerLiveResumed>((_, emit) => _fetchSnapshot(emit));
    on<IssuerLiveCompetitionChanged>(_onCompetitionChanged);
    on<_GraceElapsed>(_onGrace);
    on<_FallbackElapsed>(_onFallback);
    on<_PollTick>(_onPoll);
  }

  final CompetitionsRepository _competitions;
  final LiveRepository _live;
  final CompetitionChannelHub _hub;

  /// Server time, for the polling interval.
  final DateTime Function() _now;
  final String competitionId;
  final Duration graceDelay;
  final Duration fallbackDelay;
  final Duration pollFast;
  final Duration pollSlow;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];
  Timer? _grace;
  Timer? _fallback;
  Timer? _poll;

  Future<void> _onOpened(
    IssuerLiveOpened event,
    Emitter<IssuerLiveState> emit,
  ) async {
    emit(const IssuerLiveLoading());
    try {
      final competition = await _competitions.show(competitionId);
      if (competition.viewerRole != ViewerRole.issuer) {
        emit(IssuerLiveNotIssuer(competition));
        return;
      }
      final channel = _acquire();
      final connected = channel.connection == RealtimeConnectionState.connected;
      final restLive = competition.liveJson;
      IssuerLiveSnapshot? snapshot;
      if (restLive != null && channel.acceptSnapshot(restLive)) {
        snapshot = competition.issuerLive;
      }
      emit(
        IssuerLiveLoaded(
          competition: competition,
          snapshot: snapshot,
          connection: connected
              ? LiveConnection.connected
              : LiveConnection.connecting,
        ),
      );
      if (!connected) _armFallback();
      if (competition.status != CompetitionStatus.draft) {
        await _fetchSnapshot(emit);
      }
    } on ApiException catch (error) {
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const IssuerLiveNotFound());
      } else {
        emit(IssuerLiveFailure(error));
      }
    }
  }

  CompetitionChannel _acquire() {
    final existing = _channel;
    if (existing != null) return existing;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: ViewerRole.issuer,
    );
    _channel = channel;
    _subscriptions
      ..add(
        channel.liveSnapshots.listen(
          (json) => add(IssuerLiveSnapshotReceived(json)),
        ),
      )
      ..add(
        channel.connectionChanges.listen(
          (state) => add(IssuerLiveConnectionChanged(state)),
        ),
      )
      ..add(
        channel.resyncRequests.listen((_) => add(const IssuerLiveResumed())),
      )
      ..add(
        channel.competitionUpdates.listen(
          (_) => add(const IssuerLiveCompetitionChanged()),
        ),
      );
    return channel;
  }

  /// `GET …/live` through the version gate (resync, polling).
  Future<void> _fetchSnapshot(Emitter<IssuerLiveState> emit) async {
    final channel = _channel;
    if (channel == null) return;
    try {
      final (_, json) = await _live.issuerSnapshot(competitionId);
      final current = state;
      if (current is! IssuerLiveLoaded) return;
      if (current.snapshotError != null) {
        emit(current.copyWith(snapshotError: () => null));
      }
      if (channel.acceptSnapshot(json)) await _apply(json, emit);
    } on ApiException catch (error) {
      final current = state;
      if (current is IssuerLiveLoaded) {
        emit(current.copyWith(snapshotError: () => error));
      }
    }
  }

  Future<void> _onSnapshot(
    IssuerLiveSnapshotReceived event,
    Emitter<IssuerLiveState> emit,
  ) => _apply(event.snapshot, emit);

  Future<void> _apply(
    Map<String, dynamic> json,
    Emitter<IssuerLiveState> emit,
  ) async {
    final current = state;
    if (current is! IssuerLiveLoaded) return;
    final IssuerLiveSnapshot snapshot;
    try {
      snapshot = IssuerLiveSnapshot.fromJson(json);
    } on FormatException {
      return;
    }
    emit(current.copyWith(snapshot: () => snapshot));
    // S4: a new status (or a status, award or BAFO change) changes the
    // permissions and the schedule: refetch the competition.
    if (snapshot.status != current.competition.status ||
        snapshot.lastChange.kind.requiresCompetitionRefetch) {
      await _refetchCompetition(emit);
    }
  }

  Future<void> _onCompetitionChanged(
    IssuerLiveCompetitionChanged event,
    Emitter<IssuerLiveState> emit,
  ) => _refetchCompetition(emit);

  Future<void> _refetchCompetition(Emitter<IssuerLiveState> emit) async {
    try {
      final competition = await _competitions.show(competitionId);
      final current = state;
      if (current is IssuerLiveLoaded) {
        emit(current.copyWith(competition: competition));
      }
    } on ApiException {
      // Keep the current header; the next event retries.
    }
  }

  void _onConnection(
    IssuerLiveConnectionChanged event,
    Emitter<IssuerLiveState> emit,
  ) {
    final current = state;
    if (current is! IssuerLiveLoaded) return;
    if (event.state == RealtimeConnectionState.connected) {
      _cancelTimers();
      emit(current.copyWith(connection: LiveConnection.connected));
      return;
    }
    // Grace: keep "connected" for a moment, then fall back.
    if (current.connection == LiveConnection.connected) {
      _grace ??= Timer(graceDelay, () => add(const _GraceElapsed()));
    }
    _armFallback();
  }

  void _armFallback() {
    _fallback ??= Timer(fallbackDelay, () => add(const _FallbackElapsed()));
  }

  void _onGrace(_GraceElapsed event, Emitter<IssuerLiveState> emit) {
    _grace = null;
    final current = state;
    if (current is IssuerLiveLoaded &&
        current.connection == LiveConnection.connected &&
        _channel?.connection != RealtimeConnectionState.connected) {
      emit(current.copyWith(connection: LiveConnection.reconnecting));
    }
  }

  Future<void> _onFallback(
    _FallbackElapsed event,
    Emitter<IssuerLiveState> emit,
  ) async {
    _fallback = null;
    final current = state;
    if (current is! IssuerLiveLoaded ||
        _channel?.connection == RealtimeConnectionState.connected) {
      return;
    }
    emit(current.copyWith(connection: LiveConnection.polling));
    await _pollOnce(emit);
  }

  Future<void> _onPoll(_PollTick event, Emitter<IssuerLiveState> emit) async {
    _poll = null;
    final current = state;
    if (current is! IssuerLiveLoaded ||
        current.connection != LiveConnection.polling) {
      return;
    }
    await _pollOnce(emit);
  }

  Future<void> _pollOnce(Emitter<IssuerLiveState> emit) async {
    final current = state;
    if (current is! IssuerLiveLoaded) return;
    if (current.competition.status != CompetitionStatus.draft) {
      await _fetchSnapshot(emit);
    }
    final latest = state;
    if (latest is IssuerLiveLoaded &&
        latest.connection == LiveConnection.polling) {
      _poll?.cancel();
      _poll = Timer(_pollInterval(latest), () => add(const _PollTick()));
    }
  }

  Duration _pollInterval(IssuerLiveLoaded current) {
    final closeAt =
        current.snapshot?.effectiveCloseAt ??
        current.competition.schedule.effectiveCloseAt;
    if (closeAt == null) return pollSlow;
    final remaining = closeAt.difference(_now());
    return remaining < const Duration(minutes: 5) ? pollFast : pollSlow;
  }

  void _cancelTimers() {
    _grace?.cancel();
    _grace = null;
    _fallback?.cancel();
    _fallback = null;
    _poll?.cancel();
    _poll = null;
  }

  @override
  Future<void> close() async {
    _cancelTimers();
    for (final subscription in _subscriptions) {
      await subscription.cancel();
    }
    await _channel?.release();
    return super.close();
  }
}
