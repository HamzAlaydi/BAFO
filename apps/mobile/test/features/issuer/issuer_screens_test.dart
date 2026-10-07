import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/issuer/data/issuer_report_repository.dart';
import 'package:bafo/features/issuer/issuer.dart';
import 'package:bafo/features/issuer/presentation/award/award_screen.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/create/draft_form_fields.dart';
import 'package:bafo/features/issuer/presentation/list/my_competitions_screen.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_monitor_screen.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/l10n/generated/app_localizations_ar.dart';
import 'package:bafo/l10n/generated/app_localizations_en.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';

import '../../helpers/scope.dart';
import 'issuer_test_helpers.dart';

void main() {
  setUpAll(registerIssuerFallbacks);

  final ar = AppLocalizationsAr();
  final en = AppLocalizationsEn();

  late MockCompetitions competitions;
  late MockAttachments attachments;
  late MockInvitations invitations;
  late MockVendors vendors;
  late MockLive live;
  late MockLookups lookups;
  late MockReports reports;
  late MockDownloads downloads;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;
  late SessionCubit session;

  setUp(() {
    competitions = MockCompetitions();
    attachments = MockAttachments();
    invitations = MockInvitations();
    vendors = MockVendors();
    live = MockLive();
    lookups = MockLookups();
    reports = MockReports();
    downloads = MockDownloads();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
    when(() => lookups.lookups()).thenAnswer((_) async => fixtureLookups());
    // Parent routes build too (the list under the wizard, the detail under
    // its children).
    when(() => attachments.list(any())).thenAnswer((_) async => []);
    when(
      () => competitions.list(
        role: CompetitionListRole.issuer,
        group: any(named: 'group'),
        query: any(named: 'query'),
        page: any(named: 'page'),
      ),
    ).thenAnswer((_) async => issuerPage());
  });

  tearDown(() async {
    await session.close();
    await hub.dispose();
    await realtime.dispose();
  });

  Future<void> signIn(String meFixture) async {
    session = SessionCubit(
      tokens: InMemoryTokenStore(),
      unauthorized: const Stream.empty(),
      fetchMe: () async => fixtureMe(meFixture),
    );
    await session.signIn(
      AuthTokenPayload(token: 't', me: fixtureMe(meFixture)),
    );
  }

  Future<GoRouter> pump(
    WidgetTester tester, {
    required String location,
    Locale locale = const Locale('ar'),
    // The format cards, the vendor segment and the reserve are hidden
    // features (RELEASE_SCOPE.md §4): the screens run in scope `full` unless
    // a test says otherwise.
    FeatureFlags? flags,
  }) async {
    final rootKey = GlobalKey<NavigatorState>();
    Widget stub(String name) => Scaffold(body: Text('stub:$name'));
    final router = GoRouter(
      navigatorKey: rootKey,
      initialLocation: location,
      routes: [
        issuerTabRoute(rootKey),
        GoRoute(
          path: '/competitions/:id',
          builder: (_, state) => IssuerCompetitionScreen(
            competitionId: state.pathParameters['id']!,
          ),
          routes: [
            GoRoute(
              path: 'live',
              builder: (context, state) =>
                  issuerLiveView(context, state.pathParameters['id']!),
            ),
            GoRoute(path: 'qa', builder: (_, _) => stub('qa')),
            ...issuerCompetitionChildRoutes(rootKey),
          ],
        ),
      ],
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          scopeProvider(flags ?? ScopeFlags.full),
          RepositoryProvider<ServerClock>.value(value: ServerClock()),
          BlocProvider<NetworkStatusCubit>(
            create: (_) => NetworkStatusCubit(probe: () async {}),
          ),
          BlocProvider<SessionCubit>.value(value: session),
          RepositoryProvider<CompetitionsRepository>.value(value: competitions),
          RepositoryProvider<AttachmentsRepository>.value(value: attachments),
          RepositoryProvider<InvitationsRepository>.value(value: invitations),
          RepositoryProvider<VendorsRepository>.value(value: vendors),
          RepositoryProvider<LiveRepository>.value(value: live),
          RepositoryProvider<LookupsRepository>.value(value: lookups),
          RepositoryProvider<IssuerReportRepository>.value(value: reports),
          RepositoryProvider<FileDownloadService>.value(value: downloads),
          RepositoryProvider<CompetitionChannelHub>.value(value: hub),
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

  /// A tall phone, so lazily built lists show every row.
  void tall(WidgetTester tester) {
    tester.view
      ..physicalSize = const Size(1080, 7000)
      ..devicePixelRatio = 2.7;
    addTearDown(tester.view.reset);
  }

  void stubList() => when(
    () => competitions.list(
      role: CompetitionListRole.issuer,
      group: any(named: 'group'),
      query: any(named: 'query'),
      page: any(named: 'page'),
    ),
  ).thenAnswer((_) async => issuerPage());

  void stubDetail(Competition competition) {
    when(() => competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(() => attachments.list(competition.id)).thenAnswer((_) async => []);
  }

  group('M33 My competitions', () {
    testWidgets('lists the issuer competitions, RTL, with the create button', (
      tester,
    ) async {
      await signIn('me_issuer');
      stubList();
      await pump(tester, location: '/my-competitions');
      await tester.pumpAndSettle();

      expect(find.text(ar.issuerListSegmentActive), findsOneWidget);
      expect(find.text(ar.issuerListSegmentDrafts), findsOneWidget);
      expect(find.text(ar.issuerListSegmentEnded), findsOneWidget);
      expect(find.text('خدمات النظافة للفروع (رسوم مغطّاة)'), findsOneWidget);
      expect(
        find.widgetWithText(FloatingActionButton, ar.issuerListCreate),
        findsOneWidget,
      );
      expect(
        Directionality.of(
          tester.element(find.byType(IssuerCompetitionCard).first),
        ),
        TextDirection.rtl,
      );
    });

    testWidgets('without an active plan: the web notice, no create button', (
      tester,
    ) async {
      await signIn('me_supplier_b');
      when(
        () => competitions.list(
          role: CompetitionListRole.issuer,
          group: any(named: 'group'),
          query: any(named: 'query'),
          page: any(named: 'page'),
        ),
      ).thenAnswer((_) async => issuerPage(items: const []));
      await pump(tester, location: '/my-competitions');
      await tester.pumpAndSettle();

      expect(find.text(ar.issuerPlanRequired), findsOneWidget);
      expect(find.text(ar.billingManagedOnWeb), findsOneWidget);
      expect(find.byType(FloatingActionButton), findsNothing);
      expect(find.text(ar.issuerListEmptyActiveTitle), findsOneWidget);
      // Entitlement-only: no price, link or purchase action anywhere.
      expect(find.textContaining('http'), findsNothing);
      expect(find.textContaining('ر.س'), findsNothing);
    });

    testWidgets('a card opens the competition detail', (tester) async {
      await signIn('me_issuer');
      final closed = issuerCompetition('competition_issuer_closed');
      when(
        () => competitions.list(
          role: CompetitionListRole.issuer,
          group: any(named: 'group'),
          query: any(named: 'query'),
          page: any(named: 'page'),
        ),
      ).thenAnswer(
        (_) async => issuerPage(
          items: issuerPage().items
              .where((item) => item.id == closed.id)
              .toList(),
        ),
      );
      stubDetail(closed);
      final router = await pump(tester, location: '/my-competitions');
      await tester.pumpAndSettle();
      await tester.tap(find.text(closed.title).first);
      await tester.pumpAndSettle();
      expect(router.state.uri.path, '/competitions/${closed.id}');
      expect(find.text(ar.issuerNavAward), findsOneWidget);
    });
  });

  group('M34–M37 create', () {
    testWidgets(
      'step 1: auction gated by the organisation flag, preset picked, advanced on web',
      (tester) async {
        await signIn('me_supplier_c');
        await pump(tester, location: '/my-competitions/new');
        await tester.pumpAndSettle();

        expect(find.text(ar.issuerCreateAuctionDisabled), findsOneWidget);
        expect(find.text(ar.issuerCreateAdvancedOnWeb), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey('direction-auction')));
        await tester.pump();
        await tester.tap(find.byKey(const ValueKey('direction-tender')));
        await tester.pump();
        await tester.tap(find.byKey(const ValueKey('format-live')));
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey('preset-standard_live_tender')),
          findsOneWidget,
        );
        expect(
          find.byKey(const ValueKey('preset-surplus_sale_auction')),
          findsNothing,
        );

        await tester.tap(find.text(ar.commonActionsNext));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCreateStepBasics), findsOneWidget);
        expect(find.text('${ar.issuerFieldTitle} *'), findsOneWidget);
      },
    );

    testWidgets('without an active plan the wizard shows the web notice only', (
      tester,
    ) async {
      await signIn('me_supplier_b');
      await pump(tester, location: '/my-competitions/new');
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerPlanRequired), findsWidgets);
      expect(find.text(ar.issuerCreateStepType), findsNothing);
      verifyNever(() => lookups.lookups());
    });

    testWidgets('Next without a choice shows the step problems', (
      tester,
    ) async {
      await signIn('me_issuer');
      await pump(tester, location: '/my-competitions/new');
      await tester.pumpAndSettle();
      await tester.tap(find.text(ar.commonActionsNext));
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerCreateStepType), findsOneWidget);
      expect(find.text(ar.validationRequired), findsNWidgets(2));
    });
  });

  group('M38 issuer detail', () {
    testWidgets(
      'a draft shows the checklist and publish; sponsorship payment is on the web',
      (tester) async {
        await signIn('me_issuer');
        final draft = issuerCompetition('competition_issuer_draft');
        stubDetail(draft);
        when(() => competitions.publish(draft.id))
            .thenThrow(apiError('sponsorship_payment_required', status: 409));
        await pump(tester, location: '/competitions/${draft.id}');
        await tester.pumpAndSettle();

        expect(find.text(ar.issuerChecklistTitle), findsOneWidget);
        expect(find.text(ar.issuerNavLive), findsNothing);
        await tester.tap(
          find.widgetWithText(BafoButton, ar.issuerActionPublish),
        );
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerPublishNote), findsOneWidget);
        await tester.tap(
          find.widgetWithText(BafoButton, ar.issuerPublishConfirm),
        );
        await tester.pumpAndSettle();
        expect(find.text(ar.sponsorshipManagedOnWeb), findsOneWidget);
        expect(find.textContaining('http'), findsNothing);
      },
    );

    testWidgets(
      'a successful publish shows the server state, not an optimistic one',
      (tester) async {
        await signIn('me_issuer');
        final draft = issuerCompetition('competition_issuer_draft');
        final scheduled = issuerCompetition(
          'competition_issuer_scheduled',
          (json) => json['id'] = draft.id,
        );
        var published = false;
        when(() => competitions.show(draft.id))
            .thenAnswer((_) async => published ? scheduled : draft);
        when(() => competitions.publish(draft.id)).thenAnswer((_) async {
          published = true;
          return scheduled;
        });
        await pump(tester, location: '/competitions/${draft.id}');
        await tester.pumpAndSettle();
        await tester.tap(
          find.widgetWithText(BafoButton, ar.issuerActionPublish),
        );
        await tester.pumpAndSettle();
        await tester.tap(
          find.widgetWithText(BafoButton, ar.issuerPublishConfirm),
        );
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 500));
        expect(find.text(ar.issuerPublishDone), findsOneWidget);
        expect(find.text(ar.issuerChecklistTitle), findsNothing);
        expect(find.text(ar.competitionsStatusScheduled), findsOneWidget);
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );

    testWidgets('min participants error offers Invite, which opens M40', (
      tester,
    ) async {
      await signIn('me_issuer');
      final draft = issuerCompetition('competition_issuer_draft');
      stubDetail(draft);
      when(() => competitions.publish(draft.id)).thenThrow(
        apiError(
          'min_participants_not_met',
          status: 422,
          details: {'required': 3, 'current': 2},
        ),
      );
      when(() => competitions.suggestions(draft.id, query: any(named: 'query')))
          .thenAnswer((_) async => const []);
      final router = await pump(tester, location: '/competitions/${draft.id}');
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(BafoButton, ar.issuerActionPublish));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(BafoButton, ar.issuerPublishConfirm),
      );
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerPublishMissingInvitations(1)), findsOneWidget);
      await tester.tap(
        find.widgetWithText(BafoButton, ar.issuerActionInvite).last,
      );
      await tester.pumpAndSettle();
      expect(router.state.uri.path, '/competitions/${draft.id}/invite');
    });

    testWidgets(
      'closed: award and close-without-award are web-only; nothing to buy',
      (tester) async {
        await signIn('me_issuer');
        final closed = issuerCompetition('competition_issuer_closed');
        stubDetail(closed);
        await pump(tester, location: '/competitions/${closed.id}');
        await tester.pumpAndSettle();

        expect(find.text(ar.issuerWebOnlyActions), findsOneWidget);
        await tester.tap(find.byTooltip(ar.issuerActionsMenu));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerActionAward), findsOneWidget);
        expect(find.text(ar.issuerActionCloseWithoutAward), findsOneWidget);
        expect(find.text(ar.issuerWebOnly), findsNWidgets(2));
        final award = tester.widget<ListTile>(
          find.ancestor(
            of: find.text(ar.issuerActionAward),
            matching: find.byType(ListTile),
          ),
        );
        expect(award.enabled, isFalse);
      },
    );

    testWidgets(
      'cancel: reason sheet with the red named action, then the server answer',
      (tester) async {
        await signIn('me_issuer');
        final scheduled = issuerCompetition('competition_issuer_scheduled');
        final cancelled = issuerCompetition('competition_issuer_cancelled');
        stubDetail(scheduled);
        when(() => lookups.closeReasons(CloseReasonKind.cancel)).thenAnswer(
          (_) async => fixtureLookups().closeReasonsOf(CloseReasonKind.cancel),
        );
        when(
          () => competitions.cancel(
            scheduled.id,
            closeReasonId: any(named: 'closeReasonId'),
            note: any(named: 'note'),
          ),
        ).thenAnswer((_) async => cancelled);
        await pump(tester, location: '/competitions/${scheduled.id}');
        await tester.pumpAndSettle();
        await tester.tap(find.byTooltip(ar.issuerActionsMenu));
        await tester.pumpAndSettle();
        await tester.tap(find.text(ar.issuerActionCancel));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCancelTitle), findsWidgets);
        await tester.tap(find.text('تغيّرت المتطلبات'));
        await tester.pump();
        await tester.tap(
          find.widgetWithText(BafoButton, ar.issuerActionCancel),
        );
        await tester.pumpAndSettle();
        verify(
          () => competitions.cancel(
            scheduled.id,
            closeReasonId: any(named: 'closeReasonId'),
            note: null,
          ),
        ).called(1);
        expect(find.text(ar.issuerCancelDone), findsOneWidget);
      },
    );

    testWidgets('renders in English (LTR)', (tester) async {
      await signIn('me_issuer');
      final live = issuerCompetition('competition_issuer_live_final_window');
      stubDetail(live);
      await pump(
        tester,
        location: '/competitions/${live.id}',
        locale: const Locale('en'),
      );
      // Live: the countdown and the final-window dot keep ticking.
      await tester.pump(const Duration(milliseconds: 200));
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text(en.issuerNavLive), findsOneWidget);
      expect(find.text(en.issuerDetailLeadingOffer), findsOneWidget);
      expect(find.text('SAR 238,000.00'), findsWidgets);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  });

  group('M46 live monitor', () {
    testWidgets('sealed before unlock: the lock, who submitted, no amounts', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      final sealed = issuerCompetition('competition_issuer_live_sealed');
      final json = copyOf(fixtureData('live_issuer_sealed'));
      when(() => competitions.show(sealed.id)).thenAnswer((_) async => sealed);
      when(() => live.issuerSnapshot(sealed.id))
          .thenAnswer((_) async => (IssuerLiveSnapshot.fromJson(json), json));
      await pump(tester, location: '/competitions/${sealed.id}/live');
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text(ar.issuerLiveSealedLock), findsOneWidget);
      expect(find.text(ar.issuerLiveSubmitted), findsOneWidget);
      expect(find.text(ar.issuerLiveNotSubmitted), findsOneWidget);
      expect(find.byType(MoneyText), findsNothing);
      // Leave before the fallback timer fires.
      await tester.pumpWidget(const SizedBox.shrink());
    });

    testWidgets('live: leader, reserve indicator, improvement and ranking', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      final competition = issuerCompetition(
        'competition_issuer_live_final_window',
      );
      final json = copyOf(fixtureData('live_issuer_final_window'));
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => live.issuerSnapshot(competition.id))
          .thenAnswer((_) async => (IssuerLiveSnapshot.fromJson(json), json));
      await pump(tester, location: '/competitions/${competition.id}/live');
      await tester.pump(const Duration(milliseconds: 100));

      expect(
        find.text(ar.issuerParticipantLabel(9, bidiIsolate('Supplier C'))),
        findsWidgets,
      );
      expect(find.text(ar.issuerLiveReserveNotMet('tender')), findsOneWidget);
      expect(find.byType(RankingTile), findsWidgets);
      expect(find.text(ar.issuerLiveExtendOnWeb), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  });

  group('M48 award (read-only)', () {
    testWidgets(
      'closed: the award is taken on the web; the result PDF is offered',
      (tester) async {
        await signIn('me_issuer');
        final closed = issuerCompetition('competition_issuer_closed');
        when(() => competitions.show(closed.id))
            .thenAnswer((_) async => closed);
        when(() => competitions.issuerAward(closed.id))
            .thenAnswer((_) async => null);
        when(() => live.standings(closed.id)).thenAnswer(
          (_) async =>
              fixtureList('offers_issuer_final_window')
                  .map(ParticipantStandingRow.fromJson)
                  .toList(),
        );
        await pump(tester, location: '/competitions/${closed.id}/award');
        await tester.pumpAndSettle();

        expect(find.text(ar.issuerAwardOnWeb), findsOneWidget);
        expect(find.text(ar.issuerReportArabic), findsOneWidget);
        expect(find.text(ar.issuerReportEnglish), findsOneWidget);
        expect(find.byType(StandingTile), findsWidgets);
      },
    );

    testWidgets('awarded: the award card with the winner', (tester) async {
      await signIn('me_issuer');
      final awarded = issuerCompetition('competition_issuer_awarded');
      when(() => competitions.show(awarded.id))
          .thenAnswer((_) async => awarded);
      when(() => competitions.issuerAward(awarded.id)).thenAnswer(
        (_) async => Award.fromJson(fixtureData('award_issuer_awarded')),
      );
      when(() => live.standings(awarded.id)).thenAnswer((_) async => const []);
      await pump(tester, location: '/competitions/${awarded.id}/award');
      await tester.pumpAndSettle();

      expect(find.text(ar.issuerAwardWinner), findsOneWidget);
      expect(
        find.text(ar.issuerParticipantLabel(96, bidiIsolate('Supplier C'))),
        findsOneWidget,
      );
      expect(find.text(ar.issuerAwardOnWeb), findsNothing);
    });
  });

  group('M40 invite', () {
    testWidgets('stages pasted e-mails and marks a server row error', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      final draft = issuerCompetition('competition_issuer_draft');
      when(() => competitions.show(draft.id)).thenAnswer((_) async => draft);
      when(() => competitions.suggestions(draft.id, query: any(named: 'query')))
          .thenAnswer((_) async => const []);
      when(() => invitations.invite(draft.id, any())).thenThrow(
        apiError(
          'validation_failed',
          status: 422,
          fieldErrors: {
            'invitations.0.email': ['x'],
          },
          details: {
            'item_codes': {'invitations.0.email': 'invitation_duplicate'},
          },
        ),
      );
      await pump(tester, location: '/competitions/${draft.id}/invite');
      await tester.pumpAndSettle();

      await tester.tap(find.text(ar.issuerInviteTabEmail));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'a@x.sa, bad');
      await tester.tap(find.text(ar.issuerInviteEmailsAdd));
      await tester.pumpAndSettle();
      expect(find.text('a@x.sa'), findsOneWidget);
      expect(
        find.text(ar.issuerInviteEmailsInvalid(ltrIsolate('bad'))),
        findsOneWidget,
      );

      expect(find.text(ar.issuerInviteEmailsAdded(1)), findsOneWidget);
      // Let the toast go before tapping the button under it.
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();

      await tester.tap(find.text(ar.issuerInviteAdd(1)));
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerInviteNothingSent), findsOneWidget);
      expect(find.text(ar.issuerInviteRowDuplicate), findsOneWidget);
    });
  });

  group('M41 participants', () {
    testWidgets('revoke asks for confirmation with the named red action', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      final competition = issuerCompetition('competition_issuer_live_initial');
      final rows = fixtureList('invitations_issuer_live_initial');
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => invitations.list(competition.id)).thenAnswer(
        (_) async => InvitationList(
          invitations: rows.map(Invitation.fromJson).toList(),
          counts: const {InvitationStatus.joined: 2, InvitationStatus.sent: 1},
        ),
      );
      await pump(
        tester,
        location: '/competitions/${competition.id}/participants',
      );
      await tester.pumpAndSettle();

      expect(find.text(ar.issuerParticipantsFilterAll(3)), findsOneWidget);
      await tester.tap(
        find.widgetWithText(BafoButton, ar.issuerInvitationRevoke),
      );
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerRevokeTitle), findsOneWidget);
      await tester.tap(find.widgetWithText(BafoButton, ar.commonActionsCancel));
      await tester.pumpAndSettle();
      verifyNever(() => invitations.remove(any(), any()));
    });
  });

  group('release scope (RELEASE_SCOPE.md §2.6, §4)', () {
    final tiered = tieredLookups();

    /// The basics through the cubit (the dropdowns are covered by M35).
    void fillBasics(WidgetTester tester) {
      final cubit = tester
          .element(find.byType(DraftFormFields))
          .read<CreateCompetitionCubit>();
      cubit.update(
        (f) => f.copyWith(
          title: 'توريد أجهزة',
          categoryId: () => tiered.categories.first.id,
          regionId: () => tiered.regions.first.id,
        ),
      );
    }

    testWidgets(
      'core: no format choice, tier cards, no reserve; a quick pick sets the close',
      (tester) async {
        tall(tester);
        await signIn('me_issuer');
        when(() => lookups.lookups()).thenAnswer((_) async => tiered);
        await pump(
          tester,
          location: '/my-competitions/new',
          flags: ScopeFlags.core,
        );
        await tester.pumpAndSettle();

        expect(find.byKey(const ValueKey('format-live')), findsNothing);
        expect(find.text(ar.issuerCreateFormatTitle), findsNothing);
        await tester.tap(find.byKey(const ValueKey('direction-tender')));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerPresetTierTitle), findsOneWidget);
        for (final tier in ['simple', 'standard', 'protected']) {
          expect(
            find.byKey(ValueKey('preset-tender_live_$tier')),
            findsOneWidget,
            reason: tier,
          );
        }
        expect(find.text(ar.issuerPresetTierRecommended), findsOneWidget);
        // The legacy template is an advanced option: not in core.
        expect(
          find.byKey(const ValueKey('preset-standard_live_tender')),
          findsNothing,
        );
        expect(find.text(ar.issuerPresetTierOther), findsNothing);
        expect(find.text(ar.issuerCreateAdvancedOnWeb), findsOneWidget);

        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCreateStepBasics), findsOneWidget);
        expect(find.text(ar.issuerFieldTitleHelper), findsOneWidget);
        fillBasics(tester);
        await tester.pump();
        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();

        expect(find.text(ar.issuerCreateStepSchedule), findsOneWidget);
        expect(find.text(ar.issuerFieldStartPrice('tender')), findsOneWidget);
        expect(find.text(ar.issuerFieldReservePrice('tender')), findsNothing);
        expect(find.text(ar.issuerScheduleQuickFromPublish), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey('quick-days3')));
        await tester.pumpAndSettle();
        expect(find.byKey(const Key('schedule.relativeClose')), findsOneWidget);
        expect(find.textContaining(ar.issuerScheduleInDays(3)), findsOneWidget);
        final cubit = tester
            .element(find.byType(DraftFormFields))
            .read<CreateCompetitionCubit>();
        final close =
            (cubit.state as CreateCompetitionEditing).form.scheduledCloseAt!;
        expect(
          close.difference(DateTime.now().toUtc()).inMinutes,
          inInclusiveRange(72 * 60 - 1, 72 * 60 + 5),
        );

        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCreateStepReview), findsOneWidget);
        expect(find.text('قياسية'), findsOneWidget);
        expect(find.text(ar.issuerFieldReservePrice('tender')), findsNothing);
        expect(find.byType(FormatChip), findsOneWidget);
      },
    );

    testWidgets(
      'an amount that cannot be read blocks Next instead of saving no price '
      '(FQ2, FQ8)',
      (tester) async {
        tall(tester);
        await signIn('me_issuer');
        when(() => lookups.lookups()).thenAnswer((_) async => tiered);
        await pump(
          tester,
          location: '/my-competitions/new',
          flags: ScopeFlags.core,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('direction-tender')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        fillBasics(tester);
        await tester.pump();
        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('quick-days3')));
        await tester.pumpAndSettle();

        // The tier presets take whole riyals: «1500.50» cannot be read.
        final price = find.descendant(
          of: find.byType(MoneyInputField),
          matching: find.byType(TextFormField),
        );
        await tester.enterText(price.first, '1500.50');
        await tester.pump();
        final cubit = tester
            .element(find.byType(DraftFormFields))
            .read<CreateCompetitionCubit>();
        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCreateStepReview), findsNothing);
        expect(find.text(ar.issuerCreateStepSchedule), findsOneWidget);
        expect(
          (cubit.state as CreateCompetitionEditing).form.startPriceMinor,
          isNull,
        );

        // Fixed: Next goes on with the typed amount.
        await tester.enterText(price.first, '1500');
        await tester.pump();
        await tester.tap(find.byKey(const Key('create.next')));
        await tester.pumpAndSettle();
        expect(find.text(ar.issuerCreateStepReview), findsOneWidget);
        expect(
          (cubit.state as CreateCompetitionEditing).form.startPriceMinor,
          150000,
        );
      },
    );

    testWidgets('full: the format cards, the reserve and the other templates', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      when(() => lookups.lookups()).thenAnswer((_) async => tiered);
      await pump(
        tester,
        location: '/my-competitions/new',
        flags: ScopeFlags.full,
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('format-sealed')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('direction-tender')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('format-live')));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('preset-tender_live_standard')),
        findsOneWidget,
      );
      expect(find.text(ar.issuerPresetTierOther), findsOneWidget);
      expect(
        find.byKey(const ValueKey('preset-standard_live_tender')),
        findsOneWidget,
      );

      await tester.tap(find.byKey(const Key('create.next')));
      await tester.pumpAndSettle();
      fillBasics(tester);
      await tester.pump();
      await tester.tap(find.byKey(const Key('create.next')));
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerFieldReservePrice('tender')), findsOneWidget);
    });

    testWidgets('Next with problems lists them at the top as links (FQ8)', (
      tester,
    ) async {
      await signIn('me_issuer');
      await pump(
        tester,
        location: '/my-competitions/new',
        flags: ScopeFlags.core,
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('create.errors')), findsNothing);
      await tester.tap(find.byKey(const Key('create.next')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('create.errors')), findsOneWidget);
      expect(find.text(ar.commonErrorSummaryTitle(1)), findsOneWidget);
      expect(find.textContaining(ar.issuerCreateDirectionTitle), findsWidgets);
    });

    testWidgets('invite: the Vendors segment needs the vendor_directory flag', (
      tester,
    ) async {
      tall(tester);
      await signIn('me_issuer');
      final draft = issuerCompetition('competition_issuer_draft');
      when(() => competitions.show(draft.id)).thenAnswer((_) async => draft);
      when(() => competitions.suggestions(draft.id, query: any(named: 'query')))
          .thenAnswer((_) async => const []);
      await pump(
        tester,
        location: '/competitions/${draft.id}/invite',
        flags: ScopeFlags.core,
      );
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerInviteTabEmail), findsOneWidget);
      expect(find.text(ar.issuerInviteTabVendors), findsNothing);

      await pump(
        tester,
        location: '/competitions/${draft.id}/invite',
        flags: ScopeFlags.full,
      );
      await tester.pumpAndSettle();
      expect(find.text(ar.issuerInviteTabVendors), findsOneWidget);
    });
  });

  test('the permissions of the fixtures used here', () {
    expect(fixtureMe('me_supplier_b').canCreateCompetition, isFalse);
    expect(
      fixtureMe('me_supplier_b').can(Permissions.competitionsCreate),
      isTrue,
    );
  });
}
