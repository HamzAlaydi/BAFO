import 'package:bafo/core/config/env.dart';
import 'package:equatable/equatable.dart';

/// Connection lifecycle of a [RealtimeClient].
enum RealtimeConnectionState { disconnected, connecting, connected, failed }

/// A server event received on a channel.
final class RealtimeEvent extends Equatable {
  const RealtimeEvent({
    required this.channel,
    required this.name,
    required this.data,
  });

  /// Channel name without the `private-` prefix, e.g. `competition.01J...`.
  final String channel;

  /// Broadcast name (Laravel `broadcastAs()`), e.g. `live.updated`.
  final String name;

  /// Decoded JSON payload; empty when the payload is not an object.
  final Map<String, dynamic> data;

  @override
  List<Object?> get props => [channel, name, data];
}

/// Broadcast names (API.md §5).
abstract final class RealtimeEvents {
  static const String liveUpdated = 'live.updated';
  static const String offerAccepted = 'offer.accepted';
  static const String competitionUpdated = 'competition.updated';
  static const String commentCreated = 'comment.created';
  static const String invitationUpdated = 'invitation.updated';
  static const String notificationCreated = 'notification.created';
  static const String unreadCount = 'notifications.unread_count';
}

/// Private channel names (ARCHITECTURE.md §9.2), without `private-`.
abstract final class RealtimeChannels {
  /// Issuer side of a competition.
  static String competition(String competitionId) =>
      'competition.$competitionId';

  /// One participant organisation's view of a competition.
  static String participant(String competitionId, String organizationId) =>
      'competition.$competitionId.participant.$organizationId';

  /// One user's notifications.
  static String user(String userId) => 'user.$userId';
}

/// A live subscription to one private channel.
abstract interface class RealtimeSubscription {
  /// Channel name without the `private-` prefix.
  String get channel;

  /// Events on this channel; only [name] when given. Internal
  /// `pusher:*` / `pusher_internal:*` events are filtered out. The stream
  /// survives reconnects, reconfiguration and [RealtimeClient.suspend].
  Stream<RealtimeEvent> events([String? name]);

  /// Leaves the channel (the last subscriber unsubscribes).
  Future<void> cancel();
}

/// Realtime transport (Laravel Reverb over the Pusher protocol).
///
/// Only private channels exist in BAFO (BRIEF "Realtime"); see
/// [RealtimeChannels]. Channel names are given without the `private-`
/// prefix, as in Laravel.
abstract interface class RealtimeClient {
  Stream<RealtimeConnectionState> get connectionState;

  RealtimeConnectionState get currentState;

  /// Applies the server settings (from `GET /app-config`). A change
  /// reconnects and re-subscribes every channel.
  void configure(RealtimeConfig config);

  /// Subscribes to a private channel, connecting first if needed.
  RealtimeSubscription subscribePrivate(String channel);

  /// Closes the socket but keeps every subscription (app in background).
  Future<void> suspend();

  /// Reconnects after [suspend] and re-subscribes.
  void resume();

  /// Drops the connection and every subscription (sign-out).
  Future<void> disconnect();

  Future<void> dispose();
}
