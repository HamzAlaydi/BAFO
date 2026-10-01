import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/qa/presentation/qa_bloc.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockCompetitions extends Mock implements CompetitionsRepository {}

class _MockComments extends Mock implements CommentsRepository {}

void main() {
  const org = 'org-a';
  final competition = Competition.fromJson(
    fixtureData('competition_supplier_a_live_initial'),
  );
  final issuerView = Competition.fromJson(
    fixtureData('competition_issuer_live_initial'),
  );
  final threads = fixtureList('comments_supplier_a_live_initial')
      .map(Comment.fromJson)
      .toList();
  final channel = 'competition.${competition.id}.participant.$org';
  final mine = threads.firstWhere((t) => t.author.kind == CommentAuthorKind.me);
  final other = threads.firstWhere(
    (t) => t.author.kind == CommentAuthorKind.participant,
  );

  Map<String, dynamic> commentJson(
    String id, {
    String? parentId,
    Map<String, dynamic> author = const {'kind': 'participant', 'alias_no': 4},
  }) => {
    'id': id,
    'parent_id': parentId,
    'body': 'نص $id',
    'author': author,
    'created_at': '2026-09-29T18:00:00.000Z',
    'replies': <Object>[],
  };

  late _MockCompetitions competitions;
  late _MockComments comments;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;
  var away = false;

  setUp(() {
    competitions = _MockCompetitions();
    comments = _MockComments();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
    away = false;
    when(() => competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(() => comments.list(competition.id)).thenAnswer(
      (_) async => Paged(
        items: threads,
        meta: const PageMeta(currentPage: 1, perPage: 20, hasMore: true),
      ),
    );
  });

  tearDown(() async {
    await hub.dispose();
    await realtime.dispose();
  });

  QaBloc build() => QaBloc(
    competitions: competitions,
    comments: comments,
    hub: hub,
    competitionId: competition.id,
    organizationId: org,
    isReaderAway: () => away,
  );

  blocTest<QaBloc, QaState>(
    'loads the competition and the first page, and follows the viewer channel',
    build: build,
    act: (bloc) => bloc.add(const QaStarted()),
    expect: () => [
      const QaLoading(),
      QaLoaded(
        competition: competition,
        threads: threads,
        page: 1,
        hasMore: true,
      ),
    ],
    verify: (bloc) {
      expect(realtime.subscribed, [channel]);
      final state = bloc.state as QaLoaded;
      expect(state.canComment, competition.permissions.canComment);
      // A participant replies only under its own organisation's questions.
      expect(state.canReplyTo(mine), state.canComment);
      expect(state.canReplyTo(other), isFalse);
    },
  );

  test('the issuer may reply to every thread', () {
    final state = QaLoaded(
      competition: issuerView,
      threads: threads,
      page: 1,
      hasMore: false,
    );
    expect(state.isIssuer, isTrue);
    expect(threads.every(state.canReplyTo), issuerView.permissions.canComment);
  });

  blocTest<QaBloc, QaState>(
    'comment.created: de-duplicated by id; a reply goes under its thread; unseen counts when away',
    build: build,
    act: (bloc) async {
      bloc.add(const QaStarted());
      await pumpEventQueue();
      away = true;
      realtime
        ..emit(channel, RealtimeEvents.commentCreated, commentJson('new-1'))
        ..emit(channel, RealtimeEvents.commentCreated, commentJson('new-1'))
        ..emit(
          channel,
          RealtimeEvents.commentCreated,
          commentJson(
            'reply-1',
            parentId: mine.id,
            author: const {'kind': 'issuer', 'organization_name': 'Issuer Co'},
          ),
        )
        // A reply whose thread is on a page not loaded yet is left for it.
        ..emit(
          channel,
          RealtimeEvents.commentCreated,
          commentJson('reply-2', parentId: 'unknown'),
        );
      await pumpEventQueue();
      bloc.add(const QaUnseenCleared());
      await pumpEventQueue();
    },
    verify: (bloc) {
      final state = bloc.state as QaLoaded;
      expect(state.threads.first.id, 'new-1');
      expect(state.threads.where((t) => t.id == 'new-1'), hasLength(1));
      final thread = state.threads.firstWhere((t) => t.id == mine.id);
      expect(thread.replies.last.id, 'reply-1');
      expect(
        state.threads.any((t) => t.replies.any((r) => r.id == 'reply-2')),
        isFalse,
      );
      expect(state.highlighted, containsAll(['new-1', 'reply-1']));
      expect(state.unseen, 0);
    },
  );

  blocTest<QaBloc, QaState>(
    'a posted comment is inserted once, even if the channel repeats it',
    build: build,
    act: (bloc) async {
      bloc.add(const QaStarted());
      await pumpEventQueue();
      final posted = Comment.fromJson(
        commentJson('mine-2', author: const {'kind': 'me'}),
      );
      bloc.add(QaCommentPosted(posted));
      realtime.emit(
        channel,
        RealtimeEvents.commentCreated,
        commentJson('mine-2', author: const {'kind': 'me'}),
      );
      await pumpEventQueue();
    },
    verify: (bloc) {
      final state = bloc.state as QaLoaded;
      expect(state.threads.where((t) => t.id == 'mine-2'), hasLength(1));
      expect(state.unseen, 0);
    },
  );

  blocTest<QaBloc, QaState>(
    'the next page appends and skips overlapping threads',
    setUp: () => when(() => comments.list(competition.id, page: 2)).thenAnswer(
      (_) async => Paged(
        items: [threads.last, Comment.fromJson(commentJson('old-1'))],
        meta: const PageMeta(currentPage: 2, perPage: 20, hasMore: false),
      ),
    ),
    build: build,
    act: (bloc) async {
      bloc.add(const QaStarted());
      await pumpEventQueue();
      bloc.add(const QaNextPageRequested());
      await pumpEventQueue();
    },
    verify: (bloc) {
      final state = bloc.state as QaLoaded;
      expect(state.threads.map((t) => t.id), [
        ...threads.map((t) => t.id),
        'old-1',
      ]);
      expect(state.hasMore, isFalse);
    },
  );

  blocTest<QaBloc, QaState>(
    'a 404 never reveals the competition',
    setUp: () =>
        when(() => competitions.show(competition.id))
            .thenThrow(const ApiException(code: 'not_found', statusCode: 404)),
    build: build,
    act: (bloc) => bloc.add(const QaStarted()),
    expect: () => const [QaLoading(), QaNotFound()],
  );

  group('QaComposerCubit', () {
    blocTest<QaComposerCubit, QaComposerState>(
      'posts a trimmed body under the thread',
      setUp: () => when(
        () => comments.post(competition.id, body: 'شكرًا', parentId: mine.id),
      ).thenAnswer((_) async => threads.first),
      build: () => QaComposerCubit(
        comments: comments,
        competitionId: competition.id,
        parentId: mine.id,
      ),
      act: (cubit) => cubit.post('  شكرًا  '),
      expect: () => [
        const QaComposerPosting(),
        QaComposerPosted(threads.first),
      ],
    );

    blocTest<QaComposerCubit, QaComposerState>(
      'comments_closed comes back as an error; empty or too long bodies are not sent',
      setUp: () => when(
        () => comments.post(competition.id, body: any(named: 'body')),
      ).thenThrow(const ApiException(code: 'comments_closed', statusCode: 409)),
      build: () =>
          QaComposerCubit(comments: comments, competitionId: competition.id),
      act: (cubit) async {
        await cubit.post('   ');
        await cubit.post('x' * (QaComposerCubit.maxLength + 1));
        await cubit.post('سؤال');
      },
      expect: () => const [
        QaComposerPosting(),
        QaComposerIdle(
          error: ApiException(code: 'comments_closed', statusCode: 409),
        ),
      ],
      verify: (_) =>
          verify(() => comments.post(competition.id, body: any(named: 'body')))
              .called(1),
    );
  });
}
