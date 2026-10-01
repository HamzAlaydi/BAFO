import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/issuer/presentation/award/award_cubits.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_bloc.dart';
import 'package:bafo/features/issuer/presentation/offers_log/offers_log_bloc.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import 'issuer_test_helpers.dart';

void main() {
  setUpAll(registerIssuerFallbacks);

  late MockCompetitions competitions;
  late MockLive live;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;

  setUp(() {
    competitions = MockCompetitions();
    live = MockLive();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
  });

  tearDown(() async {
    await hub.dispose();
    await realtime.dispose();
  });

  (IssuerLiveSnapshot, Map<String, dynamic>) snapshot({
    int v = 5,
    void Function(Map<String, dynamic>)? edit,
  }) {
    final json = copyOf(fixtureData('live_issuer_final_window'))..['v'] = v;
    edit?.call(json);
    return (IssuerLiveSnapshot.fromJson(json), json);
  }

  group('IssuerLiveBloc (M46)', () {
    final competition = issuerCompetition(
      'competition_issuer_live_final_window',
    );
    final channel = 'competition.${competition.id}';

    IssuerLiveBloc build() => IssuerLiveBloc(
      competitions: competitions,
      live: live,
      hub: hub,
      now: () => DateTime.utc(2026, 9, 29, 17),
      competitionId: competition.id,
      graceDelay: const Duration(milliseconds: 10),
      fallbackDelay: const Duration(milliseconds: 30),
      pollFast: const Duration(milliseconds: 15),
      pollSlow: const Duration(milliseconds: 15),
    );

    setUp(() {
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => live.issuerSnapshot(competition.id))
          .thenAnswer((_) async => snapshot());
    });

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'opens with the embedded snapshot and resyncs over REST (v-gated)',
      setUp: () => realtime.setConnection(RealtimeConnectionState.connected),
      build: build,
      act: (bloc) => bloc.add(const IssuerLiveOpened()),
      expect: () => [
        const IssuerLiveLoading(),
        isA<IssuerLiveLoaded>()
            .having((s) => s.snapshot?.v, 'v', 5)
            .having((s) => s.snapshot?.leader?.aliasNo, 'leader', 9)
            .having(
              (s) => s.connection,
              'connection',
              LiveConnection.connected,
            ),
      ],
      verify: (_) {
        expect(realtime.subscribed, [channel]);
        verify(() => live.issuerSnapshot(competition.id)).called(1);
      },
    );

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'socket snapshots: newer apply, older drop; a status change refetches the competition',
      setUp: () => realtime.setConnection(RealtimeConnectionState.connected),
      build: build,
      act: (bloc) async {
        bloc.add(const IssuerLiveOpened());
        await Future<void>.delayed(const Duration(milliseconds: 5));
        realtime
          ..emit(channel, RealtimeEvents.liveUpdated, snapshot(v: 4).$2)
          ..emit(
            channel,
            RealtimeEvents.liveUpdated,
            snapshot(
              v: 6,
              edit: (json) => json
                ..['extension_count'] = 1
                ..['last_change'] = {'kind': 'extension', 'reason': 'auto'},
            ).$2,
          );
        await Future<void>.delayed(const Duration(milliseconds: 5));
        expect((bloc.state as IssuerLiveLoaded).snapshot?.extensionCount, 1);
        realtime.emit(
          channel,
          RealtimeEvents.liveUpdated,
          snapshot(
            v: 7,
            edit: (json) => json
              ..['status'] = 'closed'
              ..['phase'] = null
              ..['last_change'] = {'kind': 'status', 'reason': null},
          ).$2,
        );
        await Future<void>.delayed(const Duration(milliseconds: 5));
      },
      verify: (bloc) {
        final state = bloc.state as IssuerLiveLoaded;
        expect(state.snapshot?.v, 7);
        expect(state.snapshot?.status, CompetitionStatus.closed);
        verify(() => competitions.show(competition.id)).called(2);
      },
    );

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'no socket: connecting, then polling GET …/live; a connection stops the polling',
      build: build,
      act: (bloc) async {
        bloc.add(const IssuerLiveOpened());
        await Future<void>.delayed(const Duration(milliseconds: 5));
        expect(
          (bloc.state as IssuerLiveLoaded).connection,
          LiveConnection.connecting,
        );
        await Future<void>.delayed(const Duration(milliseconds: 60));
        expect(
          (bloc.state as IssuerLiveLoaded).connection,
          LiveConnection.polling,
        );
        realtime.setConnection(RealtimeConnectionState.connected);
        await Future<void>.delayed(const Duration(milliseconds: 5));
      },
      verify: (bloc) {
        expect(
          (bloc.state as IssuerLiveLoaded).connection,
          LiveConnection.connected,
        );
        // Open + at least two polls.
        verify(() => live.issuerSnapshot(competition.id))
            .called(greaterThan(2));
      },
    );

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'a dropped socket shows reconnecting after the grace period',
      setUp: () => realtime.setConnection(RealtimeConnectionState.connected),
      build: () => IssuerLiveBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        now: DateTime.now,
        competitionId: competition.id,
        graceDelay: const Duration(milliseconds: 10),
        fallbackDelay: const Duration(seconds: 5),
      ),
      act: (bloc) async {
        bloc.add(const IssuerLiveOpened());
        await Future<void>.delayed(const Duration(milliseconds: 5));
        realtime.setConnection(RealtimeConnectionState.connecting);
        await Future<void>.delayed(const Duration(milliseconds: 2));
        // Grace: still "connected".
        expect(
          (bloc.state as IssuerLiveLoaded).connection,
          LiveConnection.connected,
        );
        await Future<void>.delayed(const Duration(milliseconds: 30));
      },
      verify: (bloc) => expect(
        (bloc.state as IssuerLiveLoaded).connection,
        LiveConnection.reconnecting,
      ),
    );

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'a draft has no live state; a participant projection is sent back',
      setUp: () {
        final draft = issuerCompetition('competition_issuer_draft');
        final participant = Competition.fromJson(
          fixtureData('competition_supplier_a_live_final_window'),
        );
        when(() => competitions.show('draft')).thenAnswer((_) async => draft);
        when(() => competitions.show('other'))
            .thenAnswer((_) async => participant);
      },
      build: () => IssuerLiveBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        now: DateTime.now,
        competitionId: 'draft',
      ),
      act: (bloc) => bloc.add(const IssuerLiveOpened()),
      verify: (bloc) async {
        expect((bloc.state as IssuerLiveLoaded).snapshot, isNull);
        verifyNever(() => live.issuerSnapshot('draft'));
        final other = IssuerLiveBloc(
          competitions: competitions,
          live: live,
          hub: hub,
          now: DateTime.now,
          competitionId: 'other',
        )..add(const IssuerLiveOpened());
        await Future<void>.delayed(const Duration(milliseconds: 5));
        expect(other.state, isA<IssuerLiveNotIssuer>());
        await other.close();
      },
    );

    blocTest<IssuerLiveBloc, IssuerLiveState>(
      'the sealed projection keeps amounts null (nothing is derived)',
      setUp: () {
        final sealed = issuerCompetition('competition_issuer_live_sealed');
        final json = copyOf(fixtureData('live_issuer_sealed'));
        when(() => competitions.show(sealed.id))
            .thenAnswer((_) async => sealed);
        when(() => live.issuerSnapshot(sealed.id))
            .thenAnswer((_) async => (IssuerLiveSnapshot.fromJson(json), json));
      },
      build: () => IssuerLiveBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        now: DateTime.now,
        competitionId: issuerCompetition('competition_issuer_live_sealed').id,
      ),
      act: (bloc) => bloc.add(const IssuerLiveOpened()),
      verify: (bloc) {
        final snapshot = (bloc.state as IssuerLiveLoaded).snapshot!;
        expect(snapshot.leader, isNull);
        expect(
          snapshot.ranking.every((row) => row.currentAmountMinor == null),
          isTrue,
        );
        expect(snapshot.ranking.where((row) => row.submitted), hasLength(1));
      },
    );
  });

  group('OffersLogBloc (M47)', () {
    final competition = issuerCompetition(
      'competition_issuer_live_final_window',
    );
    final channel = 'competition.${competition.id}';
    final entries = fixtureList('offers_log_issuer_final_window')
        .map(OfferLogEntry.fromJson)
        .toList();

    Map<String, dynamic> entryJson(int seq) =>
        copyOf(fixtureList('offers_log_issuer_final_window').first)
          ..['id'] = 'offer-$seq'
          ..['seq'] = seq;

    OffersLogBloc build() => OffersLogBloc(
      competitions: competitions,
      live: live,
      hub: hub,
      competitionId: competition.id,
    );

    setUp(() {
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(
        () => live.offersLog(
          competition.id,
          afterSeq: 0,
          limit: any(named: 'limit'),
        ),
      ).thenAnswer(
        (_) async => OfferLogPage(entries: entries, lastSeq: 3, hasMore: false),
      );
    });

    blocTest<OffersLogBloc, OffersLogState>(
      'loads the log in seq order',
      build: build,
      act: (bloc) => bloc.add(const OffersLogStarted()),
      expect: () => [
        const OffersLogLoading(),
        isA<OffersLogLoaded>()
            .having((s) => s.entries.map((e) => e.seq), 'seqs', [1, 2, 3])
            .having((s) => s.lastSeq, 'lastSeq', 3),
      ],
    );

    blocTest<OffersLogBloc, OffersLogState>(
      'offer.accepted appends the next seq, de-duplicates, and fills a gap',
      setUp: () =>
          when(() => live.offersLog(competition.id, afterSeq: 4, limit: 500))
              .thenAnswer(
                (_) async => OfferLogPage(
                  entries: [
                    OfferLogEntry.fromJson(entryJson(5)),
                    OfferLogEntry.fromJson(entryJson(6)),
                  ],
                  lastSeq: 6,
                  hasMore: false,
                ),
              ),
      build: build,
      act: (bloc) async {
        bloc.add(const OffersLogStarted());
        await Future<void>.delayed(Duration.zero);
        realtime
          ..emit(channel, RealtimeEvents.offerAccepted, entryJson(4))
          ..emit(channel, RealtimeEvents.offerAccepted, entryJson(4))
          // 6 arrives before 5: a gap.
          ..emit(channel, RealtimeEvents.offerAccepted, entryJson(6));
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (bloc) {
        final state = bloc.state as OffersLogLoaded;
        expect(state.entries.map((e) => e.seq), [1, 2, 3, 4, 5, 6]);
        expect(state.lastSeq, 6);
      },
    );

    blocTest<OffersLogBloc, OffersLogState>(
      'pages with after_seq while has_more',
      setUp: () {
        when(
          () => live.offersLog(
            competition.id,
            afterSeq: 0,
            limit: any(named: 'limit'),
          ),
        ).thenAnswer(
          (_) async => OfferLogPage(
            entries: entries.take(2).toList(),
            lastSeq: 2,
            hasMore: true,
          ),
        );
        when(
          () => live.offersLog(
            competition.id,
            afterSeq: 2,
            limit: any(named: 'limit'),
          ),
        ).thenAnswer(
          (_) async => OfferLogPage(
            entries: entries.skip(2).toList(),
            lastSeq: 3,
            hasMore: false,
          ),
        );
      },
      build: build,
      act: (bloc) async {
        bloc.add(const OffersLogStarted());
        await Future<void>.delayed(Duration.zero);
        bloc.add(const OffersLogNextPageRequested());
      },
      verify: (bloc) {
        final state = bloc.state as OffersLogLoaded;
        expect(state.entries, hasLength(3));
        expect(state.hasMore, isFalse);
      },
    );

    blocTest<OffersLogBloc, OffersLogState>(
      'sealed rows keep a null amount',
      setUp: () {
        final sealed = issuerCompetition('competition_issuer_live_sealed');
        when(() => competitions.show(sealed.id))
            .thenAnswer((_) async => sealed);
        when(
          () => live.offersLog(
            sealed.id,
            afterSeq: 0,
            limit: any(named: 'limit'),
          ),
        ).thenAnswer(
          (_) async => OfferLogPage(
            entries: fixtureList('offers_log_issuer_sealed')
                .map(OfferLogEntry.fromJson)
                .toList(),
            lastSeq: 1,
            hasMore: false,
          ),
        );
      },
      build: () => OffersLogBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        competitionId: issuerCompetition('competition_issuer_live_sealed').id,
      ),
      act: (bloc) => bloc.add(const OffersLogStarted()),
      verify: (bloc) => expect(
        (bloc.state as OffersLogLoaded).entries.single.amountMinor,
        isNull,
      ),
    );
  });

  group('AwardCubit (M48)', () {
    final awarded = issuerCompetition('competition_issuer_awarded');
    final award = Award.fromJson(fixtureData('award_issuer_awarded'));
    final standings = fixtureList('offers_issuer_final_window')
        .map(ParticipantStandingRow.fromJson)
        .toList();

    AwardCubit build(String id) => AwardCubit(
      competitions: competitions,
      live: live,
      hub: hub,
      competitionId: id,
    );

    blocTest<AwardCubit, AwardState>(
      'loads the award and the final standings; refreshes on an award change',
      setUp: () {
        when(() => competitions.show(awarded.id))
            .thenAnswer((_) async => awarded);
        when(() => competitions.issuerAward(awarded.id))
            .thenAnswer((_) async => award);
        when(() => live.standings(awarded.id))
            .thenAnswer((_) async => standings);
      },
      build: () => build(awarded.id),
      act: (cubit) async {
        await cubit.load();
        realtime.emit(
          'competition.${awarded.id}',
          RealtimeEvents.liveUpdated,
          copyOf(fixtureData('live_issuer_final_window'))
            ..['competition_id'] = awarded.id
            ..['v'] = 99
            ..['last_change'] = {'kind': 'award', 'reason': null},
        );
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (cubit) {
        final state = cubit.state as AwardLoaded;
        expect(state.award?.aliasNo, 96);
        expect(state.standings, hasLength(3));
        verify(() => competitions.issuerAward(awarded.id)).called(2);
      },
    );

    blocTest<AwardCubit, AwardState>(
      'before the first close the award view is unavailable',
      setUp: () {
        final live = issuerCompetition('competition_issuer_live_initial');
        when(() => competitions.show(live.id)).thenAnswer((_) async => live);
      },
      build: () =>
          build(issuerCompetition('competition_issuer_live_initial').id),
      act: (cubit) => cubit.load(),
      verify: (cubit) {
        expect(cubit.state, isA<AwardUnavailable>());
        verifyNever(() => competitions.issuerAward(any()));
      },
    );

    blocTest<AwardCubit, AwardState>(
      'a closed competition without award and failing standings still loads',
      setUp: () {
        final closed = issuerCompetition('competition_issuer_closed');
        when(() => competitions.show(closed.id))
            .thenAnswer((_) async => closed);
        when(() => competitions.issuerAward(closed.id))
            .thenAnswer((_) async => null);
        when(() => live.standings(closed.id))
            .thenThrow(apiError('server_error', status: 500));
      },
      build: () => build(issuerCompetition('competition_issuer_closed').id),
      act: (cubit) => cubit.load(),
      verify: (cubit) {
        final state = cubit.state as AwardLoaded;
        expect(state.award, isNull);
        expect(state.standingsError?.code, 'server_error');
      },
    );
  });

  group('ResultReportCubit (result PDF)', () {
    late MockReports reports;
    late MockDownloads downloads;
    const file = FileRefFixture.pdf;

    setUp(() {
      reports = MockReports();
      downloads = MockDownloads();
    });

    ResultReportCubit build() => ResultReportCubit(
      reports: reports,
      downloads: downloads,
      competitionId: 'c',
      pollInterval: const Duration(milliseconds: 5),
      pollTimeout: const Duration(milliseconds: 200),
    );

    blocTest<ResultReportCubit, ResultReportState>(
      'pending → polls → ready → authenticated download → system viewer',
      setUp: () {
        var calls = 0;
        when(() => reports.report('c', locale: 'ar')).thenAnswer((_) async {
          calls++;
          return calls < 3
              ? const CompetitionReport(status: 'pending', locale: 'ar')
              : const CompetitionReport(
                  status: 'ready',
                  locale: 'ar',
                  file: file,
                );
        });
        when(
          () => downloads.download(file, onProgress: any(named: 'onProgress')),
        ).thenAnswer((_) async => '/tmp/report.pdf');
        when(
          () => downloads.open('/tmp/report.pdf', mimeType: 'application/pdf'),
        ).thenAnswer((_) async => FileOpenResult.opened);
      },
      build: build,
      act: (cubit) => cubit.open('ar'),
      verify: (cubit) {
        expect(
          cubit.state,
          const ResultReportOpened(locale: 'ar', result: FileOpenResult.opened),
        );
        verify(() => reports.report('c', locale: 'ar')).called(3);
      },
    );

    blocTest<ResultReportCubit, ResultReportState>(
      'report_not_available hides the report',
      setUp: () =>
          when(() => reports.report('c', locale: 'en'))
              .thenThrow(apiError('report_not_available', status: 409)),
      build: build,
      act: (cubit) => cubit.open('en'),
      expect: () => [
        const ResultReportWorking(locale: 'en'),
        const ResultReportUnavailable(),
      ],
    );

    blocTest<ResultReportCubit, ResultReportState>(
      'still pending after the timeout fails with timedOut',
      setUp: () => when(() => reports.report('c', locale: 'ar'))
          .thenAnswer((_) async => const CompetitionReport(status: 'pending')),
      build: () => ResultReportCubit(
        reports: reports,
        downloads: downloads,
        competitionId: 'c',
        pollInterval: const Duration(milliseconds: 5),
        pollTimeout: const Duration(milliseconds: 20),
      ),
      act: (cubit) => cubit.open('ar'),
      wait: const Duration(milliseconds: 60),
      verify: (cubit) => expect(
        cubit.state,
        const ResultReportFailed(locale: 'ar', timedOut: true),
      ),
    );
  });
}

/// A private PDF as the report returns it.
abstract final class FileRefFixture {
  static const pdf = FileRef(
    id: '01m3q65d51zx5tt0pq1qrw74ev',
    name: 'BAFO-T-2026-000003-ar.pdf',
    mimeType: 'application/pdf',
    extension: 'pdf',
    sizeBytes: 81850,
    downloadPath: '/api/app/v1/files/01m3q65d51zx5tt0pq1qrw74ev/download',
  );
}
