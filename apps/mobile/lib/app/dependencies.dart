import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/config/app_config_repository.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/realtime/reverb_realtime_client.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/storage/token_store.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';

/// App-lifetime services, wired once at start-up (the composition root).
///
/// Tests build it with [AppDependencies.wire] and fakes.
final class AppDependencies {
  AppDependencies._({
    required this.env,
    required this.client,
    required this.tokens,
    required this.preferences,
    required this.clock,
    required this.api,
    required this.realtime,
    required this.push,
    required this.repositories,
    required this.lookups,
    required this.appConfigRepository,
    required this.downloads,
    required this.hub,
    required this.locale,
    required this.gate,
    required this.network,
    required this.session,
    required this.appConfig,
    required this.unread,
    required this._unauthorized,
    required this._watches,
  });

  /// Production wiring: secure storage, shared preferences, Reverb, no-op
  /// push, the device model as the token name ("Pixel 8 · android").
  static Future<AppDependencies> create(Env env) async {
    final preferences = await SharedPreferencesStore.create();
    final info = await PackageInfo.fromPlatform();
    final platform = defaultTargetPlatform == TargetPlatform.iOS
        ? 'ios'
        : 'android';
    final model = await _deviceModel(platform);
    return wire(
      env: env,
      tokens: SecureTokenStore(),
      preferences: preferences,
      client: ClientInfo(platform: platform, appVersion: info.version),
      deviceName: '$model · $platform',
    );
  }

  static Future<String> _deviceModel(String platform) async {
    try {
      final plugin = DeviceInfoPlugin();
      final model = platform == 'ios'
          ? (await plugin.iosInfo).modelName
          : (await plugin.androidInfo).model;
      final trimmed = model.trim();
      return trimmed.isEmpty
          ? 'BAFO'
          : trimmed.substring(0, trimmed.length.clamp(0, 100));
    } on Object catch (error) {
      debugPrint('Device model unavailable: $error');
      return 'BAFO';
    }
  }

  /// Wires everything from the given leaves. Optional parameters replace the
  /// production implementation (tests).
  static AppDependencies wire({
    required Env env,
    required TokenStore tokens,
    required PreferencesStore preferences,
    ClientInfo? client,
    String deviceName = 'BAFO',
    ServerClock? clock,
    HttpClientAdapter? httpClientAdapter,
    RealtimeClient? realtime,
    PushService? push,
    Repositories Function(Repositories api)? repositories,
  }) {
    final clientInfo =
        client ?? const ClientInfo(platform: 'android', appVersion: '1.0.0');
    final serverClock = clock ?? ServerClock();
    final locale = LocaleCubit(preferences);
    final gate = AppGateCubit();
    // Owned by the returned instance and closed in [dispose].
    // ignore: close_sinks
    final unauthorized = StreamController<void>.broadcast();

    late final NetworkStatusCubit network;
    final api = ApiClient.create(
      env: env,
      tokens: tokens,
      clock: serverClock,
      languageCode: () => locale.state.languageCode,
      onUnauthorized: () => unauthorized.add(null),
      gate: gate,
      client: clientInfo,
      httpClientAdapter: httpClientAdapter,
      extraInterceptors: [NetworkStatusInterceptor(() => network)],
    );
    final realtimeClient =
        realtime ??
        ReverbRealtimeClient(
          config: env.realtime,
          authEndpoint: env.broadcastingAuthUrl,
          api: api,
        );
    network = NetworkStatusCubit(
      probe: () => api.get('time'),
      realtime: realtimeClient.connectionState,
    );

    final apiRepositories = Repositories.api(api, deviceName: deviceName);
    final repos = repositories?.call(apiRepositories) ?? apiRepositories;
    final appConfigRepository = ApiAppConfigRepository(api, preferences);

    final session = SessionCubit(
      tokens: tokens,
      unauthorized: unauthorized.stream,
      fetchMe: repos.account.me,
      revokeToken: repos.auth.logout,
      beforeSignOut: () => _unregisterDevice(repos.devices, preferences),
    );
    final appConfig = AppConfigCubit(
      repository: appConfigRepository,
      gate: gate,
      realtime: realtimeClient,
      env: env,
      client: clientInfo,
    );
    final unread = UnreadCountCubit(
      realtime: realtimeClient,
      fetchCount: repos.notifications.unreadCount,
    );

    // Private channels belong to the signed-in user: follow the user channel
    // while signed in, drop every channel on sign-out. Cancelled in
    // [dispose].
    // ignore: cancel_subscriptions
    final sessionWatch = session.stream.listen((state) {
      switch (state) {
        case SessionAuthenticated(:final me?):
          unread.start(
            userId: me.user.id,
            initialCount: me.unreadNotificationsCount,
          );
        case SessionUnauthenticated():
          unawaited(
            unread.stop().whenComplete(realtimeClient.disconnect),
          );
        default:
          break;
      }
    });
    // S1: a language switch is saved on the account (`PATCH /me`), and the
    // server-localised config is read again. Cancelled in [dispose].
    // ignore: cancel_subscriptions
    final localeWatch = locale.stream.listen((value) {
      unawaited(appConfig.load());
      if (!session.state.isAuthenticated) return;
      unawaited(
        repos.account
            .updateMe(locale: value.languageCode)
            .then(session.updateMe)
            .catchError((Object error) {
              debugPrint('Saving the language failed: $error');
            }),
      );
    });

    return AppDependencies._(
      env: env,
      client: clientInfo,
      tokens: tokens,
      preferences: preferences,
      clock: serverClock,
      api: api,
      realtime: realtimeClient,
      push: push ?? const NoopPushService(),
      repositories: repos,
      lookups: ApiLookupsRepository(
        api,
        languageCode: () => locale.state.languageCode,
      ),
      appConfigRepository: appConfigRepository,
      downloads: ApiFileDownloadService(api),
      hub: CompetitionChannelHub(realtimeClient),
      locale: locale,
      gate: gate,
      network: network,
      session: session,
      appConfig: appConfig,
      unread: unread,
      unauthorized: unauthorized,
      watches: [sessionWatch, localeWatch],
    );
  }

  /// Sign-out step 1 (SCREENS.md §3.6): `DELETE /devices/{id}` while the
  /// token still works.
  static Future<void> _unregisterDevice(
    DevicesRepository devices,
    PreferencesStore preferences,
  ) async {
    final id = preferences.getString(PreferenceKeys.pushDeviceId);
    if (id == null || id.isEmpty) return;
    await devices.unregister(id);
    await preferences.remove(PreferenceKeys.pushDeviceId);
  }

  final Env env;
  final ClientInfo client;
  final TokenStore tokens;
  final PreferencesStore preferences;
  final ServerClock clock;
  final ApiClient api;
  final RealtimeClient realtime;
  final PushService push;
  final Repositories repositories;
  final LookupsRepository lookups;
  final AppConfigRepository appConfigRepository;
  final FileDownloadService downloads;
  final CompetitionChannelHub hub;
  final LocaleCubit locale;
  final AppGateCubit gate;
  final NetworkStatusCubit network;
  final SessionCubit session;
  final AppConfigCubit appConfig;
  final UnreadCountCubit unread;

  final StreamController<void> _unauthorized;
  final List<StreamSubscription<Object?>> _watches;

  Future<void> dispose() async {
    for (final watch in _watches) {
      await watch.cancel();
    }
    await hub.dispose();
    await unread.close();
    await session.close();
    await appConfig.close();
    await network.close();
    await locale.close();
    await gate.close();
    await realtime.dispose();
    await _unauthorized.close();
    api.dio.close();
  }
}

/// Every feature repository (API.md §1), so phase-2 screens read them from
/// context and tests swap any of them.
final class Repositories {
  const Repositories({
    required this.auth,
    required this.legal,
    required this.account,
    required this.organization,
    required this.accountDeletion,
    required this.team,
    required this.home,
    required this.competitions,
    required this.attachments,
    required this.comments,
    required this.vendors,
    required this.invitations,
    required this.live,
    required this.billing,
    required this.notifications,
    required this.devices,
  });

  factory Repositories.api(ApiClient api, {required String deviceName}) =>
      Repositories(
        auth: ApiAuthRepository(api, deviceName: deviceName),
        legal: ApiLegalRepository(api),
        account: ApiAccountRepository(api),
        organization: ApiOrganizationRepository(api),
        accountDeletion: ApiAccountDeletionRepository(api),
        team: ApiTeamRepository(api),
        home: ApiHomeRepository(api),
        competitions: ApiCompetitionsRepository(api),
        attachments: ApiAttachmentsRepository(api),
        comments: ApiCommentsRepository(api),
        vendors: ApiVendorsRepository(api),
        invitations: ApiInvitationsRepository(api),
        live: ApiLiveRepository(api),
        billing: ApiBillingRepository(api),
        notifications: ApiNotificationsRepository(api),
        devices: ApiDevicesRepository(api),
      );

  /// A copy with some repositories replaced (tests).
  Repositories copyWith({
    AuthRepository? auth,
    LegalRepository? legal,
    AccountRepository? account,
    CompetitionsRepository? competitions,
    AttachmentsRepository? attachments,
    NotificationsRepository? notifications,
    DevicesRepository? devices,
    BillingRepository? billing,
    HomeRepository? home,
  }) => Repositories(
    auth: auth ?? this.auth,
    legal: legal ?? this.legal,
    account: account ?? this.account,
    organization: organization,
    accountDeletion: accountDeletion,
    team: team,
    home: home ?? this.home,
    competitions: competitions ?? this.competitions,
    attachments: attachments ?? this.attachments,
    comments: comments,
    vendors: vendors,
    invitations: invitations,
    live: live,
    billing: billing ?? this.billing,
    notifications: notifications ?? this.notifications,
    devices: devices ?? this.devices,
  );

  final AuthRepository auth;
  final LegalRepository legal;
  final AccountRepository account;
  final OrganizationRepository organization;
  final AccountDeletionRepository accountDeletion;
  final TeamRepository team;
  final HomeRepository home;
  final CompetitionsRepository competitions;
  final AttachmentsRepository attachments;
  final CommentsRepository comments;
  final VendorsRepository vendors;
  final InvitationsRepository invitations;
  final LiveRepository live;
  final BillingRepository billing;
  final NotificationsRepository notifications;
  final DevicesRepository devices;
}
