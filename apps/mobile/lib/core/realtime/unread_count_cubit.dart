import 'dart:async';

import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// The unread-notifications badge of the shell (SCREENS.md §3.4).
///
/// Started with the signed-in user: it seeds from
/// `me.unread_notifications_count`, then follows the private user channel
/// (`notification.created` carries `unread_count`; `notifications.unread_count`
/// arrives after read, read-all and delete) and [refresh] (`GET
/// /notifications/unread-count`) on resume.
///
/// [notificationCreated] re-publishes the raw `notification.created` payloads
/// (`{notification, unread_count}`) so the notifications list and home can
/// react without subscribing to the user channel themselves.
class UnreadCountCubit extends Cubit<int> {
  UnreadCountCubit({required this._realtime, required this._fetchCount})
    : super(0);

  final RealtimeClient _realtime;
  final Future<int> Function() _fetchCount;
  final StreamController<Map<String, dynamic>> _created =
      StreamController.broadcast();

  RealtimeSubscription? _subscription;
  StreamSubscription<RealtimeEvent>? _events;
  String? _userId;

  Stream<Map<String, dynamic>> get notificationCreated => _created.stream;

  /// Follows [userId]'s channel. Calling it again for the same user only
  /// updates the count.
  void start({required String userId, required int initialCount}) {
    if (isClosed) return;
    emit(initialCount);
    if (_userId == userId) return;
    unawaited(_unsubscribe());
    _userId = userId;
    final subscription = _realtime.subscribePrivate(
      RealtimeChannels.user(userId),
    );
    _subscription = subscription;
    _events = subscription.events().listen(_onEvent);
  }

  void _onEvent(RealtimeEvent event) {
    final count = event.data['unread_count'];
    switch (event.name) {
      case RealtimeEvents.notificationCreated:
        if (!_created.isClosed) _created.add(event.data);
        if (count is int) _set(count);
      case RealtimeEvents.unreadCount:
        if (count is int) _set(count);
    }
  }

  /// Re-reads the count from the API (resume, pull to refresh).
  Future<void> refresh() async {
    if (_userId == null) return;
    try {
      _set(await _fetchCount());
    } on Object catch (error) {
      debugPrint('Unread count refresh failed: $error');
    }
  }

  /// A local change the server will confirm (e.g. after marking one read).
  void set(int count) => _set(count);

  void _set(int count) {
    if (!isClosed) emit(count < 0 ? 0 : count);
  }

  /// Signed out: forget the user.
  Future<void> stop() async {
    await _unsubscribe();
    _userId = null;
    _set(0);
  }

  Future<void> _unsubscribe() async {
    await _events?.cancel();
    _events = null;
    await _subscription?.cancel();
    _subscription = null;
  }

  @override
  Future<void> close() async {
    await _unsubscribe();
    await _created.close();
    return super.close();
  }
}
