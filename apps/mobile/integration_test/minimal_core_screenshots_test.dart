// Runs the real app on a device against stubbed API answers (the captured
// fixtures, served by `StubAdapter`) and photographs the minimal `core` app
// of RELEASE_SCOPE.md §4.1: home, the Account hub, Competitions, My
// competitions and Notifications, as Issuer Co and as Supplier A, plus the
// same home and hub in scope `full` for comparison.
//
//   flutter drive --driver=test_driver/integration_test.dart \
//     --target=integration_test/minimal_core_screenshots_test.dart \
//     -d emulator-5554
//
// Screenshots land in `docs/screenshots/minimal/`.
import 'dart:convert';

import 'package:bafo/app/app.dart';
import 'package:bafo/app/dependencies.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/router/app_shell.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:integration_test/integration_test.dart';

import '../test/helpers/fakes.dart'
    show
        FakeRealtimeClient,
        InMemoryPreferencesStore,
        InMemoryTokenStore,
        StubAdapter,
        testEnv;
import '../test/helpers/scope.dart' show ScopeFlags;
import 'fixtures.g.dart';

/// Who is signed in, by fixture: `GET /me`, `GET /home`, the competitions
/// of each list role and the notifications.
typedef _Account = ({
  String me,
  String home,
  String? issued,
  String? participating,
  String notifications,
});

const _Account _issuer = (
  me: 'me_issuer',
  home: 'home_issuer',
  issued: 'competitions_issuer',
  participating: null,
  notifications: 'notifications_issuer',
);

const _Account _supplierA = (
  // The sign-in answer is `Me` plus the token.
  me: 'auth_login_supplier_a',
  home: 'home_supplier_a',
  issued: null,
  participating: 'competitions_participant_a',
  notifications: 'notifications_supplier_a',
);

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  /// `{status, body}` of an embedded fixture.
  Map<String, dynamic> envelope(String name) =>
      jsonDecode(embeddedFixtures[name]!) as Map<String, dynamic>;

  Map<String, dynamic> body(String name) =>
      envelope(name)['body'] as Map<String, dynamic>;

  /// One page and no more: the run never asks for page 2.
  Map<String, dynamic> singlePage(String? name) {
    final page = name == null
        ? <String, dynamic>{'data': <Object>[], 'meta': <String, Object>{}}
        : Map<String, dynamic>.of(body(name));
    final meta = Map<String, dynamic>.of(
      (page['meta'] as Map<String, dynamic>?) ?? const {},
    );
    meta['pagination'] = {
      ...((meta['pagination'] as Map<String, dynamic>?) ?? const {}),
      'type': 'page',
      'current_page': 1,
      'has_more': false,
    };
    return {...page, 'meta': meta};
  }

  StubAdapter adapter(
    _Account account,
    FeatureFlags flags, {
    required String scope,
  }) => StubAdapter((RequestOptions options) {
    final path = options.uri.path.replaceFirst('/api/app/v1', '');
    switch ((options.method, path)) {
      case ('GET', '/app-config'):
        return (
          200,
          {
            'data': {
              ...(body('app_config')['data'] as Map<String, dynamic>),
              'features': ScopeFlags.featuresJson(flags, scope: scope),
            },
            'meta': <String, Object>{},
          },
        );
      case ('GET', '/time'):
        return (200, body('time'));
      case ('GET', '/me'):
        return (200, body(account.me));
      case ('GET', '/home'):
        return (200, body(account.home));
      case ('GET', '/notifications/unread-count'):
        return (200, body('notifications_unread_count'));
      case ('GET', '/notifications'):
        return (200, singlePage(account.notifications));
      case ('GET', '/competitions'):
        final role = options.uri.queryParameters['role'];
        return (
          200,
          singlePage(role == 'issuer' ? account.issued : account.participating),
        );
    }
    return (
      404,
      {
        'message': 'Not found',
        'code': 'not_found',
        'errors': <String, Object>{},
      },
    );
  });

  Future<AppDependencies> pumpApp(
    WidgetTester tester, {
    required _Account account,
    required String scope,
  }) async {
    final deps = AppDependencies.wire(
      env: testEnv(),
      tokens: InMemoryTokenStore('stored'),
      preferences: InMemoryPreferencesStore({
        PreferenceKeys.onboardingSeen: '1',
        PreferenceKeys.locale: 'ar',
      }),
      realtime: FakeRealtimeClient(),
      httpClientAdapter: adapter(
        account,
        scope == 'full' ? ScopeFlags.full : ScopeFlags.core,
        scope: scope,
      ),
    );
    await tester.pumpWidget(BafoApp(dependencies: deps));
    // A cold start on the device takes a while: wait for the shell.
    for (var i = 0; i < 100 && find.byType(AppShell).evaluate().isEmpty; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
    expect(find.byType(AppShell), findsOneWidget);
    return deps;
  }

  Future<void> shoot(WidgetTester tester, String name) async {
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
    await binding.takeScreenshot('minimal/$name');
  }

  GoRouter router(WidgetTester tester) =>
      GoRouter.of(tester.element(find.byType(AppShell, skipOffstage: false)));

  Future<void> go(WidgetTester tester, String location) async {
    router(tester).go(location);
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
  }

  /// Scrolls the Account hub to its end (help, legal, sign-out).
  Future<void> hubEnd(WidgetTester tester) async {
    await tester.scrollUntilVisible(
      find.byKey(const Key('account.signOut')),
      300,
      scrollable: find.descendant(
        of: find.byKey(const Key('account.list')),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('core as Issuer Co: home, my competitions, account, alerts', (
    tester,
  ) async {
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(tester, account: _issuer, scope: 'core');

    await shoot(tester, '01_home_issuer_ar');
    // Core: no plan card or activity on home, no search on My competitions.
    expect(find.byKey(const Key('home.subscription')), findsNothing);

    await go(tester, '/my-competitions');
    expect(find.byKey(const Key('issuer.list.search')), findsNothing);
    await shoot(tester, '02_my_competitions_ar');

    await go(tester, '/account');
    expect(find.byKey(const Key('account.settings')), findsNothing);
    expect(find.byKey(const Key('account.organization')), findsNothing);
    await shoot(tester, '03_account_hub_ar');
    await hubEnd(tester);
    expect(find.byKey(const Key('account.legal')), findsOneWidget);
    await shoot(tester, '04_account_hub_end_ar');

    await go(tester, '/notifications');
    expect(find.byKey(const Key('notifications.filter')), findsNothing);
    await shoot(tester, '05_notifications_ar');

    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });

  testWidgets('core as Supplier A: home and the competitions tab', (
    tester,
  ) async {
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(tester, account: _supplierA, scope: 'core');

    await shoot(tester, '06_home_participant_ar');

    await go(tester, '/competitions');
    expect(find.byKey(const Key('participating.search')), findsNothing);
    await shoot(tester, '07_competitions_ar');

    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });

  testWidgets('full as Issuer Co: the same home and hub as before', (
    tester,
  ) async {
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(tester, account: _issuer, scope: 'full');

    await shoot(tester, 'full_01_home_issuer_ar');
    await go(tester, '/account');
    await shoot(tester, 'full_02_account_hub_ar');

    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });
}
