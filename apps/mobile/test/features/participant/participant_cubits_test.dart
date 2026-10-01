import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/participant/presentation/invitation_action_cubit.dart';
import 'package:bafo/features/participant/presentation/participant_competition_cubit.dart';
import 'package:bafo/features/participant/presentation/participating_list_bloc.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockCompetitions extends Mock implements CompetitionsRepository {}

class _MockAttachments extends Mock implements AttachmentsRepository {}

class _MockInvitations extends Mock implements InvitationsRepository {}

Paged<CompetitionListItem> _page(
  List<CompetitionListItem> items, {
  int page = 1,
  bool hasMore = false,
}) => Paged(
  items: items,
  meta: PageMeta(currentPage: page, perPage: 20, hasMore: hasMore),
);

void main() {
  setUpAll(() {
    registerFallbackValue(CompetitionListRole.participant);
    registerFallbackValue(CompetitionStatusGroup.all);
  });

  final rowsA = fixtureList('competitions_participant_a')
      .map(CompetitionListItem.fromJson)
      .toList();
  final rowsD = fixtureList('competitions_participant_d')
      .map(CompetitionListItem.fromJson)
      .toList();

  group('ParticipatingListBloc', () {
    late _MockCompetitions competitions;

    setUp(() => competitions = _MockCompetitions());

    void stubList(
      Future<Paged<CompetitionListItem>> Function(Invocation call) answer,
    ) => when(
      () => competitions.list(
        role: any(named: 'role'),
        group: any(named: 'group'),
        direction: any(named: 'direction'),
        query: any(named: 'query'),
        page: any(named: 'page'),
      ),
    ).thenAnswer(answer);

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'loads the active page with invitations needing action first',
      setUp: () => stubList((_) async => _page(rowsD)),
      build: () => ParticipatingListBloc(competitions: competitions),
      act: (bloc) => bloc.add(const ParticipatingListStarted()),
      expect: () => [
        const ParticipatingListLoading(ParticipatingFilter()),
        isA<ParticipatingListLoaded>()
            .having((s) => s.items.length, 'rows', rowsD.length)
            .having((s) => s.needsActionCount, 'needs action', 2)
            .having(
              (s) => s.items.take(2).every((item) => item.needsAction),
              'first rows need action',
              isTrue,
            ),
      ],
      verify: (_) => verify(
        () => competitions.list(
          role: CompetitionListRole.participant,
          group: CompetitionStatusGroup.active,
          page: 1,
        ),
      ).called(1),
    );

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'the search is debounced: only the last query is sent',
      setUp: () => stubList((_) async => _page(rowsA.take(1).toList())),
      build: () => ParticipatingListBloc(
        competitions: competitions,
        queryDebounce: const Duration(milliseconds: 20),
      ),
      seed: () => ParticipatingListLoaded(
        filter: const ParticipatingFilter(),
        items: rowsA,
        page: 1,
        hasMore: false,
      ),
      act: (bloc) async {
        bloc
          ..add(const ParticipatingListQueryChanged('ت'))
          ..add(const ParticipatingListQueryChanged('توريد'));
        await Future<void>.delayed(const Duration(milliseconds: 60));
      },
      expect: () => [
        const ParticipatingListLoading(ParticipatingFilter(query: 'توريد')),
        isA<ParticipatingListLoaded>().having(
          (s) => s.filter.query,
          'query',
          'توريد',
        ),
      ],
      verify: (_) => verify(
        () => competitions.list(
          role: CompetitionListRole.participant,
          group: CompetitionStatusGroup.active,
          query: 'توريد',
          page: 1,
        ),
      ).called(1),
    );

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'a slow answer for an old filter never overwrites the new one',
      setUp: () {
        stubList((call) async {
          final group = call.namedArguments[#group] as CompetitionStatusGroup;
          if (group == CompetitionStatusGroup.active) {
            await Future<void>.delayed(const Duration(milliseconds: 40));
            return _page(rowsA);
          }
          return _page(rowsD.take(1).toList());
        });
      },
      build: () => ParticipatingListBloc(competitions: competitions),
      act: (bloc) async {
        bloc
          ..add(const ParticipatingListStarted())
          ..add(
            const ParticipatingListGroupChanged(CompetitionStatusGroup.ended),
          );
        await Future<void>.delayed(const Duration(milliseconds: 80));
      },
      expect: () => [
        const ParticipatingListLoading(ParticipatingFilter()),
        const ParticipatingListLoading(
          ParticipatingFilter(group: CompetitionStatusGroup.ended),
        ),
        isA<ParticipatingListLoaded>()
            .having((s) => s.items.length, 'rows', 1)
            .having(
              (s) => s.filter.group,
              'group',
              CompetitionStatusGroup.ended,
            ),
      ],
    );

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'the next page appends, skipping rows already shown',
      setUp: () => stubList((_) async => _page([rowsA[1], rowsA[2]], page: 2)),
      build: () => ParticipatingListBloc(competitions: competitions),
      seed: () => ParticipatingListLoaded(
        filter: const ParticipatingFilter(),
        items: [rowsA[0], rowsA[1]],
        page: 1,
        hasMore: true,
      ),
      act: (bloc) => bloc.add(const ParticipatingListNextPageRequested()),
      expect: () => [
        isA<ParticipatingListLoaded>().having(
          (s) => s.loadingMore,
          'loading',
          true,
        ),
        isA<ParticipatingListLoaded>()
            .having((s) => s.items, 'items', [rowsA[0], rowsA[1], rowsA[2]])
            .having((s) => s.hasMore, 'has more', false)
            .having((s) => s.page, 'page', 2),
      ],
    );

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'a failed refresh keeps the rows',
      setUp: () => stubList(
        (_) async => throw const ApiException(code: ApiErrorCode.network),
      ),
      build: () => ParticipatingListBloc(competitions: competitions),
      seed: () => ParticipatingListLoaded(
        filter: const ParticipatingFilter(),
        items: rowsA,
        page: 1,
        hasMore: false,
      ),
      act: (bloc) => bloc.add(const ParticipatingListRefreshed()),
      expect: () => [
        isA<ParticipatingListLoaded>().having(
          (s) => s.refreshing,
          'refreshing',
          true,
        ),
        isA<ParticipatingListLoaded>()
            .having((s) => s.refreshing, 'refreshing', false)
            .having((s) => s.items, 'items', rowsA),
      ],
    );

    blocTest<ParticipatingListBloc, ParticipatingListState>(
      'a first-load error is a failure; the direction filter reloads',
      setUp: () {
        var calls = 0;
        stubList((_) async {
          if (calls++ == 0) {
            throw const ApiException(code: 'server_error', statusCode: 500);
          }
          return _page(rowsA.take(2).toList());
        });
      },
      build: () => ParticipatingListBloc(competitions: competitions),
      act: (bloc) async {
        bloc.add(const ParticipatingListStarted());
        await pumpEventQueue();
        bloc.add(const ParticipatingListDirectionChanged(Direction.auction));
      },
      expect: () => [
        const ParticipatingListLoading(ParticipatingFilter()),
        isA<ParticipatingListFailure>(),
        const ParticipatingListLoading(
          ParticipatingFilter(direction: Direction.auction),
        ),
        isA<ParticipatingListLoaded>().having(
          (s) => s.filter.direction,
          'direction',
          Direction.auction,
        ),
      ],
    );
  });

  group('ParticipantCompetitionCubit', () {
    late _MockCompetitions competitions;
    late _MockAttachments attachments;
    late FakeRealtimeClient realtime;
    late CompetitionChannelHub hub;

    final invitee = Competition.fromJson(
      fixtureData('competition_buyer_d_live_initial_invitee'),
    );
    final participant = Competition.fromJson(
      fixtureData('competition_supplier_a_live_auction'),
    );
    final documents = fixtureList('attachments_supplier_a_live_initial')
        .map(Attachment.fromJson)
        .toList();
    const org = 'org-a';

    setUp(() {
      competitions = _MockCompetitions();
      attachments = _MockAttachments();
      realtime = FakeRealtimeClient();
      hub = CompetitionChannelHub(realtime);
    });

    tearDown(() async {
      await hub.dispose();
      await realtime.dispose();
    });

    ParticipantCompetitionCubit build(String id) => ParticipantCompetitionCubit(
      competitions: competitions,
      attachments: attachments,
      hub: hub,
      competitionId: id,
      organizationId: org,
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'an invitee gets the teaser and its invitation documents, no channel',
      setUp: () =>
          when(() => competitions.show(invitee.id))
              .thenAnswer((_) async => invitee),
      build: () => build(invitee.id),
      act: (cubit) => cubit.load(),
      expect: () => [
        const ParticipantCompetitionLoading(),
        ParticipantCompetitionLoaded(
          competition: invitee,
          attachments: invitee.invitationDocuments,
        ),
      ],
      verify: (_) {
        expect(realtime.subscribed, isEmpty);
        verifyNever(() => attachments.list(any()));
      },
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'a join re-renders as participant and subscribes to its channel',
      setUp: () {
        when(() => competitions.show(invitee.id))
            .thenAnswer((_) async => invitee);
        when(() => attachments.list(invitee.id))
            .thenAnswer((_) async => documents);
      },
      build: () => build(invitee.id),
      act: (cubit) async {
        await cubit.load();
        await cubit.applyCompetition(
          Competition.fromJson({
            ...fixtureData('competition_supplier_a_live_auction'),
            'id': invitee.id,
          }),
        );
      },
      skip: 2,
      expect: () => [
        isA<ParticipantCompetitionLoaded>().having(
          (s) => s.refreshing,
          'busy',
          true,
        ),
        isA<ParticipantCompetitionLoaded>()
            .having(
              (s) => s.competition.isParticipantView,
              'participant',
              isTrue,
            )
            .having((s) => s.attachments, 'documents', documents)
            .having((s) => s.live?.v, 'live', 3),
      ],
      verify: (_) => expect(realtime.subscribed, [
        'competition.${invitee.id}.participant.$org',
      ]),
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'a participant follows live.updated through the v gate; a status change refetches',
      setUp: () {
        when(() => competitions.show(participant.id))
            .thenAnswer((_) async => participant);
        when(() => attachments.list(participant.id))
            .thenAnswer((_) async => documents);
      },
      build: () => build(participant.id),
      act: (cubit) async {
        await cubit.load();
        final channel = 'competition.${participant.id}.participant.$org';
        final live = fixtureData('live_supplier_a_auction');
        realtime
          ..emit(channel, RealtimeEvents.liveUpdated, {...live, 'v': 2})
          ..emit(channel, RealtimeEvents.liveUpdated, {
            ...live,
            'v': 5,
            'is_leading': false,
          });
        await pumpEventQueue();
        realtime.emit(channel, RealtimeEvents.liveUpdated, {
          ...live,
          'v': 6,
          'status': 'closed',
          'phase': null,
          'last_change': {'kind': 'status', 'reason': null},
        });
        await pumpEventQueue();
      },
      verify: (cubit) {
        final state = cubit.state as ParticipantCompetitionLoaded;
        expect(state.live?.v, 6);
        verify(() => competitions.show(participant.id)).called(2);
      },
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'an awarded participant also loads its award view (the winner message)',
      setUp: () {
        final awarded = Competition.fromJson(
          fixtureData('competition_supplier_a_awarded'),
        );
        when(() => competitions.show(awarded.id))
            .thenAnswer((_) async => awarded);
        when(() => attachments.list(awarded.id)).thenAnswer((_) async => []);
        when(() => competitions.participantAward(awarded.id)).thenAnswer(
          (_) async =>
              const ParticipantAwardView(outcome: AwardOutcome.notSelected),
        );
      },
      build: () => build(
        Competition.fromJson(fixtureData('competition_supplier_a_awarded')).id,
      ),
      act: (cubit) => cubit.load(),
      skip: 1,
      expect: () => [
        isA<ParticipantCompetitionLoaded>().having(
          (s) => s.award?.outcome,
          'award',
          AwardOutcome.notSelected,
        ),
      ],
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'an issuer projection hands over without a channel or documents',
      setUp: () {
        final issuer = Competition.fromJson(
          fixtureData('competition_issuer_live_initial'),
        );
        when(() => competitions.show(issuer.id))
            .thenAnswer((_) async => issuer);
      },
      build: () => build(
        Competition.fromJson(fixtureData('competition_issuer_live_initial')).id,
      ),
      act: (cubit) => cubit.load(),
      skip: 1,
      expect: () => [
        isA<ParticipantCompetitionLoaded>().having(
          (s) => s.competition.isIssuerView,
          'issuer',
          isTrue,
        ),
      ],
      verify: (_) {
        expect(realtime.subscribed, isEmpty);
        verifyNever(() => attachments.list(any()));
      },
    );

    blocTest<ParticipantCompetitionCubit, ParticipantCompetitionState>(
      'a 404 never reveals the competition',
      setUp: () => when(() => competitions.show('x'))
          .thenThrow(const ApiException(code: 'not_found', statusCode: 404)),
      build: () => build('x'),
      act: (cubit) => cubit.load(),
      expect: () => const [
        ParticipantCompetitionLoading(),
        ParticipantCompetitionNotFound(),
      ],
    );
  });

  group('InvitationActionCubit', () {
    late _MockInvitations invitations;

    setUp(() => invitations = _MockInvitations());

    final joined = Competition.fromJson(
      fixtureData('competition_supplier_a_live_auction'),
    );

    blocTest<InvitationActionCubit, InvitationActionState>(
      'join returns the participant projection',
      setUp: () =>
          when(() => invitations.join('inv')).thenAnswer((_) async => joined),
      build: () => InvitationActionCubit(invitations: invitations),
      act: (cubit) => cubit.join('inv'),
      expect: () => [
        const InvitationActionInProgress(InvitationAction.join),
        InvitationJoined(joined),
      ],
    );

    blocTest<InvitationActionCubit, InvitationActionState>(
      'plan_required is explained (information only) and reloads the page',
      setUp: () => when(() => invitations.join('inv')).thenThrow(
        const ApiException(
          code: 'plan_required',
          statusCode: 403,
          details: {
            'access': {'state': 'plan_required', 'coverage': 'none'},
          },
        ),
      ),
      build: () => InvitationActionCubit(invitations: invitations),
      act: (cubit) => cubit.join('inv'),
      skip: 1,
      expect: () => [
        isA<InvitationActionFailed>()
            .having((s) => s.planRequired, 'plan required', isTrue)
            .having((s) => s.meansStale, 'reload', isTrue),
      ],
    );

    blocTest<InvitationActionCubit, InvitationActionState>(
      'decline sends a trimmed reason, or none',
      setUp: () =>
          when(() => invitations.decline('inv', reason: any(named: 'reason')))
              .thenAnswer(
                (_) async => const InviteeInvitation(
                  id: 'inv',
                  status: InvitationStatus.declined,
                ),
              ),
      build: () => InvitationActionCubit(invitations: invitations),
      act: (cubit) async {
        await cubit.decline('inv', reason: '  لا نغطي هذه المنطقة  ');
        cubit.reset();
        await cubit.decline('inv', reason: '   ');
      },
      verify: (_) {
        verify(() => invitations.decline('inv', reason: 'لا نغطي هذه المنطقة'))
            .called(1);
        verify(() => invitations.decline('inv')).called(1);
      },
    );

    blocTest<InvitationActionCubit, InvitationActionState>(
      'a second tap while busy is ignored',
      setUp: () {
        final gate = Completer<Competition>();
        when(() => invitations.join('inv')).thenAnswer((_) => gate.future);
        Timer(const Duration(milliseconds: 10), () => gate.complete(joined));
      },
      build: () => InvitationActionCubit(invitations: invitations),
      act: (cubit) async {
        unawaited(cubit.join('inv'));
        await cubit.join('inv');
        await Future<void>.delayed(const Duration(milliseconds: 30));
      },
      expect: () => [
        const InvitationActionInProgress(InvitationAction.join),
        InvitationJoined(joined),
      ],
      verify: (_) => verify(() => invitations.join('inv')).called(1),
    );
  });
}
