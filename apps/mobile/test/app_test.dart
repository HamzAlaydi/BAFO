import 'dart:async';

import 'package:bafo/app/app.dart';
import 'package:bafo/app/dependencies.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/router/app_shell.dart';
import 'package:bafo/core/router/gate_screens.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/auth/presentation/login_screen.dart';
import 'package:bafo/features/auth/presentation/welcome_screen.dart';
import 'package:bafo/features/participant/presentation/participant_competition_screen.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'helpers/fakes.dart';

void main() {
  late AppDependencies deps;
  late InMemoryTokenStore tokens;
  late FakeRealtimeClient realtime;
  late StubAdapter http;

  const onboarded = {PreferenceKeys.onboardingSeen: '1'};
  final competitionId = fixtureData(
    'competition_supplier_a_live_final_window',
  )['id'] as String;

  Future<void> pumpApp(
    WidgetTester tester, {
    Map<String, String>? preferences = onboarded,
    String? token,
  }) async {
    tokens = InMemoryTokenStore(token);
    realtime = FakeRealtimeClient();
    http = fixtureAdapter({
      'GET /app-config': 'app_config',
      'GET /time': 'time',
      'GET /me': 'me_issuer',
      'POST /auth/login': 'auth_login_supplier_a',
      'GET /notifications/unread-count': 'notifications_unread_count',
      'GET /competitions/$competitionId':
          'competition_supplier_a_live_final_window',
      'GET /competitions/$competitionId/attachments':
          'attachments_supplier_a_live_initial',
    });
    deps = AppDependencies.wire(
      env: testEnv(),
      tokens: tokens,
      preferences: InMemoryPreferencesStore(preferences),
      realtime: realtime,
      httpClientAdapter: http,
    );
    addTearDown(deps.dispose);
    await tester.pumpWidget(BafoApp(dependencies: deps));
    await tester.pumpAndSettle();
  }

  TextDirection directionOf(WidgetTester tester, Type screen) =>
      Directionality.of(tester.element(find.byType(screen)));

  Future<void> enter(WidgetTester tester, String key, String text) =>
      tester.enterText(
        find.descendant(
          of: find.byKey(Key(key)),
          matching: find.byType(EditableText),
        ),
        text,
      );

  testWidgets('starts in Arabic, right-to-left, on the login screen', (
    tester,
  ) async {
    await pumpApp(tester);

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(directionOf(tester, LoginScreen), TextDirection.rtl);
    expect(find.text('تسجيل الدخول'), findsWidgets);
    expect(find.text('البريد الإلكتروني'), findsOneWidget);
  });

  testWidgets('the first run shows the welcome slides once', (tester) async {
    await pumpApp(tester, preferences: {});
    expect(find.byType(WelcomeScreen), findsOneWidget);

    await tester.tap(find.byKey(const Key('welcome.skip')));
    await tester.pumpAndSettle();

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(deps.preferences.getString(PreferenceKeys.onboardingSeen), '1');
  });

  testWidgets('a saved English preference renders left-to-right', (
    tester,
  ) async {
    await pumpApp(tester, preferences: {
      ...onboarded,
      PreferenceKeys.locale: 'en',
    });

    expect(directionOf(tester, LoginScreen), TextDirection.ltr);
    expect(find.text('Sign in'), findsWidgets);
  });

  testWidgets('the pre-login switch flips the language and remembers it', (
    tester,
  ) async {
    await pumpApp(tester);

    await tester.tap(find.byKey(const Key('login.language')));
    await tester.pumpAndSettle();

    expect(directionOf(tester, LoginScreen), TextDirection.ltr);
    expect(deps.preferences.getString(PreferenceKeys.locale), 'en');
  });

  testWidgets('client-side validation runs before any request', (tester) async {
    await pumpApp(tester);
    final before = http.requests.length;

    await tester.tap(find.byKey(const Key('login.submit')));
    await tester.pumpAndSettle();

    expect(find.text('هذا الحقل مطلوب.'), findsNWidgets(2));
    expect(tokens.token, isNull);
    expect(
      http.requests.skip(before).where((r) => r.options.path.contains('login')),
      isEmpty,
    );
  });

  testWidgets('signing in opens the home tab with the bottom navigation', (
    tester,
  ) async {
    await pumpApp(tester);

    await enter(tester, 'login.email', 'supplier-a.owner@demo.bafo.test');
    await enter(tester, 'login.password', 'secret');
    await tester.tap(find.byKey(const Key('login.submit')));
    await tester.pumpAndSettle();

    expect(tokens.token, 'test-token-redacted');
    expect(find.byType(AppShell), findsOneWidget);
    expect(directionOf(tester, AppShell), TextDirection.rtl);
    for (final label in [
      'الرئيسية',
      'مشاركاتي',
      'منافساتي',
      'الإشعارات',
      'الحساب',
    ]) {
      expect(find.text(label), findsWidgets, reason: label);
    }
    // The user channel follows the signed-in user.
    final user = fixtureData('auth_login_supplier_a')['user'] as Map;
    final userId = user['id'];
    expect(realtime.subscribed, contains('user.$userId'));
  });

  testWidgets('a stored token restores the session with GET /me', (
    tester,
  ) async {
    await pumpApp(tester, token: 'stored');
    expect(find.byType(AppShell), findsOneWidget);
    expect(find.byType(LoginScreen), findsNothing);
    expect(deps.session.state.me?.organization.name, 'Issuer Co');
    // Realtime settings come from app-config (a loopback host → API host).
    expect(realtime.configured?.appKey, isNotEmpty);
    expect(realtime.configured?.host, 'api.test');
  });

  testWidgets('maintenance blocks the app until app-config says it is over', (
    tester,
  ) async {
    await pumpApp(tester, token: 'stored');

    deps.gate.maintenance('صيانة مجدولة');
    await tester.pumpAndSettle();
    expect(find.byType(MaintenanceScreen), findsOneWidget);
    expect(find.text('صيانة مجدولة'), findsOneWidget);

    await tester.tap(find.text('إعادة المحاولة'));
    await tester.pumpAndSettle();
    expect(find.byType(AppShell), findsOneWidget);
  });

  testWidgets('a suspended organisation gets the account gate and can sign out', (
    tester,
  ) async {
    await pumpApp(tester, token: 'stored');

    deps.gate.accountBlocked('organization_suspended', '');
    await tester.pumpAndSettle();
    expect(find.byType(AccountBlockedScreen), findsOneWidget);
    expect(find.textContaining('أُوقف حساب منشأتكم'), findsOneWidget);

    await tester.tap(find.text('تسجيل الخروج'));
    await tester.pumpAndSettle();
    expect(find.byType(LoginScreen), findsOneWidget);
    expect(deps.gate.state, const AppGateOpen());
  });

  testWidgets('/competitions/:id opens above the shell from any tab', (
    tester,
  ) async {
    await pumpApp(tester, token: 'stored');

    unawaited(
      GoRouter.of(
        tester.element(find.byType(AppShell)),
      ).push('/competitions/$competitionId'),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));

    // The participant projection opens the participant page (M21).
    expect(find.byType(ParticipantCompetitionScreen), findsOneWidget);
    expect(
      find.text('توريد أجهزة ومستلزمات طبية للمستودع المركزي'),
      findsOneWidget,
    );
    // Participant projection: the participant channel of the viewer's org.
    expect(
      realtime.subscribed.where((c) => c.startsWith('competition.')),
      isNotEmpty,
    );
  });
}
