import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

/// The app's localisation and theme around [home] (a screen or a widget),
/// with a [ServerClock] and any extra [providers].
Future<void> pumpLocalized(
  WidgetTester tester,
  Widget home, {
  Locale locale = const Locale('ar'),
  ServerClock? clock,
  List<SingleChildWidget> providers = const [],
  bool wrapInScaffold = true,
}) {
  return tester.pumpWidget(
    MultiProvider(
      providers: [
        RepositoryProvider<ServerClock>.value(value: clock ?? ServerClock()),
        ...providers,
      ],
      child: MaterialApp(
        locale: locale,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          ...GlobalMaterialLocalizations.delegates,
        ],
        theme: BafoTheme.light(locale.languageCode),
        home: wrapInScaffold ? Scaffold(body: home) : home,
      ),
    ),
  );
}

/// A router app with [routes] starting at [initialLocation], for screens
/// that navigate.
Future<GoRouter> pumpRouter(
  WidgetTester tester, {
  required List<RouteBase> routes,
  required String initialLocation,
  Locale locale = const Locale('ar'),
  List<SingleChildWidget> providers = const [],
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
        BlocProvider<NetworkStatusCubit>(
          create: (_) => NetworkStatusCubit(probe: () async {}),
        ),
        ...providers,
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
  return router;
}

/// Types [text] into the [EditableText] under the widget keyed [key].
Future<void> enterByKey(WidgetTester tester, String key, String text) =>
    tester.enterText(
      find.descendant(of: find.byKey(Key(key)), matching: find.byType(EditableText)),
      text,
    );
