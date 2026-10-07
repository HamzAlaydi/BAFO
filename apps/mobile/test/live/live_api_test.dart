import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/idempotency.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/realtime/reverb_realtime_client.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/fakes.dart';

/// End-to-end check against a running API (not part of the default run).
///
/// ```sh
/// BAFO_LIVE_API=http://localhost:8000/api/app/v1 \
/// BAFO_DEMO_EMAIL=… BAFO_DEMO_PASSWORD=… \
/// flutter test test/live/live_api_test.dart
/// ```
///
/// The credentials are the demo accounts of docs/build/DEMO.md; they are
/// read from the environment and never stored in the repo.
void main() {
  final base = Platform.environment['BAFO_LIVE_API'];
  final email = Platform.environment['BAFO_DEMO_EMAIL'];
  final password = Platform.environment['BAFO_DEMO_PASSWORD'];
  final skip = base == null || email == null || password == null
      ? 'Set BAFO_LIVE_API, BAFO_DEMO_EMAIL and BAFO_DEMO_PASSWORD'
      : null;

  test(
    'sign in, /me, /home, lookups, competitions, live, Reverb',
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
      final api = ApiClient.create(
        env: env,
        tokens: tokens,
        clock: ServerClock(),
        languageCode: () => 'ar',
        onUnauthorized: () => fail('unexpected 401'),
        client: const ClientInfo(platform: 'android', appVersion: '1.0.0'),
      );
      final auth = ApiAuthRepository(api, deviceName: 'live-test · android');

      final payload = await auth.login(email: email!, password: password!);
      await tokens.write(payload.token);
      final me = await ApiAccountRepository(api).me();
      expect(me.user.email, email);
      // ignore: avoid_print
      print('Signed in: ${me.user.name} / ${me.organization.name}');

      final home = await ApiHomeRepository(api).home();
      // ignore: avoid_print
      print(
        'Home: ${home.participant.activeParticipations} participations, '
        '${home.issuer.activeCompetitions} issued, ${home.activities.length} '
        'activities, alerts ${home.alerts.map((a) => a.code.wire).toList()}',
      );

      final lookups = await ApiLookupsRepository(
        api,
        languageCode: () => 'ar',
      ).lookups();
      expect(lookups.regions, isNotEmpty);
      // The tier cards of the create flow (RELEASE_SCOPE.md §2.2, §2.6) come
      // from `preset.tier`; both release scopes offer the six tier presets.
      expect(
        lookups.presets.where((preset) => preset.isTiered),
        isNotEmpty,
        reason: 'GET /lookups presets carry `tier`',
      );
      // ignore: avoid_print
      print(
        'Presets: ${lookups.presets.map((p) => '${p.code}:${p.tier?.wire}').join(', ')}',
      );

      final competitions = ApiCompetitionsRepository(api);
      final list = await competitions.list(
        role: CompetitionListRole.participant,
      );
      // ignore: avoid_print
      print('Participating: ${list.items.length} (has more: ${list.hasMore})');
      final live = list.items
          .where((item) => item.status == CompetitionStatus.live)
          .firstOrNull;

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
      final user = realtime.subscribePrivate(RealtimeChannels.user(me.user.id));

      if (live != null) {
        final detail = await competitions.show(live.id);
        expect(detail.viewerRole, ViewerRole.participant);
        final channel = hub.acquire(
          competitionId: live.id,
          role: detail.viewerRole,
          organizationId: me.organization.id,
        );
        final (snapshot, raw) = await ApiLiveRepository(api)
            .participantSnapshot(live.id);
        expect(channel.acceptSnapshot(raw), isTrue);
        // ignore: avoid_print
        print(
          'Live ${detail.referenceNo}: v${snapshot.v}, accepting '
          '${snapshot.acceptingOffers}, leading ${snapshot.isLeading}',
        );
        await connected.timeout(const Duration(seconds: 10));
        // Give channel auth a moment; a refused auth would log and not throw.
        await Future<void>.delayed(const Duration(seconds: 2));

        // BAFO_LIVE_OFFER=1: place one offer (local dev DB only) and wait for
        // the participant snapshot to arrive over Reverb through the v gate.
        final required =
            snapshot.requiredNextAmountMinor ?? snapshot.startPriceMinor;
        if (Platform.environment['BAFO_LIVE_OFFER'] == '1' &&
            snapshot.acceptingOffers &&
            required != null) {
          final received = channel.liveSnapshots.first;
          final granularity = snapshot.amountGranularityMinor;
          // One step inside the bound, on the granularity.
          final amount = snapshot.direction == Direction.tender
              ? required - required % granularity
              : required + (granularity - required % granularity) % granularity;
          final submission = await ApiLiveRepository(api).submitOffer(
            live.id,
            amountMinor: amount,
            idempotencyKey: IdempotencyKey.generate(),
            confirmOutlier: true,
          );
          final pushed = await received.timeout(const Duration(seconds: 10));
          // ignore: avoid_print
          print(
            'Offer seq ${submission.offer.seq} accepted; realtime v${pushed['v']} '
            '(POST v${submission.live.v})',
          );
          expect(pushed['v'], greaterThan(snapshot.v));
        }
        await channel.release();
      } else {
        await connected.timeout(const Duration(seconds: 10));
      }
      // ignore: avoid_print
      print('Reverb connected: ${realtime.currentState}');

      await user.cancel();
      await hub.dispose();
      await realtime.dispose();
      await auth.logout();
      api.dio.close();
    },
    skip: skip,
    timeout: const Timeout(Duration(seconds: 60)),
  );
}
