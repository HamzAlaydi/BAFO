import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

void main() {
  late InMemoryTokenStore tokens;
  late StreamController<void> unauthorized;
  late List<String> calls;
  late Me me;
  Object? meError;

  setUp(() {
    tokens = InMemoryTokenStore();
    unauthorized = StreamController<void>.broadcast();
    calls = [];
    me = fixtureMe();
    meError = null;
  });

  tearDown(() => unauthorized.close());

  SessionCubit build({bool revokeFails = false}) => SessionCubit(
    tokens: tokens,
    unauthorized: unauthorized.stream,
    fetchMe: () async {
      calls.add('me');
      final error = meError;
      if (error != null) throw error;
      return me;
    },
    beforeSignOut: () async => calls.add('device'),
    revokeToken: () async {
      calls.add('revoke');
      if (revokeFails) throw Exception('offline');
    },
  );

  test('starts unknown', () {
    expect(build().state, const SessionUnknown());
  });

  blocTest<SessionCubit, SessionState>(
    'restores a stored token with GET /me',
    setUp: () => tokens.token = 'stored',
    build: build,
    act: (cubit) => cubit.restore(),
    expect: () => [SessionAuthenticated(me)],
    verify: (cubit) => expect(cubit.state.me?.user.email, me.user.email),
  );

  blocTest<SessionCubit, SessionState>(
    'a rejected stored token ends as expired and is forgotten',
    setUp: () {
      tokens.token = 'revoked';
      meError = const ApiException(code: 'unauthenticated', statusCode: 401);
    },
    build: build,
    act: (cubit) => cubit.restore(),
    expect: () => [const SessionUnauthenticated(expired: true)],
    verify: (_) => expect(tokens.token, isNull),
  );

  blocTest<SessionCubit, SessionState>(
    'offline at start keeps the session without Me',
    setUp: () {
      tokens.token = 'stored';
      meError = const ApiException(code: ApiErrorCode.network);
    },
    build: build,
    act: (cubit) => cubit.restore(),
    expect: () => [const SessionAuthenticated()],
    verify: (_) => expect(tokens.token, 'stored'),
  );

  blocTest<SessionCubit, SessionState>(
    'restores to unauthenticated without a token',
    build: build,
    act: (cubit) => cubit.restore(),
    expect: () => [const SessionUnauthenticated()],
    verify: (_) => expect(calls, isEmpty),
  );

  blocTest<SessionCubit, SessionState>(
    'signIn stores the token and keeps Me',
    build: build,
    act: (cubit) => cubit.signIn(AuthTokenPayload(token: 'new-token', me: me)),
    expect: () => [SessionAuthenticated(me)],
    verify: (_) => expect(tokens.token, 'new-token'),
  );

  blocTest<SessionCubit, SessionState>(
    'refreshMe replaces Me; failures keep the state',
    build: build,
    seed: () => const SessionAuthenticated(),
    act: (cubit) async {
      await cubit.refreshMe();
      meError = const ApiException(code: 'server_error', statusCode: 500);
      await cubit.refreshMe();
    },
    expect: () => [SessionAuthenticated(me)],
  );

  blocTest<SessionCubit, SessionState>(
    'signOut removes the device, revokes and forgets the token, even offline',
    setUp: () => tokens.token = 'stored',
    build: () => build(revokeFails: true),
    seed: () => const SessionAuthenticated(),
    act: (cubit) => cubit.signOut(),
    expect: () => [const SessionUnauthenticated(signedOut: true)],
    verify: (_) {
      expect(calls, ['device', 'revoke']);
      expect(tokens.token, isNull);
    },
  );

  blocTest<SessionCubit, SessionState>(
    'an API 401 expires an authenticated session',
    setUp: () => tokens.token = 'revoked',
    build: build,
    seed: () => const SessionAuthenticated(),
    act: (_) => unauthorized.add(null),
    expect: () => [const SessionUnauthenticated(expired: true)],
    verify: (_) => expect(tokens.token, isNull),
  );

  blocTest<SessionCubit, SessionState>(
    'a 401 while signed out changes nothing',
    build: build,
    seed: () => const SessionUnauthenticated(),
    act: (_) => unauthorized.add(null),
    expect: () => <SessionState>[],
  );
}
