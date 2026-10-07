import 'dart:async';

import 'package:bafo/app/dependencies.dart';
import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/presentation/onboarding_cubit.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/widgets/page_states.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Root widget: provides the app services, restores the session, loads the
/// app config and runs the router in the selected language (Arabic, RTL, by
/// default). It also owns the app lifecycle rules of SCREENS.md §3.5.
class BafoApp extends StatefulWidget {
  const BafoApp({required this.dependencies, super.key});

  final AppDependencies dependencies;

  /// A paused app drops the socket after this long in the background.
  static const Duration backgroundDisconnectDelay = Duration(seconds: 30);

  @override
  State<BafoApp> createState() => _BafoAppState();
}

class _BafoAppState extends State<BafoApp> {
  AppDependencies get _deps => widget.dependencies;

  late final GoRouter _router = createAppRouter(
    session: _deps.session,
    gate: _deps.gate,
    onboardingDue: () => OnboardingCubit.isDue(_deps.preferences),
  );
  late final DeepLinkRouter _deepLinks = DeepLinkRouter(
    router: _router,
    session: _deps.session,
    markRead: (id) => _deps.repositories.notifications.markRead(id),
    flags: () => _deps.appConfig.state.config?.flags ?? FeatureFlags.none,
  );
  late final AppLifecycleListener _lifecycle;
  StreamSubscription<PushMessage>? _pushOpened;
  Timer? _backgroundDisconnect;

  @override
  void initState() {
    super.initState();
    unawaited(_deps.appConfig.load());
    unawaited(_deps.session.restore());
    _pushOpened = _deps.push.onMessageOpenedApp.listen(
      (message) => unawaited(_deepLinks.openPush(message)),
    );
    _lifecycle = AppLifecycleListener(
      onResume: _resumed,
      onHide: _hidden,
    );
  }

  /// Paused / hidden: realtime drops after 30 s (the OS would anyway).
  void _hidden() {
    _backgroundDisconnect?.cancel();
    _backgroundDisconnect = Timer(
      BafoApp.backgroundDisconnectDelay,
      () => unawaited(_deps.realtime.suspend()),
    );
  }

  /// Resumed: re-sync the clock, reconnect, resync every open competition,
  /// and refresh the session-level data (S3 step 1, §3.5).
  void _resumed() {
    _backgroundDisconnect?.cancel();
    _deps.realtime.resume();
    unawaited(_syncTime());
    _deps.hub.notifyResumed();
    unawaited(_deps.appConfig.load());
    if (_deps.session.state.isAuthenticated) {
      unawaited(_deps.session.refreshMe());
      unawaited(_deps.unread.refresh());
    }
  }

  Future<void> _syncTime() async {
    try {
      await _deps.api.get('time');
    } on Object catch (error) {
      debugPrint('Clock sync failed: $error');
    }
  }

  @override
  void dispose() {
    _backgroundDisconnect?.cancel();
    unawaited(_pushOpened?.cancel());
    _lifecycle.dispose();
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final deps = _deps;
    final repos = deps.repositories;
    return MultiRepositoryProvider(
      providers: [
        RepositoryProvider<ClientInfo>.value(value: deps.client),
        RepositoryProvider<ApiClient>.value(value: deps.api),
        RepositoryProvider<ServerClock>.value(value: deps.clock),
        RepositoryProvider<RealtimeClient>.value(value: deps.realtime),
        RepositoryProvider<CompetitionChannelHub>.value(value: deps.hub),
        RepositoryProvider<PushService>.value(value: deps.push),
        RepositoryProvider<PreferencesStore>.value(value: deps.preferences),
        RepositoryProvider<LookupsRepository>.value(value: deps.lookups),
        RepositoryProvider<FileDownloadService>.value(value: deps.downloads),
        RepositoryProvider<DeepLinkRouter>.value(value: _deepLinks),
        RepositoryProvider<AuthRepository>.value(value: repos.auth),
        RepositoryProvider<LegalRepository>.value(value: repos.legal),
        RepositoryProvider<AccountRepository>.value(value: repos.account),
        RepositoryProvider<OrganizationRepository>.value(
          value: repos.organization,
        ),
        RepositoryProvider<AccountDeletionRepository>.value(
          value: repos.accountDeletion,
        ),
        RepositoryProvider<TeamRepository>.value(value: repos.team),
        RepositoryProvider<HomeRepository>.value(value: repos.home),
        RepositoryProvider<CompetitionsRepository>.value(
          value: repos.competitions,
        ),
        RepositoryProvider<AttachmentsRepository>.value(
          value: repos.attachments,
        ),
        RepositoryProvider<CommentsRepository>.value(value: repos.comments),
        RepositoryProvider<VendorsRepository>.value(value: repos.vendors),
        RepositoryProvider<InvitationsRepository>.value(
          value: repos.invitations,
        ),
        RepositoryProvider<LiveRepository>.value(value: repos.live),
        RepositoryProvider<BillingRepository>.value(value: repos.billing),
        RepositoryProvider<NotificationsRepository>.value(
          value: repos.notifications,
        ),
        RepositoryProvider<DevicesRepository>.value(value: repos.devices),
      ],
      child: MultiBlocProvider(
        providers: [
          BlocProvider<LocaleCubit>.value(value: deps.locale),
          BlocProvider<SessionCubit>.value(value: deps.session),
          BlocProvider<AppGateCubit>.value(value: deps.gate),
          BlocProvider<AppConfigCubit>.value(value: deps.appConfig),
          BlocProvider<NetworkStatusCubit>.value(value: deps.network),
          BlocProvider<UnreadCountCubit>.value(value: deps.unread),
        ],
        child: BlocBuilder<LocaleCubit, Locale>(
          builder: (context, locale) => MaterialApp.router(
            onGenerateTitle: (context) => context.l10n.appName,
            debugShowCheckedModeBanner: false,
            routerConfig: _router,
            builder: (context, child) => AnnotatedRegion(
              // System bar icons for screens without an app bar (login).
              value: BafoTheme.systemBarsOn(Theme.of(context).brightness),
              child: _OfflineFrame(child: child ?? const SizedBox.shrink()),
            ),
            locale: locale,
            supportedLocales: AppLocales.supported,
            localizationsDelegates: const [
              AppLocalizations.delegate,
              ...GlobalMaterialLocalizations.delegates,
            ],
            theme: BafoTheme.light(locale.languageCode),
            darkTheme: BafoTheme.dark(locale.languageCode),
            // SCREENS.md CD11: mobile is light-only in v1.
            themeMode: ThemeMode.light,
          ),
        ),
      ),
    );
  }
}

/// Puts the offline banner (M05) above every screen, under the status bar.
class _OfflineFrame extends StatelessWidget {
  const _OfflineFrame({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final offline = context.select<NetworkStatusCubit, bool>(
      (cubit) => cubit.state.isOffline,
    );
    if (!offline) return child;
    final media = MediaQuery.of(context);
    return Column(
      children: [
        Padding(
          padding: EdgeInsetsDirectional.only(top: media.padding.top),
          child: const OfflineBanner(),
        ),
        Expanded(
          child: MediaQuery.removePadding(
            context: context,
            removeTop: true,
            child: child,
          ),
        ),
      ],
    );
  }
}
