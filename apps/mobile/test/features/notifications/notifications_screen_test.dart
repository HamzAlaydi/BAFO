import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/notifications/presentation/notifications_screen.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fakes.dart';
import '../account/support.dart';

/// An in-memory `/notifications` over the Supplier A fixture.
class _InMemoryNotifications implements NotificationsRepository {
  _InMemoryNotifications(this.items);

  List<AppNotification> items;
  final List<String> readCalls = [];
  final List<String> deleted = [];

  int get _unread => items.where((n) => !n.isRead).length;

  @override
  Future<NotificationPage> list({
    bool unreadOnly = false,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async {
    final rows = unreadOnly ? items.where((n) => !n.isRead).toList() : items;
    return NotificationPage(
      page: Paged(
        items: rows.skip((page - 1) * perPage).take(perPage).toList(),
        meta: PageMeta(
          currentPage: page,
          perPage: perPage,
          hasMore: rows.length > page * perPage,
        ),
      ),
      unreadCount: _unread,
    );
  }

  @override
  Future<int> unreadCount() async => _unread;

  @override
  Future<AppNotification> markRead(String notificationId) async {
    readCalls.add(notificationId);
    final at = DateTime.utc(2026, 10, 1);
    items = [
      for (final n in items) n.id == notificationId ? n.markedRead(at) : n,
    ];
    return items.firstWhere((n) => n.id == notificationId);
  }

  @override
  Future<int> markAllRead() async {
    final at = DateTime.utc(2026, 10, 1);
    items = [for (final n in items) n.markedRead(at)];
    return 0;
  }

  @override
  Future<void> delete(String notificationId) async {
    deleted.add(notificationId);
    items = items.where((n) => n.id != notificationId).toList();
  }

  @override
  Future<void> deleteAll() async => items = [];
}

void main() {
  late SessionCubit session;
  late UnreadCountCubit unread;
  late _InMemoryNotifications repository;
  final fixture = fixtureList('notifications_supplier_a')
      .map(AppNotification.fromJson)
      .toList();

  setUp(() async {
    session = await signedInSession(fixtureMe());
    unread = testUnreadCount();
    // Five rows: the first unread, the rest read.
    final at = DateTime.utc(2026, 9, 29, 18);
    repository = _InMemoryNotifications([
      fixture.first,
      for (final n in fixture.skip(1).take(4)) n.markedRead(at),
    ]);
  });

  tearDown(() async {
    await session.close();
    await unread.close();
  });

  Future<GoRouter> pumpNotifications(WidgetTester tester) async {
    final router = await pumpFeature(
      tester,
      initialLocation: '/notifications',
      routes: [
        GoRoute(
          path: '/notifications',
          builder: (_, _) => const NotificationsScreen(),
        ),
        GoRoute(
          path: '/competitions/:id/live',
          builder: (_, state) =>
              Scaffold(body: Text('live ${state.pathParameters['id']}')),
        ),
      ],
      providers: (router) => [
        RepositoryProvider<NotificationsRepository>.value(value: repository),
        BlocProvider<SessionCubit>.value(value: session),
        BlocProvider<UnreadCountCubit>.value(value: unread),
        RepositoryProvider<DeepLinkRouter>.value(
          value: DeepLinkRouter(
            router: router,
            session: session,
            markRead: repository.markRead,
          ),
        ),
      ],
    );
    await tester.pumpAndSettle();
    return router;
  }

  testWidgets('lists the notifications with the unread count', (tester) async {
    await pumpNotifications(tester);

    expect(find.text(fixture.first.title), findsWidgets);
    expect(find.text('غير المقروءة (1)'), findsOneWidget);
    expect(find.byKey(const Key('notification.unreadDot')), findsOneWidget);
    expect(unread.state, 1);
  });

  testWidgets('tapping opens the S11 route and marks it read once', (
    tester,
  ) async {
    await pumpNotifications(tester);

    await tester.tap(find.text(fixture.first.body));
    await tester.pumpAndSettle();

    // competition.opened → /competitions/{id}/live.
    expect(find.text('live 01m3q4e7myd08bs5aeeywrp8ah'), findsOneWidget);
    expect(repository.readCalls, [fixture.first.id]);
    expect(unread.state, 0);
  });

  testWidgets('the unread filter has its own empty state', (tester) async {
    await repository.markAllRead();
    await pumpNotifications(tester);

    await tester.tap(find.text('غير المقروءة'));
    await tester.pumpAndSettle();
    expect(find.text('لا توجد إشعارات غير مقروءة'), findsOneWidget);
  });

  testWidgets('mark all read waits for the server, then clears the badge', (
    tester,
  ) async {
    await pumpNotifications(tester);

    await tester.tap(find.byKey(const Key('notifications.markAllRead')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('notification.unreadDot')), findsNothing);
    expect(find.text('عُلّمت جميع الإشعارات كمقروءة.'), findsOneWidget);
    expect(unread.state, 0);
  });

  testWidgets('swiping towards the start edge deletes one', (tester) async {
    await pumpNotifications(tester);
    final row = find.byKey(ValueKey(fixture.first.id));

    // RTL: the start edge is on the right, so swipe to the right.
    await tester.drag(row, const Offset(600, 0));
    await tester.pumpAndSettle();
    expect(repository.deleted, [fixture.first.id]);
    expect(find.byKey(ValueKey(fixture.first.id)), findsNothing);
    expect(find.text('حُذف الإشعار.'), findsOneWidget);
    expect(unread.state, 0);
  });

  testWidgets('delete all asks first, with a red button that names it', (
    tester,
  ) async {
    await pumpNotifications(tester);

    await tester.tap(find.byKey(const Key('notifications.menu')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('notifications.deleteAll')));
    await tester.pumpAndSettle();
    expect(find.text('حذف كل الإشعارات؟'), findsOneWidget);

    await tester.tap(find.text('حذف كل الإشعارات').last);
    await tester.pumpAndSettle();
    expect(find.text('لا توجد إشعارات'), findsOneWidget);
    expect(repository.items, isEmpty);
  });

  testWidgets('screen readers get the row actions', (tester) async {
    final handle = tester.ensureSemantics();
    await pumpNotifications(tester);

    final semantics = tester.getSemantics(
      find
          .descendant(
            of: find.byKey(ValueKey(fixture.first.id)),
            matching: find.byType(Semantics),
          )
          .first,
    );
    expect(semantics.label, startsWith('غير مقروء'));
    handle.dispose();
  });
}
