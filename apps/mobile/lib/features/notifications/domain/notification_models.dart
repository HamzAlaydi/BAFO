import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:equatable/equatable.dart';

/// An in-app notification (API.md §2.11). [title] and [body] come rendered
/// in the request language; [route] is a locale-free client route mapped by
/// `DeepLinkRouter`. Notifications never carry amounts.
final class AppNotification extends Equatable {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.createdAt,
    this.route,
    this.subjectType,
    this.subjectId,
    this.params = const {},
    this.readAt,
  });

  factory AppNotification.fromJson(Json json) {
    final subject = json.objOrNull('subject') ?? const {};
    return AppNotification(
      id: json.str('id'),
      type: json.str('type'),
      title: json.str('title'),
      body: json.strOrNull('body') ?? '',
      subjectType: subject.strOrNull('type'),
      subjectId: subject.strOrNull('id'),
      route: json.strOrNull('route'),
      params: json.looseMap('params'),
      readAt: json.dateOrNull('read_at'),
      createdAt: json.date('created_at'),
    );
  }

  final String id;

  /// e.g. `competition.invited`, `standing.lost_lead` (ARCHITECTURE.md §11.3).
  final String type;
  final String title;
  final String body;
  final String? subjectType;
  final String? subjectId;
  final String? route;
  final Map<String, Object?> params;
  final DateTime? readAt;
  final DateTime createdAt;

  bool get isRead => readAt != null;

  AppNotification markedRead(DateTime at) => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    subjectType: subjectType,
    subjectId: subjectId,
    route: route,
    params: params,
    readAt: readAt ?? at,
    createdAt: createdAt,
  );

  @override
  List<Object?> get props => [
    id,
    type,
    title,
    body,
    subjectType,
    subjectId,
    route,
    params,
    readAt,
    createdAt,
  ];
}

/// A page of notifications plus `meta.unread_count`.
final class NotificationPage extends Equatable {
  const NotificationPage({required this.page, required this.unreadCount});

  final Paged<AppNotification> page;
  final int unreadCount;

  @override
  List<Object?> get props => [page, unreadCount];
}

/// A registered push device (API.md §2.11).
final class Device extends Equatable {
  const Device({
    required this.id,
    required this.platform,
    this.deviceName,
    this.appVersion,
    this.lastSeenAt,
  });

  factory Device.fromJson(Json json) => Device(
    id: json.str('id'),
    platform: json.str('platform'),
    deviceName: json.strOrNull('device_name'),
    appVersion: json.strOrNull('app_version'),
    lastSeenAt: json.dateOrNull('last_seen_at'),
  );

  final String id;
  final String platform;
  final String? deviceName;
  final String? appVersion;
  final DateTime? lastSeenAt;

  @override
  List<Object?> get props => [id, platform, deviceName, appVersion, lastSeenAt];
}
