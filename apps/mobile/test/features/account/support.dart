import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import '../../helpers/fakes.dart';

/// A session signed in as [me] (a fixture `Me`), or signed in without `Me`
/// (an offline start) when [me] is null.
Future<SessionCubit> signedInSession([Me? me]) async {
  final session = SessionCubit(
    tokens: InMemoryTokenStore('test-token'),
    unauthorized: const Stream.empty(),
    fetchMe: () async {
      if (me == null) throw const ApiException(code: ApiErrorCode.network);
      return me;
    },
  );
  await session.restore();
  return session;
}

/// An unread-count cubit on a fake realtime client (never subscribes until
/// started).
UnreadCountCubit testUnreadCount() =>
    UnreadCountCubit(realtime: FakeRealtimeClient(), fetchCount: () async => 0);

const testClient = ClientInfo(platform: 'android', appVersion: '1.2.3');

/// Pumps a go_router app with [routes] and the app-level providers the
/// feature screens read ([ServerClock], [NetworkStatusCubit], [ClientInfo]);
/// [providers] may use the router (e.g. a `DeepLinkRouter`).
Future<GoRouter> pumpFeature(
  WidgetTester tester, {
  required List<RouteBase> routes,
  required String initialLocation,
  List<SingleChildWidget> Function(GoRouter router)? providers,
  Locale locale = const Locale('ar'),
  Object? initialExtra,
}) async {
  final router = GoRouter(
    initialLocation: initialLocation,
    initialExtra: initialExtra,
    routes: routes,
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        RepositoryProvider<ServerClock>.value(value: ServerClock()),
        RepositoryProvider<ClientInfo>.value(value: testClient),
        BlocProvider<NetworkStatusCubit>(
          create: (_) => NetworkStatusCubit(probe: () async {}),
        ),
        ...?providers?.call(router),
      ],
      child: MaterialApp.router(
        routerConfig: router,
        locale: locale,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          ...GlobalMaterialLocalizations.delegates,
        ],
        theme: BafoTheme.light(locale.languageCode),
      ),
    ),
  );
  await tester.pump();
  return router;
}

/// Every visible text of the widget tree (for "never shows X" checks).
Iterable<String> visibleTexts(WidgetTester tester) sync* {
  for (final widget in tester.allWidgets) {
    if (widget is Text) {
      final data = widget.data ?? widget.textSpan?.toPlainText();
      if (data != null) yield data;
    } else if (widget is RichText) {
      yield widget.text.toPlainText();
    }
  }
}
