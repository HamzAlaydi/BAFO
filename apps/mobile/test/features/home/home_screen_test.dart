import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/features/home/presentation/home_screen.dart';
import 'package:bafo/features/home/presentation/home_sections.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scope.dart';
import '../account/support.dart';

/// Answers `GET /home` with a fixture, or fails the first [failures] calls.
class _FixtureHome implements HomeRepository {
  _FixtureHome(this.fixture, {this.failures = 0, this.alerts});

  final String fixture;
  int failures;
  int calls = 0;

  /// Replaces the fixture's `alerts`.
  final List<Map<String, Object?>>? alerts;

  @override
  Future<Home> home() async {
    calls++;
    if (failures > 0) {
      failures--;
      throw const ApiException(code: ApiErrorCode.network);
    }
    return Home.fromJson({
      ...fixtureData(fixture),
      if (alerts != null) 'alerts': alerts,
    });
  }
}

void main() {
  late SessionCubit session;
  late UnreadCountCubit unread;

  tearDown(() async {
    await session.close();
    await unread.close();
  });

  Widget stub(String name) => Scaffold(body: Text('route:$name'));

  Future<GoRouter> pumpHome(
    WidgetTester tester, {
    required Me me,
    required HomeRepository home,
    Locale locale = const Locale('ar'),
    String scope = 'full',
  }) async {
    // A tall phone, so the whole dashboard is laid out.
    tester.view
      ..physicalSize = const Size(1080, 4800)
      ..devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
    session = await signedInSession(me);
    unread = testUnreadCount();
    final router = await pumpFeature(
      tester,
      initialLocation: '/home',
      locale: locale,
      routes: [
        GoRoute(path: '/home', builder: (_, _) => const HomeScreen()),
        GoRoute(path: '/billing', builder: (_, _) => stub('billing')),
        GoRoute(
          path: '/competitions',
          builder: (_, _) => stub('competitions'),
          routes: [
            GoRoute(
              path: ':id',
              builder: (_, state) =>
                  stub('competition ${state.pathParameters['id']}'),
            ),
          ],
        ),
        GoRoute(
          path: '/my-competitions',
          builder: (_, _) => stub('my-competitions'),
          routes: [GoRoute(path: 'new', builder: (_, _) => stub('create'))],
        ),
        GoRoute(
          path: '/account/organization',
          builder: (_, state) => stub('organization ${state.uri.query}'),
        ),
      ],
      providers: (_) => [
        releaseScopeProvider(scope),
        RepositoryProvider<HomeRepository>.value(value: home),
        BlocProvider<SessionCubit>.value(value: session),
        BlocProvider<UnreadCountCubit>.value(value: unread),
      ],
    );
    await tester.pumpAndSettle();
    return router;
  }

  testWidgets('issuer: stats, plan, activity and the create action', (
    tester,
  ) async {
    await pumpHome(tester, me: fixtureMe(), home: _FixtureHome('home_issuer'));

    expect(find.text('مرحباً، ${isolate('سارة المالكة')}'), findsOneWidget);
    expect(find.text('المنافسات التي تطرحونها'), findsOneWidget);
    expect(find.text('مشاركاتكم'), findsOneWidget);
    // The issuer side comes first for an issuing organisation.
    expect(
      tester.getTopLeft(find.text('المنافسات التي تطرحونها')).dy,
      lessThan(tester.getTopLeft(find.text('مشاركاتكم')).dy),
    );
    final active = find.byKey(const Key('home.stat.activeCompetitions'));
    expect(
      find.descendant(of: active, matching: find.text('8')),
      findsOneWidget,
    );
    expect(find.text('باقة برو'), findsOneWidget);
    expect(find.byKey(const Key('home.createCompetition')), findsOneWidget);

    await tester.scrollUntilVisible(find.text('آخر النشاطات'), 300);
    expect(
      find.text('نُشرت «${isolate('خدمات النظافة للفروع (رسوم مغطّاة)')}».'),
      findsOneWidget,
    );
    // The API's "system" actor is localised, and isolated from the time.
    expect(find.textContaining('${isolate('النظام')} · '), findsWidgets);
  });

  testWidgets('the plan card opens the plan status (M58)', (tester) async {
    await pumpHome(tester, me: fixtureMe(), home: _FixtureHome('home_issuer'));

    await tester.scrollUntilVisible(
      find.byKey(const Key('home.subscription')),
      300,
    );
    await tester.tap(find.byKey(const Key('home.subscription')));
    await tester.pumpAndSettle();
    expect(find.text('route:billing'), findsOneWidget);
  });

  testWidgets('an activity row opens its competition', (tester) async {
    await pumpHome(tester, me: fixtureMe(), home: _FixtureHome('home_issuer'));

    final row = find.text(
      'أُلغيت «${isolate('خدمات تسويق وطباعة المواد الترويجية')}».',
    );
    await tester.scrollUntilVisible(row, 300);
    await tester.tap(row);
    await tester.pumpAndSettle();
    expect(
      find.text('route:competition 01m3q4e7kycnbff05zqtegmmc5'),
      findsOneWidget,
    );
  });

  testWidgets('without a plan: alerts and no purchase action (§3.2)', (
    tester,
  ) async {
    await pumpHome(
      tester,
      me: fixtureMe('me_supplier_b'),
      home: _FixtureHome('home_supplier_b'),
    );

    expect(
      find.text(
        'تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات والانضمام إليها.',
      ),
      findsOneWidget,
    );
    expect(find.text('تتوفر لمنشأتكم فترة تجريبية مجانية.'), findsOneWidget);
    expect(
      find.text('تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.'),
      findsWidgets,
    );
    // S10: competitions.create without can_issue → the text, not a button.
    expect(find.byKey(const Key('home.createCompetition')), findsNothing);
    await tester.scrollUntilVisible(
      find.byKey(const Key('home.createNeedsPlan')),
      300,
    );
    expect(find.text('تحتاج إلى باقة فعّالة لطرح المنافسات.'), findsOneWidget);
    for (final text in visibleTexts(tester)) {
      expect(text, isNot(contains('http')), reason: text);
      expect(text, isNot(contains('ر.س')), reason: text);
    }
    // The participant side leads when the organisation only takes part.
    expect(
      tester.getTopLeft(find.text('مشاركاتكم')).dy,
      lessThan(tester.getTopLeft(find.text('المنافسات التي تطرحونها')).dy),
    );
  });

  testWidgets('a failed load offers a retry', (tester) async {
    final home = _FixtureHome('home_issuer', failures: 1);
    await pumpHome(tester, me: fixtureMe(), home: home);

    expect(find.text('تعذّر إكمال الطلب'), findsOneWidget);
    await tester.tap(find.text('إعادة المحاولة'));
    await tester.pumpAndSettle();
    expect(find.text('المنافسات التي تطرحونها'), findsOneWidget);
    expect(home.calls, 2);
  });

  testWidgets('English is left-to-right with the same content', (tester) async {
    await pumpHome(
      tester,
      me: fixtureMe(),
      home: _FixtureHome('home_issuer'),
      locale: const Locale('en'),
    );

    expect(find.text('Hello, ${isolate('سارة المالكة')}'), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.text('Competitions you issue'))),
      TextDirection.ltr,
    );
    expect(find.text('Create a competition'), findsOneWidget);
  });

  group('release scope (RELEASE_SCOPE.md §4.1)', () {
    const billingProfileAlert = [
      {
        'code': 'billing_profile_incomplete',
        'severity': 'info',
        'params': <String, Object?>{},
      },
    ];

    testWidgets('core: role tiles and quick actions only', (tester) async {
      await pumpHome(
        tester,
        me: fixtureMe(),
        home: _FixtureHome('home_issuer', alerts: billingProfileAlert),
        scope: 'core',
      );

      expect(find.text('المنافسات التي تطرحونها'), findsOneWidget);
      expect(find.text('مشاركاتكم'), findsOneWidget);
      expect(
        find.byKey(const Key('home.stat.activeCompetitions')),
        findsOneWidget,
      );
      expect(find.byKey(const Key('home.createCompetition')), findsOneWidget);
      // Hidden, not deleted: the plan card lives in the Account tab.
      expect(
        find.byKey(const Key('home.stat.offersReceived30d')),
        findsNothing,
      );
      expect(find.byKey(const Key('home.subscription')), findsNothing);
      expect(find.byKey(const Key('home.activity')), findsNothing);
      expect(find.text('آخر النشاطات'), findsNothing);
      expect(find.text('بيانات الفوترة لمنشأتكم غير مكتملة.'), findsNothing);
    });

    testWidgets('full: the same home as before the minimal release', (
      tester,
    ) async {
      await pumpHome(
        tester,
        me: fixtureMe(),
        home: _FixtureHome('home_issuer', alerts: billingProfileAlert),
      );

      expect(
        find.byKey(const Key('home.stat.offersReceived30d')),
        findsOneWidget,
      );
      expect(find.byKey(const Key('home.subscription')), findsOneWidget);
      expect(find.byKey(const Key('home.activity')), findsOneWidget);
      expect(find.text('آخر النشاطات'), findsOneWidget);
      expect(find.text('بيانات الفوترة لمنشأتكم غير مكتملة.'), findsOneWidget);
    });

    testWidgets(
      'core: an organisation that cannot issue sees its participant tiles only',
      (tester) async {
        await pumpHome(
          tester,
          me: fixtureMe('me_supplier_b'),
          home: _FixtureHome('home_supplier_b'),
          scope: 'core',
        );
        expect(find.text('مشاركاتكم'), findsOneWidget);
        expect(find.text('المنافسات التي تطرحونها'), findsNothing);
        // The quick actions still say why creating needs a plan.
        expect(find.byKey(const Key('home.createNeedsPlan')), findsOneWidget);
      },
    );

    testWidgets('full: the same organisation also sees the issuer tiles', (
      tester,
    ) async {
      await pumpHome(
        tester,
        me: fixtureMe('me_supplier_b'),
        home: _FixtureHome('home_supplier_b'),
      );
      expect(find.text('المنافسات التي تطرحونها'), findsOneWidget);
    });
  });
}
