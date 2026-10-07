import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/participant/participant_routes.dart';
import 'package:bafo/features/participant/presentation/participant_competition_screen.dart';
import 'package:bafo/features/participant/presentation/widgets/participant_competition_card.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';

import '../../helpers/fakes.dart';
import '../../helpers/pump.dart';
import '../../helpers/scope.dart';
import 'harness.dart';

void main() {
  setUpAll(() {
    registerFallbackValue(CompetitionListRole.participant);
    registerFallbackValue(CompetitionStatusGroup.all);
  });

  late ParticipantHarness h;
  final ar = l10nOf('ar');

  setUp(() async {
    h = ParticipantHarness();
    await h.signIn();
  });

  tearDown(() => h.dispose());

  GoRoute detailRoute() => GoRoute(
    path: '/competitions/:id',
    builder: (_, state) => ParticipantCompetitionScreen(
      competitionId: state.pathParameters['id']!,
      openJoin: state.uri.queryParameters['intent'] == 'join',
      issuerView: (_, id) => Text('issuer view $id'),
      afterJoin: (_) async {},
    ),
  );

  group('M16 participating list', () {
    final rows = fixtureList('competitions_participant_d')
        .map(CompetitionListItem.fromJson)
        .toList();

    setUp(() {
      when(
        () => h.competitions.list(
          role: any(named: 'role'),
          group: any(named: 'group'),
          direction: any(named: 'direction'),
          query: any(named: 'query'),
          page: any(named: 'page'),
        ),
      ).thenAnswer(
        (_) async => Paged(
          items: rows,
          meta: const PageMeta(currentPage: 1, perPage: 20, hasMore: false),
        ),
      );
    });

    testWidgets(
      'invitations come first with Join and Decline; declining refreshes',
      (tester) async {
        final pending = rows.where((row) => row.needsAction).first;
        when(
          () => h.invitations.decline(
            pending.invitationId!,
            reason: any(named: 'reason'),
          ),
        ).thenAnswer(
          (_) async => InviteeInvitation(
            id: pending.invitationId!,
            status: InvitationStatus.declined,
          ),
        );
        await pumpRouter(
          tester,
          initialLocation: '/competitions',
          providers: h.providers(),
          routes: [
            GoRoute(
              path: '/competitions',
              builder: (_, _) => const ParticipatingListScreen(),
            ),
            detailRoute(),
          ],
        );
        await tester.pumpAndSettle();

        expect(
          find.text(ar.competitionsParticipatingNeedsAction(2)),
          findsOneWidget,
        );
        expect(find.text(ar.invitationsJoinAction), findsNWidgets(2));
        // Every card shows its format, live included (as on the issuer's).
        expect(
          find.descendant(
            of: find.byType(ParticipantCompetitionCard).first,
            matching: find.text(ar.competitionsFormatLive),
          ),
          findsOneWidget,
        );
        // No other participant's identity or price on a card.
        expect(find.textContaining('Supplier'), findsNothing);

        await tester.tap(find.text(ar.invitationsDeclineAction).first);
        await tester.pumpAndSettle();
        expect(find.text(ar.invitationsDeclineMessage), findsOneWidget);
        await tester.enterText(
          find.descendant(
            of: find.byKey(const Key('decline-reason')),
            matching: find.byType(EditableText),
          ),
          'خارج نطاق عملنا',
        );
        await tester.tap(find.byKey(const Key('decline-confirm')));
        await tester.pumpAndSettle();

        verify(
          () => h.invitations.decline(
            pending.invitationId!,
            reason: 'خارج نطاق عملنا',
          ),
        ).called(1);
        expect(find.text(ar.invitationsDeclineSucceeded), findsOneWidget);
        verify(
          () => h.competitions.list(
            role: CompetitionListRole.participant,
            group: CompetitionStatusGroup.active,
            page: 1,
          ),
        ).called(2);
      },
    );

    testWidgets('an empty list explains where invitations appear', (
      tester,
    ) async {
      when(
        () => h.competitions.list(
          role: any(named: 'role'),
          group: any(named: 'group'),
          direction: any(named: 'direction'),
          query: any(named: 'query'),
          page: any(named: 'page'),
        ),
      ).thenAnswer(
        (_) async => const Paged<CompetitionListItem>(
          items: [],
          meta: PageMeta(currentPage: 1, perPage: 20, hasMore: false),
        ),
      );
      await pumpRouter(
        tester,
        initialLocation: '/competitions',
        providers: h.providers(),
        routes: [
          GoRoute(
            path: '/competitions',
            builder: (_, _) => const ParticipatingListScreen(),
          ),
        ],
      );
      await tester.pumpAndSettle();
      expect(find.text(ar.competitionsParticipatingEmptyTitle), findsOneWidget);
    });
  });

  group('M16 release scope (RELEASE_SCOPE.md §4.1)', () {
    setUp(() {
      when(
        () => h.competitions.list(
          role: any(named: 'role'),
          group: any(named: 'group'),
          direction: any(named: 'direction'),
          query: any(named: 'query'),
          page: any(named: 'page'),
        ),
      ).thenAnswer(
        (_) async => const Paged<CompetitionListItem>(
          items: [],
          meta: PageMeta(currentPage: 1, perPage: 20, hasMore: false),
        ),
      );
    });

    Future<void> pumpList(WidgetTester tester, FeatureFlags flags) async {
      await pumpRouter(
        tester,
        initialLocation: '/competitions',
        providers: h.providers(flags: flags),
        routes: [
          GoRoute(
            path: '/competitions',
            builder: (_, _) => const ParticipatingListScreen(),
          ),
        ],
      );
      await tester.pumpAndSettle();
    }

    testWidgets('core: the status segments only', (tester) async {
      await pumpList(tester, ScopeFlags.core);
      expect(
        find.text(ar.competitionsParticipatingFilterActive),
        findsOneWidget,
      );
      expect(
        find.text(ar.competitionsParticipatingFilterEnded),
        findsOneWidget,
      );
      expect(find.byKey(const Key('participating.search')), findsNothing);
      expect(find.byKey(const Key('participating.direction')), findsNothing);
    });

    testWidgets('full: search and the direction chips come back', (
      tester,
    ) async {
      await pumpList(tester, ScopeFlags.full);
      expect(find.byKey(const Key('participating.search')), findsOneWidget);
      expect(find.byKey(const Key('participating.direction')), findsOneWidget);
    });
  });

  group('M17 invitee', () {
    final invitee = Competition.fromJson(
      fixtureData('competition_buyer_d_live_initial_invitee'),
    );

    testWidgets(
      'joining needs the terms, then the page becomes the participant view',
      (tester) async {
        final joined = Competition.fromJson({
          ...fixtureData('competition_supplier_a_live_auction'),
          'id': invitee.id,
        });
        when(() => h.competitions.show(invitee.id))
            .thenAnswer((_) async => invitee);
        when(() => h.invitations.join(invitee.invitation!.id))
            .thenAnswer((_) async => joined);
        when(() => h.attachments.list(invitee.id)).thenAnswer(
          (_) async =>
              fixtureList('attachments_supplier_a_live_initial')
                  .map(Attachment.fromJson)
                  .toList(),
        );
        await pumpRouter(
          tester,
          initialLocation: '/competitions/${invitee.id}',
          providers: h.providers(),
          routes: [detailRoute()],
        );
        await tester.pumpAndSettle();

        expect(find.text(ar.invitationsCardTitle), findsOneWidget);
        expect(find.text(ar.invitationsOwnPlan), findsOneWidget);
        // The join deadline as a date, with the time left.
        expect(
          find.textContaining(ar.invitationsJoinBy('').trim()),
          findsOneWidget,
        );

        await tester.tap(find.byKey(const Key('invitee-join')));
        await tester.pumpAndSettle();
        expect(find.text(ar.invitationsJoinIntro), findsOneWidget);

        await tester.ensureVisible(find.byKey(const Key('join-confirm')));
        await tester.tap(find.byKey(const Key('join-confirm')));
        await tester.pumpAndSettle();
        expect(find.text(ar.invitationsJoinTermsRequired), findsOneWidget);
        verifyNever(() => h.invitations.join(any()));

        await tester.tap(find.text(ar.invitationsJoinAcceptTerms));
        await tester.pump();
        await tester.tap(find.byKey(const Key('join-confirm')));
        await tester.pumpAndSettle();

        verify(() => h.invitations.join(invitee.invitation!.id)).called(1);
        expect(find.text(ar.invitationsJoinSucceeded), findsOneWidget);
        expect(find.byKey(const Key('open-live-room')), findsOneWidget);
        expect(find.text(ar.invitationsCardTitle), findsNothing);
      },
    );

    testWidgets(
      'the list "Join" opens the sheet once the invitation has loaded',
      (tester) async {
        when(() => h.competitions.show(invitee.id))
            .thenAnswer((_) async => invitee);
        await pumpRouter(
          tester,
          initialLocation: '/competitions/${invitee.id}?intent=join',
          providers: h.providers(),
          routes: [detailRoute()],
        );
        await tester.pumpAndSettle();
        expect(find.text(ar.invitationsJoinIntro), findsOneWidget);
      },
    );

    testWidgets(
      'plan required: information only, no price, link or purchase button',
      (tester) async {
        final json = fixtureData('competition_buyer_d_live_initial_invitee');
        final planRequired = Competition.fromJson({
          ...json,
          'access': {
            ...(json['access'] as Map<String, dynamic>),
            'state': 'plan_required',
            'coverage': 'none',
          },
          'permissions': {
            ...(json['permissions'] as Map<String, dynamic>),
            'can_join': false,
            'can_decline': true,
          },
        });
        when(() => h.competitions.show(invitee.id))
            .thenAnswer((_) async => planRequired);
        await pumpRouter(
          tester,
          initialLocation: '/competitions/${invitee.id}',
          providers: h.providers(),
          routes: [detailRoute()],
        );
        await tester.pumpAndSettle();

        expect(find.text(ar.invitationsPlanRequiredBody), findsOneWidget);
        expect(find.text(ar.billingManagedOnWeb), findsOneWidget);
        expect(find.byKey(const Key('invitee-join')), findsNothing);
        expect(find.byKey(const Key('invitee-decline')), findsOneWidget);

        await tester.tap(find.text(ar.invitationsPlanRequiredMore));
        await tester.pumpAndSettle();
        expect(find.text(ar.invitationsAccessInfoPlanRequired), findsOneWidget);
        for (final text in visibleTexts(tester)) {
          expect(text, isNot(matches(RegExp(r'https?://|www\.'))));
        }
        expect(find.textContaining(RegExp(r'^\s*SAR|ر\.س')), findsNothing);
      },
    );
  });

  group('M21 participant', () {
    testWidgets('a live auction shows the standing, the offer and the links', (
      tester,
    ) async {
      final competition = Competition.fromJson(
        fixtureData('competition_supplier_a_live_auction'),
      );
      when(() => h.competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => h.attachments.list(competition.id))
          .thenAnswer((_) async => []);
      await pumpRouter(
        tester,
        initialLocation: '/competitions/${competition.id}',
        providers: h.providers(),
        routes: [detailRoute()],
      );
      await tester.pumpAndSettle();

      expect(find.text(ar.liveStatusLeading), findsOneWidget);
      expect(find.text(ar.competitionsParticipantCurrentOffer), findsOneWidget);
      expect(find.textContaining('51,000.00'), findsWidgets);
      expect(find.byKey(const Key('open-live-room')), findsOneWidget);
      expect(find.byKey(const Key('open-qa')), findsOneWidget);
      expect(find.byKey(const Key('open-my-offers')), findsOneWidget);
      expect(h.realtime.subscribed, [
        'competition.${competition.id}.participant.${h.organizationId}',
      ]);
    });

    testWidgets('Q&A and documents follow their flags (on in core and full)', (
      tester,
    ) async {
      final competition = Competition.fromJson(
        fixtureData('competition_supplier_a_live_auction'),
      );
      when(() => h.competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => h.attachments.list(competition.id))
          .thenAnswer((_) async => []);
      Future<void> pumpWith(FeatureFlags flags) async {
        await pumpRouter(
          tester,
          initialLocation: '/competitions/${competition.id}',
          providers: h.providers(flags: flags),
          routes: [detailRoute()],
        );
        await tester.pumpAndSettle();
      }

      await pumpWith(ScopeFlags.core);
      expect(find.byKey(const Key('open-qa')), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text(ar.competitionsDetailDocuments),
        200,
      );
      expect(find.text(ar.competitionsDetailDocuments), findsOneWidget);

      // A later release may switch them off: built conditionally.
      await pumpWith(FeatureFlags(const {}));
      expect(find.byKey(const Key('open-qa')), findsNothing);
      expect(find.byKey(const Key('open-my-offers')), findsOneWidget);
      expect(find.text(ar.competitionsDetailDocuments), findsNothing);
    });

    testWidgets('an awarded competition shows the result panel', (
      tester,
    ) async {
      final competition = Competition.fromJson(
        fixtureData('competition_supplier_a_awarded'),
      );
      when(() => h.competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => h.attachments.list(competition.id))
          .thenAnswer((_) async => []);
      when(() => h.competitions.participantAward(competition.id)).thenAnswer(
        (_) async =>
            const ParticipantAwardView(outcome: AwardOutcome.notSelected),
      );
      await pumpRouter(
        tester,
        initialLocation: '/competitions/${competition.id}',
        providers: h.providers(),
        routes: [detailRoute()],
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text(ar.competitionsParticipantResultTitle),
        200,
      );
      expect(
        find.text(ar.competitionsParticipantResultNotSelected),
        findsOneWidget,
      );
    });

    testWidgets('an issuer at the same path gets the issuer view', (
      tester,
    ) async {
      final issuer = Competition.fromJson(
        fixtureData('competition_issuer_live_initial'),
      );
      when(() => h.competitions.show(issuer.id))
          .thenAnswer((_) async => issuer);
      when(() => h.attachments.list(issuer.id)).thenAnswer((_) async => []);
      await pumpRouter(
        tester,
        initialLocation: '/competitions/${issuer.id}',
        providers: h.providers(),
        routes: [detailRoute()],
      );
      await tester.pumpAndSettle();
      expect(find.text('issuer view ${issuer.id}'), findsOneWidget);
    });
  });

  testWidgets('participantRoutes: my-offers and Q&A resolve before the shell', (
    tester,
  ) async {
    final competition = Competition.fromJson(
      fixtureData('competition_supplier_a_live_auction'),
    );
    when(() => h.live.myOffers(competition.id)).thenAnswer((_) async => []);
    // The detail page sits under its children in the stack.
    when(() => h.competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(() => h.attachments.list(competition.id)).thenAnswer((_) async => []);
    final root = GlobalKey<NavigatorState>();
    final router = GoRouter(
      navigatorKey: root,
      initialLocation: '/competitions/${competition.id}/my-offers',
      routes: [
        ...participantRoutes(root, issuerDetail: (_, id) => Text('issuer $id')),
        GoRoute(
          path: '/competitions/:id/offers',
          builder: (_, _) => const Text('issuer offers log'),
        ),
      ],
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          BlocProvider<NetworkStatusCubit>(
            create: (_) => NetworkStatusCubit(probe: () async {}),
          ),
          ...h.providers(),
        ],
        child: MaterialApp.router(
          routerConfig: router,
          locale: const Locale('ar'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            ...GlobalMaterialLocalizations.delegates,
          ],
          theme: BafoTheme.light('ar'),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text(ar.offersMyEmptyTitle), findsOneWidget);

    // A child the participant routes do not have falls through.
    router.go('/competitions/${competition.id}/offers');
    await tester.pumpAndSettle();
    expect(find.text('issuer offers log'), findsOneWidget);
  });
}
