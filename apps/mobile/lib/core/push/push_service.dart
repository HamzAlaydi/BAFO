import 'package:equatable/equatable.dart';

/// Result of asking the user for notification permission.
enum PushPermission { granted, provisional, denied, notDetermined }

/// A received push notification.
final class PushMessage extends Equatable {
  const PushMessage({this.title, this.body, this.data = const {}});

  final String? title;
  final String? body;

  /// Routing payload, e.g. `{type: offer_outbid, competition_id: ...}`.
  final Map<String, String> data;

  @override
  List<Object?> get props => [title, body, data];
}

/// Push notifications, behind an interface so the app runs without Firebase.
///
/// Today only [NoopPushService] exists (no Firebase project yet). A
/// `firebase_messaging` implementation will register the device token with
/// the API and map FCM messages to [PushMessage].
abstract interface class PushService {
  Future<void> initialize();

  /// Asks the OS for permission. Call it in context (after an explanation),
  /// not at app start.
  Future<PushPermission> requestPermission();

  /// Device token to register with the API, or null if unavailable.
  Future<String?> deviceToken();

  /// Messages received while the app is in the foreground.
  Stream<PushMessage> get onMessage;

  /// Notifications the user tapped to open the app.
  Stream<PushMessage> get onMessageOpenedApp;
}

/// [PushService] that does nothing. Used until Firebase is configured.
final class NoopPushService implements PushService {
  const NoopPushService();

  @override
  Future<void> initialize() async {}

  @override
  Future<PushPermission> requestPermission() async =>
      PushPermission.notDetermined;

  @override
  Future<String?> deviceToken() async => null;

  @override
  Stream<PushMessage> get onMessage => const Stream.empty();

  @override
  Stream<PushMessage> get onMessageOpenedApp => const Stream.empty();
}
