import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/notifications/presentation/notifications_bloc.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockNotifications extends Mock implements NotificationsRepository {}

void main() {
  late _MockNotifications repository;
  late List<int> published;
  final at = DateTime.utc(2026, 10, 1, 9);

  final all = fixtureList('notifications_supplier_a')
      .map(AppNotification.fromJson)
      .toList();
  final first = all.first;
  final second = all[1];
  const offline = ApiException(code: ApiErrorCode.network);

  NotificationPage page(
    List<AppNotification> items, {
    int number = 1,
    bool hasMore = false,
    int unread = 2,
  }) => NotificationPage(
    page: Paged(
      items: items,
      meta: PageMeta(currentPage: number, perPage: 20, hasMore: hasMore),
    ),
    unreadCount: unread,
  );

  NotificationsLoaded loaded(
    List<AppNotification> items, {
    NotificationsFilter filter = NotificationsFilter.all,
    int unread = 2,
    bool hasMore = false,
    int number = 1,
  }) => NotificationsLoaded(
    filter,
    items: items,
    page: number,
    hasMore: hasMore,
    unreadCount: unread,
  );

  NotificationsBloc build({Stream<Map<String, dynamic>>? created}) =>
      NotificationsBloc(
        repository: repository,
        created: created,
        onUnreadCount: published.add,
        now: () => at,
      );

  setUp(() {
    repository = _MockNotifications();
    published = [];
  });

  group('NotificationsBloc', () {
    blocTest<NotificationsBloc, NotificationsState>(
      'loads page 1 and publishes the unread count to the badge',
      setUp: () => when(() => repository.list()).thenAnswer(
        (_) async => page(all.take(2).toList(), hasMore: true, unread: 32),
      ),
      build: build,
      act: (bloc) => bloc.add(const NotificationsStarted()),
      expect: () => [
        const NotificationsLoading(NotificationsFilter.all),
        loaded(all.take(2).toList(), unread: 32, hasMore: true),
      ],
      verify: (_) => expect(published, [32]),
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'a failed load is an error state',
      setUp: () => when(() => repository.list()).thenThrow(offline),
      build: build,
      act: (bloc) => bloc.add(const NotificationsStarted()),
      expect: () => [
        const NotificationsLoading(NotificationsFilter.all),
        const NotificationsFailure(NotificationsFilter.all, offline),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'the unread filter reloads with unread=1',
      setUp: () =>
          when(() => repository.list(unreadOnly: true))
              .thenAnswer((_) async => page([first])),
      build: build,
      seed: () => loaded(all.take(2).toList()),
      act: (bloc) => bloc.add(
        const NotificationsFilterChanged(NotificationsFilter.unread),
      ),
      expect: () => [
        const NotificationsLoading(NotificationsFilter.unread),
        loaded([first], filter: NotificationsFilter.unread),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'the next page appends without repeating rows',
      setUp: () =>
          when(() => repository.list(page: 2))
              .thenAnswer((_) async => page([second, all[2]], number: 2)),
      build: build,
      seed: () => loaded([first, second], hasMore: true),
      act: (bloc) => bloc.add(const NotificationsNextPageRequested()),
      expect: () => [
        loaded([first, second], hasMore: true).copyWith(loadingMore: true),
        loaded([first, second, all[2]], number: 2),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'a failed next page keeps the rows and offers a retry',
      setUp: () => when(() => repository.list(page: 2)).thenThrow(offline),
      build: build,
      seed: () => loaded([first], hasMore: true),
      act: (bloc) => bloc.add(const NotificationsNextPageRequested()),
      expect: () => [
        loaded([first], hasMore: true).copyWith(loadingMore: true),
        loaded([first], hasMore: true).copyWith(pageError: () => offline),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'notification.created prepends a new row once',
      build: () {
        final bloc = build();
        return bloc;
      },
      seed: () => loaded([second], unread: 1),
      act: (bloc) {
        final payload = {
          'notification': fixtureList('notifications_supplier_a').first,
          'unread_count': 2,
        };
        bloc
          ..add(NotificationsReceived(payload))
          ..add(NotificationsReceived(payload));
      },
      expect: () => [
        loaded([first, second]),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'opening an unread one marks it read at once, then confirms',
      setUp: () =>
          when(() => repository.markRead(first.id))
              .thenAnswer((_) async => first.markedRead(at)),
      build: build,
      seed: () => loaded([first, second]),
      act: (bloc) => bloc.add(NotificationOpened(first)),
      expect: () => [
        loaded([first.markedRead(at), second], unread: 1),
      ],
      verify: (_) {
        verify(() => repository.markRead(first.id)).called(1);
        expect(published, [1]);
      },
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'a refused mark-read is undone and reported',
      setUp: () => when(() => repository.markRead(first.id)).thenThrow(offline),
      build: build,
      seed: () => loaded([first, second]),
      act: (bloc) => bloc.add(NotificationMarkedRead(first.id)),
      expect: () => [
        loaded([first.markedRead(at), second], unread: 1),
        isA<NotificationsLoaded>()
            .having((s) => s.items, 'items', [first, second])
            .having((s) => s.unreadCount, 'unread', 2)
            .having(
              (s) => s.outcome?.kind,
              'outcome',
              NotificationsOutcomeKind.failed,
            ),
      ],
      verify: (_) => expect(published, [1, 2]),
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'a read notification is not marked again',
      build: build,
      seed: () => loaded([first.markedRead(at)], unread: 0),
      act: (bloc) => bloc.add(NotificationOpened(first.markedRead(at))),
      expect: () => <NotificationsState>[],
      verify: (_) => verifyNever(() => repository.markRead(any())),
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'deleting removes the row, then confirms',
      setUp: () =>
          when(() => repository.delete(first.id)).thenAnswer((_) async {}),
      build: build,
      seed: () => loaded([first, second]),
      act: (bloc) => bloc.add(NotificationDeleted(first.id)),
      expect: () => [
        loaded([second], unread: 1),
        isA<NotificationsLoaded>().having(
          (s) => s.outcome?.kind,
          'outcome',
          NotificationsOutcomeKind.deleted,
        ),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'a refused delete puts the row back where it was',
      setUp: () => when(() => repository.delete(second.id)).thenThrow(offline),
      build: build,
      seed: () => loaded([first, second, all[2]]),
      act: (bloc) => bloc.add(NotificationDeleted(second.id)),
      expect: () => [
        loaded([first, all[2]], unread: 1),
        isA<NotificationsLoaded>()
            .having((s) => s.items, 'items', [first, second, all[2]])
            .having((s) => s.unreadCount, 'unread', 2),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'mark all read waits for the server',
      setUp: () => when(repository.markAllRead).thenAnswer((_) async => 0),
      build: build,
      seed: () => loaded([first, second]),
      act: (bloc) => bloc.add(const NotificationsAllMarkedRead()),
      expect: () => [
        loaded([first, second]).copyWith(busy: true),
        isA<NotificationsLoaded>()
            .having((s) => s.items.every((n) => n.isRead), 'all read', isTrue)
            .having((s) => s.unreadCount, 'unread', 0)
            .having((s) => s.busy, 'busy', isFalse),
      ],
      verify: (_) => expect(published, [0]),
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'delete all clears the list after the server answers',
      setUp: () => when(repository.deleteAll).thenAnswer((_) async {}),
      build: build,
      seed: () => loaded([first, second], hasMore: true),
      act: (bloc) => bloc.add(const NotificationsAllDeleted()),
      expect: () => [
        loaded([first, second], hasMore: true).copyWith(busy: true),
        isA<NotificationsLoaded>()
            .having((s) => s.items, 'items', isEmpty)
            .having((s) => s.hasMore, 'hasMore', isFalse)
            .having(
              (s) => s.outcome?.kind,
              'outcome',
              NotificationsOutcomeKind.allDeleted,
            ),
      ],
    );

    blocTest<NotificationsBloc, NotificationsState>(
      'pull to refresh keeps the rows and completes',
      setUp: () =>
          when(() => repository.list())
              .thenAnswer((_) async => page([first, second], unread: 5)),
      build: build,
      seed: () => loaded([second]),
      act: (bloc) async {
        final done = Completer<void>();
        bloc.add(NotificationsRefreshed(done));
        await done.future;
      },
      expect: () => [
        loaded([first, second], unread: 5),
      ],
    );

    test('follows notification.created from the user channel', () async {
      final created = StreamController<Map<String, dynamic>>.broadcast();
      final bloc = build(created: created.stream);
      when(() => repository.list()).thenAnswer((_) async => page([second]));
      bloc.add(const NotificationsStarted());
      await pumpEventQueue();
      created.add({
        'notification': fixtureList('notifications_supplier_a').first,
        'unread_count': 7,
      });
      await pumpEventQueue();
      final state = bloc.state as NotificationsLoaded;
      expect(state.items.map((n) => n.id), [first.id, second.id]);
      expect(state.unreadCount, 7);
      await bloc.close();
      await created.close();
    });
  });
}
