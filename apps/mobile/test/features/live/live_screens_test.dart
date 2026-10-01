import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/presentation/live_room_screen.dart';
import 'package:bafo/features/live/presentation/my_offers_screen.dart';
import 'package:bafo/widgets/ltr.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';
import '../../helpers/pump.dart';
import '../participant/harness.dart';

void main() {
  late ParticipantHarness h;
  final ar = l10nOf('ar');
  final en = l10nOf('en');

  setUp(() async {
    h = ParticipantHarness();
    await h.signIn();
  });

  tearDown(() => h.dispose());

  List<RouteBase> routes() => [
    GoRoute(
      path: '/competitions/:id',
      builder: (_, state) => Text('overview ${state.pathParameters['id']}'),
      routes: [
        GoRoute(
          path: 'live',
          builder: (_, state) => LiveRoomScreen(
            competitionId: state.pathParameters['id']!,
            clockSync: NoopClockSync(),
          ),
        ),
        GoRoute(
          path: 'my-offers',
          builder: (_, state) =>
              MyOffersScreen(competitionId: state.pathParameters['id']!),
        ),
      ],
    ),
  ];

  void stubRoom(
    String competitionFixture,
    String liveFixture, {
    Map<String, dynamic> liveChanges = const {},
  }) {
    final competition = Competition.fromJson(fixtureData(competitionFixture));
    final live = {...fixtureData(liveFixture), ...liveChanges};
    when(() => h.competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(
      () => h.live.participantSnapshot(competition.id),
    ).thenAnswer((_) async => (ParticipantLiveSnapshot.fromJson(live), live));
    when(() => h.live.heartbeat(competition.id)).thenAnswer((_) async {});
  }

  Future<String> openRoom(
    WidgetTester tester,
    String competitionFixture,
    String liveFixture, {
    Locale locale = const Locale('ar'),
    Map<String, dynamic> liveChanges = const {},
  }) async {
    stubRoom(competitionFixture, liveFixture, liveChanges: liveChanges);
    final id = fixtureData(competitionFixture)['id'] as String;
    h.realtime.setConnection(RealtimeConnectionState.connected);
    await pumpRouter(
      tester,
      initialLocation: '/competitions/$id/live',
      locale: locale,
      providers: h.providers(),
      routes: routes(),
    );
    await tester.pumpAndSettle();
    return id;
  }

  Future<void> typeAmount(WidgetTester tester, String text) => tester.enterText(
    find.descendant(
      of: find.byKey(const Key('offer-amount')),
      matching: find.byType(EditableText),
    ),
    text,
  );

  testWidgets(
    'M23 auction: standing, leading amount, bound pre-check, confirm and submit',
    (tester) async {
      final id = await openRoom(
        tester,
        'competition_supplier_a_live_auction',
        'live_supplier_a_auction',
      );
      expect(find.text(ar.liveRoomTitle), findsOneWidget);
      expect(find.text(ar.liveStatusLeading), findsOneWidget);
      expect(find.textContaining('51,000.00'), findsWidgets);
      expect(find.text(ar.liveConnectionLive), findsOneWidget);

      // Below the required next amount: refused before sending (CD13).
      await typeAmount(tester, '51000');
      await tester.tap(find.byKey(const Key('offer-submit')));
      await tester.pumpAndSettle();
      expect(
        find.text(
          ar.offersErrorStepNotMet(
            'auction',
            ltrIsolate(
              MoneyFormat.format(const Money(5150000), languageCode: 'ar'),
            ),
          ),
        ),
        findsOneWidget,
      );
      verifyNever(
        () => h.live.submitOffer(
          any(),
          amountMinor: any(named: 'amountMinor'),
          idempotencyKey: any(named: 'idempotencyKey'),
          confirmOutlier: any(named: 'confirmOutlier'),
        ),
      );

      // "Use 51,500.00" fills the server bound.
      await tester.tap(find.byKey(const Key('use-required-amount')));
      await tester.pumpAndSettle();
      final live = fixtureData('live_supplier_a_auction');
      final accepted = {
        ...live,
        'v': 4,
        'my_offer': {
          'id': 'offer-2',
          'seq': 3,
          'amount_minor': 5150000,
          'stage': 'live',
          'accepted_at': '2026-09-29T18:30:01.412Z',
        },
        'my_offers_count': 2,
        'leading_amount_minor': 5150000,
        'required_next_amount_minor': 5200000,
      };
      when(
        () => h.live.submitOffer(
          id,
          amountMinor: 5150000,
          idempotencyKey: any(named: 'idempotencyKey'),
        ),
      ).thenAnswer(
        (_) async => OfferSubmission(
          offer: MyOffer.fromJson(
            accepted['my_offer']! as Map<String, dynamic>,
          ),
          live: ParticipantLiveSnapshot.fromJson(accepted),
          liveJson: accepted,
        ),
      );
      await tester.tap(find.byKey(const Key('offer-submit')));
      await tester.pumpAndSettle();
      expect(find.text(ar.offersConfirmTitle), findsOneWidget);
      expect(find.text(ar.offersConfirmExclVat), findsOneWidget);

      await tester.tap(find.byKey(const Key('offer-confirm')));
      await tester.pumpAndSettle();
      final keys = verify(
        () => h.live.submitOffer(
          id,
          amountMinor: 5150000,
          idempotencyKey: captureAny(named: 'idempotencyKey'),
        ),
      ).captured;
      expect(keys.single, matches(RegExp(r'^[A-Za-z0-9_-]{8,64}$')));
      // The receipt time is the server's, in Riyadh time with milliseconds.
      expect(find.textContaining('21:30:01.412'), findsWidgets);
      // The room applied the response snapshot (v guard): the new bound.
      expect(find.textContaining('52,000.00'), findsWidgets);
    },
  );

  testWidgets('M26 sealed: the lock panel, the sealed note, and the receipt', (
    tester,
  ) async {
    final id = await openRoom(
      tester,
      'competition_supplier_a_live_sealed',
      'live_supplier_a_sealed',
    );
    expect(find.text(ar.liveSealedTitle), findsOneWidget);
    expect(find.text(ar.liveStatusLeading), findsNothing);
    expect(find.text(ar.liveComposerSubmitRevise), findsOneWidget);

    final live = fixtureData('live_supplier_a_sealed');
    final offer = {
      'id': 'offer-9',
      'seq': 4,
      'amount_minor': 17000000,
      'stage': 'sealed',
      'accepted_at': '2026-09-29T18:31:00.250Z',
    };
    final accepted = {...live, 'v': 3, 'my_offer': offer, 'my_offers_count': 2};
    when(
      () => h.live.submitOffer(
        id,
        amountMinor: 17000000,
        idempotencyKey: any(named: 'idempotencyKey'),
      ),
    ).thenAnswer(
      (_) async => OfferSubmission(
        offer: MyOffer.fromJson(offer),
        live: ParticipantLiveSnapshot.fromJson(accepted),
        liveJson: accepted,
      ),
    );
    await typeAmount(tester, '170000');
    await tester.tap(find.byKey(const Key('offer-submit')));
    await tester.pumpAndSettle();
    expect(find.text(ar.offersConfirmSealed), findsOneWidget);
    await tester.tap(find.byKey(const Key('offer-confirm')));
    await tester.pumpAndSettle();
    expect(find.text(ar.offersSealedReceived), findsOneWidget);
    expect(find.text('#4'), findsOneWidget);
  });

  testWidgets(
    'M27 BAFO: the shortlisted get the banner and a final-offer composer',
    (tester) async {
      await openRoom(
        tester,
        'competition_supplier_a_bafo_round',
        'live_supplier_c_bafo',
        // Newer than the competition's own live block.
        liveChanges: const {'v': 99},
      );
      expect(find.text(ar.competitionsStatusBafoRound), findsWidgets);
      expect(find.text(ar.bafoRule('tender')), findsWidgets);
      expect(find.text(ar.liveComposerSubmitBafo), findsOneWidget);
    },
  );

  testWidgets('M27 BAFO: the others see why they cannot offer', (tester) async {
    await openRoom(
      tester,
      'competition_supplier_a_bafo_round',
      'live_supplier_a_bafo',
    );
    expect(find.text(ar.liveComposerNotShortlisted), findsWidgets);
    expect(find.byKey(const Key('offer-submit')), findsNothing);
  });

  testWidgets('M23 in English is left to right, with the currency first', (
    tester,
  ) async {
    await openRoom(
      tester,
      'competition_supplier_a_live_auction',
      'live_supplier_a_auction',
      locale: const Locale('en'),
    );
    expect(find.text(en.liveRoomTitle), findsOneWidget);
    expect(find.text(en.liveStatusLeading), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.text(en.liveStatusLeading))),
      TextDirection.ltr,
    );
    expect(find.textContaining('SAR 51,000.00'), findsWidgets);
  });

  testWidgets('M23 reconnecting shows the banner and pauses submitting', (
    tester,
  ) async {
    await openRoom(
      tester,
      'competition_supplier_a_live_auction',
      'live_supplier_a_auction',
    );
    h.realtime.setConnection(RealtimeConnectionState.connecting);
    await tester.pump();
    await tester.pump(const Duration(seconds: 3));
    await tester.pump();
    expect(find.text(ar.liveConnectionReconnecting), findsWidgets);
    expect(find.text(ar.liveConnectionSubmitPaused), findsOneWidget);
    h.realtime.setConnection(RealtimeConnectionState.connected);
    await tester.pumpAndSettle();
    expect(find.text(ar.liveConnectionSubmitPaused), findsNothing);
  });

  testWidgets('the heartbeat stops while another page covers the room', (
    tester,
  ) async {
    final id = await openRoom(
      tester,
      'competition_supplier_a_live_auction',
      'live_supplier_a_auction',
    );
    verify(() => h.live.heartbeat(id)).called(1);
    when(() => h.live.myOffers(id)).thenAnswer((_) async => []);

    // My offers opens above the room: no heartbeat while it covers it (S4).
    await tester.tap(find.byTooltip(ar.liveMyOffersLink));
    await tester.pumpAndSettle();
    expect(find.byType(MyOffersScreen), findsOneWidget);
    await tester.pump(const Duration(seconds: 45));
    verifyNever(() => h.live.heartbeat(id));

    // Back in the room: the heartbeat starts again at once.
    GoRouter.of(tester.element(find.byType(MyOffersScreen))).pop();
    await tester.pumpAndSettle();
    verify(() => h.live.heartbeat(id)).called(1);
  });

  testWidgets('an invitee opening the room goes back to the overview', (
    tester,
  ) async {
    final invitee = Competition.fromJson(
      fixtureData('competition_buyer_d_live_initial_invitee'),
    );
    when(() => h.competitions.show(invitee.id))
        .thenAnswer((_) async => invitee);
    await pumpRouter(
      tester,
      initialLocation: '/competitions/${invitee.id}/live',
      providers: h.providers(),
      routes: routes(),
    );
    await tester.pumpAndSettle();
    expect(find.text('overview ${invitee.id}'), findsOneWidget);
  });

  testWidgets('M28 my offers: sequence, stage and the neutral change', (
    tester,
  ) async {
    const id = 'c1';
    when(() => h.live.myOffers(id)).thenAnswer(
      (_) async => [
        MyOffer(
          id: 'o2',
          seq: 5,
          amountMinor: 9800000,
          stage: OfferStage.live,
          acceptedAt: DateTime.utc(2026, 9, 29, 18, 0, 1, 412),
        ),
        MyOffer(
          id: 'o1',
          seq: 2,
          amountMinor: 10000000,
          stage: OfferStage.initial,
          acceptedAt: DateTime.utc(2026, 9, 29, 17, 0),
        ),
      ],
    );
    await pumpRouter(
      tester,
      initialLocation: '/competitions/$id/my-offers',
      providers: h.providers(),
      routes: routes(),
    );
    await tester.pumpAndSettle();
    expect(find.text(ar.offersMySeq(5)), findsOneWidget);
    expect(find.text(ar.offersStageLive), findsOneWidget);
    expect(find.text('↓ 2%'), findsOneWidget);
    expect(find.text('2026-09-29 21:00:01.412 (KSA)'), findsOneWidget);
  });
}
