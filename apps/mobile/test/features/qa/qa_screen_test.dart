import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/qa/presentation/qa_screen.dart';
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

  setUp(() async {
    h = ParticipantHarness();
    await h.signIn();
  });

  tearDown(() => h.dispose());

  Future<void> open(
    WidgetTester tester,
    String competitionFixture,
    String commentsFixture,
  ) async {
    final competition = Competition.fromJson(fixtureData(competitionFixture));
    when(() => h.competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(() => h.comments.list(competition.id)).thenAnswer(
      (_) async => Paged(
        items: fixtureList(commentsFixture).map(Comment.fromJson).toList(),
        meta: const PageMeta(currentPage: 1, perPage: 20, hasMore: false),
      ),
    );
    await pumpRouter(
      tester,
      initialLocation: '/competitions/${competition.id}/qa',
      providers: h.providers(),
      routes: [
        GoRoute(
          path: '/competitions/:id/qa',
          builder: (_, state) =>
              QaScreen(competitionId: state.pathParameters['id']!),
        ),
      ],
    );
    await tester.pumpAndSettle();
  }

  testWidgets(
    'M31 participant: authors by alias, «أنتم», replies only on own threads',
    (tester) async {
      await open(
        tester,
        'competition_supplier_a_live_initial',
        'comments_supplier_a_live_initial',
      );
      expect(find.text(ar.qaTitle), findsOneWidget);
      expect(find.text(ar.qaAuthorMe), findsOneWidget);
      expect(find.text(ar.qaAuthorParticipant(2)), findsOneWidget);
      // Other participants' organisations are never named.
      expect(find.textContaining('Supplier'), findsNothing);
      expect(find.text('Issuer Co'), findsOneWidget);
      expect(find.text(ar.qaReplyAction), findsOneWidget);
      expect(find.text(ar.qaAskAction), findsOneWidget);
    },
  );

  testWidgets('M32 ask: posts the question and shows it at the top', (
    tester,
  ) async {
    await open(
      tester,
      'competition_supplier_a_live_initial',
      'comments_supplier_a_live_initial',
    );
    final competitionId =
        fixtureData('competition_supplier_a_live_initial')['id'] as String;
    when(
      () => h.comments.post(competitionId, body: 'هل يمكن التوريد على دفعات؟'),
    ).thenAnswer(
      (_) async => Comment.fromJson({
        'id': 'new-q',
        'parent_id': null,
        'body': 'هل يمكن التوريد على دفعات؟',
        'author': {'kind': 'me'},
        'created_at': '2026-09-29T18:29:00.000Z',
        'replies': <Object>[],
      }),
    );
    await tester.tap(find.byKey(const Key('qa-compose')));
    await tester.pumpAndSettle();
    // Required: an empty question is not sent.
    await tester.tap(find.byKey(const Key('qa-send')));
    await tester.pumpAndSettle();
    expect(find.text(ar.validationRequired), findsOneWidget);

    await tester.enterText(
      find.descendant(
        of: find.byKey(const Key('qa-body')),
        matching: find.byType(EditableText),
      ),
      'هل يمكن التوريد على دفعات؟',
    );
    await tester.tap(find.byKey(const Key('qa-send')));
    await tester.pumpAndSettle();
    verify(
      () => h.comments.post(competitionId, body: 'هل يمكن التوريد على دفعات؟'),
    ).called(1);
    expect(find.text(ar.qaPosted), findsOneWidget);
    expect(find.text('هل يمكن التوريد على دفعات؟'), findsOneWidget);
    expect(find.text(ar.qaAuthorMe), findsNWidgets(2));
  });

  testWidgets('M31 live: comment.created appears once', (tester) async {
    h.realtime.setConnection(RealtimeConnectionState.connected);
    await open(
      tester,
      'competition_supplier_a_live_initial',
      'comments_supplier_a_live_initial',
    );
    final id =
        fixtureData('competition_supplier_a_live_initial')['id'] as String;
    final channel = 'competition.$id.participant.${h.organizationId}';
    final payload = {
      'id': 'live-1',
      'parent_id': null,
      'body': 'إعلان: تمديد موعد الاستفسارات',
      'author': {'kind': 'issuer', 'organization_name': 'Issuer Co'},
      'created_at': '2026-09-29T18:29:30.000Z',
      'replies': <Object>[],
    };
    h.realtime
      ..emit(channel, RealtimeEvents.commentCreated, payload)
      ..emit(channel, RealtimeEvents.commentCreated, payload);
    await tester.pumpAndSettle();
    expect(find.text('إعلان: تمديد موعد الاستفسارات'), findsOneWidget);
    expect(find.text(ar.qaAnnouncement), findsOneWidget);
  });

  testWidgets('M31 closed: read-only notice and no composer', (tester) async {
    await open(
      tester,
      'competition_supplier_a_awarded',
      'comments_supplier_a_live_initial',
    );
    expect(find.text(ar.qaClosedTitle), findsOneWidget);
    expect(find.byKey(const Key('qa-compose')), findsNothing);
    expect(find.text(ar.qaReplyAction), findsNothing);
  });
}
