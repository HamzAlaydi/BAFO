import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/legal_cubit.dart';
import 'package:bafo/features/auth/presentation/onboarding_cubit.dart';
import 'package:bafo/features/auth/presentation/otp_cubit.dart';
import 'package:bafo/features/auth/presentation/password_reset_cubits.dart';
import 'package:bafo/features/auth/presentation/register_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockAuth extends Mock implements AuthRepository {}

class _MockLookups extends Mock implements LookupsRepository {}

class _MockLegal extends Mock implements LegalRepository {}

const _request = RegistrationRequest(
  name: 'أحمد',
  email: 'a@b.sa',
  phone: '+966512345678',
  password: 'Abcdef1!',
  passwordConfirmation: 'Abcdef1!',
  acceptTerms: true,
  acceptPrivacy: true,
  organization: OrganizationRegistration(
    name: 'شركة',
    crNumber: '1234567890',
    regionId: 'r',
    city: 'الرياض',
  ),
);

void main() {
  final now = DateTime.utc(2026, 9, 29, 12);
  late _MockAuth auth;
  late SessionCubit session;
  late AuthTokenPayload payload;

  setUpAll(() {
    registerFallbackValue(_request);
    registerFallbackValue(OtpPurpose.emailVerification);
  });

  setUp(() {
    auth = _MockAuth();
    payload = fixturePayload('auth_otp_verify');
    session = SessionCubit(
      tokens: InMemoryTokenStore(),
      unauthorized: const Stream.empty(),
      fetchMe: () async => payload.me,
    );
  });

  tearDown(() => session.close());

  group('OnboardingCubit', () {
    late InMemoryPreferencesStore preferences;

    setUp(() => preferences = InMemoryPreferencesStore());

    blocTest<OnboardingCubit, OnboardingState>(
      'tracks the page and is remembered once completed',
      build: () => OnboardingCubit(preferences),
      act: (cubit) async {
        expect(OnboardingCubit.isDue(preferences), isTrue);
        cubit
          ..pageChanged(1)
          ..pageChanged(9);
        await cubit.complete();
      },
      expect: () => const [
        OnboardingState(page: 1),
        OnboardingState(page: 2),
        OnboardingState(page: 2, completed: true),
      ],
      verify: (_) {
        expect(preferences.getString(PreferenceKeys.onboardingSeen), '1');
        expect(OnboardingCubit.isDue(preferences), isFalse);
      },
    );
  });

  group('RegisterCubit', () {
    late _MockLookups lookups;
    final data = Lookups.fromJson(fixtureData('lookups'));

    setUp(() {
      lookups = _MockLookups();
      when(() => lookups.lookups(refresh: any(named: 'refresh')))
          .thenAnswer((_) async => data);
    });

    RegisterCubit build() =>
        RegisterCubit(auth: auth, lookups: lookups, now: () => now);

    blocTest<RegisterCubit, RegisterState>(
      'loads lookups and moves through the three steps',
      build: build,
      act: (cubit) async {
        await cubit.loadLookups();
        cubit
          ..next()
          ..next()
          ..next()
          ..back();
      },
      expect: () => [
        RegisterState(lookups: data),
        RegisterState(lookups: data, step: 1),
        RegisterState(lookups: data, step: 2),
        RegisterState(lookups: data, step: 1),
      ],
    );

    blocTest<RegisterCubit, RegisterState>(
      'a successful submit carries the e-mail to verify',
      setUp: () => when(() => auth.register(any())).thenAnswer(
        (_) async => RegistrationResult.fromJson(fixtureData('auth_register')),
      ),
      build: build,
      seed: () => const RegisterState(step: 2),
      act: (cubit) => cubit.submit(_request),
      verify: (cubit) {
        expect(cubit.state.status, RegisterStatus.succeeded);
        expect(cubit.state.result?.email, contains('@demo.bafo.test'));
      },
    );

    blocTest<RegisterCubit, RegisterState>(
      'server field errors jump back to the earliest step holding them',
      setUp: () => when(() => auth.register(any())).thenThrow(
        ApiException.fromResponse(
          statusCode: 422,
          body: fixtureBody('error_register_taken'),
        ),
      ),
      build: build,
      seed: () => const RegisterState(step: 2),
      act: (cubit) => cubit.submit(_request),
      verify: (cubit) {
        final state = cubit.state;
        expect(state.status, RegisterStatus.editing);
        expect(state.step, 0);
        expect(state.fieldError('email'), isNotNull);
        expect(state.fieldError('organization.cr_number'), isNotNull);
        expect(state.error, isNull);
      },
    );

    blocTest<RegisterCubit, RegisterState>(
      'editing a field drops its server error',
      build: build,
      seed: () => const RegisterState(
        fieldErrors: {
          'email': ['taken'],
          'phone': ['bad'],
        },
      ),
      act: (cubit) => cubit.fieldEdited('email'),
      expect: () => const [
        RegisterState(
          fieldErrors: {
            'phone': ['bad'],
          },
        ),
      ],
    );

    blocTest<RegisterCubit, RegisterState>(
      'a 429 without fields is a general error with a cooldown',
      setUp: () => when(() => auth.register(any())).thenThrow(
        const ApiException(
          code: 'too_many_requests',
          statusCode: 429,
          details: {'retry_after_seconds': 30},
        ),
      ),
      build: build,
      seed: () => const RegisterState(step: 2),
      act: (cubit) => cubit.submit(_request),
      verify: (cubit) {
        expect(cubit.state.error?.code, 'too_many_requests');
        expect(cubit.state.retryAt, now.add(const Duration(seconds: 30)));
        expect(cubit.state.step, 2);
      },
    );

    test('field paths map to their steps', () {
      expect(RegisterCubit.stepOf('password_confirmation'), 0);
      expect(RegisterCubit.stepOf('organization.vat_number'), 1);
      expect(RegisterCubit.stepOf('organization.national_address.street'), 2);
      expect(RegisterCubit.stepOf('organization.category_ids.3'), 2);
      expect(RegisterCubit.stepOf('accept_terms'), 2);
      expect(
        RegisterCubit.stepOfFields(['accept_terms', 'organization.city']),
        1,
      );
    });
  });

  group('OtpCubit', () {
    OtpCubit build({DateTime Function()? clock}) => OtpCubit(
      auth: auth,
      session: session,
      email: 'new@company.sa',
      expiresAt: now.add(const Duration(minutes: 10)),
      now: clock ?? () => now,
    );

    blocTest<OtpCubit, OtpState>(
      'a valid code signs in',
      setUp: () =>
          when(() => auth.verifyEmail(email: 'new@company.sa', code: '123456'))
              .thenAnswer((_) async => payload),
      build: build,
      act: (cubit) => cubit.verify('123456'),
      verify: (cubit) {
        expect(cubit.state.status, OtpStatus.verified);
        expect(session.state, SessionAuthenticated(payload.me));
      },
    );

    blocTest<OtpCubit, OtpState>(
      'otp_invalid stays on the screen with the error',
      setUp: () =>
          when(
            () => auth.verifyEmail(
              email: any(named: 'email'),
              code: any(named: 'code'),
            ),
          ).thenThrow(
            ApiException.fromResponse(
              statusCode: 422,
              body: fixtureBody('error_otp_invalid'),
            ),
          ),
      build: build,
      act: (cubit) => cubit.verify('000000'),
      verify: (cubit) {
        expect(cubit.state.status, OtpStatus.idle);
        expect(cubit.state.error?.code, 'otp_invalid');
        expect(session.state.isAuthenticated, isFalse);
      },
    );

    test('resend waits for the cooldown, then sends and restarts it', () async {
      var clock = now;
      when(
        () => auth.sendOtp(
          email: any(named: 'email'),
          purpose: any(named: 'purpose'),
        ),
      ).thenAnswer(
        (_) async => OtpSent(expiresAt: now.add(const Duration(minutes: 20))),
      );
      final cubit = build(clock: () => clock);
      await cubit.resend();
      verifyNever(
        () => auth.sendOtp(
          email: any(named: 'email'),
          purpose: any(named: 'purpose'),
        ),
      );

      clock = now.add(const Duration(seconds: 61));
      await cubit.resend();
      expect(cubit.state.resent, isTrue);
      expect(cubit.state.expiresAt, now.add(const Duration(minutes: 20)));
      expect(cubit.state.resendAvailableAt, clock.add(OtpCubit.resendCooldown));
      await cubit.close();
    });

    blocTest<OtpCubit, OtpState>(
      'otp_resend_cooldown moves the resend to the server time',
      setUp: () =>
          when(
            () => auth.sendOtp(
              email: any(named: 'email'),
              purpose: any(named: 'purpose'),
            ),
          ).thenThrow(
            ApiException.fromResponse(
              statusCode: 429,
              body: fixtureBody('error_otp_resend_cooldown'),
            ),
          ),
      build: () => OtpCubit(
        auth: auth,
        session: session,
        email: 'a@b.sa',
        codeJustSent: false,
        now: () => now,
      ),
      act: (cubit) => cubit.resend(),
      verify: (cubit) {
        expect(cubit.state.error?.code, 'otp_resend_cooldown');
        expect(
          cubit.state.resendAvailableAt,
          now.add(const Duration(seconds: 51)),
        );
      },
    );
  });

  group('ForgotPasswordCubit', () {
    blocTest<ForgotPasswordCubit, ForgotPasswordState>(
      'always continues with the e-mail (202)',
      setUp: () =>
          when(() => auth.forgotPassword(any())).thenAnswer((_) async {}),
      build: () => ForgotPasswordCubit(auth),
      act: (cubit) => cubit.submit(' a@b.sa '),
      expect: () => const [
        ForgotPasswordSubmitting(),
        ForgotPasswordSent('a@b.sa'),
      ],
    );

    blocTest<ForgotPasswordCubit, ForgotPasswordState>(
      'network errors are reported',
      setUp: () =>
          when(() => auth.forgotPassword(any()))
              .thenThrow(const ApiException(code: ApiErrorCode.network)),
      build: () => ForgotPasswordCubit(auth),
      act: (cubit) => cubit.submit('a@b.sa'),
      expect: () => const [
        ForgotPasswordSubmitting(),
        ForgotPasswordFailure(ApiException(code: ApiErrorCode.network)),
      ],
    );
  });

  group('ResetPasswordCubit', () {
    ResetPasswordCubit build() =>
        ResetPasswordCubit(auth: auth, email: 'a@b.sa', now: () => now);

    blocTest<ResetPasswordCubit, ResetPasswordState>(
      'an early check marks the code valid, then the reset succeeds',
      setUp: () {
        when(() => auth.checkResetCode(email: 'a@b.sa', code: '123456'))
            .thenAnswer((_) async {});
        when(
          () => auth.resetPassword(
            email: 'a@b.sa',
            code: '123456',
            password: 'Abcdef1!',
            passwordConfirmation: 'Abcdef1!',
          ),
        ).thenAnswer((_) async {});
      },
      build: build,
      act: (cubit) async {
        await cubit.checkCode('123456');
        await cubit.submit(
          code: '123456',
          password: 'Abcdef1!',
          passwordConfirmation: 'Abcdef1!',
        );
      },
      verify: (cubit) {
        expect(cubit.state.status, ResetStatus.done);
        expect(cubit.state.codeValid, isTrue);
      },
    );

    blocTest<ResetPasswordCubit, ResetPasswordState>(
      'code errors attach to the code, field errors to their fields',
      setUp: () {
        when(
          () => auth.checkResetCode(
            email: any(named: 'email'),
            code: any(named: 'code'),
          ),
        ).thenThrow(
          ApiException.fromResponse(
            statusCode: 422,
            body: fixtureBody('error_otp_check_invalid'),
          ),
        );
        when(
          () => auth.resetPassword(
            email: any(named: 'email'),
            code: any(named: 'code'),
            password: any(named: 'password'),
            passwordConfirmation: any(named: 'passwordConfirmation'),
          ),
        ).thenThrow(
          const ApiException(
            code: 'validation_failed',
            statusCode: 422,
            fieldErrors: {
              'password': ['weak'],
            },
          ),
        );
      },
      build: build,
      act: (cubit) async {
        await cubit.checkCode('000000');
        expect(cubit.state.codeError?.code, 'otp_invalid');
        cubit.codeEdited();
        expect(cubit.state.codeError, isNull);
        await cubit.submit(
          code: '123456',
          password: 'weak',
          passwordConfirmation: 'weak',
        );
      },
      verify: (cubit) {
        expect(cubit.state.fieldError('password'), 'weak');
        expect(cubit.state.error, isNull);
      },
    );
  });

  group('LegalCubit', () {
    late _MockLegal legal;

    setUp(() => legal = _MockLegal());

    blocTest<LegalCubit, LegalState>(
      'loads the document in the request language',
      setUp: () => when(() => legal.document(LegalCode.terms)).thenAnswer(
        (_) async => LegalDocument.fromJson(fixtureData('legal_terms')),
      ),
      build: () => LegalCubit(legal, LegalCode.terms),
      act: (cubit) => cubit.load(),
      expect: () => [
        const LegalLoading(),
        LegalLoaded(LegalDocument.fromJson(fixtureData('legal_terms'))),
      ],
    );

    blocTest<LegalCubit, LegalState>(
      'an unpublished document is unavailable, not an error',
      setUp: () => when(() => legal.document(LegalCode.refund))
          .thenThrow(const ApiException(code: 'not_found', statusCode: 404)),
      build: () => LegalCubit(legal, LegalCode.refund),
      act: (cubit) => cubit.load(),
      expect: () => const [LegalLoading(), LegalUnavailable()],
    );
  });

  test('Me helpers used by the auth flow', () {
    expect(payload.me.can(Permissions.billingView), isTrue);
  });
}
