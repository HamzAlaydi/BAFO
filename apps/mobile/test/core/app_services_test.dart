import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/config/app_config_repository.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/fakes.dart';

class _FakeAppConfigRepository implements AppConfigRepository {
  _FakeAppConfigRepository(this.answer);

  Future<AppConfig> Function() answer;

  @override
  AppConfig? current;

  @override
  Future<AppConfig> fetch() async => current = await answer();
}

/// Swallows the error the interceptor passes on (no Dio pipeline here).
class _SilentHandler extends ErrorInterceptorHandler {
  @override
  void next(DioException err) {}
}

AppConfig _config({
  String minAndroid = '1.0.0',
  bool maintenance = false,
  String host = 'localhost',
}) {
  final json = fixtureData('app_config');
  return AppConfig.fromJson({
    ...json,
    'min_version': {'ios': '1.0.0', 'android': minAndroid},
    'maintenance': {'enabled': maintenance, 'message': 'صيانة'},
    'realtime': {...json['realtime'] as Map<String, dynamic>, 'host': host},
  });
}

void main() {
  group('Env.resolveRealtime', () {
    const server = ServerRealtime(
      key: 'k',
      host: 'localhost',
      port: 8085,
      scheme: 'http',
    );

    test('a loopback server host becomes the API host (emulator)', () {
      final env = Env(
        apiBaseUrl: Uri.parse('http://10.0.2.2:8000/api/app/v1'),
        broadcastingAuthUrl: Uri.parse('http://10.0.2.2:8000/broadcasting/auth'),
        realtime: const RealtimeConfig(
          appKey: '',
          host: '10.0.2.2',
          port: 8085,
          useTls: false,
        ),
      );
      final config = env.resolveRealtime(server);
      expect(config.host, '10.0.2.2');
      expect(config.appKey, 'k');
      expect(config.useTls, isFalse);
      expect(config.enabled, isTrue);
    });

    test('a real host is kept; explicit defines win', () {
      final env = Env(
        apiBaseUrl: Uri.parse('https://api.bafo.sa/api/app/v1'),
        broadcastingAuthUrl: Uri.parse('https://api.bafo.sa/broadcasting/auth'),
        realtime: const RealtimeConfig(
          appKey: '',
          host: 'x',
          port: 1,
          useTls: false,
        ),
        realtimeOverrides: const RealtimeOverrides(port: 6001),
      );
      final config = env.resolveRealtime(
        const ServerRealtime(key: 'k', host: 'ws.bafo.sa', port: 443, scheme: 'https'),
      );
      expect(config.host, 'ws.bafo.sa');
      expect(config.port, 6001);
      expect(config.useTls, isTrue);
    });
  });

  test('compareSemver', () {
    expect(compareSemver('1.0.0', '1.0.0'), 0);
    expect(compareSemver('1.2.0', '1.10.0'), lessThan(0));
    expect(compareSemver('2.0', '1.9.9'), greaterThan(0));
    expect(compareSemver('1.0.0+7', '1.0.0'), 0);
  });

  group('AppConfigCubit', () {
    late AppGateCubit gate;
    late FakeRealtimeClient realtime;
    late _FakeAppConfigRepository repository;

    setUp(() {
      gate = AppGateCubit();
      realtime = FakeRealtimeClient();
      repository = _FakeAppConfigRepository(() async => _config());
    });

    tearDown(() async {
      await gate.close();
      await realtime.dispose();
    });

    AppConfigCubit build() => AppConfigCubit(
      repository: repository,
      gate: gate,
      realtime: realtime,
      env: testEnv(),
      client: const ClientInfo(platform: 'android', appVersion: '1.0.0'),
    );

    blocTest<AppConfigCubit, AppConfigState>(
      'loads the config and configures realtime from it',
      build: build,
      act: (cubit) => cubit.load(),
      expect: () => [const AppConfigLoading(), AppConfigLoaded(_config())],
      verify: (_) {
        expect(realtime.configured?.host, 'api.test');
        expect(realtime.configured?.port, 8085);
        expect(gate.state, const AppGateOpen());
      },
    );

    blocTest<AppConfigCubit, AppConfigState>(
      'a minimum version above this build closes the gate (M03)',
      setUp: () => repository.answer = () async => _config(minAndroid: '1.1.0'),
      build: build,
      act: (cubit) => cubit.load(),
      verify: (_) => expect(gate.state, const AppGateUpdateRequired()),
    );

    blocTest<AppConfigCubit, AppConfigState>(
      'maintenance closes the gate and a later config reopens it (M04)',
      setUp: () => repository.answer = () async => _config(maintenance: true),
      build: build,
      act: (cubit) async {
        await cubit.load();
        expect(gate.state, const AppGateMaintenance('صيانة'));
        repository.answer = () async => _config();
        await cubit.retryGate();
      },
      verify: (_) => expect(gate.state, const AppGateOpen()),
    );

    blocTest<AppConfigCubit, AppConfigState>(
      'a failure keeps the cached config',
      setUp: () {
        repository.current = _config();
        repository.answer = () async =>
            throw const ApiException(code: ApiErrorCode.network);
      },
      build: build,
      act: (cubit) => cubit.load(),
      expect: () => [
        AppConfigLoading(_config()),
        AppConfigFailure(const ApiException(code: ApiErrorCode.network), _config()),
      ],
    );
  });

  group('ApiAppConfigRepository', () {
    test('persists the last answer for an offline start', () async {
      final preferences = InMemoryPreferencesStore();
      final api = ApiClient.create(
        env: testEnv(),
        tokens: InMemoryTokenStore(),
        clock: ServerClock(),
        languageCode: () => 'ar',
        onUnauthorized: () {},
        httpClientAdapter: fixtureAdapter({'GET /app-config': 'app_config'}),
      );
      final config = await ApiAppConfigRepository(api, preferences).fetch();
      expect(preferences.getString(PreferenceKeys.appConfig), isNotNull);
      final restored = ApiAppConfigRepository(api, preferences).current;
      expect(restored, config);
    });
  });

  group('NetworkStatusCubit', () {
    test('goes offline on a transport failure and probes back online', () {
      fakeAsync((async) {
        var probes = 0;
        late NetworkStatusCubit cubit;
        cubit = NetworkStatusCubit(
          probe: () async {
            probes++;
            if (probes >= 2) cubit.reportReachable();
          },
        );
        cubit.reportUnreachable();
        expect(cubit.state, NetworkStatus.offline);
        async.elapse(const Duration(seconds: 10));
        expect(cubit.state, NetworkStatus.offline);
        async.elapse(const Duration(seconds: 10));
        expect(cubit.state, NetworkStatus.online);
        async.elapse(const Duration(seconds: 30));
        expect(probes, 2);
        unawaited(cubit.close());
      });
    });

    test('a connected socket means online', () async {
      final realtime = StreamController<RealtimeConnectionState>();
      final cubit = NetworkStatusCubit(
        probe: () async {},
        realtime: realtime.stream,
      )..reportUnreachable();
      realtime.add(RealtimeConnectionState.connected);
      await pumpEventQueue();
      expect(cubit.state, NetworkStatus.online);
      await cubit.close();
      await realtime.close();
    });

    test('the interceptor reports answers and transport failures', () async {
      final cubit = NetworkStatusCubit(probe: () async {});
      final interceptor = NetworkStatusInterceptor(() => cubit);
      final options = RequestOptions(path: '/x');
      interceptor.onError(
        DioException(requestOptions: options, type: DioExceptionType.connectionError),
        _SilentHandler(),
      );
      expect(cubit.state, NetworkStatus.offline);
      interceptor.onError(
        DioException(
          requestOptions: options,
          type: DioExceptionType.badResponse,
          response: Response<Object?>(requestOptions: options, statusCode: 500),
        ),
        _SilentHandler(),
      );
      expect(cubit.state, NetworkStatus.online);
      await cubit.close();
    });
  });

  group('the account gate', () {
    test('403 account codes close it only for authenticated requests', () {
      final gate = AppGateCubit();
      final interceptor = AppGateInterceptor(gate);
      DioException error({required bool withToken}) {
        final options = RequestOptions(
          path: '/x',
          headers: {if (withToken) 'Authorization': 'Bearer t'},
        );
        return DioException(
          requestOptions: options,
          type: DioExceptionType.badResponse,
          response: Response<Object?>(
            requestOptions: options,
            statusCode: 403,
            data: {
              'message': 'm',
              'code': 'organization_suspended',
              'errors': <String, Object>{},
            },
          ),
        );
      }

      interceptor.onError(error(withToken: false), _SilentHandler());
      expect(gate.state, const AppGateOpen());
      interceptor.onError(error(withToken: true), _SilentHandler());
      expect(
        gate.state,
        const AppGateAccountBlocked(code: 'organization_suspended', message: 'm'),
      );
      unawaited(gate.close());
    });
  });

  group('DeepLinkRouter.map (S11)', () {
    test('maps the canonical routes', () {
      const id = '01m3q4e71m4tnkvsecsj2f351b';
      expect(DeepLinkRouter.map('/competitions/$id'), '/competitions/$id');
      expect(DeepLinkRouter.map('/competitions/$id/live'), '/competitions/$id/live');
      expect(DeepLinkRouter.map('/competitions/$id/qa'), '/competitions/$id/qa');
      expect(DeepLinkRouter.map('/billing'), '/billing');
      expect(
        DeepLinkRouter.map('/billing/invoices/$id'),
        '/billing?notice=invoices',
      );
      expect(DeepLinkRouter.map('/integrations'), '/home');
      expect(DeepLinkRouter.map('/integrations/webhooks'), '/home');
      expect(DeepLinkRouter.map('/notifications'), '/notifications');
    });

    test('unknown, malformed or external routes fall back to notifications', () {
      for (final route in [
        null,
        '',
        '/competitions',
        '/competitions/x/award',
        '/competitions/../../etc',
        'https://evil.example/competitions/1',
        '//evil.example',
        '/admin',
      ]) {
        expect(DeepLinkRouter.map(route), '/notifications', reason: route);
      }
    });

    test('every notification route of the demo seed maps', () {
      for (final name in ['notifications_supplier_a', 'notifications_issuer']) {
        for (final row in fixtureList(name)) {
          final route = row['route'] as String;
          expect(DeepLinkRouter.map(route), route, reason: route);
        }
      }
    });
  });
}
