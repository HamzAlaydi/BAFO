import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/data/clock_sync_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/presentation/live_room_bloc.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockCompetitions extends Mock implements CompetitionsRepository {}

class _MockLive extends Mock implements LiveRepository {}

class _MockClockSync extends Mock implements ClockSyncRepository {}

void main() {
  const org = 'org-a';
  final competition = Competition.fromJson(
    fixtureData('competition_supplier_a_live_auction'),
  );
  final base = fixtureData('live_supplier_a_auction');
  final channel = 'competition.${competition.id}.participant.$org';
  final closeAt = DateTime.parse(base['effective_close_at'] as String);

  Map<String, dynamic> snap(int v, [Map<String, dynamic> changes = const {}]) =>
      {...base, 'v': v, ...changes};

  (ParticipantLiveSnapshot, Map<String, dynamic>) rest(
    Map<String, dynamic> json,
  ) => (ParticipantLiveSnapshot.fromJson(json), json);

  late _MockCompetitions competitions;
  late _MockLive live;
  late _MockClockSync clockSync;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;

  setUp(() {
    competitions = _MockCompetitions();
    live = _MockLive();
    clockSync = _MockClockSync();
    when(() => competitions.show(competition.id))
        .thenAnswer((_) async => competition);
    when(() => live.participantSnapshot(competition.id))
        .thenAnswer((_) async => rest(snap(3)));
    when(() => live.heartbeat(competition.id)).thenAnswer((_) async {});
    when(() => clockSync.sync()).thenAnswer((_) async {});
  });

  /// Runs [body] in fake time starting at [start], with a room built on a
  /// server clock that follows the fake time.
  /// The realtime fake and the hub are made inside the fake zone, so their
  /// stream deliveries run on the fake microtask queue.
  void run(
    DateTime start,
    void Function(
      FakeAsync async,
      LiveRoomBloc bloc,
      List<LiveRoomState> states,
    )
    body, {
    StreamController<bool>? offline,
    bool connected = false,
  }) {
    fakeAsync((async) {
      realtime = FakeRealtimeClient();
      if (connected) realtime.setConnection(RealtimeConnectionState.connected);
      hub = CompetitionChannelHub(realtime);
      final clock = ServerClock(deviceNow: () => async.getClock(start).now());
      final bloc = LiveRoomBloc(
        competitions: competitions,
        live: live,
        clockSync: clockSync,
        hub: hub,
        clock: clock,
        competitionId: competition.id,
        organizationId: org,
        offline: offline?.stream,
      );
      final states = <LiveRoomState>[];
      final subscription = bloc.stream.listen(states.add);
      body(async, bloc, states);
      unawaited(subscription.cancel());
      unawaited(bloc.close());
      async.flushMicrotasks();
      unawaited(hub.dispose());
      unawaited(realtime.dispose());
      async.flushMicrotasks();
    }, initialTime: start);
  }

  LiveRoomLoaded loaded(LiveRoomBloc bloc) => bloc.state as LiveRoomLoaded;

  final farFromClose = closeAt.subtract(const Duration(hours: 20));

  test('opening subscribes, resyncs over REST, and starts heartbeat and clock sync', () {
    run(farFromClose, connected: true, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      expect(states.first, const LiveRoomLoading());
      final state = loaded(bloc);
      expect(state.snapshot.v, 3);
      expect(state.connection, LiveConnection.connected);
      expect(state.canSubmit, isTrue);
      expect(realtime.subscribed, [channel]);
      verify(() => live.participantSnapshot(competition.id)).called(1);
      verify(() => live.heartbeat(competition.id)).called(1);
      verify(() => clockSync.sync()).called(1);

      // Heartbeat every 20 s, clock sync every 60 s.
      async.elapse(const Duration(seconds: 60));
      verify(() => live.heartbeat(competition.id)).called(3);
      verify(() => clockSync.sync()).called(1);
    });
  });

  test('snapshots apply by v only: older and equal versions are dropped', () {
    run(farFromClose, connected: true, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();

      realtime.emit(
        channel,
        RealtimeEvents.liveUpdated,
        snap(5, {'is_leading': false}),
      );
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 5);
      expect(loaded(bloc).snapshot.isLeading, isFalse);

      realtime.emit(
        channel,
        RealtimeEvents.liveUpdated,
        snap(4, {'is_leading': true}),
      );
      realtime.emit(
        channel,
        RealtimeEvents.liveUpdated,
        snap(5, {'is_leading': true}),
      );
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 5);
      expect(loaded(bloc).snapshot.isLeading, isFalse);

      // A REST read with an older v (a resync after a reconnect) is dropped.
      when(() => live.participantSnapshot(competition.id))
          .thenAnswer((_) async => rest(snap(4)));
      hub.notifyResumed();
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 5);

      realtime.emit(
        channel,
        RealtimeEvents.liveUpdated,
        snap(6, {'is_leading': true}),
      );
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 6);
      expect(loaded(bloc).snapshot.isLeading, isTrue);
    });
  });

  test('a socket snapshot that arrives before the first REST read is kept', () {
    run(farFromClose, (async, bloc, states) {
      // Made in the fake zone so its completion runs on the fake queue.
      final pending =
          Completer<(ParticipantLiveSnapshot, Map<String, dynamic>)>();
      when(() => live.participantSnapshot(competition.id))
          .thenAnswer((_) => pending.future);
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      // Subscribed first (buffering), then the REST read is in flight.
      expect(realtime.subscribed, [channel]);
      realtime.emit(channel, RealtimeEvents.liveUpdated, snap(9));
      async.flushMicrotasks();
      pending.complete(rest(snap(7)));
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 9);
    });
  });

  test('connection drop: grace, reconnecting after 3 s, polling after 10 s; reconnect resyncs', () {
    run(farFromClose, connected: true, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      clearInteractions(live);

      realtime.setConnection(RealtimeConnectionState.connecting);
      async.flushMicrotasks();
      expect(loaded(bloc).connection, LiveConnection.grace);
      expect(loaded(bloc).canSubmit, isTrue);

      async.elapse(const Duration(seconds: 3));
      expect(loaded(bloc).connection, LiveConnection.reconnecting);
      expect(loaded(bloc).canSubmit, isFalse, reason: 'submit is disabled');

      async.elapse(const Duration(seconds: 7));
      expect(loaded(bloc).connection, LiveConnection.polling);
      // Far from the close: every 10 s.
      expect(loaded(bloc).pollInterval, const Duration(seconds: 10));
      expect(loaded(bloc).canSubmit, isTrue, reason: 'a fresh poll');
      verify(() => live.participantSnapshot(competition.id)).called(1);

      async.elapse(const Duration(seconds: 10));
      verify(() => live.participantSnapshot(competition.id)).called(1);

      realtime.setConnection(RealtimeConnectionState.connected);
      async.flushMicrotasks();
      expect(loaded(bloc).connection, LiveConnection.connected);
      // The hub asks for a resync after the reconnect.
      verify(() => live.participantSnapshot(competition.id)).called(1);
      async.elapse(const Duration(seconds: 30));
      verifyNever(() => live.participantSnapshot(competition.id));
    });
  });

  test('polling runs every 3 s in the last 5 minutes and goes stale after two missed intervals', () {
    run(closeAt.subtract(const Duration(minutes: 4)), (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      expect(loaded(bloc).connection, LiveConnection.connecting);
      expect(
        loaded(bloc).canSubmit,
        isTrue,
        reason: 'after the first snapshot',
      );

      async.elapse(const Duration(seconds: 10));
      expect(loaded(bloc).connection, LiveConnection.polling);
      expect(loaded(bloc).pollInterval, const Duration(seconds: 3));

      when(() => live.participantSnapshot(competition.id))
          .thenThrow(const ApiException(code: ApiErrorCode.network));
      async.elapse(const Duration(seconds: 5));
      expect(loaded(bloc).pollStale, isFalse);
      async.elapse(const Duration(seconds: 2));
      expect(loaded(bloc).pollStale, isTrue);
      expect(loaded(bloc).canSubmit, isFalse);

      when(() => live.participantSnapshot(competition.id))
          .thenAnswer((_) async => rest(snap(3)));
      async.elapse(const Duration(seconds: 3));
      expect(loaded(bloc).pollStale, isFalse);
      expect(loaded(bloc).canSubmit, isTrue);
    });
  });

  test('offline disables submit; back online restores the socket state', () {
    final offline = StreamController<bool>.broadcast();
    run(farFromClose, offline: offline, connected: true, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      offline.add(true);
      async.flushMicrotasks();
      expect(loaded(bloc).connection, LiveConnection.offline);
      expect(loaded(bloc).canSubmit, isFalse);
      offline.add(false);
      async.flushMicrotasks();
      expect(loaded(bloc).connection, LiveConnection.connected);
      unawaited(offline.close());
    });
  });

  test(
    'pausing stops the heartbeat at once; resuming restarts it and resyncs',
    () {
      run(farFromClose, connected: true, (async, bloc, states) {
        bloc.add(const LiveRoomOpened());
        async.flushMicrotasks();
        clearInteractions(live);

        bloc.add(const LiveRoomPaused());
        async.elapse(const Duration(seconds: 60));
        verifyNever(() => live.heartbeat(competition.id));

        bloc.add(const LiveRoomResumed());
        // The app root asks every open channel to resync on resume.
        hub.notifyResumed();
        async.flushMicrotasks();
        verify(() => live.heartbeat(competition.id)).called(1);
        verify(() => live.participantSnapshot(competition.id)).called(1);
        async.elapse(const Duration(seconds: 20));
        verify(() => live.heartbeat(competition.id)).called(1);
      });
    },
  );

  test('the last 10 seconds show the server-timing hint; zero says closing until the server decides', () {
    run(closeAt.subtract(const Duration(seconds: 15)), connected: true, (
      async,
      bloc,
      states,
    ) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      expect(loaded(bloc).finalSeconds, isFalse);

      async.elapse(const Duration(seconds: 5));
      expect(loaded(bloc).finalSeconds, isTrue);
      expect(loaded(bloc).deadlinePassed, isFalse);

      async.elapse(const Duration(seconds: 10));
      expect(loaded(bloc).deadlinePassed, isTrue);
      expect(loaded(bloc).canSubmit, isFalse);
      expect(
        loaded(bloc).snapshot.status,
        CompetitionStatus.live,
        reason: 'never "closed" before the server says so',
      );

      // The recheck asks the server, which has closed the competition.
      final closedCompetition = Competition.fromJson({
        ...fixtureData('competition_supplier_a_live_auction'),
        'status': 'closed',
        'phase': null,
      });
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => closedCompetition);
      when(() => live.participantSnapshot(competition.id)).thenAnswer(
        (_) async => rest(
          snap(4, {
            'status': 'closed',
            'phase': null,
            'accepting_offers': false,
            'last_change': {'kind': 'status', 'reason': null},
          }),
        ),
      );
      async.elapse(const Duration(seconds: 2));
      final state = loaded(bloc);
      expect(state.snapshot.status, CompetitionStatus.closed);
      expect(state.deadlinePassed, isFalse);
      expect(state.competition.status, CompetitionStatus.closed);
    });
  });

  test(
    'an anti-sniping extension raises the notice with the rules duration',
    () {
      run(farFromClose, connected: true, (async, bloc, states) {
        bloc.add(const LiveRoomOpened());
        async.flushMicrotasks();
        expect(loaded(bloc).extension, isNull);
        realtime.emit(
          channel,
          RealtimeEvents.liveUpdated,
          snap(4, {
            'extension_count': 1,
            'effective_close_at': closeAt
                .add(const Duration(minutes: 2))
                .toIso8601String(),
            'last_change': {'kind': 'extension', 'reason': 'auto'},
          }),
        );
        async.flushMicrotasks();
        final notice = loaded(bloc).extension!;
        expect(notice.reason, ExtensionReason.auto);
        expect(notice.bySeconds, competition.rules.autoExtend.bySeconds);
        expect(loaded(bloc).closeAt, closeAt.add(const Duration(minutes: 2)));
      });
    },
  );

  test('an offer response snapshot goes through the same gate', () {
    run(farFromClose, connected: true, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      final newer = snap(8, {'my_offers_count': 2});
      bloc.add(
        LiveRoomOfferAccepted(
          OfferSubmission(
            offer: MyOffer.fromJson(
              fixtureList('my_offers_supplier_a_final_window').first,
            ),
            live: ParticipantLiveSnapshot.fromJson(newer),
            liveJson: newer,
          ),
        ),
      );
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.myOffersCount, 2);
      // An older response (a replay) changes nothing.
      final older = snap(7, {'my_offers_count': 1});
      bloc.add(
        LiveRoomOfferAccepted(
          OfferSubmission(
            offer: MyOffer.fromJson(
              fixtureList('my_offers_supplier_a_final_window').first,
            ),
            live: ParticipantLiveSnapshot.fromJson(older),
            liveJson: older,
          ),
        ),
      );
      async.flushMicrotasks();
      expect(loaded(bloc).snapshot.v, 8);
    });
  });

  test('an invitee is not a participant: the room is unavailable', () {
    final invitee = Competition.fromJson(
      fixtureData('competition_buyer_d_live_initial_invitee'),
    );
    when(() => competitions.show(competition.id))
        .thenAnswer((_) async => invitee);
    run(farFromClose, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      expect(bloc.state, LiveRoomUnavailable(invitee));
      expect(realtime.subscribed, isEmpty);
      verifyNever(() => live.heartbeat(any()));
    });
  });

  test('a 404 never reveals the competition', () {
    when(() => competitions.show(competition.id))
        .thenThrow(const ApiException(code: 'not_found', statusCode: 404));
    run(farFromClose, (async, bloc, states) {
      bloc.add(const LiveRoomOpened());
      async.flushMicrotasks();
      expect(bloc.state, const LiveRoomNotFound());
    });
  });

  test('closing the room stops every timer at once', () {
    fakeAsync((async) {
      realtime = FakeRealtimeClient()
        ..setConnection(RealtimeConnectionState.connected);
      hub = CompetitionChannelHub(realtime);
      final bloc = LiveRoomBloc(
        competitions: competitions,
        live: live,
        clockSync: clockSync,
        hub: hub,
        clock: ServerClock(deviceNow: () => async.getClock(farFromClose).now()),
        competitionId: competition.id,
        organizationId: org,
      )..add(const LiveRoomOpened());
      async.flushMicrotasks();
      unawaited(bloc.close());
      async.flushMicrotasks();
      clearInteractions(live);
      expect(async.pendingTimers, isEmpty);
      async.elapse(const Duration(minutes: 5));
      verifyNever(() => live.heartbeat(any()));
    }, initialTime: farFromClose);
  });

  test('closing the room releases the shared channel', () async {
    realtime = FakeRealtimeClient()
      ..setConnection(RealtimeConnectionState.connected);
    hub = CompetitionChannelHub(realtime);
    final bloc = LiveRoomBloc(
      competitions: competitions,
      live: live,
      clockSync: clockSync,
      hub: hub,
      clock: ServerClock(),
      competitionId: competition.id,
      organizationId: org,
    )..add(const LiveRoomOpened());
    await pumpEventQueue();
    expect(bloc.state, isA<LiveRoomLoaded>());
    await bloc.close();
    expect(realtime.unsubscribed, [channel]);
    await hub.dispose();
    await realtime.dispose();
  });
}
