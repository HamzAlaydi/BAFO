import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Where push notifications stand on this device.
enum PushSetupStatus {
  /// Never requested here (or not registered yet).
  off,

  /// The OS prompt or the device registration is running.
  requesting,

  /// The device is registered with `POST /devices`.
  enabled,

  /// The user refused the OS permission.
  denied,

  /// No prompt or no device token: push is not available on this build
  /// (today: `NoopPushService`, no Firebase project yet).
  unavailable,

  /// The permission was granted but `POST /devices` failed.
  failed,
}

final class PushPermissionState extends Equatable {
  const PushPermissionState(this.status, {this.error});

  final PushSetupStatus status;
  final ApiException? error;

  @override
  List<Object?> get props => [status, error];
}

/// M50 and the push row of M59: asks for the notification permission in
/// context (after the first join or publish, or from settings; never at cold
/// start, CONVENTIONS.md §5.1) and registers the device token.
///
/// With `NoopPushService` there is no permission prompt and no token, so no
/// request is sent and the status is [PushSetupStatus.unavailable].
class PushPermissionCubit extends Cubit<PushPermissionState> {
  PushPermissionCubit({
    required this._push,
    required this._devices,
    required this._preferences,
    required this._client,
    this._deviceName,
  }) : super(
         PushPermissionState(
           (_preferences.getString(PreferenceKeys.pushDeviceId) ?? '').isEmpty
               ? PushSetupStatus.off
               : PushSetupStatus.enabled,
         ),
       );

  final PushService _push;
  final DevicesRepository _devices;
  final PreferencesStore _preferences;
  final ClientInfo _client;

  /// `device_name` of `POST /devices`, when known.
  final String? _deviceName;

  /// Whether the explainer (M50) should show: once per device, and never
  /// when push is already on.
  bool get shouldExplain =>
      state.status != PushSetupStatus.enabled &&
      (_preferences.getString(PreferenceKeys.pushExplainerShown) ?? '').isEmpty;

  /// Remembers that the explainer was shown (whatever the answer).
  Future<void> markExplained() =>
      _preferences.setString(PreferenceKeys.pushExplainerShown, '1');

  /// The OS prompt, then `POST /devices {token, platform, device_name,
  /// app_version}`; the device id is kept for the sign-out cleanup.
  Future<void> request() async {
    if (state.status == PushSetupStatus.requesting) return;
    emit(const PushPermissionState(PushSetupStatus.requesting));
    final permission = await _push.requestPermission();
    switch (permission) {
      case PushPermission.denied:
        emit(const PushPermissionState(PushSetupStatus.denied));
        return;
      case PushPermission.notDetermined:
        emit(const PushPermissionState(PushSetupStatus.unavailable));
        return;
      case PushPermission.granted:
      case PushPermission.provisional:
        break;
    }
    final token = await _push.deviceToken();
    if (token == null || token.isEmpty) {
      emit(const PushPermissionState(PushSetupStatus.unavailable));
      return;
    }
    try {
      final device = await _devices.register(
        token: token,
        platform: _client.platform,
        deviceName: _deviceName,
        appVersion: _client.appVersion,
      );
      await _preferences.setString(PreferenceKeys.pushDeviceId, device.id);
      emit(const PushPermissionState(PushSetupStatus.enabled));
    } on ApiException catch (error) {
      emit(PushPermissionState(PushSetupStatus.failed, error: error));
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(PushPermissionState state) {
    if (!isClosed) super.emit(state);
  }
}
