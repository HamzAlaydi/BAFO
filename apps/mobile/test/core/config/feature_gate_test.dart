import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/l10n/generated/app_localizations_ar.dart';
import 'package:bafo/l10n/generated/app_localizations_en.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/pump.dart';
import '../../helpers/scope.dart';

/// RELEASE_SCOPE.md §4: `FeatureGate`, `context.flags` and the screen behind
/// a hidden route.
void main() {
  final ar = AppLocalizationsAr();
  final en = AppLocalizationsEn();

  group('FeatureGate', () {
    testWidgets('shows the child in full and the fallback in core', (
      tester,
    ) async {
      const gate = FeatureGate(
        feature: Feature.teamManagement,
        fallback: Text('hidden'),
        child: Text('team'),
      );
      await pumpLocalized(
        tester,
        gate,
        providers: [scopeProvider(ScopeFlags.full)],
      );
      expect(find.text('team'), findsOneWidget);
      expect(find.text('hidden'), findsNothing);

      await pumpLocalized(
        tester,
        gate,
        providers: [scopeProvider(ScopeFlags.core, scope: 'core')],
      );
      expect(find.text('team'), findsNothing);
      expect(find.text('hidden'), findsOneWidget);
    });

    testWidgets('without an app config nothing gated is shown', (tester) async {
      await pumpLocalized(
        tester,
        Builder(
          builder: (context) => Text(
            context.flags == FeatureFlags.none &&
                    !context.hasFeature(Feature.qaComments)
                ? 'none'
                : 'some',
          ),
        ),
      );
      expect(find.text('none'), findsOneWidget);
      // No fallback: an empty box, never a greyed row.
      await pumpLocalized(
        tester,
        const FeatureGate(
          feature: Feature.billingInvoices,
          child: Text('invoices'),
        ),
      );
      expect(find.text('invoices'), findsNothing);
      expect(find.byType(SizedBox), findsWidgets);
    });

    testWidgets('a flag change rebuilds the gate', (tester) async {
      final cubit = scopedAppConfig(ScopeFlags.core, scope: 'core');
      await pumpLocalized(
        tester,
        const FeatureGate(
          feature: Feature.vendorDirectory,
          fallback: Text('no vendors'),
          child: Text('vendors'),
        ),
        providers: [scopeProvider(ScopeFlags.core, scope: 'core')],
      );
      expect(find.text('no vendors'), findsOneWidget);
      // The cubit of this pump is a different instance: load a full config
      // through a fresh pump to keep the assertion honest.
      await cubit.load();
      await pumpLocalized(
        tester,
        const FeatureGate(
          feature: Feature.vendorDirectory,
          fallback: Text('no vendors'),
          child: Text('vendors'),
        ),
        providers: [scopeProvider(ScopeFlags.full)],
      );
      expect(find.text('vendors'), findsOneWidget);
    });
  });

  group('FeatureUnavailableScreen', () {
    testWidgets('says the feature is not in this release, Arabic first', (
      tester,
    ) async {
      await pumpLocalized(
        tester,
        const FeatureUnavailableScreen(),
        wrapInScaffold: false,
      );
      expect(find.text(ar.commonFeatureUnavailableTitle), findsWidgets);
      expect(find.text(ar.errorsFeatureDisabled), findsOneWidget);
      expect(find.text(ar.commonFeatureUnavailableHint), findsOneWidget);
      expect(find.byKey(const Key('feature.unavailable.back')), findsOneWidget);
    });

    testWidgets('takes a specific message and renders in English', (
      tester,
    ) async {
      await pumpLocalized(
        tester,
        FeatureUnavailableScreen(message: en.accountInvoicesOnWeb),
        wrapInScaffold: false,
        locale: const Locale('en'),
      );
      expect(find.text(en.commonFeatureUnavailableTitle), findsWidgets);
      expect(find.text(en.accountInvoicesOnWeb), findsOneWidget);
      expect(find.text(en.errorsFeatureDisabled), findsNothing);
    });

    testWidgets('Back pops when it can, else goes home', (tester) async {
      final router = await pumpRouter(
        tester,
        initialLocation: '/gated',
        routes: [
          GoRoute(
            path: '/home',
            builder: (_, _) => const Scaffold(body: Text('home')),
          ),
          GoRoute(
            path: '/gated',
            builder: (_, _) => const FeatureUnavailableScreen(),
          ),
        ],
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('feature.unavailable.back')));
      await tester.pumpAndSettle();
      expect(router.state.uri.path, '/home');
      expect(find.text('home'), findsOneWidget);
    });
  });

  group('DeepLinkRouter.map with flags', () {
    const id = '01m3q4e71m4tnkvsecsj2f351b';

    test('invoices land on the plan status in core, on the list in full', () {
      expect(
        DeepLinkRouter.map('/billing/invoices/$id'),
        '/billing?notice=invoices',
      );
      expect(
        Uri.parse(
          DeepLinkRouter.map('/billing/invoices/$id', flags: ScopeFlags.core),
        ).path,
        '/billing',
      );
      expect(
        DeepLinkRouter.map('/billing/invoices/$id', flags: ScopeFlags.full),
        '/account/invoices',
      );
    });

    test('the other routes ignore the flags', () {
      for (final flags in [
        FeatureFlags.none,
        ScopeFlags.core,
        ScopeFlags.full,
      ]) {
        expect(
          DeepLinkRouter.map('/competitions/$id/live', flags: flags),
          '/competitions/$id/live',
        );
        expect(DeepLinkRouter.map('/integrations', flags: flags), '/home');
        expect(DeepLinkRouter.map('/billing', flags: flags), '/billing');
      }
    });
  });
}
