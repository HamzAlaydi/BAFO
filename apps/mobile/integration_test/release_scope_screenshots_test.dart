// Runs the real app on a device against stubbed API answers (the captured
// fixtures, served by `StubAdapter`) and photographs the release-scope
// surfaces of RELEASE_SCOPE.md §2.6 and §4:
//
//   flutter drive --driver=test_driver/integration_test.dart \
//     --target=integration_test/release_scope_screenshots_test.dart \
//     -d emulator-5554
//
// Screenshots land in `docs/screenshots/core/`.
import 'dart:async';
import 'dart:convert';

import 'package:bafo/app/app.dart';
import 'package:bafo/app/dependencies.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/router/app_shell.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/create/draft_form_fields.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:integration_test/integration_test.dart';

import '../test/features/issuer/issuer_test_helpers.dart' show withTierPresets;
import '../test/helpers/fakes.dart'
    show
        FakeRealtimeClient,
        InMemoryPreferencesStore,
        InMemoryTokenStore,
        StubAdapter,
        testEnv;
import '../test/helpers/scope.dart' show ScopeFlags;
import 'fixtures.g.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  /// `{status, body}` of an embedded fixture.
  Map<String, dynamic> envelope(String name) =>
      jsonDecode(embeddedFixtures[name]!) as Map<String, dynamic>;

  Map<String, dynamic> data(String name) =>
      (envelope(name)['body'] as Map<String, dynamic>)['data']
          as Map<String, dynamic>;

  /// Fixtures by `METHOD /path`; the app-config carries [flags], the
  /// lookups carry the tier presets.
  StubAdapter adapter(FeatureFlags flags, {required String scope}) {
    final routes = {
      'GET /time': 'time',
      'GET /me': 'me_issuer',
      'GET /home': 'home_issuer',
      'GET /notifications/unread-count': 'notifications_unread_count',
      'GET /competitions': 'competitions_issuer',
    };
    return StubAdapter((RequestOptions options) {
      final path = options.uri.path.replaceFirst('/api/app/v1', '');
      if (path == '/app-config') {
        return (
          200,
          {
            'data': {
              ...data('app_config'),
              'features': ScopeFlags.featuresJson(flags, scope: scope),
            },
            'meta': <String, Object>{},
          },
        );
      }
      if (path == '/lookups') {
        return (
          200,
          {
            'data': withTierPresets(data('lookups')),
            'meta': <String, Object>{},
          },
        );
      }
      final fixture = routes['${options.method} $path'];
      if (fixture == null) {
        return (
          404,
          {
            'message': 'Not found',
            'code': 'not_found',
            'errors': <String, Object>{},
          },
        );
      }
      final found = envelope(fixture);
      return (found['status'] as int, found['body']);
    });
  }

  Future<AppDependencies> pumpApp(
    WidgetTester tester, {
    required FeatureFlags flags,
    required String scope,
    String locale = 'ar',
  }) async {
    final deps = AppDependencies.wire(
      env: testEnv(),
      tokens: InMemoryTokenStore('stored'),
      preferences: InMemoryPreferencesStore({
        PreferenceKeys.onboardingSeen: '1',
        PreferenceKeys.locale: locale,
      }),
      realtime: FakeRealtimeClient(),
      httpClientAdapter: adapter(flags, scope: scope),
    );
    await tester.pumpWidget(BafoApp(dependencies: deps));
    // A cold start on the device takes a while (fonts, the first frames):
    // wait for the signed-in shell instead of a fixed settle.
    for (var i = 0; i < 100 && find.byType(AppShell).evaluate().isEmpty; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
    expect(find.byType(AppShell), findsOneWidget);
    return deps;
  }

  /// The hub's Organization section (Team, plan, invoices) sits below the
  /// first screen: bring it into view before the shot.
  Future<void> revealHubSections(WidgetTester tester) async {
    await tester.scrollUntilVisible(
      find.byKey(const Key('account.settings')),
      200,
      scrollable: find.descendant(
        of: find.byKey(const Key('account.list')),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> shoot(WidgetTester tester, String name) async {
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
    await binding.takeScreenshot(name);
  }

  // The shell sits offstage under a page pushed on the root navigator.
  GoRouter router(WidgetTester tester) =>
      GoRouter.of(tester.element(find.byType(AppShell, skipOffstage: false)));

  /// Opens a page above the shell (the wizard).
  Future<void> push(WidgetTester tester, String location) async {
    unawaited(router(tester).push(location));
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
  }

  /// Replaces the stack with a shell location (a tab or its child).
  Future<void> go(WidgetTester tester, String location) async {
    router(tester).go(location);
    await tester.pumpAndSettle(const Duration(milliseconds: 300));
  }

  testWidgets('core: the create flow, the account hub and a hidden feature', (
    tester,
  ) async {
    // Android: the surface becomes an image for this test only (the
    // binding reverts it at tear-down), so every test converts it.
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(tester, flags: ScopeFlags.core, scope: 'core');

    await push(tester, '/my-competitions/new');
    // FQ8: an empty "Next" lists the problem at the top.
    await tester.tap(find.byKey(const Key('create.next')));
    await shoot(tester, 'core_01_create_type_errors_ar');

    await tester.tap(find.byKey(const ValueKey('direction-tender')));
    await shoot(tester, 'core_02_create_type_tiers_ar');

    await tester.tap(find.byKey(const Key('create.next')));
    await tester.pumpAndSettle();
    await shoot(tester, 'core_03_create_basics_ar');

    final cubit = tester
        .element(find.byType(DraftFormFields))
        .read<CreateCompetitionCubit>();
    final lookups = (cubit.state as CreateCompetitionEditing).lookups;
    cubit.update(
      (f) => f.copyWith(
        title: 'توريد أجهزة حاسب محمول للإدارة العامة',
        description: '40 جهازاً، تسليم في الرياض خلال 30 يوماً.',
        categoryId: () => lookups.categories.first.id,
        regionId: () => lookups.regions.first.id,
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('create.next')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('quick-days3')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('schedule.relativeClose')));
    await shoot(tester, 'core_04_create_schedule_ar');

    await tester.tap(find.byKey(const Key('create.next')));
    await shoot(tester, 'core_05_create_review_ar');

    // The account surfaces replace the stack (the unsaved form is local only).
    await go(tester, '/account');
    await revealHubSections(tester);
    await shoot(tester, 'core_06_account_hub_ar');

    await go(tester, '/account/team');
    await shoot(tester, 'core_07_feature_unavailable_ar');

    await go(tester, '/account/invoices');
    await shoot(tester, 'core_08_invoices_unavailable_ar');

    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });

  testWidgets('core in English: the create flow', (tester) async {
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(
      tester,
      flags: ScopeFlags.core,
      scope: 'core',
      locale: 'en',
    );
    await push(tester, '/my-competitions/new');
    await tester.tap(find.byKey(const ValueKey('direction-tender')));
    await shoot(tester, 'core_09_create_type_tiers_en');
    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });

  testWidgets('full: everything comes back', (tester) async {
    await binding.convertFlutterSurfaceToImage();
    final deps = await pumpApp(tester, flags: ScopeFlags.full, scope: 'full');
    await go(tester, '/account');
    await revealHubSections(tester);
    await shoot(tester, 'full_01_account_hub_ar');
    await push(tester, '/my-competitions/new');
    await tester.tap(find.byKey(const ValueKey('direction-tender')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('format-live')));
    await shoot(tester, 'full_02_create_type_ar');
    await tester.pumpWidget(const SizedBox.shrink());
    await deps.dispose();
  });
}
