import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/data/clock_sync_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// The live-data connection states of SCREENS.md S4.
enum LiveConnection {
  /// The screen opened; the socket is not connected yet.
  connecting,

  /// Subscribed and resynced.
  connected,

  /// The socket dropped less than 3 s ago: nothing changes yet.
  grace,

  /// Dropped for 3 s or more; the fallback is not running yet.
  reconnecting,

  /// No socket for 10 s: `GET …/live` is polled.
  polling,

  /// No network at all.
  offline,
}

/// The timings of S3/S4, injectable for tests.
final class LiveRoomTimings {
  const LiveRoomTimings({
    this.heartbeat = const Duration(seconds: 20),
    this.clockSync = const Duration(seconds: 60),
    this.grace = const Duration(seconds: 3),
    this.pollingAfter = const Duration(seconds: 10),
    this.fastPoll = const Duration(seconds: 3),
    this.slowPoll = const Duration(seconds: 10),
    this.fastPollWindow = const Duration(minutes: 5),
    this.finalSeconds = const Duration(seconds: 10),
    this.closeRecheck = const Duration(seconds: 2),
  });

  /// `POST …/live/heartbeat` while the screen is visible (§9.5).
  final Duration heartbeat;

  /// `GET /time` while a countdown is on screen (§9.6).
  final Duration clockSync;

  /// Socket down this long → "Reconnecting…", submit disabled.
  final Duration grace;

  /// Socket down this long → polling.
  final Duration pollingAfter;

  /// Poll interval in the last [fastPollWindow] before the close.
  final Duration fastPoll;
  final Duration slowPoll;
  final Duration fastPollWindow;

  /// The server-timing hint shows in the last [finalSeconds].
  final Duration finalSeconds;

  /// After the countdown reaches zero, ask the server whether it closed.
  final Duration closeRecheck;
}

/// An extension of the close seen while the room is open (anti-sniping or
/// a manual extension), for the extension banner.
final class LiveExtensionNotice extends Equatable {
  const LiveExtensionNotice({
    required this.extensionCount,
    this.reason,
    this.bySeconds,
  });

  final int extensionCount;

  /// `auto` (anti-sniping), `manual` or `admin`; null when the snapshot
  /// that carried the new close was another kind of change.
  final ExtensionReason? reason;

  /// `rules.auto_extend.by_seconds`, for "extended by N minutes".
  final int? bySeconds;

  @override
  List<Object?> get props => [extensionCount, reason, bySeconds];
}

sealed class LiveRoomState extends Equatable {
  const LiveRoomState();

  @override
  List<Object?> get props => [];
}

final class LiveRoomInitial extends LiveRoomState {
  const LiveRoomInitial();
}

final class LiveRoomLoading extends LiveRoomState {
  const LiveRoomLoading();
}

/// 404: never reveals whether the competition exists.
final class LiveRoomNotFound extends LiveRoomState {
  const LiveRoomNotFound();
}

/// The viewer is not a joined participant (an invitee, or the issuer): the
/// room is not theirs. The screen goes back to the competition overview.
final class LiveRoomUnavailable extends LiveRoomState {
  const LiveRoomUnavailable(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class LiveRoomFailure extends LiveRoomState {
  const LiveRoomFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// The room with the latest applied [snapshot] (always the highest `v`).
final class LiveRoomLoaded extends LiveRoomState {
  const LiveRoomLoaded({
    required this.competition,
    required this.snapshot,
    this.connection = LiveConnection.connecting,
    this.pollInterval,
    this.pollStale = false,
    this.deadlinePassed = false,
    this.finalSeconds = false,
    this.slowConnection = false,
    this.extension,
    this.refreshing = false,
  });

  final Competition competition;
  final ParticipantLiveSnapshot snapshot;
  final LiveConnection connection;

  /// The current poll interval while [connection] is polling.
  final Duration? pollInterval;

  /// Polling: the last successful poll is older than two intervals.
  final bool pollStale;

  /// The countdown reached zero but the server has not closed yet
  /// («جارٍ الإغلاق…», S3 step 9): the composer is disabled.
  final bool deadlinePassed;

  /// Ten seconds or less before the close (server-timing hint).
  final bool finalSeconds;

  /// The last clock sample's round trip was over 2 s.
  final bool slowConnection;
  final LiveExtensionNotice? extension;
  final bool refreshing;

  /// The close the countdown targets: the effective close while live, the
  /// cutoff during a BAFO round.
  DateTime? get closeAt => switch (snapshot.status) {
    CompetitionStatus.live => snapshot.effectiveCloseAt,
    CompetitionStatus.bafoRound => snapshot.bafo?.cutoffAt,
    _ => null,
  };

  /// Whether the connection allows a submit (S4 table): not while
  /// reconnecting or offline; while polling only with a fresh poll.
  bool get connectionAllowsSubmit => switch (connection) {
    LiveConnection.connecting ||
    LiveConnection.connected ||
    LiveConnection.grace => true,
    LiveConnection.polling => !pollStale,
    LiveConnection.reconnecting || LiveConnection.offline => false,
  };

  /// The composer is enabled: the server accepts offers from this
  /// participant, the countdown has not reached zero, and the bounds are
  /// fresh enough.
  bool get canSubmit =>
      snapshot.acceptingOffers && !deadlinePassed && connectionAllowsSubmit;

  LiveRoomLoaded copyWith({
    Competition? competition,
    ParticipantLiveSnapshot? snapshot,
    LiveConnection? connection,
    Duration? pollInterval,
    bool clearPollInterval = false,
    bool? pollStale,
    bool? deadlinePassed,
    bool? finalSeconds,
    bool? slowConnection,
    LiveExtensionNotice? extension,
    bool? refreshing,
  }) => LiveRoomLoaded(
    competition: competition ?? this.competition,
    snapshot: snapshot ?? this.snapshot,
    connection: connection ?? this.connection,
    pollInterval: clearPollInterval
        ? null
        : (pollInterval ?? this.pollInterval),
    pollStale: pollStale ?? this.pollStale,
    deadlinePassed: deadlinePassed ?? this.deadlinePassed,
    finalSeconds: finalSeconds ?? this.finalSeconds,
    slowConnection: slowConnection ?? this.slowConnection,
    extension: extension ?? this.extension,
    refreshing: refreshing ?? this.refreshing,
  );

  @override
  List<Object?> get props => [
    competition,
    snapshot,
    connection,
    pollInterval,
    pollStale,
    deadlinePassed,
    finalSeconds,
    slowConnection,
    extension,
    refreshing,
  ];
}

sealed class LiveRoomEvent {
  const LiveRoomEvent();
}

/// The screen opened: load, subscribe, resync, start the heartbeat.
final class LiveRoomOpened extends LiveRoomEvent {
  const LiveRoomOpened();
}

/// The app went to the background: stop the heartbeat and polling at once.
final class LiveRoomPaused extends LiveRoomEvent {
  const LiveRoomPaused();
}

/// The app came back: heartbeat, clock sync and a resync.
final class LiveRoomResumed extends LiveRoomEvent {
  const LiveRoomResumed();
}

/// Pull to refresh, or a submit error that means the view is stale.
final class LiveRoomRefreshRequested extends LiveRoomEvent {
  const LiveRoomRefreshRequested();
}

/// A `POST …/offers` response: its snapshot goes through the `v` guard.
final class LiveRoomOfferAccepted extends LiveRoomEvent {
  const LiveRoomOfferAccepted(this.submission);

  final OfferSubmission submission;
}

final class _SocketSnapshot extends LiveRoomEvent {
  const _SocketSnapshot(this.json);

  final Json json;
}

final class _CompetitionChanged extends LiveRoomEvent {
  const _CompetitionChanged();
}

final class _Resync extends LiveRoomEvent {
  const _Resync();
}

final class _SocketChanged extends LiveRoomEvent {
  const _SocketChanged(this.state);

  final RealtimeConnectionState state;
}

final class _NetworkChanged extends LiveRoomEvent {
  const _NetworkChanged({required this.offline});

  final bool offline;
}

final class _HeartbeatTick extends LiveRoomEvent {
  const _HeartbeatTick();
}

final class _ClockSyncTick extends LiveRoomEvent {
  const _ClockSyncTick();
}

final class _GraceElapsed extends LiveRoomEvent {
  const _GraceElapsed();
}

final class _PollingStarted extends LiveRoomEvent {
  const _PollingStarted();
}

final class _PollTick extends LiveRoomEvent {
  const _PollTick();
}

final class _PollStale extends LiveRoomEvent {
  const _PollStale();
}

final class _DeadlineReached extends LiveRoomEvent {
  const _DeadlineReached();
}

final class _FinalSecondsReached extends LiveRoomEvent {
  const _FinalSecondsReached();
}

/// The participant live room (SCREENS.md M23, S3, S4, ARCHITECTURE.md
/// §9.3–§9.6).
///
/// * **Snapshots** come from the socket, `GET …/live`, the competition's
///   `live` block and `POST …/offers` responses; all pass the channel's `v`
///   gate, and the room always shows the highest `v` (CD10).
/// * **Resync** on open, reconnect and resume: subscribe first, then fetch.
/// * **Connection** states and the polling fallback (3 s in the last 5
///   minutes, else 10 s) follow the S4 table.
/// * **Time** is the [ServerClock]'s: `GET /time` at open and every 60 s;
///   at zero the room says «جارٍ الإغلاق…» until the server decides.
/// * **Heartbeat** every 20 s while visible.
class LiveRoomBloc extends Bloc<LiveRoomEvent, LiveRoomState> {
  LiveRoomBloc({
    required this._competitions,
    required this._live,
    required this._clockSync,
    required this._hub,
    required this._clock,
    required this.competitionId,
    this.organizationId,
    Stream<bool>? offline,
    bool initiallyOffline = false,
    this.timings = const LiveRoomTimings(),
  }) : _offline = initiallyOffline,
       super(const LiveRoomInitial()) {
    on<LiveRoomOpened>(_onOpened);
    on<LiveRoomPaused>(_onPaused);
    on<LiveRoomResumed>(_onResumed);
    on<LiveRoomRefreshRequested>(_onRefresh);
    on<LiveRoomOfferAccepted>(_onOfferAccepted);
    on<_SocketSnapshot>(_onSocketSnapshot);
    on<_CompetitionChanged>(_onCompetitionChanged);
    on<_Resync>(_onResync);
    on<_SocketChanged>(_onSocketChanged);
    on<_NetworkChanged>(_onNetworkChanged);
    on<_HeartbeatTick>(_onHeartbeat);
    on<_ClockSyncTick>(_onClockSync);
    on<_GraceElapsed>(_onGraceElapsed);
    on<_PollingStarted>(_onPollingStarted);
    on<_PollTick>(_onPollTick);
    on<_PollStale>(_onPollStale);
    on<_DeadlineReached>(_onDeadlineReached);
    on<_FinalSecondsReached>(_onFinalSeconds);
    _networkSubscription = offline?.listen(
      (value) => add(_NetworkChanged(offline: value)),
    );
  }

  final CompetitionsRepository _competitions;
  final LiveRepository _live;
  final ClockSyncRepository _clockSync;
  final CompetitionChannelHub _hub;
  final ServerClock _clock;
  final String competitionId;
  final String? organizationId;
  final LiveRoomTimings timings;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _channelSubscriptions = [];
  StreamSubscription<bool>? _networkSubscription;

  /// The newest snapshot accepted by the gate, whatever the state (a socket
  /// snapshot can arrive before the first REST read completes).
  ParticipantLiveSnapshot? _latest;

  bool _offline;
  bool _visible = true;

  /// The socket-driven part of the connection (without [_offline]).
  LiveConnection _socketPhase = LiveConnection.connecting;

  Timer? _heartbeatTimer;
  Timer? _clockTimer;
  Timer? _graceTimer;
  Timer? _pollingAfterTimer;
  Timer? _pollTimer;
  Timer? _staleTimer;
  Timer? _deadlineTimer;
  Timer? _finalSecondsTimer;
  Timer? _recheckTimer;

  LiveConnection get _connection =>
      _offline ? LiveConnection.offline : _socketPhase;

  Future<void> _onOpened(
    LiveRoomOpened event,
    Emitter<LiveRoomState> emit,
  ) async {
    emit(const LiveRoomLoading());
    final Competition competition;
    try {
      competition = await _competitions.show(competitionId);
    } on ApiException catch (error) {
      emit(
        _isNotFound(error) ? const LiveRoomNotFound() : LiveRoomFailure(error),
      );
      return;
    }
    if (!competition.isParticipantView) {
      emit(LiveRoomUnavailable(competition));
      return;
    }
    final channel = _acquire(competition);
    // Render from the competition's block at once; then resync over REST
    // after subscribing (ARCHITECTURE.md §9.4). The gate keeps the newest.
    final block = competition.liveJson;
    if (block != null) _acceptRest(block);
    try {
      final (_, raw) = await _live.participantSnapshot(competitionId);
      _acceptRest(raw);
    } on ApiException catch (error) {
      if (_latest == null) {
        if (error.code == 'not_a_participant') {
          emit(LiveRoomUnavailable(competition));
        } else {
          emit(
            _isNotFound(error)
                ? const LiveRoomNotFound()
                : LiveRoomFailure(error),
          );
        }
        return;
      }
    }
    final snapshot = _latest;
    if (snapshot == null) {
      emit(const LiveRoomFailure(ApiException(code: ApiErrorCode.badResponse)));
      return;
    }
    if (channel.connection == RealtimeConnectionState.connected) {
      _stopPolling();
      _socketPhase = LiveConnection.connected;
    } else if (_socketPhase == LiveConnection.connected) {
      _socketPhase = LiveConnection.connecting;
      _startPollingCountdown();
    } else {
      _startPollingCountdown();
    }
    emit(
      LiveRoomLoaded(
        competition: competition,
        snapshot: snapshot,
        connection: _connection,
        slowConnection: _clock.isSlow,
      ),
    );
    _startHeartbeat();
    _startClockSync();
    _scheduleDeadline(emit);
    if (_needsCompetitionRefetch(competition, snapshot)) {
      add(const _CompetitionChanged());
    }
  }

  CompetitionChannel _acquire(Competition competition) {
    final existing = _channel;
    if (existing != null) return existing;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: competition.viewerRole,
      organizationId: organizationId,
    );
    _channel = channel;
    _channelSubscriptions
      ..add(channel.liveSnapshots.listen((json) => add(_SocketSnapshot(json))))
      ..add(
        channel.competitionUpdates.listen(
          (_) => add(const _CompetitionChanged()),
        ),
      )
      ..add(channel.resyncRequests.listen((_) => add(const _Resync())))
      ..add(
        channel.connectionChanges.listen((state) => add(_SocketChanged(state))),
      );
    return channel;
  }

  /// A REST snapshot through the gate; true when it became the latest.
  bool _acceptRest(Json raw) {
    final channel = _channel;
    if (channel == null || !channel.acceptSnapshot(raw)) return false;
    _latest = ParticipantLiveSnapshot.fromJson(raw);
    return true;
  }

  static bool _isNotFound(ApiException error) =>
      error.statusCode == 404 || error.code == 'not_found';

  static bool _needsCompetitionRefetch(
    Competition competition,
    ParticipantLiveSnapshot snapshot,
  ) =>
      snapshot.status != competition.status ||
      snapshot.lastChange.kind.requiresCompetitionRefetch;

  /// Applies [_latest] to a loaded room: extension notice, deadline marks,
  /// and a competition refetch when the permissions may have changed.
  void _applyLatest(Emitter<LiveRoomState> emit) {
    final current = state;
    final snapshot = _latest;
    if (current is! LiveRoomLoaded || snapshot == null) return;
    if (identical(current.snapshot, snapshot)) return;
    final previous = current.snapshot;
    LiveExtensionNotice? extension;
    if (snapshot.extensionCount > previous.extensionCount) {
      final kind = snapshot.lastChange.kind;
      extension = LiveExtensionNotice(
        extensionCount: snapshot.extensionCount,
        reason: kind == LastChangeKind.extension
            ? snapshot.lastChange.reason
            : null,
        bySeconds: current.competition.rules.autoExtend.bySeconds,
      );
    }
    emit(current.copyWith(snapshot: snapshot, extension: extension));
    _scheduleDeadline(emit);
    if (_needsCompetitionRefetch(current.competition, snapshot)) {
      add(const _CompetitionChanged());
    }
  }

  void _onSocketSnapshot(_SocketSnapshot event, Emitter<LiveRoomState> emit) {
    // Already gated by the channel handle.
    _latest = ParticipantLiveSnapshot.fromJson(event.json);
    _applyLatest(emit);
  }

  void _onOfferAccepted(
    LiveRoomOfferAccepted event,
    Emitter<LiveRoomState> emit,
  ) {
    if (_acceptRest(event.submission.liveJson)) _applyLatest(emit);
  }

  /// `GET …/live`; true when the request succeeded.
  Future<bool> _fetchSnapshot(Emitter<LiveRoomState> emit) async {
    try {
      final (_, raw) = await _live.participantSnapshot(competitionId);
      if (_acceptRest(raw)) _applyLatest(emit);
      return true;
    } on ApiException catch (error) {
      // The viewer role changed (e.g. the participation was removed).
      if (error.code == 'not_a_participant') add(const _CompetitionChanged());
      return false;
    }
  }

  Future<void> _onCompetitionChanged(
    _CompetitionChanged event,
    Emitter<LiveRoomState> emit,
  ) async {
    if (state is! LiveRoomLoaded) return;
    try {
      final competition = await _competitions.show(competitionId);
      final current = state;
      if (current is! LiveRoomLoaded) return;
      if (!competition.isParticipantView) {
        emit(LiveRoomUnavailable(competition));
        return;
      }
      final block = competition.liveJson;
      final newer = block != null && _acceptRest(block);
      emit(current.copyWith(competition: competition));
      if (newer) _applyLatest(emit);
    } on ApiException catch (error) {
      if (_isNotFound(error)) emit(const LiveRoomNotFound());
    }
  }

  Future<void> _onResync(_Resync event, Emitter<LiveRoomState> emit) async {
    if (state is! LiveRoomLoaded) return;
    await _fetchSnapshot(emit);
  }

  Future<void> _onRefresh(
    LiveRoomRefreshRequested event,
    Emitter<LiveRoomState> emit,
  ) async {
    final current = state;
    if (current is! LiveRoomLoaded) {
      if (current is LiveRoomFailure) add(const LiveRoomOpened());
      return;
    }
    emit(current.copyWith(refreshing: true));
    await _fetchSnapshot(emit);
    add(const _CompetitionChanged());
    final after = state;
    if (after is LiveRoomLoaded) emit(after.copyWith(refreshing: false));
  }

  // ── Connection (S4) ──────────────────────────────────────────────────

  void _emitConnection(Emitter<LiveRoomState> emit) {
    final current = state;
    if (current is! LiveRoomLoaded) return;
    emit(
      current.copyWith(
        connection: _connection,
        clearPollInterval: _socketPhase != LiveConnection.polling,
      ),
    );
  }

  void _onSocketChanged(_SocketChanged event, Emitter<LiveRoomState> emit) {
    if (event.state == RealtimeConnectionState.connected) {
      _stopPolling();
      _socketPhase = LiveConnection.connected;
      // The hub asks for a resync after a reconnect (resyncRequests).
    } else if (_socketPhase == LiveConnection.connected) {
      _socketPhase = LiveConnection.grace;
      _graceTimer?.cancel();
      _graceTimer = Timer(timings.grace, () => add(const _GraceElapsed()));
      _startPollingCountdown();
    } else {
      _startPollingCountdown();
    }
    _emitConnection(emit);
  }

  void _onGraceElapsed(_GraceElapsed event, Emitter<LiveRoomState> emit) {
    if (_socketPhase != LiveConnection.grace) return;
    _socketPhase = LiveConnection.reconnecting;
    _emitConnection(emit);
  }

  /// No socket for [LiveRoomTimings.pollingAfter] → polling.
  void _startPollingCountdown() {
    if (_pollingAfterTimer != null || _socketPhase == LiveConnection.polling) {
      return;
    }
    _pollingAfterTimer = Timer(
      timings.pollingAfter,
      () => add(const _PollingStarted()),
    );
  }

  void _onPollingStarted(_PollingStarted event, Emitter<LiveRoomState> emit) {
    _pollingAfterTimer = null;
    if (_socketPhase == LiveConnection.connected) return;
    _graceTimer?.cancel();
    _socketPhase = LiveConnection.polling;
    _emitConnection(emit);
    if (_visible) add(const _PollTick());
  }

  Duration _pollInterval() {
    final current = state;
    final closeAt = current is LiveRoomLoaded ? current.closeAt : null;
    if (closeAt != null &&
        _clock.remainingUntil(closeAt) <= timings.fastPollWindow) {
      return timings.fastPoll;
    }
    return timings.slowPoll;
  }

  Future<void> _onPollTick(_PollTick event, Emitter<LiveRoomState> emit) async {
    _pollTimer?.cancel();
    if (_socketPhase != LiveConnection.polling || !_visible) return;
    final ok = await _fetchSnapshot(emit);
    if (_socketPhase != LiveConnection.polling || isClosed) return;
    final interval = _pollInterval();
    final current = state;
    if (ok && current is LiveRoomLoaded) {
      emit(current.copyWith(pollStale: false, pollInterval: interval));
      _staleTimer?.cancel();
      _staleTimer = Timer(interval * 2, () => add(const _PollStale()));
    } else if (current is LiveRoomLoaded && current.pollInterval == null) {
      emit(current.copyWith(pollInterval: interval));
    }
    _pollTimer = Timer(interval, () => add(const _PollTick()));
  }

  void _onPollStale(_PollStale event, Emitter<LiveRoomState> emit) {
    final current = state;
    if (current is LiveRoomLoaded && _socketPhase == LiveConnection.polling) {
      emit(current.copyWith(pollStale: true));
    }
  }

  void _stopPolling() {
    _graceTimer?.cancel();
    _pollingAfterTimer?.cancel();
    _pollingAfterTimer = null;
    _pollTimer?.cancel();
    _staleTimer?.cancel();
  }

  void _onNetworkChanged(_NetworkChanged event, Emitter<LiveRoomState> emit) {
    if (_offline == event.offline) return;
    _offline = event.offline;
    _emitConnection(emit);
  }

  // ── Heartbeat and clock (§9.5, §9.6) ────────────────────────────────

  void _startHeartbeat() {
    _heartbeatTimer?.cancel();
    add(const _HeartbeatTick());
    _heartbeatTimer = Timer.periodic(
      timings.heartbeat,
      (_) => add(const _HeartbeatTick()),
    );
  }

  Future<void> _onHeartbeat(
    _HeartbeatTick event,
    Emitter<LiveRoomState> emit,
  ) async {
    if (!_visible || state is! LiveRoomLoaded) return;
    final snapshot = _latest;
    // Presence only matters while offers can move.
    if (snapshot == null || snapshot.status.isTerminal) return;
    try {
      await _live.heartbeat(competitionId);
    } on ApiException {
      // Presence is best effort.
    }
  }

  void _startClockSync() {
    _clockTimer?.cancel();
    add(const _ClockSyncTick());
    _clockTimer = Timer.periodic(
      timings.clockSync,
      (_) => add(const _ClockSyncTick()),
    );
  }

  Future<void> _onClockSync(
    _ClockSyncTick event,
    Emitter<LiveRoomState> emit,
  ) async {
    if (!_visible) return;
    try {
      await _clockSync.sync();
    } on ApiException {
      // The previous offset stays in use.
    }
    final current = state;
    if (current is! LiveRoomLoaded) return;
    if (current.slowConnection != _clock.isSlow) {
      emit(current.copyWith(slowConnection: _clock.isSlow));
    }
    // A new offset moves the marks.
    _scheduleDeadline(emit);
  }

  // ── Deadline marks (S3 steps 8–9) ───────────────────────────────────

  void _scheduleDeadline(Emitter<LiveRoomState> emit) {
    _deadlineTimer?.cancel();
    _finalSecondsTimer?.cancel();
    final current = state;
    if (current is! LiveRoomLoaded) return;
    final snapshot = current.snapshot;
    final closeAt = current.closeAt;
    if (closeAt == null) {
      _stopRecheck();
      final opensAt = snapshot.biddingOpensAt;
      // Scheduled: ask again once the opening time has passed.
      if (snapshot.status == CompetitionStatus.scheduled && opensAt != null) {
        final wait = _clock.remainingUntil(opensAt);
        _deadlineTimer = Timer(
          wait + timings.closeRecheck,
          () => add(const _Resync()),
        );
      }
      if (current.deadlinePassed || current.finalSeconds) {
        emit(current.copyWith(deadlinePassed: false, finalSeconds: false));
      }
      return;
    }
    final remaining = _clock.remainingUntil(closeAt);
    if (remaining == Duration.zero) {
      if (!current.deadlinePassed || !current.finalSeconds) {
        emit(current.copyWith(deadlinePassed: true, finalSeconds: true));
      }
      _scheduleRecheck();
      return;
    }
    _stopRecheck();
    final inFinal = remaining <= timings.finalSeconds;
    if (current.deadlinePassed || current.finalSeconds != inFinal) {
      emit(current.copyWith(deadlinePassed: false, finalSeconds: inFinal));
    }
    _deadlineTimer = Timer(remaining, () => add(const _DeadlineReached()));
    if (!inFinal) {
      _finalSecondsTimer = Timer(
        remaining - timings.finalSeconds,
        () => add(const _FinalSecondsReached()),
      );
    }
  }

  /// The server closes at the effective close under a lock: ask it (a status
  /// snapshot normally arrives on its own; this covers a silent socket and a
  /// late close job): after [LiveRoomTimings.closeRecheck], then every 5×
  /// that until the server answers with a new state.
  void _scheduleRecheck() {
    if (_recheckTimer != null) return;
    _recheckTimer = Timer(timings.closeRecheck, () {
      add(const _Resync());
      _recheckTimer = Timer.periodic(
        timings.closeRecheck * 5,
        (_) => add(const _Resync()),
      );
    });
  }

  void _stopRecheck() {
    _recheckTimer?.cancel();
    _recheckTimer = null;
  }

  void _onDeadlineReached(_DeadlineReached event, Emitter<LiveRoomState> emit) {
    _scheduleDeadline(emit);
  }

  void _onFinalSeconds(
    _FinalSecondsReached event,
    Emitter<LiveRoomState> emit,
  ) {
    final current = state;
    if (current is LiveRoomLoaded && !current.finalSeconds) {
      emit(current.copyWith(finalSeconds: true));
    }
  }

  // ── Lifecycle ────────────────────────────────────────────────────────

  void _onPaused(LiveRoomPaused event, Emitter<LiveRoomState> emit) {
    _visible = false;
    _heartbeatTimer?.cancel();
    _clockTimer?.cancel();
    _pollTimer?.cancel();
    _staleTimer?.cancel();
  }

  void _onResumed(LiveRoomResumed event, Emitter<LiveRoomState> emit) {
    if (_visible) return;
    _visible = true;
    if (state is! LiveRoomLoaded) return;
    _startHeartbeat();
    _startClockSync();
    if (_socketPhase == LiveConnection.polling) add(const _PollTick());
    // The snapshot itself is refetched by the hub's resync request (the
    // app root calls `CompetitionChannelHub.notifyResumed`).
  }

  @override
  Future<void> close() async {
    for (final timer in [
      _heartbeatTimer,
      _clockTimer,
      _graceTimer,
      _pollingAfterTimer,
      _pollTimer,
      _staleTimer,
      _deadlineTimer,
      _finalSecondsTimer,
      _recheckTimer,
    ]) {
      timer?.cancel();
    }
    await _networkSubscription?.cancel();
    for (final subscription in _channelSubscriptions) {
      await subscription.cancel();
    }
    await _channel?.release();
    return super.close();
  }
}
