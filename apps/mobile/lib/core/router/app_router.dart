import 'dart:async';

import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/router/app_shell.dart';
import 'package:bafo/core/router/gate_screens.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/account/presentation/account_routes.dart';
import 'package:bafo/features/auth/presentation/auth_routes.dart';
import 'package:bafo/features/auth/presentation/splash_screen.dart';
import 'package:bafo/features/billing/presentation/plan_status_screen.dart';
import 'package:bafo/features/home/presentation/home_screen.dart';
import 'package:bafo/features/issuer/issuer.dart';
import 'package:bafo/features/notifications/presentation/notifications_screen.dart';
import 'package:bafo/features/participant/participant_routes.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Route paths. Feature routes follow the canonical client routes of
/// CONVENTIONS.md §4.3 (e.g. `/competitions/:id`, `/competitions/:id/live`),
/// so push deep links map one to one (see `DeepLinkRouter`).
abstract final class AppRoutes {
  static const String splash = '/splash';
  static const String welcome = '/welcome';
  static const String login = '/login';
  static const String register = '/register';
  static const String verify = '/verify';
  static const String forgotPassword = '/forgot-password';
  static const String resetPassword = '/reset-password';

  /// `/legal/:code` (M13), open with or without a session.
  static const String legalPrefix = '/legal';
  static String legal(String code) => '$legalPrefix/$code';

  static const String updateRequired = '/update-required';
  static const String maintenance = '/maintenance';
  static const String accountBlocked = '/account-blocked';

  /// M58, on the root navigator (SCREENS.md R-M2).
  static const String billing = '/billing';

  // Tabs of the signed-in shell, in bottom-navigation order.
  static const String home = '/home';

  /// Participant side: competitions the organisation is invited to.
  static const String competitions = '/competitions';

  /// `/competitions/:id` and its children, on the root navigator.
  static String competition(String id) => '$competitions/$id';

  /// Issuer side: competitions the organisation issues.
  static const String myCompetitions = '/my-competitions';
  static const String notifications = '/notifications';
  static const String account = '/account';

  /// The signed-out flow; a signed-in user is sent home from these.
  static const Set<String> authFlow = {
    welcome,
    login,
    register,
    verify,
    forgotPassword,
    resetPassword,
  };

  static const Set<String> gates = {
    updateRequired,
    maintenance,
    accountBlocked,
  };

  /// Reachable without a session.
  static bool isPublic(String path) =>
      authFlow.contains(path) || path.startsWith('$legalPrefix/');
}

/// The full redirect: the app gate first, then [authRedirect].
String? appRedirect({
  required AppGateState gate,
  required SessionState session,
  required Uri location,
  bool onboardingDue = false,
}) {
  final path = location.path;
  switch (gate) {
    case AppGateUpdateRequired():
      return path == AppRoutes.updateRequired ? null : AppRoutes.updateRequired;
    case AppGateMaintenance():
      return path == AppRoutes.maintenance ? null : AppRoutes.maintenance;
    case AppGateAccountBlocked():
      if (!session.isAuthenticated) break;
      return path == AppRoutes.accountBlocked ? null : AppRoutes.accountBlocked;
    case AppGateOpen():
      break;
  }
  // Leaving a gate screen: start again from home; auth decides from there.
  if (AppRoutes.gates.contains(path)) return AppRoutes.home;
  return authRedirect(session, location, onboardingDue: onboardingDue);
}

/// Where the session sends [location], or null to stay.
///
/// * unknown: the splash, until the session is restored;
/// * signed out: the welcome slides on the first run, otherwise the login
///   screen; a protected target is kept in `from` (a path only, never a
///   token) and restored after sign-in, except right after an explicit
///   sign-out;
/// * signed in: away from splash and the sign-in flow, to `from` or home.
String? authRedirect(
  SessionState session,
  Uri location, {
  bool onboardingDue = false,
}) {
  final path = location.path;
  final atSplash = path == AppRoutes.splash;

  switch (session) {
    case SessionUnknown():
      return atSplash ? null : _withFrom(AppRoutes.splash, location);
    case SessionUnauthenticated(:final signedOut):
      if (AppRoutes.isPublic(path)) return null;
      // After a sign-out the page the user was on is not kept: whoever signs
      // in next starts at home.
      final from = signedOut
          ? null
          : atSplash
          ? _fromOf(location)
          : location;
      if (onboardingDue) return _withFrom(AppRoutes.welcome, from);
      return _withFrom(AppRoutes.login, from);
    case SessionAuthenticated():
      if (!atSplash && !AppRoutes.authFlow.contains(path)) return null;
      return _fromOf(location)?.toString() ?? AppRoutes.home;
  }
}

String _withFrom(String target, Uri? from) {
  if (from == null || !_isRestorable(from.path)) return target;
  return Uri(
    path: target,
    queryParameters: {'from': from.toString()},
  ).toString();
}

Uri? _fromOf(Uri location) {
  final from = location.queryParameters['from'];
  if (from == null) return null;
  final uri = Uri.tryParse(from);
  // Only in-app paths: never follow a scheme or host from the query.
  if (uri == null || uri.hasScheme || uri.hasAuthority) return null;
  return _isRestorable(uri.path) ? uri : null;
}

bool _isRestorable(String path) =>
    path.startsWith('/') &&
    !path.startsWith('//') &&
    path != '/' &&
    path != AppRoutes.splash &&
    path != AppRoutes.home &&
    !AppRoutes.isPublic(path) &&
    !AppRoutes.gates.contains(path);

/// Builds the app router. It re-evaluates [appRedirect] whenever the session
/// or the gate changes.
GoRouter createAppRouter({
  required SessionCubit session,
  required AppGateCubit gate,
  bool Function()? onboardingDue,
  GlobalKey<NavigatorState>? navigatorKey,
}) {
  // The navigator above the shell: competition screens and `/billing` open
  // here, so they work from any tab and from push (SCREENS.md §3.1) and hide
  // the bottom bar.
  final rootKey = navigatorKey ?? GlobalKey<NavigatorState>(debugLabel: 'root');
  return GoRouter(
    navigatorKey: rootKey,
    initialLocation: AppRoutes.home,
    debugLogDiagnostics: kDebugMode,
    refreshListenable: _StreamsListenable([session.stream, gate.stream]),
    redirect: (context, state) => appRedirect(
      gate: gate.state,
      session: session.state,
      location: state.uri,
      onboardingDue: onboardingDue?.call() ?? false,
    ),
    routes: [
      GoRoute(path: '/', redirect: (_, _) => AppRoutes.home),
      GoRoute(path: AppRoutes.splash, builder: (_, _) => const SplashScreen()),
      ...authRoutes(),
      GoRoute(
        path: AppRoutes.updateRequired,
        builder: (_, _) => const UpdateRequiredScreen(),
      ),
      GoRoute(
        path: AppRoutes.maintenance,
        builder: (_, _) => const MaintenanceScreen(),
      ),
      GoRoute(
        path: AppRoutes.accountBlocked,
        builder: (_, _) => const AccountBlockedScreen(),
      ),
      GoRoute(
        path: AppRoutes.billing,
        parentNavigatorKey: rootKey,
        builder: (_, state) => PlanStatusScreen(
          highlightInvoices: state.uri.queryParameters['notice'] == 'invoices',
        ),
      ),
      // Participant screens on the root navigator (M17/M21, M23, M28, M31):
      // owned by features/participant, live and qa. Listed before the shell
      // so `/competitions/:id`, `live`, `qa` and `my-offers` win over the
      // shell's overview subtree; the issuer's side of the shared paths is
      // chosen by `viewer_role` (M38, M46).
      ...participantRoutes(
        rootKey,
        issuerDetail: issuerDetailView,
        issuerLive: issuerLiveView,
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(navigationShell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.home,
                builder: (_, _) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.competitions,
                // M16 «مشاركاتي»: owned by features/participant. The
                // `/competitions/:id` subtree lives on the root navigator
                // (participantRoutes and issuerCompetitionRoutes).
                builder: (_, _) => const ParticipatingListScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              // My competitions tab (M33–M37): owned by features/issuer.
              issuerTabRoute(rootKey),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.notifications,
                builder: (_, _) => const NotificationsScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              // Account tab (M51–M61): owned by features/account.
              accountRoute(),
            ],
          ),
        ],
      ),
      // Issuer-only children of `/competitions/:id` (M39–M42, M47, M48):
      // owned by features/issuer, matched after the shell's `:id` subtree.
      ...issuerCompetitionRoutes(rootKey),
    ],
  );
}

/// Adapts streams to the [Listenable] go_router listens to.
class _StreamsListenable extends ChangeNotifier {
  _StreamsListenable(List<Stream<Object?>> streams) {
    _subscriptions = [
      for (final stream in streams) stream.listen((_) => notifyListeners()),
    ];
  }

  late final List<StreamSubscription<Object?>> _subscriptions;

  @override
  void dispose() {
    for (final subscription in _subscriptions) {
      unawaited(subscription.cancel());
    }
    super.dispose();
  }
}
