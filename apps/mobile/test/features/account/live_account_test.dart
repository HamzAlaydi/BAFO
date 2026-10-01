import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/account/presentation/deletion/account_deletion_cubit.dart';
import 'package:bafo/features/account/presentation/invoices/invoices_cubit.dart';
import 'package:bafo/features/account/presentation/organization/organization_cubit.dart';
import 'package:bafo/features/account/presentation/team/team_cubit.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/presentation/home_cubit.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/presentation/notifications_bloc.dart';
import 'package:bafo/features/notifications/presentation/push_permission_cubit.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';
import 'support.dart';

/// A push service that grants the permission and has a token, to exercise
/// the real `POST /devices` (the device is removed again at the end).
final class _GrantingPush implements PushService {
  const _GrantingPush();

  @override
  Future<void> initialize() async {}

  @override
  Future<PushPermission> requestPermission() async => PushPermission.granted;

  @override
  Future<String?> deviceToken() async => 'mobile-live-test-token';

  @override
  Stream<PushMessage> get onMessage => const Stream.empty();

  @override
  Stream<PushMessage> get onMessageOpenedApp => const Stream.empty();
}

/// The home, notifications and account read paths against a running API
/// (not part of the default run):
///
/// ```sh
/// BAFO_LIVE_API=http://localhost:8000/api/app/v1 \
/// BAFO_DEMO_EMAIL=… BAFO_DEMO_PASSWORD=… \
/// flutter test test/features/account/live_account_test.dart
/// ```
///
/// Read-only, except one push device that is registered and removed.
void main() {
  final base = Platform.environment['BAFO_LIVE_API'];
  final email = Platform.environment['BAFO_DEMO_EMAIL'];
  final password = Platform.environment['BAFO_DEMO_PASSWORD'];
  final skip = base == null || email == null || password == null
      ? 'Set BAFO_LIVE_API, BAFO_DEMO_EMAIL and BAFO_DEMO_PASSWORD'
      : null;

  test('home, notifications, organisation, team, invoices, deletion, push', () async {
    final apiBase = Uri.parse(base!);
    final tokens = InMemoryTokenStore();
    final api = ApiClient.create(
      env: Env(
        apiBaseUrl: apiBase,
        broadcastingAuthUrl: apiBase.replace(path: '/broadcasting/auth'),
        realtime: const RealtimeConfig(
          appKey: '',
          host: 'localhost',
          port: 8085,
          useTls: false,
        ),
      ),
      tokens: tokens,
      clock: ServerClock(),
      languageCode: () => 'ar',
      onUnauthorized: () => fail('unexpected 401'),
      client: testClient,
    );
    final payload = await ApiAuthRepository(
      api,
      deviceName: 'live-test · android',
    ).login(email: email!, password: password!);
    await tokens.write(payload.token);
    final me = payload.me;
    final session = await signedInSession(me);
    void log(String line) {
      // ignore: avoid_print
      print(line);
    }

    final home = HomeCubit(home: ApiHomeRepository(api));
    await home.load();
    final homeState = home.state as HomeLoaded;
    log(
      'Home: ${homeState.home.alerts.map((a) => a.code.wire).toList()} alerts, '
      '${homeState.home.activities.length} activities',
    );
    await home.close();

    final published = <int>[];
    final notifications = NotificationsBloc(
      repository: ApiNotificationsRepository(api),
      onUnreadCount: published.add,
    )..add(const NotificationsStarted());
    final loaded = await notifications.stream.firstWhere(
      (s) => s is NotificationsLoaded,
    ) as NotificationsLoaded;
    log(
      'Notifications: ${loaded.items.length} rows, '
      '${loaded.unreadCount} unread, more: ${loaded.hasMore}',
    );
    expect(published, [loaded.unreadCount]);
    if (loaded.hasMore) {
      notifications.add(const NotificationsNextPageRequested());
      final next = await notifications.stream.firstWhere(
        (s) => s is NotificationsLoaded && !s.loadingMore,
      ) as NotificationsLoaded;
      expect(next.items.length, greaterThan(loaded.items.length));
      expect(next.items.map((n) => n.id).toSet().length, next.items.length);
    }
    await notifications.close();

    final organization = OrganizationCubit(
      organizations: ApiOrganizationRepository(api),
      lookups: ApiLookupsRepository(api, languageCode: () => 'ar'),
      session: session,
    );
    await organization.load();
    final org = organization.state as OrganizationLoaded;
    expect(org.organization.id, me.organization.id);
    expect(org.lookups?.regions, isNotEmpty);
    await organization.close();

    final team = TeamCubit(
      team: ApiTeamRepository(api),
      canManage: me.can(Permissions.teamManage),
    );
    await team.load();
    log('Team: ${team.state.runtimeType}');
    await team.close();

    final invoices = InvoicesCubit(
      billing: ApiBillingRepository(api),
      canView: me.can(Permissions.billingView),
    );
    await invoices.load();
    log('Invoices: ${invoices.state.runtimeType}');
    await invoices.close();

    final deletion = AccountDeletionCubit(ApiAccountDeletionRepository(api));
    await deletion.load();
    expect(deletion.state, isA<AccountDeletionLoaded>());
    await deletion.close();

    final preferences = InMemoryPreferencesStore();
    final devices = ApiDevicesRepository(api);
    final push = PushPermissionCubit(
      push: const _GrantingPush(),
      devices: devices,
      preferences: preferences,
      client: testClient,
    );
    await push.request();
    expect(push.state.status, PushSetupStatus.enabled);
    final deviceId = preferences.getString(PreferenceKeys.pushDeviceId)!;
    await devices.unregister(deviceId);
    await push.close();
    await session.close();
    // Revoke this run's sign-in token.
    await ApiAuthRepository(api, deviceName: 'live-test · android').logout();
  }, skip: skip);
}
