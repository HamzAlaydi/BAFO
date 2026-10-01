import 'dart:async';

import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

/// Maps notification routes to app locations and opens them (SCREENS.md S11,
/// §3.6).
///
/// A notification's `route` is locale-free (ARCHITECTURE.md §11.1). Mobile
/// maps it with one table; unknown routes fall back to the notifications
/// list.
class DeepLinkRouter {
  DeepLinkRouter({
    required this._router,
    required this._session,
    required this._markRead,
  });

  final GoRouter _router;
  final SessionCubit _session;
  final Future<void> Function(String notificationId) _markRead;

  /// Query flag on `/billing` that highlights "invoices are on the web".
  static const String invoicesNotice = 'invoices';

  static final RegExp _id = RegExp(r'^[A-Za-z0-9]{1,64}$');

  /// The app location for a server [route] (S11):
  ///
  /// | route                        | app location                  |
  /// |------------------------------|-------------------------------|
  /// | `/competitions/{id}`         | `/competitions/{id}`          |
  /// | `/competitions/{id}/live`    | `/competitions/{id}/live`     |
  /// | `/competitions/{id}/qa`      | `/competitions/{id}/qa`       |
  /// | `/billing`                   | `/billing`                    |
  /// | `/billing/invoices/{id}`     | `/billing?notice=invoices`    |
  /// | `/integrations`              | `/home` (web only)            |
  /// | `/notifications`             | `/notifications`              |
  /// | anything else                | `/notifications`              |
  static String map(String? route) {
    final uri = route == null ? null : Uri.tryParse(route.trim());
    if (uri == null || uri.hasScheme || uri.hasAuthority) {
      return '/notifications';
    }
    final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
    switch (segments) {
      case ['competitions', final id] when _id.hasMatch(id):
        return '/competitions/$id';
      case ['competitions', final id, final tab]
          when _id.hasMatch(id) && (tab == 'live' || tab == 'qa'):
        return '/competitions/$id/$tab';
      case ['billing']:
        return '/billing';
      case ['billing', 'invoices', final id] when _id.hasMatch(id):
        return '/billing?notice=$invoicesNotice';
      case ['integrations', ...]:
        return '/home';
      case ['notifications']:
        return '/notifications';
      default:
        return '/notifications';
    }
  }

  /// Opens [route]: marks the notification read (fire and forget), then
  /// pushes the mapped location. Signed out (or still restoring), the
  /// location goes through the auth redirect and is restored after sign-in
  /// (`from`).
  Future<void> open(String? route, {String? notificationId}) async {
    final location = map(route);
    final id = notificationId;
    if (_session.state.isAuthenticated) {
      if (id != null && id.isNotEmpty) {
        unawaited(
          _markRead(id).catchError((Object error) {
            debugPrint('Marking $id read failed: $error');
          }),
        );
      }
      unawaited(_router.push(location));
    } else {
      _router.go(location);
    }
  }

  /// A tapped push notification (`data.route`, `data.notification_id`).
  Future<void> openPush(PushMessage message) => open(
    message.data['route'],
    notificationId: message.data['notification_id'],
  );
}
