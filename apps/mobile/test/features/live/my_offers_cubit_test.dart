import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/presentation/my_offers_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockLive extends Mock implements LiveRepository {}

void main() {
  const competitionId = 'c1';
  const org = 'org-a';
  const channel = 'competition.$competitionId.participant.$org';
  final offers = fixtureList('my_offers_supplier_a_final_window')
      .map(MyOffer.fromJson)
      .toList();
  final snapshot = fixtureData('live_supplier_a_final_window');

  late _MockLive live;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;

  setUp(() {
    live = _MockLive();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
  });

  tearDown(() async {
    await hub.dispose();
    await realtime.dispose();
  });

  MyOffersCubit build() => MyOffersCubit(
    live: live,
    hub: hub,
    competitionId: competitionId,
    organizationId: org,
  );

  blocTest<MyOffersCubit, MyOffersState>(
    'loads the own offers and refreshes on an offer or void snapshot only',
    setUp: () =>
        when(() => live.myOffers(competitionId))
            .thenAnswer((_) async => offers),
    build: build,
    act: (cubit) async {
      await cubit.load();
      realtime.emit(channel, RealtimeEvents.liveUpdated, {
        ...snapshot,
        'v': 10,
        'last_change': {'kind': 'extension', 'reason': 'auto'},
      });
      await pumpEventQueue();
      realtime.emit(channel, RealtimeEvents.liveUpdated, {
        ...snapshot,
        'v': 11,
        'last_change': {'kind': 'offer', 'reason': null},
      });
      await pumpEventQueue();
    },
    expect: () => [
      const MyOffersLoading(),
      MyOffersLoaded(offers: offers),
      MyOffersLoaded(offers: offers, refreshing: true),
      MyOffersLoaded(offers: offers),
    ],
    verify: (_) {
      expect(realtime.subscribed, [channel]);
      verify(() => live.myOffers(competitionId)).called(2);
    },
  );

  blocTest<MyOffersCubit, MyOffersState>(
    'a non-participant is sent back (403), a 404 stays not found',
    setUp: () => when(
      () => live.myOffers(competitionId),
    ).thenThrow(const ApiException(code: 'not_a_participant', statusCode: 403)),
    build: build,
    act: (cubit) => cubit.load(),
    expect: () => const [
      MyOffersLoading(),
      MyOffersUnavailable(notFound: false),
    ],
    verify: (_) => expect(realtime.subscribed, isEmpty),
  );

  blocTest<MyOffersCubit, MyOffersState>(
    'a network error on the first load is a failure',
    setUp: () =>
        when(() => live.myOffers(competitionId))
            .thenThrow(const ApiException(code: ApiErrorCode.network)),
    build: build,
    act: (cubit) => cubit.load(),
    expect: () => const [
      MyOffersLoading(),
      MyOffersFailure(ApiException(code: ApiErrorCode.network)),
    ],
  );
}
