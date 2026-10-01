import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

/// Pumps [child] in a themed, localised app.
Future<void> pumpKit(
  WidgetTester tester,
  Widget child, {
  Locale locale = const Locale('ar'),
  ServerClock? clock,
}) {
  return tester.pumpWidget(
    RepositoryProvider<ServerClock>.value(
      value: clock ?? ServerClock(),
      child: MaterialApp(
        locale: locale,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          ...GlobalMaterialLocalizations.delegates,
        ],
        theme: BafoTheme.light(locale.languageCode),
        home: Scaffold(body: child),
      ),
    ),
  );
}

void main() {
  group('formatCountdown', () {
    late AppLocalizations ar;
    late AppLocalizations en;

    setUpAll(() async {
      ar = await AppLocalizations.delegate.load(const Locale('ar'));
      en = await AppLocalizations.delegate.load(const Locale('en'));
    });

    test('shows HH:MM:SS under 24 hours', () {
      expect(
        formatCountdown(const Duration(minutes: 4, seconds: 5), en),
        '00:04:05',
      );
      expect(
        formatCountdown(
          const Duration(hours: 23, minutes: 59, seconds: 59),
          en,
        ),
        '23:59:59',
      );
    });

    test('shows "N days HH:MM" from 24 hours, pluralised per language', () {
      const d = Duration(days: 2, hours: 3, minutes: 15, seconds: 7);
      expect(formatCountdown(d, en), '2 days 03:15');
      expect(formatCountdown(d, ar), 'يومان 03:15');
      expect(formatCountdown(const Duration(days: 1), en), '1 day 00:00');
      expect(formatCountdown(const Duration(days: 11), ar), '11 يوماً 00:00');
    });
  });

  testWidgets('CountdownText ticks on the server clock and reports the end', (
    tester,
  ) async {
    var deviceNow = DateTime.utc(2026, 10, 1, 12);
    final clock = ServerClock(deviceNow: () => deviceNow)
      // The device runs 10 s behind the server.
      ..sync(DateTime.utc(2026, 10, 1, 12, 0, 10));
    var elapsed = 0;

    await pumpKit(
      tester,
      CountdownText(
        deadline: DateTime.utc(2026, 10, 1, 12, 0, 13),
        clock: clock,
        onElapsed: () => elapsed++,
      ),
      locale: const Locale('en'),
    );
    final text = find.text('00:00:03');
    expect(text, findsOneWidget);
    // The last five minutes use the warning colour.
    expect(
      tester.widget<Text>(text).style?.color,
      BafoSemanticColors.light.warning.foreground,
    );

    deviceNow = deviceNow.add(const Duration(seconds: 1));
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('00:00:02'), findsOneWidget);

    deviceNow = deviceNow.add(const Duration(seconds: 2));
    await tester.pump(const Duration(seconds: 2));
    await tester.pump();
    expect(find.text('Time is up'), findsOneWidget);
    expect(elapsed, 1);
  });

  testWidgets('MoneyText formats per language with Western digits', (
    tester,
  ) async {
    await pumpKit(tester, const MoneyText(Money(125050)));
    expect(find.text('1,250.50 ر.س'), findsOneWidget);

    await pumpKit(
      tester,
      const MoneyText(Money(125050)),
      locale: const Locale('en'),
    );
    expect(find.text('SAR 1,250.50'), findsOneWidget);
  });

  testWidgets('ConfirmDialog resolves true only on confirm', (tester) async {
    bool? result;
    await pumpKit(
      tester,
      Builder(
        builder: (context) => BafoButton(
          label: 'open',
          onPressed: () async => result = await showConfirmDialog(
            context,
            title: 'Delete?',
            message: 'This cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
          ),
        ),
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('إلغاء'));
    await tester.pumpAndSettle();
    expect(result, isFalse);

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Delete'));
    await tester.pumpAndSettle();
    expect(result, isTrue);
  });

  testWidgets('BafoButton ignores taps while loading', (tester) async {
    var taps = 0;
    await pumpKit(
      tester,
      BafoButton(label: 'Save', loading: true, onPressed: () => taps++),
    );
    await tester.tap(find.text('Save'));
    expect(taps, 0);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });

  test('OrgAvatar initials handle Arabic and Latin names', () {
    expect(OrgAvatar.initialsOf('شركة الأفق'), 'شا');
    expect(OrgAvatar.initialsOf('acme trading co'), 'AT');
    expect(OrgAvatar.initialsOf('  '), '');
  });
}
