import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/notifications/presentation/push_permission_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockPush extends Mock implements PushService {}

class _MockDevices extends Mock implements DevicesRepository {}

void main() {
  late _MockPush push;
  late _MockDevices devices;
  late InMemoryPreferencesStore preferences;
  const client = ClientInfo(platform: 'android', appVersion: '1.2.3');
  const device = Device(id: 'device-1', platform: 'android');

  setUp(() {
    push = _MockPush();
    devices = _MockDevices();
    preferences = InMemoryPreferencesStore();
  });

  PushPermissionCubit build() => PushPermissionCubit(
    push: push,
    devices: devices,
    preferences: preferences,
    client: client,
    deviceName: 'Pixel 8 · android',
  );

  group('PushPermissionCubit', () {
    test('starts enabled when a device is already registered', () {
      preferences.values[PreferenceKeys.pushDeviceId] = 'device-1';
      expect(build().state.status, PushSetupStatus.enabled);
      expect(build().shouldExplain, isFalse);
    });

    test('the explainer shows once per device', () async {
      final cubit = build();
      expect(cubit.shouldExplain, isTrue);
      await cubit.markExplained();
      expect(build().shouldExplain, isFalse);
    });

    blocTest<PushPermissionCubit, PushPermissionState>(
      'the no-op service (no Firebase) sends nothing: unavailable',
      setUp: () =>
          when(push.requestPermission)
              .thenAnswer((_) async => PushPermission.notDetermined),
      build: build,
      act: (cubit) => cubit.request(),
      expect: () => const [
        PushPermissionState(PushSetupStatus.requesting),
        PushPermissionState(PushSetupStatus.unavailable),
      ],
      verify: (_) => verifyZeroInteractions(devices),
    );

    blocTest<PushPermissionCubit, PushPermissionState>(
      'a refused OS prompt is denied',
      setUp: () =>
          when(push.requestPermission)
              .thenAnswer((_) async => PushPermission.denied),
      build: build,
      act: (cubit) => cubit.request(),
      expect: () => const [
        PushPermissionState(PushSetupStatus.requesting),
        PushPermissionState(PushSetupStatus.denied),
      ],
    );

    blocTest<PushPermissionCubit, PushPermissionState>(
      'granted with a token registers the device and keeps its id',
      setUp: () {
        when(push.requestPermission)
            .thenAnswer((_) async => PushPermission.granted);
        when(push.deviceToken).thenAnswer((_) async => 'fcm-token');
        when(
          () => devices.register(
            token: 'fcm-token',
            platform: 'android',
            deviceName: 'Pixel 8 · android',
            appVersion: '1.2.3',
          ),
        ).thenAnswer((_) async => device);
      },
      build: build,
      act: (cubit) => cubit.request(),
      expect: () => const [
        PushPermissionState(PushSetupStatus.requesting),
        PushPermissionState(PushSetupStatus.enabled),
      ],
      verify: (_) => expect(
        preferences.getString(PreferenceKeys.pushDeviceId),
        'device-1',
      ),
    );

    blocTest<PushPermissionCubit, PushPermissionState>(
      'granted without a token is unavailable',
      setUp: () {
        when(push.requestPermission)
            .thenAnswer((_) async => PushPermission.provisional);
        when(push.deviceToken).thenAnswer((_) async => null);
      },
      build: build,
      act: (cubit) => cubit.request(),
      expect: () => const [
        PushPermissionState(PushSetupStatus.requesting),
        PushPermissionState(PushSetupStatus.unavailable),
      ],
      verify: (_) => verifyZeroInteractions(devices),
    );

    blocTest<PushPermissionCubit, PushPermissionState>(
      'a failed registration is reported and can be retried',
      setUp: () {
        when(push.requestPermission)
            .thenAnswer((_) async => PushPermission.granted);
        when(push.deviceToken).thenAnswer((_) async => 'fcm-token');
        when(
          () => devices.register(
            token: any(named: 'token'),
            platform: any(named: 'platform'),
            deviceName: any(named: 'deviceName'),
            appVersion: any(named: 'appVersion'),
          ),
        ).thenThrow(const ApiException(code: ApiErrorCode.network));
      },
      build: build,
      act: (cubit) => cubit.request(),
      expect: () => const [
        PushPermissionState(PushSetupStatus.requesting),
        PushPermissionState(
          PushSetupStatus.failed,
          error: ApiException(code: ApiErrorCode.network),
        ),
      ],
      verify: (_) =>
          expect(preferences.getString(PreferenceKeys.pushDeviceId), isNull),
    );
  });
}
