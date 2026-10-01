import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

void main() {
  late InMemoryTokenStore tokens;
  late ServerClock clock;
  late String language;
  late int unauthorizedCalls;
  late AppGateCubit gate;

  setUp(() {
    gate = AppGateCubit();
    tokens = InMemoryTokenStore();
    clock = ServerClock();
    language = 'ar';
    unauthorizedCalls = 0;
  });

  ApiClient clientFor(StubAdapter adapter) => ApiClient.create(
    env: testEnv(),
    tokens: tokens,
    clock: clock,
    languageCode: () => language,
    onUnauthorized: () => unauthorizedCalls++,
    gate: gate,
    client: const ClientInfo(platform: 'android', appVersion: '1.2.3'),
    httpClientAdapter: adapter,
  );

  test('sends JSON, language, client and bearer headers', () async {
    tokens.token = 'secret-token';
    final adapter = StubAdapter((_) => (200, {'data': <String, Object>{}}));

    await clientFor(adapter).get('me');
    language = 'en';
    await clientFor(adapter).get('/me');

    final first = adapter.requests.first.options;
    expect(first.uri.toString(), 'http://api.test/api/app/v1/me');
    expect(first.headers['Accept'], 'application/json');
    expect(first.headers['Accept-Language'], 'ar');
    expect(first.headers['Authorization'], 'Bearer secret-token');
    expect(first.headers['X-Platform'], 'android');
    expect(first.headers['X-App-Version'], '1.2.3');
    expect(first.uri.queryParameters, isEmpty, reason: 'no token in URLs');
    final requestId = first.headers['X-Request-Id'] as String;
    expect(requestId, matches(RegExp(r'^[0-9a-f]{32}$')));

    final second = adapter.requests.last.options;
    expect(second.uri.toString(), 'http://api.test/api/app/v1/me');
    expect(second.headers['Accept-Language'], 'en');
    expect(second.headers['X-Request-Id'], isNot(requestId));
  });

  test('omits Authorization without a token', () async {
    final adapter = StubAdapter((_) => (200, {'data': null}));
    await clientFor(adapter).post('auth/login', body: {'email': 'a@b.sa'});
    expect(
      adapter.requests.single.options.headers.containsKey('Authorization'),
      isFalse,
    );
  });

  test('unwraps the data/meta envelope', () async {
    final adapter = StubAdapter(
      (_) => (
        200,
        {
          'data': [
            {'id': '01J'},
          ],
          'meta': {
            'pagination': {'total': 1},
          },
        },
      ),
    );
    final response = await clientFor(adapter).get('competitions');
    expect(response.dataList.single['id'], '01J');
    expect(response.meta['pagination'], {'total': 1});
  });

  test('syncs the server clock from meta.server_time', () async {
    final serverTime = DateTime.now().toUtc().add(const Duration(minutes: 5));
    final adapter = StubAdapter(
      (_) => (
        200,
        {
          'data': <String, Object>{},
          'meta': {'server_time': serverTime.toIso8601String()},
        },
      ),
    );
    await clientFor(adapter).get('time');

    expect(clock.isSynced, isTrue);
    expect(clock.offset.inSeconds, inInclusiveRange(299, 300));
  });

  test('throws ApiException with the server envelope', () async {
    final adapter = StubAdapter(
      (_) => (
        422,
        {
          'message': 'Invalid',
          'code': 'validation_failed',
          'errors': {
            'email': ['Taken'],
          },
        },
      ),
    );
    await expectLater(
      clientFor(adapter).post('auth/login'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.code, 'code', 'validation_failed')
            .having((e) => e.fieldError('email'), 'email', 'Taken'),
      ),
    );
    expect(unauthorizedCalls, 0);
  });

  test('reports a 401 on an authenticated request', () async {
    tokens.token = 'revoked';
    final adapter = StubAdapter(
      (_) => (401, {'message': 'Unauthenticated.', 'code': 'unauthenticated'}),
    );
    await expectLater(
      clientFor(adapter).get('me'),
      throwsA(isA<ApiException>()),
    );
    expect(unauthorizedCalls, 1);
  });

  test('a 401 without a token (failed login) does not end a session', () async {
    final adapter = StubAdapter(
      (_) => (401, {'message': 'Bad credentials', 'code': 'unauthenticated'}),
    );
    await expectLater(
      clientFor(adapter).post('auth/login'),
      throwsA(isA<ApiException>()),
    );
    expect(unauthorizedCalls, 0);
  });

  test('carries details from the error envelope', () async {
    final adapter = StubAdapter(
      (_) => (
        422,
        {
          'message': 'Offer step not met',
          'code': 'offer_step_not_met',
          'errors': <String, Object>{},
          'details': {'required_amount_minor': 9750000},
        },
      ),
    );
    await expectLater(
      clientFor(adapter).post('competitions/01j/offers'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.details['required_amount_minor'],
          'required_amount_minor',
          9750000,
        ),
      ),
    );
  });

  test('426 closes the gate for a required update', () async {
    final adapter = StubAdapter(
      (_) => (426, {'message': 'Update', 'code': 'app_version_unsupported'}),
    );
    await expectLater(
      clientFor(adapter).get('me'),
      throwsA(isA<ApiException>()),
    );
    expect(gate.state, const AppGateUpdateRequired());
  });

  test('503 maintenance closes the gate with the server message', () async {
    final adapter = StubAdapter(
      (_) => (503, {'message': 'صيانة مجدولة', 'code': 'maintenance'}),
    );
    await expectLater(
      clientFor(adapter).get('me'),
      throwsA(isA<ApiException>()),
    );
    expect(gate.state, const AppGateMaintenance('صيانة مجدولة'));
  });

  test('a 503 that is not maintenance leaves the gate open', () async {
    final adapter = StubAdapter(
      (_) => (503, {'message': 'Down', 'code': 'service_unavailable'}),
    );
    await expectLater(
      clientFor(adapter).get('me'),
      throwsA(isA<ApiException>()),
    );
    expect(gate.state, const AppGateOpen());
  });

  test('posts form fields to an absolute URL (channel auth)', () async {
    tokens.token = 't';
    final adapter = StubAdapter((_) => (200, {'auth': 'key:signature'}));
    final response = await clientFor(adapter).post(
      'http://api.test/broadcasting/auth',
      body: {'socket_id': '1.2', 'channel_name': 'private-competition.01J'},
      contentType: 'application/x-www-form-urlencoded',
    );

    final request = adapter.requests.single;
    expect(request.options.uri.toString(), 'http://api.test/broadcasting/auth');
    expect(request.body, 'socket_id=1.2&channel_name=private-competition.01J');
    expect(response.dataMap['auth'], 'key:signature');
  });
}
