import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/storage/token_store.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:dio/dio.dart';

/// Local-dev configuration with realtime disabled.
Env testEnv() => Env(
  apiBaseUrl: Uri.parse('http://api.test/api/app/v1'),
  broadcastingAuthUrl: Uri.parse('http://api.test/broadcasting/auth'),
  realtime: const RealtimeConfig(
    appKey: '',
    host: 'api.test',
    port: 8085,
    useTls: false,
  ),
);

/// A payload captured from the running API (`test/fixtures/api/{name}.json`):
/// `{status, body}`. Tokens are redacted.
Map<String, dynamic> fixtureEnvelope(String name) =>
    jsonDecode(File('test/fixtures/api/$name.json').readAsStringSync())
        as Map<String, dynamic>;

/// The response body of fixture [name].
Map<String, dynamic> fixtureBody(String name) =>
    fixtureEnvelope(name)['body'] as Map<String, dynamic>;

/// The `data` object of fixture [name].
Map<String, dynamic> fixtureData(String name) =>
    fixtureBody(name)['data'] as Map<String, dynamic>;

/// The `data` list of fixture [name].
List<Map<String, dynamic>> fixtureList(String name) =>
    (fixtureBody(name)['data'] as List).cast<Map<String, dynamic>>();

/// `Me` of a demo user, from the captured `GET /me`.
Me fixtureMe([String name = 'me_issuer']) => Me.fromJson(fixtureData(name));

/// The captured sign-in payload of Supplier A (token redacted).
AuthTokenPayload fixturePayload([String name = 'auth_login_supplier_a']) =>
    AuthTokenPayload.fromJson(fixtureData(name));

class InMemoryTokenStore implements TokenStore {
  InMemoryTokenStore([this.token]);

  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String token) async => this.token = token;

  @override
  Future<void> clear() async => token = null;
}

class InMemoryPreferencesStore implements PreferencesStore {
  InMemoryPreferencesStore([Map<String, String>? initial])
    : values = {...?initial};

  final Map<String, String> values;

  @override
  String? getString(String key) => values[key];

  @override
  Future<void> setString(String key, String value) async => values[key] = value;

  @override
  Future<void> remove(String key) async => values.remove(key);
}

/// Scripted [AuthRepository]: sign-in answers with [payload] (or throws
/// [error]); the other calls succeed.
class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({AuthTokenPayload? payload, this.error})
    : payload = payload ?? fixturePayload();

  AuthTokenPayload payload;
  ApiException? error;
  int logoutCalls = 0;
  RegistrationRequest? lastRegistration;

  Future<T> _answer<T>(T value) async {
    final failure = error;
    if (failure != null) throw failure;
    return value;
  }

  @override
  Future<AuthTokenPayload> login({
    required String email,
    required String password,
  }) => _answer(payload);

  @override
  Future<RegistrationResult> register(RegistrationRequest request) {
    lastRegistration = request;
    return _answer(RegistrationResult(email: request.email));
  }

  @override
  Future<OtpSent> sendOtp({required String email, required OtpPurpose purpose}) =>
      _answer(const OtpSent());

  @override
  Future<AuthTokenPayload> verifyEmail({
    required String email,
    required String code,
  }) => _answer(payload);

  @override
  Future<void> checkResetCode({required String email, required String code}) =>
      _answer(null);

  @override
  Future<void> forgotPassword(String email) => _answer(null);

  @override
  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) => _answer(null);

  @override
  Future<void> logout() async => logoutCalls++;

  @override
  Future<TeamInvitationLookup> lookupTeamInvitation(String token) =>
      throw UnimplementedError();

  @override
  Future<AuthTokenPayload> acceptTeamInvitation({
    required String token,
    required String password,
    required String passwordConfirmation,
  }) => _answer(payload);
}

/// A controllable [RealtimeClient]: tests push events into channels and
/// drive the connection state.
class FakeRealtimeClient implements RealtimeClient {
  final StreamController<RealtimeConnectionState> _state =
      StreamController.broadcast();

  /// Closed in [dispose].
  // ignore: close_sinks
  final Map<String, StreamController<RealtimeEvent>> _channels = {};

  /// Every channel subscribed, in order (with repeats).
  final List<String> subscribed = [];

  /// Every channel whose last subscription was cancelled.
  final List<String> unsubscribed = [];
  final Map<String, int> _references = {};
  int disconnects = 0;
  int suspends = 0;
  int resumes = 0;
  RealtimeConfig? configured;
  RealtimeConnectionState _current = RealtimeConnectionState.disconnected;

  @override
  Stream<RealtimeConnectionState> get connectionState => _state.stream;

  @override
  RealtimeConnectionState get currentState => _current;

  void setConnection(RealtimeConnectionState state) {
    _current = state;
    _state.add(state);
  }

  /// Delivers [name] with [data] on [channel].
  void emit(String channel, String name, Map<String, dynamic> data) =>
      _channels[channel]?.add(
        RealtimeEvent(channel: channel, name: name, data: data),
      );

  int references(String channel) => _references[channel] ?? 0;

  @override
  void configure(RealtimeConfig config) => configured = config;

  @override
  RealtimeSubscription subscribePrivate(String channel) {
    subscribed.add(channel);
    _references[channel] = references(channel) + 1;
    // Owned by [_channels]; closed in [dispose].
    // ignore: close_sinks
    final controller = _channels.putIfAbsent(
      channel,
      StreamController<RealtimeEvent>.broadcast,
    );
    return _FakeSubscription(this, channel, controller);
  }

  void _release(String channel) {
    _references[channel] = references(channel) - 1;
    if (references(channel) <= 0) unsubscribed.add(channel);
  }

  @override
  Future<void> suspend() async => suspends++;

  @override
  void resume() => resumes++;

  @override
  Future<void> disconnect() async => disconnects++;

  @override
  Future<void> dispose() async {
    await _state.close();
    for (final controller in _channels.values) {
      await controller.close();
    }
  }
}

class _FakeSubscription implements RealtimeSubscription {
  _FakeSubscription(this._owner, this.channel, this._controller);

  final FakeRealtimeClient _owner;
  final StreamController<RealtimeEvent> _controller;
  bool _cancelled = false;

  @override
  final String channel;

  @override
  Stream<RealtimeEvent> events([String? name]) => name == null
      ? _controller.stream
      : _controller.stream.where((event) => event.name == name);

  @override
  Future<void> cancel() async {
    if (_cancelled) return;
    _cancelled = true;
    _owner._release(channel);
  }
}

/// A captured request.
class RecordedRequest {
  RecordedRequest(this.options, this.body);

  final RequestOptions options;
  final String? body;
}

/// Dio adapter that answers every request with [handler] and records it.
class StubAdapter implements HttpClientAdapter {
  StubAdapter(this.handler);

  /// Returns (status, JSON body). Throw a [DioException] to simulate
  /// transport failures.
  final (int, Object?) Function(RequestOptions options) handler;
  final List<RecordedRequest> requests = [];

  /// Extra response headers (lower-case names).
  Map<String, List<String>> responseHeaders = {};

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    String? body;
    if (requestStream != null) {
      final bytes = await requestStream.expand((chunk) => chunk).toList();
      body = utf8.decode(bytes, allowMalformed: true);
    }
    requests.add(RecordedRequest(options, body));
    final (status, json) = handler(options);
    return ResponseBody.fromString(
      json == null ? '' : jsonEncode(json),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
        ...responseHeaders,
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Serves captured fixtures by request path: [routes] maps
/// `"METHOD /path"` (path relative to `/api/app/v1`, without the query) to a
/// fixture name. Unknown routes answer 404.
StubAdapter fixtureAdapter(Map<String, String> routes) => StubAdapter((options) {
  final path = options.uri.path.replaceFirst('/api/app/v1', '');
  final fixture = routes['${options.method} $path'];
  if (fixture == null) {
    return (
      404,
      {'message': 'Not found', 'code': 'not_found', 'errors': <String, Object>{}},
    );
  }
  final envelope = fixtureEnvelope(fixture);
  return (envelope['status'] as int, envelope['body']);
});
