import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/account/presentation/account_routes.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/features/home/presentation/home_screen.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/notifications/presentation/notifications_screen.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scope.dart';
import 'support.dart';

class _MockHome extends Mock implements HomeRepository {}

class _MockNotifications extends Mock implements NotificationsRepository {}

class _MockOrganizations extends Mock implements OrganizationRepository {}

class _MockDeletion extends Mock implements AccountDeletionRepository {}

class _MockTeam extends Mock implements TeamRepository {}

class _MockBilling extends Mock implements BillingRepository {}

class _MockDevices extends Mock implements DevicesRepository {}

class _MockAccount extends Mock implements AccountRepository {}

class _FixtureLookups implements LookupsRepository {
  final Lookups data = Lookups.fromJson(fixtureData('lookups'));

  @override
  Future<Lookups> lookups({bool refresh = false}) async => data;

  @override
  Future<List<CloseReason>> closeReasons(CloseReasonKind kind) async =>
      data.closeReasonsOf(kind);
}

/// SCREENS.md S9: text scaled to 200% on a 360 dp phone wraps or scrolls,
/// never overflows (a RenderFlex overflow fails the test). Both languages.
void main() {
  final paths = [
    '/home',
    '/notifications',
    '/account',
    '/account/profile',
    '/account/password',
    '/account/organization',
    '/account/organization?edit=1',
    '/account/team',
    '/account/team/new',
    '/account/invoices',
    '/account/settings',
    '/account/delete-account',
  ];

  for (final locale in const [Locale('ar'), Locale('en')]) {
    for (final path in paths) {
      testWidgets('$path at 200% text, ${locale.languageCode}', (tester) async {
        tester.view
          ..physicalSize = const Size(1080, 2340)
          ..devicePixelRatio = 3;
        tester.platformDispatcher.textScaleFactorTestValue = 2;
        addTearDown(() {
          tester.view.reset();
          tester.platformDispatcher.clearTextScaleFactorTestValue();
        });

        final session = await signedInSession(fixtureMe());
        final unread = testUnreadCount();
        final preferences = InMemoryPreferencesStore();
        final localeCubit = LocaleCubit(preferences);
        addTearDown(() async {
          await session.close();
          await unread.close();
          await localeCubit.close();
        });

        final home = _MockHome();
        when(home.home).thenAnswer(
          (_) async => Home.fromJson(fixtureData('home_supplier_b')),
        );
        final notifications = _MockNotifications();
        final rows = fixtureList('notifications_supplier_a')
            .map(AppNotification.fromJson)
            .toList();
        when(() => notifications.list()).thenAnswer(
          (_) async => NotificationPage(
            page: Paged(items: rows, meta: PageMeta.single(rows.length)),
            unreadCount: rows.length,
          ),
        );
        final organizations = _MockOrganizations();
        when(organizations.organization).thenAnswer(
          (_) async =>
              Organization.fromJson(fixtureData('organization_issuer')),
        );
        final team = _MockTeam();
        when(() => team.members()).thenAnswer(
          (_) async => TeamRoster(
            members: fixtureList('team_members_issuer')
                .map(TeamMember.fromJson)
                .toList(),
            seats: const Seats(used: 3, total: 3),
          ),
        );
        final billing = _MockBilling();
        final invoices = fixtureList('billing_invoices_issuer')
            .map(InvoiceSummary.fromJson)
            .toList();
        when(() => billing.invoices()).thenAnswer(
          (_) async =>
              Paged(items: invoices, meta: PageMeta.single(invoices.length)),
        );
        final deletion = _MockDeletion();
        when(deletion.current).thenAnswer((_) async => null);

        await pumpFeature(
          tester,
          initialLocation: path,
          locale: locale,
          routes: [
            GoRoute(path: '/home', builder: (_, _) => const HomeScreen()),
            GoRoute(
              path: '/notifications',
              builder: (_, _) => const NotificationsScreen(),
            ),
            accountRoute(),
          ],
          providers: (router) => [
            BlocProvider<SessionCubit>.value(value: session),
            BlocProvider<UnreadCountCubit>.value(value: unread),
            BlocProvider<LocaleCubit>.value(value: localeCubit),
            // Team and Invoices exist in scope `full` (RELEASE_SCOPE.md §4).
            scopeProvider(ScopeFlags.full),
            RepositoryProvider<HomeRepository>.value(value: home),
            RepositoryProvider<NotificationsRepository>.value(
              value: notifications,
            ),
            RepositoryProvider<DeepLinkRouter>.value(
              value: DeepLinkRouter(
                router: router,
                session: session,
                markRead: (_) async {},
              ),
            ),
            RepositoryProvider<AccountRepository>.value(value: _MockAccount()),
            RepositoryProvider<OrganizationRepository>.value(
              value: organizations,
            ),
            RepositoryProvider<AccountDeletionRepository>.value(
              value: deletion,
            ),
            RepositoryProvider<TeamRepository>.value(value: team),
            RepositoryProvider<BillingRepository>.value(value: billing),
            RepositoryProvider<LookupsRepository>.value(
              value: _FixtureLookups(),
            ),
            RepositoryProvider<PushService>.value(
              value: const NoopPushService(),
            ),
            RepositoryProvider<DevicesRepository>.value(value: _MockDevices()),
            RepositoryProvider<PreferencesStore>.value(value: preferences),
          ],
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  }
}
