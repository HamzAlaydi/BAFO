import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';

/// In-app notifications (API.md §1.8). Every method throws `ApiException`.
abstract interface class NotificationsRepository {
  /// `GET /notifications?unread=&page=`, newest first, with the unread count.
  Future<NotificationPage> list({
    bool unreadOnly = false,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  });

  /// `GET /notifications/unread-count`.
  Future<int> unreadCount();

  /// `POST /notifications/{id}/read`.
  Future<AppNotification> markRead(String notificationId);

  /// `POST /notifications/read-all` → the new unread count (0).
  Future<int> markAllRead();

  /// `DELETE /notifications/{id}`.
  Future<void> delete(String notificationId);

  /// `DELETE /notifications`.
  Future<void> deleteAll();
}

final class ApiNotificationsRepository implements NotificationsRepository {
  ApiNotificationsRepository(this._api);

  final ApiClient _api;

  @override
  Future<NotificationPage> list({
    bool unreadOnly = false,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async {
    final response = await _api.get(
      'notifications',
      query: {if (unreadOnly) 'unread': 1, ...pageQuery(page, perPage)},
    );
    final unread = response.meta['unread_count'];
    return NotificationPage(
      page: Paged.fromResponse(response, AppNotification.fromJson),
      unreadCount: unread is int ? unread : 0,
    );
  }

  @override
  Future<int> unreadCount() async =>
      (await _api.get('notifications/unread-count')).dataMap
          .intOrNull('unread_count') ??
      0;

  @override
  Future<AppNotification> markRead(String notificationId) async =>
      AppNotification.fromJson(
        (await _api.post('notifications/$notificationId/read')).dataMap,
      );

  @override
  Future<int> markAllRead() async =>
      (await _api.post('notifications/read-all')).dataMap
          .intOrNull('unread_count') ??
      0;

  @override
  Future<void> delete(String notificationId) =>
      _api.delete('notifications/$notificationId');

  @override
  Future<void> deleteAll() => _api.delete('notifications');
}

/// Push devices (API.md §1.8). With `NoopPushService` there is no token, so
/// nothing is registered.
abstract interface class DevicesRepository {
  /// `POST /devices` (upsert by token).
  Future<Device> register({
    required String token,
    required String platform,
    String? deviceName,
    String? appVersion,
  });

  /// `DELETE /devices/{id}` (sign-out).
  Future<void> unregister(String deviceId);
}

final class ApiDevicesRepository implements DevicesRepository {
  ApiDevicesRepository(this._api);

  final ApiClient _api;

  @override
  Future<Device> register({
    required String token,
    required String platform,
    String? deviceName,
    String? appVersion,
  }) async => Device.fromJson(
    (await _api.post(
      'devices',
      body: {
        'token': token,
        'platform': platform,
        'device_name': deviceName,
        'app_version': appVersion,
      },
    )).dataMap,
  );

  @override
  Future<void> unregister(String deviceId) => _api.delete('devices/$deviceId');
}
