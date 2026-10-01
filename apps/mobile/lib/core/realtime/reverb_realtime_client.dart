import 'dart:async';
import 'dart:math' as math;

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:dio/dio.dart' show Headers;
import 'package:flutter/foundation.dart';

/// [RealtimeClient] for Laravel Reverb, using the Pusher protocol client
/// `dart_pusher_channels` (it supports a custom host and port).
///
/// * Each channel is an entry with its own event stream. Entries outlive the
///   socket: after a reconnect, a [configure] change or [suspend]/[resume],
///   the channel is re-created on the new socket and keeps feeding the same
///   stream, so subscribers never re-subscribe themselves.
/// * Connects lazily on the first subscription and reconnects with
///   exponential backoff (1 s … 30 s).
/// * Private-channel auth is `POST /broadcasting/auth` (form fields
///   `socket_id`, `channel_name`) through [ApiClient], so it carries the
///   Bearer token and Accept-Language, and a revoked token ends the session
///   like any other 401 (ARCHITECTURE.md §9.1).
/// * Realtime stays off while no app key is known (before app-config).
final class ReverbRealtimeClient implements RealtimeClient {
  ReverbRealtimeClient({
    required this._config,
    required Uri authEndpoint,
    required ApiClient api,
  }) : _authDelegate = _ApiChannelAuthorizationDelegate(api, authEndpoint);

  static const Duration _maxBackoff = Duration(seconds: 30);

  RealtimeConfig _config;
  final _ApiChannelAuthorizationDelegate _authDelegate;
  final StreamController<RealtimeConnectionState> _state =
      StreamController.broadcast();
  final Map<String, _ChannelEntry> _channels = {};

  PusherChannelsClient? _client;
  StreamSubscription<PusherChannelsClientLifeCycleState>? _lifecycle;
  Timer? _retryTimer;
  int _failures = 0;
  bool _suspended = false;
  RealtimeConnectionState _current = RealtimeConnectionState.disconnected;

  @override
  Stream<RealtimeConnectionState> get connectionState => _state.stream;

  @override
  RealtimeConnectionState get currentState => _current;

  @override
  void configure(RealtimeConfig config) {
    if (config == _config) return;
    _config = config;
    _teardownSocket();
    _connectIfNeeded();
  }

  @override
  RealtimeSubscription subscribePrivate(String channel) {
    final entry = _channels.putIfAbsent(channel, () => _ChannelEntry(channel));
    entry.references++;
    final client = _connectIfNeeded();
    if (client != null && entry.channel == null) _attach(entry, client);
    return _ReverbSubscription(this, entry);
  }

  @override
  Future<void> suspend() async {
    _suspended = true;
    _teardownSocket();
  }

  @override
  void resume() {
    if (!_suspended) return;
    _suspended = false;
    _connectIfNeeded();
  }

  void _release(_ChannelEntry entry) {
    entry.references--;
    if (entry.references > 0) return;
    _channels.remove(entry.name);
    _detach(entry, unsubscribe: true);
    unawaited(entry.events.close());
  }

  /// The socket, created when there is something to listen to.
  PusherChannelsClient? _connectIfNeeded() {
    if (_suspended || !_config.enabled || _channels.isEmpty) return null;
    final existing = _client;
    if (existing != null) return existing;

    final client = PusherChannelsClient.websocket(
      options: PusherChannelsOptions.fromHost(
        scheme: _config.useTls ? 'wss' : 'ws',
        host: _config.host,
        port: _config.port,
        key: _config.appKey,
      ),
      connectionErrorHandler: (error, trace, refresh) {
        _emit(RealtimeConnectionState.failed);
        _retryTimer?.cancel();
        _retryTimer = Timer(_backoff(_failures++), refresh);
      },
    );
    _lifecycle = client.lifecycleStream.listen(_onLifecycle);
    _client = client;
    for (final entry in _channels.values) {
      _attach(entry, client);
    }
    unawaited(client.connect());
    return client;
  }

  void _attach(_ChannelEntry entry, PusherChannelsClient client) {
    final channel = client.privateChannel(
      'private-${entry.name}',
      authorizationDelegate: _authDelegate,
    );
    entry.channel = channel;
    entry.binding = channel
        .bindToAll()
        .where((event) => !event.name.startsWith('pusher'))
        .map(
          (event) => RealtimeEvent(
            channel: entry.name,
            name: event.name,
            data: event.tryGetDataAsMap() ?? const {},
          ),
        )
        .listen(entry.events.add);
    channel.subscribeIfNotUnsubscribed();
  }

  void _detach(_ChannelEntry entry, {required bool unsubscribe}) {
    unawaited(entry.binding?.cancel());
    entry.binding = null;
    if (unsubscribe) entry.channel?.unsubscribe();
    entry.channel = null;
  }

  void _teardownSocket() {
    _retryTimer?.cancel();
    _retryTimer = null;
    for (final entry in _channels.values) {
      _detach(entry, unsubscribe: false);
    }
    unawaited(_lifecycle?.cancel());
    _lifecycle = null;
    final client = _client;
    _client = null;
    _failures = 0;
    client?.dispose();
    _emit(RealtimeConnectionState.disconnected);
  }

  void _onLifecycle(PusherChannelsClientLifeCycleState state) {
    switch (state) {
      case PusherChannelsClientLifeCycleState.establishedConnection:
        _failures = 0;
        for (final entry in _channels.values) {
          entry.channel?.subscribeIfNotUnsubscribed();
        }
        _emit(RealtimeConnectionState.connected);
      case PusherChannelsClientLifeCycleState.pendingConnection:
      case PusherChannelsClientLifeCycleState.reconnecting:
        _emit(RealtimeConnectionState.connecting);
      case PusherChannelsClientLifeCycleState.connectionError:
      case PusherChannelsClientLifeCycleState.gotPusherError:
        _emit(RealtimeConnectionState.failed);
      case PusherChannelsClientLifeCycleState.disconnected:
      case PusherChannelsClientLifeCycleState.disposed:
      case PusherChannelsClientLifeCycleState.inactive:
        _emit(RealtimeConnectionState.disconnected);
    }
  }

  void _emit(RealtimeConnectionState state) {
    if (state == _current) return;
    _current = state;
    if (!_state.isClosed) _state.add(state);
  }

  static Duration _backoff(int failures) {
    final seconds = math.pow(2, math.min(failures, 5)).toInt();
    return Duration(seconds: math.min(seconds, _maxBackoff.inSeconds));
  }

  @override
  Future<void> disconnect() async {
    _teardownSocket();
    final entries = _channels.values.toList();
    _channels.clear();
    for (final entry in entries) {
      await entry.events.close();
    }
    _suspended = false;
  }

  @override
  Future<void> dispose() async {
    await disconnect();
    await _state.close();
  }
}

final class _ChannelEntry {
  _ChannelEntry(this.name);

  final String name;

  /// Closed by the client when the entry is released or on disconnect.
  // ignore: close_sinks
  final StreamController<RealtimeEvent> events = StreamController.broadcast();
  PrivateChannel? channel;

  /// Cancelled in `_detach`.
  // ignore: cancel_subscriptions
  StreamSubscription<RealtimeEvent>? binding;
  int references = 0;
}

final class _ReverbSubscription implements RealtimeSubscription {
  _ReverbSubscription(this._owner, this._entry);

  final ReverbRealtimeClient _owner;
  final _ChannelEntry _entry;
  bool _cancelled = false;

  @override
  String get channel => _entry.name;

  @override
  Stream<RealtimeEvent> events([String? name]) {
    final stream = _entry.events.stream;
    return name == null ? stream : stream.where((event) => event.name == name);
  }

  @override
  Future<void> cancel() async {
    if (_cancelled) return;
    _cancelled = true;
    _owner._release(_entry);
  }
}

/// Channel auth through [ApiClient]. Laravel answers `{"auth": "key:sig"}`.
final class _ApiChannelAuthorizationDelegate
    implements
        EndpointAuthorizableChannelAuthorizationDelegate<
          PrivateChannelAuthorizationData
        > {
  _ApiChannelAuthorizationDelegate(this._api, this._endpoint);

  final ApiClient _api;
  final Uri _endpoint;

  @override
  EndpointAuthFailedCallback? get onAuthFailed =>
      (exception, trace) => debugPrint('Channel auth failed: $exception');

  @override
  Future<PrivateChannelAuthorizationData> authorizationData(
    String socketId,
    String channelName,
  ) async {
    final response = await _api.post(
      _endpoint.toString(),
      body: {'socket_id': socketId, 'channel_name': channelName},
      contentType: Headers.formUrlEncodedContentType,
    );
    final auth = response.dataMap['auth'];
    if (auth is! String || auth.isEmpty) {
      throw StateError('Channel auth for $channelName returned no auth key');
    }
    return PrivateChannelAuthorizationData(authKey: auth);
  }
}
