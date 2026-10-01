import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

void main() {
  group('SnapshotVersionGate', () {
    test('applies only strictly newer versions', () {
      final gate = SnapshotVersionGate();
      expect(gate.accept(5), isTrue);
      expect(gate.accept(5), isFalse);
      expect(gate.accept(4), isFalse);
      expect(gate.accept(null), isFalse);
      expect(gate.accept(6), isTrue);
      expect(gate.lastApplied, 6);
      expect(SnapshotVersionGate.versionOf({'v': 9}), 9);
      expect(SnapshotVersionGate.versionOf({'v': '9'}), isNull);
    });
  });

  group('CompetitionChannelHub', () {
    late FakeRealtimeClient realtime;
    late CompetitionChannelHub hub;

    setUp(() {
      realtime = FakeRealtimeClient();
      hub = CompetitionChannelHub(realtime);
    });

    tearDown(() async {
      await hub.dispose();
      await realtime.dispose();
    });

    test('picks the channel by viewer role', () async {
      final issuer = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final participant = hub.acquire(
        competitionId: 'c1',
        role: ViewerRole.participant,
        organizationId: 'o1',
      );
      final invitee = hub.acquire(competitionId: 'c1', role: ViewerRole.invitee);
      expect(issuer.channelName, 'competition.c1');
      expect(participant.channelName, 'competition.c1.participant.o1');
      expect(invitee.channelName, isNull);
      expect(realtime.subscribed, [
        'competition.c1',
        'competition.c1.participant.o1',
      ]);
      await issuer.release();
      await participant.release();
      await invitee.release();
    });

    test('is ref-counted: one subscription, left by the last release', () async {
      final a = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final b = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      expect(realtime.subscribed, ['competition.c1']);
      await a.release();
      expect(realtime.unsubscribed, isEmpty);
      await b.release();
      expect(realtime.unsubscribed, ['competition.c1']);
    });

    test('each handle applies live snapshots by v on its own', () async {
      final a = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final b = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final seenA = <int>[];
      final seenB = <int>[];
      a.liveSnapshots.listen((s) => seenA.add(s['v'] as int));
      b.liveSnapshots.listen((s) => seenB.add(s['v'] as int));

      // A's REST snapshot (v 7) arrives first; B has none yet.
      expect(a.acceptSnapshot({'v': 7}), isTrue);
      for (final v in [5, 8, 8, 6, 9]) {
        realtime.emit('competition.c1', RealtimeEvents.liveUpdated, {'v': v});
      }
      await pumpEventQueue();

      expect(seenA, [8, 9]);
      expect(seenB, [5, 8, 9]);
      // A POST …/offers response goes through the same guard.
      expect(a.acceptSnapshot({'v': 9}), isFalse);
      expect(a.lastAppliedVersion, 9);
      await a.release();
      await b.release();
    });

    test('routes the other events', () async {
      final handle = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final received = <String>[];
      handle.offersAccepted.listen((_) => received.add('offer'));
      handle.competitionUpdates.listen((_) => received.add('updated'));
      handle.commentsCreated.listen((_) => received.add('comment'));
      handle.invitationUpdates.listen((_) => received.add('invitation'));
      realtime
        ..emit('competition.c1', RealtimeEvents.offerAccepted, {'seq': 1})
        ..emit('competition.c1', RealtimeEvents.competitionUpdated, {})
        ..emit('competition.c1', RealtimeEvents.commentCreated, {})
        ..emit('competition.c1', RealtimeEvents.invitationUpdated, {})
        ..emit('competition.c2', RealtimeEvents.competitionUpdated, {});
      await pumpEventQueue();
      expect(received, ['offer', 'updated', 'comment', 'invitation']);
      await handle.release();
    });

    test('asks for a resync after a reconnect and on resume', () async {
      final handle = hub.acquire(competitionId: 'c1', role: ViewerRole.issuer);
      final invitee = hub.acquire(competitionId: 'c1', role: ViewerRole.invitee);
      var resyncs = 0;
      var inviteeResyncs = 0;
      handle.resyncRequests.listen((_) => resyncs++);
      invitee.resyncRequests.listen((_) => inviteeResyncs++);

      realtime
        ..setConnection(RealtimeConnectionState.connected)
        ..setConnection(RealtimeConnectionState.connected)
        ..setConnection(RealtimeConnectionState.failed)
        ..setConnection(RealtimeConnectionState.connected);
      await pumpEventQueue();
      expect(resyncs, 2);

      hub.notifyResumed();
      await pumpEventQueue();
      expect(resyncs, 3);
      // Invitees have no channel and refetch on resume.
      expect(inviteeResyncs, 3);
      await handle.release();
      await invitee.release();
    });
  });

  group('UnreadCountCubit', () {
    late FakeRealtimeClient realtime;
    var remote = 0;

    setUp(() {
      realtime = FakeRealtimeClient();
      remote = 7;
    });

    tearDown(() => realtime.dispose());

    UnreadCountCubit build() =>
        UnreadCountCubit(realtime: realtime, fetchCount: () async => remote);

    blocTest<UnreadCountCubit, int>(
      'seeds from Me, then follows the user channel',
      build: build,
      act: (cubit) async {
        cubit.start(userId: 'u1', initialCount: 3);
        realtime.emit('user.u1', RealtimeEvents.notificationCreated, {
          'notification': {'id': 'n'},
          'unread_count': 4,
        });
        await pumpEventQueue();
        realtime.emit('user.u1', RealtimeEvents.unreadCount, {'unread_count': 0});
        await pumpEventQueue();
      },
      expect: () => [3, 4, 0],
      verify: (_) => expect(realtime.subscribed, ['user.u1']),
    );

    blocTest<UnreadCountCubit, int>(
      'republishes notification.created payloads',
      build: build,
      act: (cubit) async {
        final created = <Map<String, dynamic>>[];
        cubit.notificationCreated.listen(created.add);
        cubit.start(userId: 'u1', initialCount: 0);
        realtime.emit('user.u1', RealtimeEvents.notificationCreated, {
          'notification': {'id': 'n1'},
          'unread_count': 1,
        });
        await pumpEventQueue();
        expect(created.single['notification'], {'id': 'n1'});
      },
      expect: () => [0, 1],
    );

    blocTest<UnreadCountCubit, int>(
      'refresh reads the API; stop forgets the user',
      build: build,
      act: (cubit) async {
        await cubit.refresh(); // Not started: nothing.
        cubit.start(userId: 'u1', initialCount: 1);
        await cubit.refresh();
        await cubit.stop();
      },
      expect: () => [1, 7, 0],
      verify: (_) => expect(realtime.unsubscribed, ['user.u1']),
    );
  });
}
