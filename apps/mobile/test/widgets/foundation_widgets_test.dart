import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:dio/dio.dart' show CancelToken, ProgressCallback;
import 'package:flutter/semantics.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../helpers/fakes.dart';
import '../helpers/pump.dart';

class _FakeDownloads implements FileDownloadService {
  final List<String> downloaded = [];
  final List<String> opened = [];

  @override
  Future<String> download(
    FileRef file, {
    ProgressCallback? onProgress,
    CancelToken? cancelToken,
  }) async {
    onProgress?.call(50, 100);
    downloaded.add(file.downloadPath);
    return '/tmp/${file.name}';
  }

  @override
  Future<FileOpenResult> open(String path, {String? mimeType}) async {
    opened.add(path);
    return FileOpenResult.opened;
  }
}

/// A clock frozen at [at] (server time).
ServerClock frozenClock(DateTime at) => ServerClock(deviceNow: () => at);

void main() {
  final now = DateTime.utc(2026, 11, 9, 12);

  group('chips', () {
    testWidgets('direction chip: glyph and rule, same neutral tone', (
      tester,
    ) async {
      await pumpLocalized(
        tester,
        const Column(
          children: [
            DirectionChip(direction: Direction.tender),
            DirectionChip(direction: Direction.auction),
            FormatChip(format: CompetitionFormat.sealed),
          ],
        ),
      );
      expect(find.text('مناقصة · الأقل سعراً يفوز'), findsOneWidget);
      expect(find.text('مزايدة · الأعلى سعراً يفوز'), findsOneWidget);
      expect(find.byIcon(Icons.south_rounded), findsOneWidget);
      expect(find.byIcon(Icons.north_rounded), findsOneWidget);
      expect(find.text('بظرف مغلق'), findsOneWidget);
    });

    testWidgets('status chip with the closing-soon and extended pills', (
      tester,
    ) async {
      await pumpLocalized(
        tester,
        CompetitionStatusChip(
          status: CompetitionStatus.live,
          phase: CompetitionPhase.open,
          effectiveCloseAt: now.add(const Duration(minutes: 4)),
          extensionCount: 2,
          clock: frozenClock(now),
        ),
        locale: const Locale('en'),
      );
      expect(find.text('Open for offers'), findsOneWidget);
      expect(find.text('Closing soon'), findsOneWidget);
      expect(find.text('Extended'), findsOneWidget);
    });

    testWidgets('BAFO round and cancelled chips', (tester) async {
      await pumpLocalized(
        tester,
        const Column(
          children: [
            CompetitionStatusChip(status: CompetitionStatus.bafoRound),
            CompetitionStatusChip(status: CompetitionStatus.cancelled),
            AccessStateChip(state: AccessState.planRequired),
            ResultChip(outcome: AwardOutcome.won),
            FeesCoveredBadge(),
          ],
        ),
      );
      expect(find.text('جولة العرض النهائي'), findsOneWidget);
      expect(find.text('ملغاة'), findsOneWidget);
      expect(find.text('تتطلب باقة'), findsOneWidget);
      expect(find.text('تمت الترسية عليكم'), findsOneWidget);
      expect(find.text('رسوم مغطّاة'), findsOneWidget);
    });

    testWidgets('standing badge is hidden unless projected', (tester) async {
      await pumpLocalized(
        tester,
        const Column(
          children: [
            StandingBadge(isLeading: true),
            StandingBadge(isLeading: false),
            StandingBadge(isLeading: null),
          ],
        ),
      );
      expect(find.text('متصدر'), findsOneWidget);
      expect(find.text('غير متصدر'), findsOneWidget);
      expect(find.byType(StatusPill), findsNWidgets(2));
    });
  });

  group('StandingBanner', () {
    testWidgets('renders the server standing with direction-aware copy', (
      tester,
    ) async {
      await pumpLocalized(
        tester,
        const Column(
          children: [
            StandingBanner(
              direction: Direction.tender,
              hasOffer: true,
              isLeading: true,
            ),
            StandingBanner(
              direction: Direction.tender,
              hasOffer: true,
              isLeading: false,
            ),
            StandingBanner(
              direction: Direction.auction,
              hasOffer: true,
              isLeading: false,
            ),
            StandingBanner(
              direction: Direction.tender,
              hasOffer: true,
              rank: 2,
              rankedCount: 5,
            ),
            StandingBanner(direction: Direction.tender, hasOffer: true),
            StandingBanner(direction: Direction.tender, hasOffer: false),
          ],
        ),
      );
      expect(find.text('عرضك هو العرض المتصدر'), findsOneWidget);
      expect(
        find.text('عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.'),
        findsOneWidget,
      );
      expect(
        find.text('عرضك ليس العرض المتصدر. ارفع عرضك لتنافس.'),
        findsOneWidget,
      );
      expect(find.text('ترتيبك 2 من 5'), findsOneWidget);
      expect(
        find.text('استُلم عرضك. لا تُظهر هذه المنافسة الترتيب.'),
        findsOneWidget,
      );
      expect(find.text('لم تقدّم عرضاً بعد.'), findsOneWidget);
    });

    testWidgets('is a live region whose label is throttled', (tester) async {
      var leading = true;
      late StateSetter update;
      await pumpLocalized(
        tester,
        StatefulBuilder(
          builder: (context, setState) {
            update = setState;
            return StandingBanner(
              direction: Direction.tender,
              hasOffer: true,
              isLeading: leading,
            );
          },
        ),
      );
      final handle = tester.ensureSemantics();
      Matcher liveRegionWith(String label) =>
          isSemantics(label: label, isLiveRegion: true);
      SemanticsNode node() => tester.getSemantics(
        find
            .descendant(
              of: find.byType(StandingBanner),
              matching: find.byType(Semantics),
            )
            .first,
      );
      expect(node(), liveRegionWith('عرضك هو العرض المتصدر'));

      update(() => leading = false);
      await tester.pump();
      // Visual updates at once; the announced label waits (≤ 1 per 10 s).
      expect(
        find.text('عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.'),
        findsOneWidget,
      );
      expect(node(), liveRegionWith('عرضك هو العرض المتصدر'));
      await tester.pump(const Duration(seconds: 11));
      expect(
        node(),
        liveRegionWith('عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.'),
      );
      handle.dispose();
    });
  });

  group('MoneyInputField', () {
    Future<(GlobalKey<FormState>, List<int?>)> pump(
      WidgetTester tester, {
      Locale locale = const Locale('ar'),
      int granularity = 1,
    }) async {
      final key = GlobalKey<FormState>();
      final amounts = <int?>[];
      await pumpLocalized(
        tester,
        Form(
          key: key,
          child: MoneyInputField(
            label: 'المبلغ',
            granularityMinor: granularity,
            onAmountChanged: amounts.add,
            validator: (minor) => minor > 25000000 ? 'فوق السقف' : null,
          ),
        ),
        locale: locale,
      );
      return (key, amounts);
    }

    testWidgets(
      'Arabic: label after the amount, Arabic-Indic digits accepted',
      (tester) async {
        final (key, amounts) = await pump(tester);
        expect(find.text('ر.س'), findsOneWidget);
        expect(
          find.text('الأسعار لا تشمل ضريبة القيمة المضافة'),
          findsOneWidget,
        );
        await tester.enterText(find.byType(EditableText), '١٢٥٠٫٥');
        expect(amounts.last, 125050);
        expect(key.currentState!.validate(), isTrue);
        await tester.enterText(find.byType(EditableText), '300000');
        expect(key.currentState!.validate(), isFalse);
        await tester.pump();
        expect(find.text('فوق السقف'), findsOneWidget);
      },
    );

    testWidgets(
      'separators while not focused, plain digits while editing (FQ3)',
      (tester) async {
        final (key, amounts) = await pump(tester, granularity: 100);
        await tester.enterText(find.byType(EditableText), '125000');
        expect(amounts.last, 12500000);
        expect(find.text('مثال: 125,000.00'), findsOneWidget);

        FocusManager.instance.primaryFocus?.unfocus();
        await tester.pump();
        final field = tester.widget<EditableText>(find.byType(EditableText));
        expect(field.controller.text, '125,000');
        expect(key.currentState!.validate(), isTrue);

        await tester.tap(find.byType(EditableText));
        await tester.pump();
        expect(field.controller.text, '125000');

        // Halalas allowed: two decimals in the grouped text.
        final (_, decimals) = await pump(tester);
        await tester.enterText(find.byType(EditableText), '1250.5');
        expect(decimals.last, 125050);
        FocusManager.instance.primaryFocus?.unfocus();
        await tester.pump();
        expect(
          tester
              .widget<EditableText>(find.byType(EditableText))
              .controller
              .text,
          '1,250.50',
        );
      },
    );

    testWidgets('English: SAR before the amount; whole riyals only', (
      tester,
    ) async {
      final (key, amounts) = await pump(
        tester,
        locale: const Locale('en'),
        granularity: 100,
      );
      expect(find.text('SAR'), findsOneWidget);
      await tester.enterText(find.byType(EditableText), '10.50');
      expect(amounts.last, isNull);
      expect(key.currentState!.validate(), isFalse);
      await tester.pump();
      expect(
        find.text('Enter the amount in whole riyals, without halalas.'),
        findsOneWidget,
      );
    });
  });

  group('form fields', () {
    testWidgets('OTP: one numeric input, six boxes, completes at 6 digits', (
      tester,
    ) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      String? completed;
      await pumpLocalized(
        tester,
        OtpField(controller: controller, onCompleted: (v) => completed = v),
      );
      final field = tester.widget<TextField>(
        find.byKey(const Key('otp.input')),
      );
      expect(field.autofillHints, contains(AutofillHints.oneTimeCode));
      await tester.enterText(find.byKey(const Key('otp.input')), '١٢٣٤٥٦٧');
      await tester.pump();
      expect(controller.text, '123456');
      expect(completed, '123456');
      for (final digit in ['1', '2', '3', '4', '5', '6']) {
        expect(find.text(digit), findsOneWidget);
      }
    });

    testWidgets('password checklist follows the typing', (tester) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await pumpLocalized(
        tester,
        SingleChildScrollView(
          child: PasswordField(controller: controller, showRules: true),
        ),
        locale: const Locale('en'),
      );
      expect(find.byIcon(Icons.check_circle_rounded), findsNothing);
      await tester.enterText(find.byType(EditableText), 'Bafo-2026');
      await tester.pump();
      expect(find.byIcon(Icons.check_circle_rounded), findsNWidgets(5));
    });

    testWidgets('phone field: fixed +966, LTR, 9 digits', (tester) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await pumpLocalized(tester, PhoneField(controller: controller));
      expect(find.text('+966'), findsOneWidget);
      await tester.enterText(find.byType(EditableText), '٥١٢٣٤٥٦٧٨٩٩');
      expect(controller.text, '512345678');
      expect(PhoneField.toE164(controller.text), '+966512345678');
      final editable = tester.widget<EditableText>(find.byType(EditableText));
      expect(editable.textDirection, TextDirection.ltr);
    });

    testWidgets('cooldown button counts down', (tester) async {
      await pumpLocalized(
        tester,
        CooldownButton(
          label: 'Send',
          onPressed: () {},
          availableAt: DateTime.now().add(const Duration(seconds: 3)),
        ),
        locale: const Locale('en'),
      );
      expect(find.text('Try again in 3s'), findsOneWidget);
      await tester.pump(const Duration(seconds: 4));
      expect(find.text('Send'), findsOneWidget);
    });
  });

  group('CompetitionCountdown', () {
    testWidgets('extension banner, latest close, and "Closing…" at zero', (
      tester,
    ) async {
      var at = now;
      final clock = ServerClock(deviceNow: () => at);
      await pumpLocalized(
        tester,
        CompetitionCountdown(
          target: CountdownTarget.closes,
          deadline: now.add(const Duration(seconds: 2)),
          extensionCount: 3,
          hardStopAt: now.add(const Duration(minutes: 30)),
          clock: clock,
        ),
        clock: clock,
      );
      expect(find.text('تُغلق خلال'), findsOneWidget);
      expect(find.text('00:00:02'), findsOneWidget);
      expect(find.text('مُدّد وقت الإغلاق 3 مرات.'), findsOneWidget);
      expect(find.textContaining('أقصى موعد للإغلاق'), findsOneWidget);
      at = now.add(const Duration(seconds: 3));
      await tester.pump(const Duration(seconds: 3));
      expect(find.text('جارٍ الإغلاق…'), findsOneWidget);
    });
  });

  group('content', () {
    testWidgets('attachment tile downloads with the service and opens', (
      tester,
    ) async {
      final downloads = _FakeDownloads();
      final attachment = Attachment.fromJson(
        fixtureList('attachments_issuer_live_initial').first,
      );
      await pumpLocalized(
        tester,
        AttachmentTile(attachment: attachment, downloads: downloads),
      );
      expect(find.text('كراسة الشروط والمواصفات'), findsOneWidget);
      expect(find.text('218 بايت'), findsOneWidget);
      expect(find.text('ملحق'), findsOneWidget);
      await tester.tap(find.byType(AttachmentTile));
      await tester.pump();
      await tester.pump();
      expect(downloads.downloaded, [attachment.file!.downloadPath]);
      expect(downloads.opened, ['/tmp/specifications.pdf']);
    });

    testWidgets('rules summary shows the server lines', (tester) async {
      await pumpLocalized(
        tester,
        const RulesSummaryCard(
          lines: ['مناقصة: العرض الأقل سعراً يتصدر.', 'سطر ثانٍ'],
        ),
      );
      expect(find.text('قواعد المنافسة'), findsOneWidget);
      expect(find.text('سطر ثانٍ'), findsOneWidget);
    });

    testWidgets('markdown: headings, emphasis and lists', (tester) async {
      await pumpLocalized(
        tester,
        const SingleChildScrollView(
          child: MarkdownView(
            data: '# العنوان\n\nنص **مهم** هنا.\n\n- أول\n- ثانٍ\n\n1. واحد',
          ),
        ),
      );
      expect(find.text('العنوان'), findsOneWidget);
      expect(find.textContaining('مهم', findRichText: true), findsOneWidget);
      expect(find.text('•'), findsNWidgets(2));
      expect(find.text('1.'), findsOneWidget);
    });

    testWidgets('offline banner shows while offline, RTL', (tester) async {
      final network = NetworkStatusCubit(probe: () async {});
      addTearDown(network.close);
      await pumpLocalized(
        tester,
        const OfflineBanner(),
        providers: [BlocProvider<NetworkStatusCubit>.value(value: network)],
      );
      expect(find.text('لا يوجد اتصال بالإنترنت.'), findsNothing);
      network.reportUnreachable();
      await tester.pumpAndSettle();
      expect(find.text('لا يوجد اتصال بالإنترنت.'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.byType(OfflineBanner))),
        TextDirection.rtl,
      );
      network.reportReachable();
    });

    testWidgets('forbidden and not-found states', (tester) async {
      await pumpLocalized(
        tester,
        const Column(
          children: [
            Expanded(child: ForbiddenState()),
            Expanded(child: NotFoundState()),
          ],
        ),
      );
      expect(
        find.text('ليست لديك صلاحية لهذه الصفحة. تواصل مع مالك الحساب.'),
        findsOneWidget,
      );
      expect(find.text('غير متاحة'), findsOneWidget);
    });
  });
}
