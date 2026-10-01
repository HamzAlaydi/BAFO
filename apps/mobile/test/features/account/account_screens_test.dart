import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/config/app_config_repository.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/account/data/contact_repository.dart';
import 'package:bafo/features/account/domain/contact_message.dart';
import 'package:bafo/features/account/presentation/account_routes.dart';
import 'package:bafo/features/account/presentation/help/help_screen.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';
import '../../helpers/pump.dart';
import 'support.dart';

class _MockAccount extends Mock implements AccountRepository {}

class _MockOrganizations extends Mock implements OrganizationRepository {}

class _MockDeletion extends Mock implements AccountDeletionRepository {}

class _MockTeam extends Mock implements TeamRepository {}

class _MockBilling extends Mock implements BillingRepository {}

class _MockContact extends Mock implements ContactRepository {}

class _MockDevices extends Mock implements DevicesRepository {}

class _FixtureLookups implements LookupsRepository {
  final Lookups data = Lookups.fromJson(fixtureData('lookups'));

  @override
  Future<Lookups> lookups({bool refresh = false}) async => data;

  @override
  Future<List<CloseReason>> closeReasons(CloseReasonKind kind) async =>
      data.closeReasonsOf(kind);
}

class _FixtureConfig implements AppConfigRepository {
  _FixtureConfig(this.current);

  @override
  final AppConfig? current;

  @override
  Future<AppConfig> fetch() async => current!;
}

void main() {
  late SessionCubit session;
  late _MockAccount account;
  late _MockOrganizations organizations;
  late _MockDeletion deletion;
  late _MockTeam team;
  late _MockBilling billing;
  late _MockDevices devices;
  late InMemoryPreferencesStore preferences;

  final organization = Organization.fromJson(
    fixtureData('organization_issuer'),
  );
  final members = fixtureList('team_members_issuer')
      .map(TeamMember.fromJson)
      .toList();
  final invoices = fixtureList('billing_invoices_issuer')
      .map(InvoiceSummary.fromJson)
      .toList();

  setUpAll(() {
    registerFallbackValue(
      const OrganizationUpdate(name: '', regionId: '', city: ''),
    );
    registerFallbackValue(
      const TeamMemberInvite(name: '', email: '', role: MembershipRole.member),
    );
    registerFallbackValue(
      const ContactMessage(name: '', email: '', subject: '', message: ''),
    );
  });

  setUp(() {
    account = _MockAccount();
    organizations = _MockOrganizations();
    deletion = _MockDeletion();
    team = _MockTeam();
    billing = _MockBilling();
    devices = _MockDevices();
    preferences = InMemoryPreferencesStore();
    when(organizations.organization).thenAnswer((_) async => organization);
    when(() => team.members()).thenAnswer(
      (_) async =>
          TeamRoster(members: members, seats: const Seats(used: 3, total: 3)),
    );
    when(() => billing.invoices()).thenAnswer(
      (_) async =>
          Paged(items: invoices, meta: PageMeta.single(invoices.length)),
    );
    when(deletion.current).thenAnswer((_) async => null);
  });

  tearDown(() => session.close());

  Future<GoRouter> pumpAccount(
    WidgetTester tester, {
    Me? me,
    String location = '/account',
    Object? extra,
    Locale locale = const Locale('ar'),
  }) async {
    tester.view
      ..physicalSize = const Size(1080, 4000)
      ..devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
    session = await signedInSession(me ?? fixtureMe());
    final localeCubit = LocaleCubit(preferences);
    addTearDown(localeCubit.close);
    final router = await pumpFeature(
      tester,
      initialLocation: location,
      initialExtra: extra,
      locale: locale,
      routes: [
        accountRoute(),
        GoRoute(
          path: '/billing',
          builder: (_, _) => const Scaffold(body: Text('route:billing')),
        ),
        GoRoute(
          path: '/competitions/:id',
          builder: (_, state) => Scaffold(
            body: Text('route:competition ${state.pathParameters['id']}'),
          ),
        ),
      ],
      providers: (_) => [
        BlocProvider<SessionCubit>.value(value: session),
        BlocProvider<LocaleCubit>.value(value: localeCubit),
        RepositoryProvider<AccountRepository>.value(value: account),
        RepositoryProvider<OrganizationRepository>.value(value: organizations),
        RepositoryProvider<AccountDeletionRepository>.value(value: deletion),
        RepositoryProvider<TeamRepository>.value(value: team),
        RepositoryProvider<BillingRepository>.value(value: billing),
        RepositoryProvider<LookupsRepository>.value(value: _FixtureLookups()),
        RepositoryProvider<PushService>.value(value: const NoopPushService()),
        RepositoryProvider<DevicesRepository>.value(value: devices),
        RepositoryProvider<PreferencesStore>.value(value: preferences),
      ],
    );
    await tester.pumpAndSettle();
    return router;
  }

  group('M51 account hub', () {
    testWidgets('the owner sees every link, the plan and the version', (
      tester,
    ) async {
      await pumpAccount(tester);

      expect(find.text('سارة المالكة'), findsOneWidget);
      expect(find.text('Issuer Co'), findsOneWidget);
      expect(find.text('المالك'), findsOneWidget);
      expect(find.text('باقة برو'), findsOneWidget);
      for (final key in [
        'account.profile',
        'account.password',
        'account.organization',
        'account.team',
        'account.plan',
        'account.invoices',
        'account.settings',
        'account.help',
        'account.delete',
      ]) {
        expect(find.byKey(Key(key)), findsOneWidget, reason: key);
      }
      expect(find.text('الإصدار 1.2.3'), findsOneWidget);
    });

    testWidgets('a member sees no team or invoices link (S10)', (tester) async {
      await pumpAccount(tester, me: fixtureMe('me_member'));

      expect(find.text('عضو'), findsOneWidget);
      expect(find.byKey(const Key('account.team')), findsNothing);
      expect(find.byKey(const Key('account.invoices')), findsNothing);
      expect(find.byKey(const Key('account.organization')), findsOneWidget);
    });

    testWidgets('an offline start still offers settings, help and sign-out', (
      tester,
    ) async {
      tester.view
        ..physicalSize = const Size(1080, 4000)
        ..devicePixelRatio = 2.5;
      addTearDown(tester.view.reset);
      session = await signedInSession();
      final localeCubit = LocaleCubit(preferences);
      addTearDown(localeCubit.close);
      await pumpFeature(
        tester,
        initialLocation: '/account',
        routes: [accountRoute()],
        providers: (_) => [
          BlocProvider<SessionCubit>.value(value: session),
          BlocProvider<LocaleCubit>.value(value: localeCubit),
        ],
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('تعذّر تحميل بيانات الحساب'), findsOneWidget);
      expect(find.byKey(const Key('account.settings')), findsOneWidget);
      expect(find.byKey(const Key('account.signOut')), findsOneWidget);
      expect(find.byKey(const Key('account.delete')), findsNothing);
    });

    testWidgets('sign-out asks first, then ends the session', (tester) async {
      await pumpAccount(tester);

      await tester.tap(find.byKey(const Key('account.signOut')));
      await tester.pumpAndSettle();
      expect(find.text('تسجيل الخروج؟'), findsOneWidget);
      await tester.tap(find.text('تسجيل الخروج').last);
      await tester.pumpAndSettle();
      expect(session.state, isA<SessionUnauthenticated>());
    });

    testWidgets('links open the account screens and the plan status', (
      tester,
    ) async {
      final router = await pumpAccount(tester);

      await tester.tap(find.byKey(const Key('account.plan')));
      await tester.pumpAndSettle();
      expect(find.text('route:billing'), findsOneWidget);

      router.go('/account');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('account.team')));
      await tester.pumpAndSettle();
      expect(router.state.uri.path, '/account/team');
      expect(find.text('فريق العمل'), findsOneWidget);
    });
  });

  group('M52 profile and M54 password', () {
    testWidgets('saves the name; the e-mail cannot be edited', (tester) async {
      when(
        () => account.updateMe(
          name: any(named: 'name'),
          phone: any(named: 'phone'),
        ),
      ).thenAnswer((_) async => fixtureMe());
      await pumpAccount(tester, location: '/account/profile');

      final email = tester.widget<TextField>(
        find.descendant(
          of: find.byKey(const Key('profile.email')),
          matching: find.byType(TextField),
        ),
      );
      expect(email.enabled, isFalse);
      await enterByKey(tester, 'profile.name', 'سارة العتيبي');
      await tester.pump();
      await tester.tap(find.byKey(const Key('profile.save')));
      await tester.pumpAndSettle();

      verify(
        () => account.updateMe(name: 'سارة العتيبي', phone: '+966500000011'),
      ).called(1);
      expect(find.text('حُفظت التغييرات.'), findsOneWidget);
    });

    testWidgets('password_incorrect is shown on the current password', (
      tester,
    ) async {
      when(
        () => account.changePassword(
          currentPassword: any(named: 'currentPassword'),
          password: any(named: 'password'),
          passwordConfirmation: any(named: 'passwordConfirmation'),
        ),
      ).thenThrow(
        const ApiException(code: 'password_incorrect', statusCode: 422),
      );
      await pumpAccount(tester, location: '/account/password');

      expect(find.text('سيُسجَّل خروجكم من الأجهزة الأخرى.'), findsOneWidget);
      await enterByKey(tester, 'password.current', 'wrong');
      await enterByKey(tester, 'password.new', 'New-pass1!');
      await enterByKey(tester, 'password.confirm', 'New-pass1!');
      await tester.tap(find.byKey(const Key('password.submit')));
      await tester.pumpAndSettle();
      expect(find.text('كلمة المرور الحالية غير صحيحة.'), findsOneWidget);
    });
  });

  group('M55 organisation', () {
    testWidgets('a member reads it only', (tester) async {
      await pumpAccount(
        tester,
        me: fixtureMe('me_member'),
        location: '/account/organization',
      );

      expect(find.text('Issuer Co'), findsWidgets);
      expect(
        find.text('يعدّل مالك الحساب أو مديره بيانات المنشأة.'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('organization.edit')), findsNothing);
      expect(find.byKey(const Key('organization.logo')), findsNothing);
    });

    testWidgets('the owner edits and saves through PATCH /organization', (
      tester,
    ) async {
      when(() => organizations.update(any()))
          .thenAnswer((_) async => organization);
      when(account.me).thenAnswer((_) async => fixtureMe());
      await pumpAccount(tester, location: '/account/organization');

      await tester.tap(find.byKey(const Key('organization.edit')));
      await tester.pumpAndSettle();
      // Nothing changed yet: saving is off.
      expect(
        tester
            .widget<FilledButton>(
              find.descendant(
                of: find.byKey(const Key('organization.save')),
                matching: find.byType(FilledButton),
              ),
            )
            .onPressed,
        isNull,
      );
      await enterByKey(tester, 'organization.city', 'جدة');
      await tester.pump();
      await tester.ensureVisible(find.byKey(const Key('organization.save')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('organization.save')));
      await tester.pumpAndSettle();

      final sent =
          verify(() => organizations.update(captureAny())).captured.single
              as OrganizationUpdate;
      expect(sent.city, 'جدة');
      expect(sent.toJson().containsKey('cr_number'), isFalse);
      expect(find.text('حُفظت التغييرات.'), findsOneWidget);
      expect(find.byKey(const Key('organization.edit')), findsOneWidget);
    });
  });

  group('M56–M57 team', () {
    testWidgets('a member without team.manage gets the forbidden state', (
      tester,
    ) async {
      await pumpAccount(
        tester,
        me: fixtureMe('me_member'),
        location: '/account/team',
      );

      expect(find.text('لا تملك صلاحية الوصول'), findsOneWidget);
      verifyNever(() => team.members());
    });

    testWidgets('full seats say so without an upgrade; owner and self locked', (
      tester,
    ) async {
      await pumpAccount(tester, location: '/account/team');

      expect(find.text('3 من 3'), findsOneWidget);
      expect(find.byKey(const Key('team.seatsFull')), findsOneWidget);
      expect(
        find.text('تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.'),
        findsOneWidget,
      );
      // The owner is the viewer: one locked row.
      expect(find.byIcon(Icons.lock_outline_rounded), findsOneWidget);
      expect(find.textContaining('(أنت)'), findsOneWidget);
    });

    testWidgets('inviting shows seat_limit_reached with the seats', (
      tester,
    ) async {
      when(() => team.invite(any())).thenThrow(
        const ApiException(
          code: 'seat_limit_reached',
          statusCode: 409,
          details: {
            'seats': {'used': 3, 'total': 3},
          },
        ),
      );
      await pumpAccount(tester, location: '/account/team/new');

      await enterByKey(tester, 'member.name', 'منى');
      await enterByKey(tester, 'member.email', 'mona@acme.sa');
      await tester.tap(find.byKey(const Key('member.role.admin')));
      await tester.pump();
      // Admin defaults: both flags on (ARCHITECTURE §8.1).
      expect(
        tester
            .widget<SwitchListTile>(find.byKey(const Key('member.canAward')))
            .value,
        isTrue,
      );
      await tester.tap(find.byKey(const Key('member.submit')));
      await tester.pumpAndSettle();

      final sent =
          verify(() => team.invite(captureAny())).captured.single
              as TeamMemberInvite;
      expect(sent.role, MembershipRole.admin);
      expect(sent.canPurchase, isTrue);
      expect(find.byKey(const Key('member.seatLimit')), findsOneWidget);
      expect(find.text('اكتملت مقاعد باقتكم (3/3).'), findsOneWidget);
    });

    testWidgets(
      'an admin without award and purchase rights cannot grant them (SECURITY_REVIEW S-02)',
      (tester) async {
        final restricted = Me.fromJson({
          ...fixtureData('me_issuer'),
          'permissions': [
            'organization.update',
            'team.manage',
            'billing.view',
            'competitions.create',
            'competitions.manage_all',
            'participation.submit_offers',
            'integrations.manage',
          ],
        });
        await pumpAccount(
          tester,
          me: restricted,
          location: '/account/team/new',
        );

        await tester.tap(find.byKey(const Key('member.role.admin')));
        await tester.pump();

        for (final key in ['member.canAward', 'member.canPurchase']) {
          final toggle = tester.widget<SwitchListTile>(find.byKey(Key(key)));
          expect(toggle.value, isFalse, reason: key);
          expect(toggle.onChanged, isNull, reason: key);
        }
      },
    );

    testWidgets('removing a member asks with a named red button, then pops', (
      tester,
    ) async {
      final member = members.last;
      when(() => team.remove(member.id)).thenAnswer((_) async {});
      final router = await pumpAccount(tester, location: '/account/team');

      unawaited(router.push('/account/team/${member.id}', extra: member));
      await tester.pumpAndSettle();
      expect(find.text('عضو الفريق'), findsOneWidget);
      await tester.tap(find.byKey(const Key('member.remove')));
      await tester.pumpAndSettle();
      expect(find.text('إزالة ${member.user.name} من الفريق؟'), findsOneWidget);
      await tester.tap(find.text('إزالة العضو').last);
      await tester.pumpAndSettle();

      verify(() => team.remove(member.id)).called(1);
      expect(router.state.uri.path, '/account/team');
    });
  });

  group('invoices (read-only)', () {
    testWidgets('number, date and status only: no price, link or button', (
      tester,
    ) async {
      await pumpAccount(tester, location: '/account/invoices');

      expect(find.text('BAFO-INV-2026-000001'), findsOneWidget);
      expect(find.text('معتمدة'), findsOneWidget);
      expect(
        find.text('الفواتير متاحة في لوحة تحكم بافو على الويب.'),
        findsOneWidget,
      );
      for (final text in visibleTexts(tester)) {
        expect(text, isNot(contains('ر.س')), reason: text);
        expect(text, isNot(contains('SAR')), reason: text);
        expect(text, isNot(contains('http')), reason: text);
        expect(text, isNot(contains('1,725')), reason: text);
      }
      expect(find.byType(FilledButton), findsNothing);
    });

    testWidgets('without billing.view it is forbidden', (tester) async {
      await pumpAccount(
        tester,
        me: fixtureMe('me_member'),
        location: '/account/invoices',
      );
      expect(find.text('لا تملك صلاحية الوصول'), findsOneWidget);
      verifyNever(() => billing.invoices());
    });
  });

  group('M59 settings', () {
    testWidgets('language, push on this device, legal links and version', (
      tester,
    ) async {
      await pumpAccount(tester, location: '/account/settings');

      expect(find.text('العربية'), findsOneWidget);
      expect(find.text('التنبيهات الفورية غير مفعّلة.'), findsOneWidget);
      expect(find.byKey(const Key('settings.legal.terms')), findsOneWidget);
      expect(find.text('1.2.3'), findsOneWidget);

      await tester.tap(find.byKey(const Key('settings.pushTurnOn')));
      await tester.pumpAndSettle();
      // The no-op push service has no prompt and no token: nothing is sent.
      expect(find.textContaining('غير متاحة على هذا الجهاز'), findsOneWidget);
      verifyZeroInteractions(devices);
    });

    testWidgets('switching the language updates the app locale', (
      tester,
    ) async {
      await pumpAccount(tester, location: '/account/settings');

      await tester.tap(find.text('English'));
      await tester.pumpAndSettle();
      expect(preferences.getString(PreferenceKeys.locale), 'en');
    });
  });

  group('M61 delete account', () {
    testWidgets('the owner is told the organisation goes; blockers link out', (
      tester,
    ) async {
      when(
        () => deletion.request(
          password: any(named: 'password'),
          reason: any(named: 'reason'),
        ),
      ).thenThrow(
        const ApiException(
          code: 'account_deletion_blocked',
          statusCode: 409,
          details: {
            'blockers': [
              {
                'type': 'issued_competition',
                'competition_id': '01jcompetition',
                'title': 'توريد أجهزة',
              },
            ],
          },
        ),
      );
      final router = await pumpAccount(
        tester,
        location: '/account/delete-account',
      );

      expect(
        find.text('سيُحذف حساب المنشأة وجميع أعضائها بعد 14 يوماً.'),
        findsOneWidget,
      );
      await enterByKey(tester, 'deletion.password', 'secret');
      await tester.tap(find.byKey(const Key('deletion.submit')));
      await tester.pumpAndSettle();
      expect(find.text('حذف الحساب؟'), findsOneWidget);
      await tester.tap(find.text('حذف الحساب').last);
      await tester.pumpAndSettle();

      expect(find.text('لا يمكن حذف الحساب الآن'), findsOneWidget);
      await tester.tap(find.text('توريد أجهزة'));
      await tester.pumpAndSettle();
      expect(router.state.uri.path, '/competitions/01jcompetition');
    });

    testWidgets('a pending request shows its date and can be cancelled', (
      tester,
    ) async {
      when(deletion.current).thenAnswer(
        (_) async => AccountDeletionRequest(
          id: '01jdel',
          scope: DeletionScope.user,
          status: 'pending',
          scheduledFor: DateTime.utc(2026, 10, 15, 9),
        ),
      );
      when(deletion.cancel).thenAnswer((_) async {});
      await pumpAccount(
        tester,
        me: fixtureMe('me_member'),
        location: '/account/delete-account',
      );

      expect(find.text('سيُحذف الحساب في 15 أكتوبر 2026.'), findsOneWidget);
      await tester.tap(find.byKey(const Key('deletion.cancel')));
      await tester.pumpAndSettle();
      verify(deletion.cancel).called(1);
      expect(find.text('أُلغي طلب الحذف.'), findsOneWidget);
      expect(
        find.text(
          'سيُحذف حسابكم الشخصي فقط بعد 14 يوماً، وتبقى المنشأة وبقية أعضائها.',
        ),
        findsOneWidget,
      );
    });
  });

  group('M60 help', () {
    Future<_MockContact> pumpHelp(
      WidgetTester tester, {
      SupportContacts support = const SupportContacts(),
    }) async {
      tester.view
        ..physicalSize = const Size(1080, 4000)
        ..devicePixelRatio = 2.5;
      addTearDown(tester.view.reset);
      session = await signedInSession(fixtureMe());
      final contact = _MockContact();
      final json = fixtureData('app_config');
      final config = AppConfig.fromJson({
        ...json,
        'support': {
          'email': support.email,
          'phone': support.phone,
          'whatsapp': support.whatsapp,
        },
      });
      final gate = AppGateCubit();
      final realtime = FakeRealtimeClient();
      final appConfig = AppConfigCubit(
        repository: _FixtureConfig(config),
        gate: gate,
        realtime: realtime,
        env: testEnv(),
        client: testClient,
      );
      addTearDown(() async {
        await appConfig.close();
        await gate.close();
        await realtime.dispose();
      });
      await pumpLocalized(
        tester,
        const HelpScreen(),
        wrapInScaffold: false,
        providers: [
          BlocProvider<SessionCubit>.value(value: session),
          BlocProvider<AppConfigCubit>.value(value: appConfig),
          RepositoryProvider<ContactRepository>.value(value: contact),
        ],
      );
      await tester.pumpAndSettle();
      return contact;
    }

    testWidgets('pre-fills the form from the account and sends it', (
      tester,
    ) async {
      final contact = await pumpHelp(tester);
      when(() => contact.send(any())).thenAnswer((_) async => '01jcontact');

      expect(find.byKey(const Key('help.noContacts')), findsOneWidget);
      await enterByKey(tester, 'help.subject', 'استفسار');
      await enterByKey(tester, 'help.message', 'كيف أدعو فريقي؟');
      await tester.tap(find.byKey(const Key('help.send')));
      await tester.pumpAndSettle();

      final sent =
          verify(() => contact.send(captureAny())).captured.single
              as ContactMessage;
      expect(sent.name, 'سارة المالكة');
      expect(sent.email, 'issuer.owner@demo.bafo.test');
      expect(sent.company, 'Issuer Co');
      expect(sent.toJson()['website_url'], '');
      expect(find.byKey(const Key('help.sent')), findsOneWidget);
    });

    testWidgets('shows the support contacts from app-config', (tester) async {
      await pumpHelp(
        tester,
        support: const SupportContacts(
          email: 'support@bafo.sa',
          phone: '+966500000000',
        ),
      );

      expect(find.text('support@bafo.sa'), findsOneWidget);
      expect(find.text('+966500000000'), findsOneWidget);
      expect(find.byKey(const Key('help.noContacts')), findsNothing);
    });
  });
}
