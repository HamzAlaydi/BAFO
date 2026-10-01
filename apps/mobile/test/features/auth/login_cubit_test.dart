import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/presentation/login_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late InMemoryTokenStore tokens;
  late _MockAuthRepository auth;
  late SessionCubit session;
  late AuthTokenPayload payload;
  final now = DateTime.utc(2026, 9, 29, 12);

  setUp(() {
    tokens = InMemoryTokenStore();
    auth = _MockAuthRepository();
    payload = fixturePayload();
    session = SessionCubit(
      tokens: tokens,
      unauthorized: const Stream.empty(),
      fetchMe: () async => payload.me,
    );
  });

  tearDown(() => session.close());

  LoginCubit build() =>
      LoginCubit(auth: auth, session: session, now: () => now);

  void loginAnswers(Future<AuthTokenPayload> Function() answer) => when(
    () => auth.login(
      email: any(named: 'email'),
      password: any(named: 'password'),
    ),
  ).thenAnswer((_) => answer());

  blocTest<LoginCubit, LoginState>(
    'signs in with the trimmed e-mail, stores the token and keeps Me',
    setUp: () => loginAnswers(() async => payload),
    build: build,
    act: (cubit) => cubit.submit(email: ' user@company.sa ', password: 'pw'),
    expect: () => const [LoginSubmitting(), LoginSuccess()],
    verify: (_) {
      verify(() => auth.login(email: 'user@company.sa', password: 'pw'))
          .called(1);
      expect(tokens.token, payload.token);
      expect(session.state, SessionAuthenticated(payload.me));
    },
  );

  const invalid = ApiException(
    code: 'validation_failed',
    message: 'Invalid',
    statusCode: 422,
    fieldErrors: {
      'email': ['The e-mail format is invalid.'],
    },
  );

  blocTest<LoginCubit, LoginState>(
    'exposes server field errors',
    setUp: () => loginAnswers(() async => throw invalid),
    build: build,
    act: (cubit) => cubit.submit(email: 'a@b', password: 'pw'),
    expect: () => const [LoginSubmitting(), LoginFailure(invalid)],
    verify: (cubit) {
      expect(cubit.state.fieldError('email'), 'The e-mail format is invalid.');
      expect(cubit.state.generalError, isNull);
      expect(session.state.isAuthenticated, isFalse);
    },
  );

  blocTest<LoginCubit, LoginState>(
    'reports invalid_credentials (no field) as a general error',
    setUp: () => loginAnswers(
      () async => throw ApiException.fromResponse(
        statusCode: 401,
        body: fixtureBody('error_invalid_credentials'),
      ),
    ),
    build: build,
    act: (cubit) => cubit.submit(email: 'a@b.sa', password: 'wrong'),
    verify: (cubit) =>
        expect(cubit.state.generalError?.code, 'invalid_credentials'),
  );

  blocTest<LoginCubit, LoginState>(
    'email_not_verified continues to verification with the expiry',
    setUp: () => loginAnswers(
      () async => throw ApiException.fromResponse(
        statusCode: 403,
        body: fixtureBody('error_email_not_verified'),
      ),
    ),
    build: build,
    act: (cubit) => cubit.submit(email: 'new@company.sa', password: 'pw'),
    expect: () => [
      const LoginSubmitting(),
      LoginNeedsVerification(
        email: 'new@company.sa',
        otpExpiresAt: DateTime.utc(2026, 9, 29, 18, 5, 23),
      ),
    ],
  );

  blocTest<LoginCubit, LoginState>(
    'a 429 keeps the submit disabled for Retry-After',
    setUp: () => loginAnswers(
      () async => throw const ApiException(
        code: 'too_many_requests',
        statusCode: 429,
        details: {'retry_after_seconds': 42},
      ),
    ),
    build: build,
    act: (cubit) => cubit.submit(email: 'a@b.sa', password: 'pw'),
    verify: (cubit) {
      final state = cubit.state as LoginFailure;
      expect(state.retryAt, now.add(const Duration(seconds: 42)));
    },
  );

  blocTest<LoginCubit, LoginState>(
    'editing clears a failure',
    build: build,
    seed: () => const LoginFailure(invalid),
    act: (cubit) => cubit.edited(),
    expect: () => const [LoginInitial()],
  );

  test('ignores a second submit while one is running', () async {
    final gate = Completer<AuthTokenPayload>();
    loginAnswers(() => gate.future);
    final cubit = build();

    final first = cubit.submit(email: 'a@b.sa', password: 'pw');
    await cubit.submit(email: 'a@b.sa', password: 'pw');
    gate.complete(payload);
    await first;

    verify(
      () => auth.login(
        email: any(named: 'email'),
        password: any(named: 'password'),
      ),
    ).called(1);
    expect(cubit.state, const LoginSuccess());
    await cubit.close();
  });
}
