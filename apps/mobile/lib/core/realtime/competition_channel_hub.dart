import 'dart:async';

import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/realtime_client.dart';

/// Monotonic guard for live snapshots (ARCHITECTURE.md §9.3): a snapshot is
/// applied only when its `v` is greater than the last applied one.
///
/// Snapshots are full projections for their audience, so applying the
/// highest `v` seen is the whole resync algorithm of §9.4: subscribe first,
/// fetch `GET …/live`, and let this guard drop whatever is older, whether it
/// came over the socket, from REST or in a `POST …/offers` response.
final class SnapshotVersionGate {
  int? _lastApplied;

  int? get lastApplied => _lastApplied;

  /// True (and remembered) when [v] is newer than everything applied so far.
  bool accept(int? v) {
    if (v == null) return false;
    final last = _lastApplied;
    if (last != null && v <= last) return false;
    _lastApplied = v;
    return true;
  }

  /// `v` of a raw snapshot payload.
  static int? versionOf(Map<String, dynamic> snapshot) {
    final v = snapshot['v'];
    return v is int ? v : null;
  }
}

/// One screen's view of a competition channel. Get it from
/// [CompetitionChannelHub.acquire] and [release] it when the screen closes.
///
/// Each handle has its own [SnapshotVersionGate]: two screens of the same
/// competition share the socket subscription but apply snapshots
/// independently.
abstract interface class CompetitionChannel {
  String get competitionId;

  /// Null for invitees, who have no channel (SCREENS.md S4).
  String? get channelName;

  /// Raw `live.updated` payloads (`IssuerLiveSnapshot` or
  /// `ParticipantLiveSnapshot`) that passed this handle's version gate.
  Stream<Map<String, dynamic>> get liveSnapshots;

  /// Feeds a snapshot obtained over REST (`GET …/live`, the `live` block of
  /// a competition, or a `POST …/offers` response) through the same gate.
  /// Returns true when the caller should apply it.
  bool acceptSnapshot(Map<String, dynamic> snapshot);

  int? get lastAppliedVersion;

  /// `offer.accepted` (issuer): `OfferLogEntry` payloads, in arrival order.
  Stream<Map<String, dynamic>> get offersAccepted;

  /// `competition.updated`: `{competition_id, fields, server_time}`.
  Stream<Map<String, dynamic>> get competitionUpdates;

  /// `comment.created`: `Comment` payloads projected for this audience.
  Stream<Map<String, dynamic>> get commentsCreated;

  /// `invitation.updated` (issuer): `Invitation` payloads.
  Stream<Map<String, dynamic>> get invitationUpdates;

  /// Fires after a reconnect or an app resume: refetch `GET …/live` (and
  /// whatever else the screen shows) and apply it through [acceptSnapshot].
  Stream<void> get resyncRequests;

  RealtimeConnectionState get connection;

  Stream<RealtimeConnectionState> get connectionChanges;

  Future<void> release();
}

/// Ref-counted competition channels (SCREENS.md S4, §3.5): the detail, live,
/// Q&A, participants and offers-log screens of one competition share a
/// single subscription; the last [CompetitionChannel.release] leaves it.
class CompetitionChannelHub {
  CompetitionChannelHub(this._realtime)
    : _lastConnection = _realtime.currentState {
    _connection = _realtime.connectionState.listen(_onConnection);
  }

  final RealtimeClient _realtime;
  final Map<String, _HubEntry> _entries = {};
  final Set<_Handle> _handles = {};
  late final StreamSubscription<RealtimeConnectionState> _connection;
  RealtimeConnectionState _lastConnection;

  /// The channel for a viewer of [competitionId]:
  /// * issuer → `competition.{id}`;
  /// * participant → `competition.{id}.participant.{organizationId}`;
  /// * invitee (or unknown) → no channel; refetch on focus instead.
  CompetitionChannel acquire({
    required String competitionId,
    required ViewerRole role,
    String? organizationId,
  }) {
    final channelName = switch (role) {
      ViewerRole.issuer => RealtimeChannels.competition(competitionId),
      ViewerRole.participant when organizationId != null =>
        RealtimeChannels.participant(competitionId, organizationId),
      _ => null,
    };
    _HubEntry? entry;
    if (channelName != null) {
      entry = _entries.putIfAbsent(
        channelName,
        () => _HubEntry(_realtime.subscribePrivate(channelName)),
      );
      entry.references++;
    }
    final handle = _Handle(this, competitionId, channelName, entry);
    _handles.add(handle);
    return handle;
  }

  /// Call when the app returns to the foreground: every open handle is asked
  /// to resync (SCREENS.md §3.5).
  void notifyResumed() {
    for (final handle in _handles.toList()) {
      handle._requestResync();
    }
  }

  void _onConnection(RealtimeConnectionState state) {
    final reconnected =
        state == RealtimeConnectionState.connected &&
        _lastConnection != RealtimeConnectionState.connected;
    _lastConnection = state;
    for (final handle in _handles.toList()) {
      handle._connectionChanged(state);
      if (reconnected) handle._requestResync();
    }
  }

  Future<void> _release(_Handle handle) async {
    _handles.remove(handle);
    final name = handle.channelName;
    final entry = handle._entry;
    if (name == null || entry == null) return;
    entry.references--;
    if (entry.references <= 0) {
      _entries.remove(name);
      await entry.subscription.cancel();
    }
  }

  Future<void> dispose() async {
    await _connection.cancel();
    for (final handle in _handles.toList()) {
      await handle.release();
    }
  }
}

final class _HubEntry {
  _HubEntry(this.subscription);

  final RealtimeSubscription subscription;
  int references = 0;
}

final class _Handle implements CompetitionChannel {
  _Handle(this._hub, this.competitionId, this.channelName, this._entry) {
    final entry = _entry;
    if (entry != null) {
      _events = entry.subscription.events().listen(_onEvent);
    }
  }

  final CompetitionChannelHub _hub;
  final _HubEntry? _entry;
  final SnapshotVersionGate _gate = SnapshotVersionGate();
  final StreamController<Map<String, dynamic>> _live =
      StreamController.broadcast();
  final StreamController<Map<String, dynamic>> _offers =
      StreamController.broadcast();
  final StreamController<Map<String, dynamic>> _updates =
      StreamController.broadcast();
  final StreamController<Map<String, dynamic>> _comments =
      StreamController.broadcast();
  final StreamController<Map<String, dynamic>> _invitations =
      StreamController.broadcast();
  final StreamController<void> _resync = StreamController.broadcast();
  final StreamController<RealtimeConnectionState> _connection =
      StreamController.broadcast();
  StreamSubscription<RealtimeEvent>? _events;
  bool _released = false;

  @override
  final String competitionId;

  @override
  final String? channelName;

  void _onEvent(RealtimeEvent event) {
    switch (event.name) {
      case RealtimeEvents.liveUpdated:
        if (acceptSnapshot(event.data)) _live.add(event.data);
      case RealtimeEvents.offerAccepted:
        _offers.add(event.data);
      case RealtimeEvents.competitionUpdated:
        _updates.add(event.data);
      case RealtimeEvents.commentCreated:
        _comments.add(event.data);
      case RealtimeEvents.invitationUpdated:
        _invitations.add(event.data);
    }
  }

  @override
  bool acceptSnapshot(Map<String, dynamic> snapshot) =>
      _gate.accept(SnapshotVersionGate.versionOf(snapshot));

  @override
  int? get lastAppliedVersion => _gate.lastApplied;

  @override
  Stream<Map<String, dynamic>> get liveSnapshots => _live.stream;

  @override
  Stream<Map<String, dynamic>> get offersAccepted => _offers.stream;

  @override
  Stream<Map<String, dynamic>> get competitionUpdates => _updates.stream;

  @override
  Stream<Map<String, dynamic>> get commentsCreated => _comments.stream;

  @override
  Stream<Map<String, dynamic>> get invitationUpdates => _invitations.stream;

  @override
  Stream<void> get resyncRequests => _resync.stream;

  @override
  RealtimeConnectionState get connection => channelName == null
      ? RealtimeConnectionState.disconnected
      : _hub._realtime.currentState;

  @override
  Stream<RealtimeConnectionState> get connectionChanges => _connection.stream;

  void _requestResync() {
    if (!_released) _resync.add(null);
  }

  void _connectionChanged(RealtimeConnectionState state) {
    if (!_released && channelName != null) _connection.add(state);
  }

  @override
  Future<void> release() async {
    if (_released) return;
    _released = true;
    await _events?.cancel();
    await _hub._release(this);
    for (final controller in [
      _live,
      _offers,
      _updates,
      _comments,
      _invitations,
    ]) {
      await controller.close();
    }
    await _resync.close();
    await _connection.close();
  }
}
