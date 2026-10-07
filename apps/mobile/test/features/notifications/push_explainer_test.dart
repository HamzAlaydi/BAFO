import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/presentation/push_explainer_sheet.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';
import '../../helpers/pump.dart';
import '../../helpers/scope.dart';
import '../account/support.dart';

class _MockDevices extends Mock implements DevicesRepository {}

void main() {
  late InMemoryPreferencesStore preferences;
  late _MockDevices devices;

  setUp(() {
    preferences = InMemoryPreferencesStore();
    devices = _MockDevices();
  });

  Future<void> pumpTrigger(
    WidgetTester tester, {
    Future<void> Function(BuildContext context) show =
        showPushExplainerIfNeeded,
    String? scope,
  }) => pumpLocalized(
    tester,
    Builder(
      builder: (context) => Center(
        child: TextButton(
          onPressed: () => show(context),
          child: const Text('joined'),
        ),
      ),
    ),
    providers: [
      if (scope != null) releaseScopeProvider(scope),
      RepositoryProvider<PushService>.value(value: const NoopPushService()),
      RepositoryProvider<DevicesRepository>.value(value: devices),
      RepositoryProvider<PreferencesStore>.value(value: preferences),
      RepositoryProvider<ClientInfo>.value(value: testClient),
    ],
  );

  testWidgets('M50 explains once, then asks; no-op push sends nothing', (
    tester,
  ) async {
    await pumpTrigger(tester);

    await tester.tap(find.text('joined'));
    await tester.pumpAndSettle();
    expect(find.text('فعّلوا التنبيهات الفورية'), findsOneWidget);
    expect(find.textContaining('لا تتضمن التنبيهات أي مبالغ'), findsOneWidget);

    await tester.tap(find.byKey(const Key('push.allow')));
    await tester.pumpAndSettle();
    expect(find.textContaining('غير متاحة على هذا الجهاز'), findsOneWidget);
    verifyZeroInteractions(devices);
    expect(preferences.getString(PreferenceKeys.pushExplainerShown), '1');

    // Shown once per device.
    await tester.tap(find.text('joined'));
    await tester.pumpAndSettle();
    expect(find.text('فعّلوا التنبيهات الفورية'), findsNothing);
  });

  testWidgets('"Not now" closes without asking the OS', (tester) async {
    await pumpTrigger(tester);

    await tester.tap(find.text('joined'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('push.notNow')));
    await tester.pumpAndSettle();
    expect(find.text('فعّلوا التنبيهات الفورية'), findsNothing);
    expect(find.textContaining('غير متاحة'), findsNothing);
  });

  group('after join / publish in a release scope (RELEASE_SCOPE.md §4.1)', () {
    testWidgets('core never prompts', (tester) async {
      await pumpTrigger(tester, show: showPushExplainerInScope, scope: 'core');

      await tester.tap(find.text('joined'));
      await tester.pumpAndSettle();
      expect(find.text('فعّلوا التنبيهات الفورية'), findsNothing);
      // Not marked shown: a later full release still explains it once.
      expect(preferences.getString(PreferenceKeys.pushExplainerShown), isNull);
    });

    testWidgets('full explains once as before', (tester) async {
      await pumpTrigger(tester, show: showPushExplainerInScope, scope: 'full');

      await tester.tap(find.text('joined'));
      await tester.pumpAndSettle();
      expect(find.text('فعّلوا التنبيهات الفورية'), findsOneWidget);
    });
  });
}
