import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/realtime/reverb_realtime_client.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/live/data/clock_sync_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/presentation/live_room_bloc.dart';
import 'package:bafo/features/live/presentation/my_offers_cubit.dart';
import 'package:bafo/features/live/presentation/offer_submit_cubit.dart';
import 'package:bafo/features/participant/presentation/invitation_action_cubit.dart';
import 'package:bafo/features/participant/presentation/participant_competition_cubit.dart';
import 'package:bafo/features/participant/presentation/participating_list_bloc.dart';
import 'package:bafo/features/qa/presentation/qa_bloc.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

void _log(String message) {
  // ignore: avoid_print
  print(message);
}

/// The participant flows (M16–M32) against a running API and Reverb, with
/// the app's own blocs and cubits. Not part of the default run.
///
/// ```sh
/// BAFO_LIVE_API=http://localhost:8000/api/app/v1 \
/// BAFO_DEMO_EMAIL=<a participant demo account> BAFO_DEMO_PASSWORD=… \
/// flutter test test/features/live/participant_flow_live_test.dart
/// ```
///
/// Add `BAFO_LIVE_MUTATE=1` (local dev database only) to also join an
/// invitation, place an offer on a live auction and post a question.
/// Credentials come from the environment (docs/build/DEMO.md), never the repo.
void main() {
  final base = Platform.environment['BAFO_LIVE_API'];
  final email = Platform.environment['BAFO_DEMO_EMAIL'];
  final password = Platform.environment['BAFO_DEMO_PASSWORD'];
  final mutate = Platform.environment['BAFO_LIVE_MUTATE'] == '1';
  final skip = base == null || email == null || password == null
      ? 'Set BAFO_LIVE_API, BAFO_DEMO_EMAIL and BAFO_DEMO_PASSWORD'
      : null;

  test(
    'participant flows against the running API',
    () async {
      final apiBase = Uri.parse(base!);
      final env = Env(
        apiBaseUrl: apiBase,
        broadcastingAuthUrl: apiBase.replace(path: '/broadcasting/auth'),
        realtime: const RealtimeConfig(
          appKey: '',
          host: 'localhost',
          port: 8085,
          useTls: false,
        ),
      );
      final tokens = InMemoryTokenStore();
      final clock = ServerClock();
      final api = ApiClient.create(
        env: env,
        tokens: tokens,
        clock: clock,
        languageCode: () => 'ar',
        onUnauthorized: () => fail('unexpected 401'),
        client: const ClientInfo(platform: 'android', appVersion: '1.0.0'),
      );
      final auth = ApiAuthRepository(
        api,
        deviceName: 'participant-flow · android',
      );
      final payload = await auth.login(email: email!, password: password!);
      await tokens.write(payload.token);
      final me = payload.me;
      final org = me.organization.id;
      _log('Signed in as ${me.organization.name}');

      final competitions = ApiCompetitionsRepository(api);
      final attachments = ApiAttachmentsRepository(api);
      final invitations = ApiInvitationsRepository(api);
      final live = ApiLiveRepository(api);
      final comments = ApiCommentsRepository(api);
      final config = AppConfig.fromJson((await api.get('app-config')).dataMap);
      final realtime = ReverbRealtimeClient(
        config: env.resolveRealtime(config.realtime),
        authEndpoint: env.broadcastingAuthUrl,
        api: api,
      );
      final hub = CompetitionChannelHub(realtime);
      final connected = realtime.connectionState.firstWhere(
        (state) => state == RealtimeConnectionState.connected,
      );
      // The app follows the user channel while signed in; it opens the socket.
      final user = realtime.subscribePrivate(RealtimeChannels.user(me.user.id));

      // ── M16 ────────────────────────────────────────────────────────────
      final list = ParticipatingListBloc(competitions: competitions)
        ..add(const ParticipatingListGroupChanged(CompetitionStatusGroup.all));
      final listed = await list.stream.firstWhere(
        (s) => s is ParticipatingListLoaded,
      ) as ParticipatingListLoaded;
      final firstPlain = listed.items.indexWhere((item) => !item.needsAction);
      expect(
        listed.items
            .skip(firstPlain < 0 ? listed.items.length : firstPlain)
            .where((item) => item.needsAction),
        isEmpty,
        reason: 'invitations needing action come first',
      );
      _log(
        'M16: ${listed.items.length} rows, ${listed.needsActionCount} need action',
      );
      await list.close();

      // ── M17 / M18 ──────────────────────────────────────────────────────
      final invite = listed.items
          .where((item) => item.access?.state == AccessState.joinRequired)
          .firstOrNull;
      if (invite != null) {
        final detail = ParticipantCompetitionCubit(
          competitions: competitions,
          attachments: attachments,
          hub: hub,
          competitionId: invite.id,
          organizationId: org,
        );
        await detail.load();
        final teaser = detail.state as ParticipantCompetitionLoaded;
        expect(teaser.competition.isInviteeView, isTrue);
        expect(teaser.competition.permissions.canJoin, isTrue);
        _log(
          'M17: ${teaser.competition.referenceNo} invitee, '
          '${teaser.attachments.length} invitation documents',
        );
        if (mutate) {
          final action = InvitationActionCubit(invitations: invitations);
          await action.join(teaser.competition.invitation!.id);
          final joined = action.state;
          expect(joined, isA<InvitationJoined>());
          await detail.applyCompetition(
            (joined as InvitationJoined).competition,
          );
          final state = detail.state as ParticipantCompetitionLoaded;
          expect(state.competition.isParticipantView, isTrue);
          _log(
            'M18: joined as participant ${state.competition.participation?.aliasNo}, '
            'access ${state.competition.access?.state.wire}',
          );
          await action.close();
        }
        await detail.close();
      }

      // ── M23 / M24 ──────────────────────────────────────────────────────
      await connected.timeout(const Duration(seconds: 10));
      final again = await competitions.list(
        role: CompetitionListRole.participant,
        group: CompetitionStatusGroup.active,
        direction: Direction.auction,
      );
      final auction = again.items
          .where(
            (item) =>
                item.status == CompetitionStatus.live &&
                item.access?.state == AccessState.full,
          )
          .firstOrNull;
      if (auction != null) {
        final room = LiveRoomBloc(
          competitions: competitions,
          live: live,
          clockSync: ApiClockSyncRepository(api),
          hub: hub,
          clock: clock,
          competitionId: auction.id,
          organizationId: org,
        )..add(const LiveRoomOpened());
        final opened = await room.stream.firstWhere(
          (s) => s is LiveRoomLoaded,
        ) as LiveRoomLoaded;
        _log(
          'M23: ${opened.competition.referenceNo} v${opened.snapshot.v} '
          '${opened.connection.name}, leading ${opened.snapshot.isLeading}, '
          'next ${opened.snapshot.requiredNextAmountMinor}',
        );
        expect(opened.snapshot.acceptingOffers, isTrue);
        // Channel auth settles.
        await Future<void>.delayed(const Duration(seconds: 2));

        final submit = OfferSubmitCubit(
          live: live,
          competitionId: auction.id,
          onAccepted: (s) => room.add(LiveRoomOfferAccepted(s)),
          onStale: () => room.add(const LiveRoomRefreshRequested()),
        );
        final next = opened.snapshot.requiredNextAmountMinor!;
        // Below the bound (the composer would stop it): the server refuses
        // with the required amount; nothing is created.
        submit.openConfirm(next - opened.snapshot.amountGranularityMinor);
        await submit.submit();
        final refused = submit.state as OfferComposing;
        expect(refused.issue?.kind, OfferIssueKind.stepNotMet);
        expect(refused.issue?.amountMinor, next);
        _log(
          'M24: below the bound → ${refused.issue?.kind.name} ${refused.issue?.amountMinor}',
        );

        if (mutate) {
          // Rate limit (2 s per participant) after the refused attempt.
          await Future<void>.delayed(const Duration(seconds: 3));
          final socket = room.stream.firstWhere(
            (s) => s is LiveRoomLoaded && s.snapshot.v > opened.snapshot.v,
          );
          submit.openConfirm(next);
          await submit.submit();
          final accepted = submit.state;
          expect(accepted, isA<OfferAccepted>());
          final after = await socket.timeout(
            const Duration(seconds: 10),
          ) as LiveRoomLoaded;
          expect(after.snapshot.myOffer?.amountMinor, next);
          _log(
            'M24: offer seq ${(accepted as OfferAccepted).submission.offer.seq} '
            'accepted; room v${after.snapshot.v}, leading ${after.snapshot.isLeading}',
          );

          // M28 lists it.
          final mine = MyOffersCubit(
            live: live,
            hub: hub,
            competitionId: auction.id,
            organizationId: org,
          );
          await mine.load();
          expect((mine.state as MyOffersLoaded).offers.first.amountMinor, next);
          await mine.close();
        }
        await submit.close();
        await room.close();
      }

      // ── M31 / M32 ──────────────────────────────────────────────────────
      final open = again.items
          .where(
            (item) =>
                item.status == CompetitionStatus.live &&
                item.access?.state == AccessState.full,
          )
          .firstOrNull;
      if (open != null) {
        final qa = QaBloc(
          competitions: competitions,
          comments: comments,
          hub: hub,
          competitionId: open.id,
          organizationId: org,
        )..add(const QaStarted());
        final loaded =
            await qa.stream.firstWhere((s) => s is QaLoaded) as QaLoaded;
        _log(
          'M31: ${loaded.threads.length} threads, can comment ${loaded.canComment}',
        );
        if (mutate && loaded.canComment) {
          await Future<void>.delayed(const Duration(seconds: 2));
          final composer = QaComposerCubit(
            comments: comments,
            competitionId: open.id,
          );
          final body =
              'سؤال تجريبي من اختبار التطبيق ${DateTime.now().millisecondsSinceEpoch}';
          final echoed = qa.stream.firstWhere(
            (s) => s is QaLoaded && s.threads.any((t) => t.body == body),
          );
          await composer.post(body);
          final posted = composer.state as QaComposerPosted;
          expect(posted.comment.author.kind.wire, 'me');
          // comment.created over Reverb; the posted copy must not double it.
          qa.add(QaCommentPosted(posted.comment));
          final state =
              await echoed.timeout(const Duration(seconds: 10)) as QaLoaded;
          await Future<void>.delayed(const Duration(seconds: 2));
          expect(
            (qa.state as QaLoaded).threads.where((t) => t.body == body),
            hasLength(1),
          );
          _log(
            'M32: posted and received once (${state.threads.length} threads)',
          );
          await composer.close();
        }
        await qa.close();
      }

      await user.cancel();
      await hub.dispose();
      await realtime.dispose();
      await auth.logout();
      api.dio.close();
    },
    skip: skip,
    timeout: const Timeout(Duration(seconds: 90)),
  );
}
