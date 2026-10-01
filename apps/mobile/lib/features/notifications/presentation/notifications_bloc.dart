import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum NotificationsFilter { all, unread }

// ---------------------------------------------------------------- events

sealed class NotificationsEvent extends Equatable {
  const NotificationsEvent();

  @override
  List<Object?> get props => [];
}

/// The screen opened: load page 1 of the current filter.
final class NotificationsStarted extends NotificationsEvent {
  const NotificationsStarted();
}

final class NotificationsFilterChanged extends NotificationsEvent {
  const NotificationsFilterChanged(this.filter);

  final NotificationsFilter filter;

  @override
  List<Object?> get props => [filter];
}

/// Pull to refresh. [done] completes when the reload ends.
final class NotificationsRefreshed extends NotificationsEvent {
  const NotificationsRefreshed([this.done]);

  final Completer<void>? done;

  @override
  List<Object?> get props => [done];
}

/// Infinite scroll reached the end of the loaded rows.
final class NotificationsNextPageRequested extends NotificationsEvent {
  const NotificationsNextPageRequested();
}

/// `notification.created` on the user channel: `{notification, unread_count}`.
final class NotificationsReceived extends NotificationsEvent {
  const NotificationsReceived(this.payload);

  final Map<String, dynamic> payload;

  @override
  List<Object?> get props => [payload];
}

/// The user opened [notification] (the screen then follows its route). An
/// unread one is marked read (CD9 allows it optimistically).
final class NotificationOpened extends NotificationsEvent {
  const NotificationOpened(this.notification);

  final AppNotification notification;

  @override
  List<Object?> get props => [notification];
}

final class NotificationMarkedRead extends NotificationsEvent {
  const NotificationMarkedRead(this.notificationId);

  final String notificationId;

  @override
  List<Object?> get props => [notificationId];
}

final class NotificationsAllMarkedRead extends NotificationsEvent {
  const NotificationsAllMarkedRead();
}

/// Swipe or menu delete (removed at once, restored if the server refuses).
final class NotificationDeleted extends NotificationsEvent {
  const NotificationDeleted(this.notificationId);

  final String notificationId;

  @override
  List<Object?> get props => [notificationId];
}

final class NotificationsAllDeleted extends NotificationsEvent {
  const NotificationsAllDeleted();
}

// ---------------------------------------------------------------- states

/// What the last user action did (for a toast). [id] makes two equal
/// outcomes distinct states.
enum NotificationsOutcomeKind { deleted, allMarkedRead, allDeleted, failed }

final class NotificationsOutcome extends Equatable {
  const NotificationsOutcome(this.kind, this.id, {this.error});

  final NotificationsOutcomeKind kind;
  final int id;
  final ApiException? error;

  @override
  List<Object?> get props => [kind, id, error];
}

sealed class NotificationsState extends Equatable {
  const NotificationsState(this.filter);

  final NotificationsFilter filter;

  @override
  List<Object?> get props => [filter];
}

final class NotificationsLoading extends NotificationsState {
  const NotificationsLoading(super.filter);
}

final class NotificationsFailure extends NotificationsState {
  const NotificationsFailure(super.filter, this.error);

  final ApiException error;

  @override
  List<Object?> get props => [filter, error];
}

final class NotificationsLoaded extends NotificationsState {
  const NotificationsLoaded(
    super.filter, {
    required this.items,
    required this.page,
    required this.hasMore,
    required this.unreadCount,
    this.loadingMore = false,
    this.pageError,
    this.busy = false,
    this.outcome,
  });

  final List<AppNotification> items;

  /// The last page loaded.
  final int page;
  final bool hasMore;
  final int unreadCount;
  final bool loadingMore;

  /// The next page failed; the footer offers a retry.
  final ApiException? pageError;

  /// Mark all read or delete all is running.
  final bool busy;

  /// The result of the last action, for a toast.
  final NotificationsOutcome? outcome;

  NotificationsLoaded copyWith({
    List<AppNotification>? items,
    int? page,
    bool? hasMore,
    int? unreadCount,
    bool? loadingMore,
    ApiException? Function()? pageError,
    bool? busy,
    NotificationsOutcome? Function()? outcome,
  }) => NotificationsLoaded(
    filter,
    items: items ?? this.items,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    unreadCount: unreadCount ?? this.unreadCount,
    loadingMore: loadingMore ?? this.loadingMore,
    pageError: pageError == null ? this.pageError : pageError(),
    busy: busy ?? this.busy,
    outcome: outcome == null ? this.outcome : outcome(),
  );

  @override
  List<Object?> get props => [
    filter,
    items,
    page,
    hasMore,
    unreadCount,
    loadingMore,
    pageError,
    busy,
    outcome,
  ];
}

// ------------------------------------------------------------------ bloc

/// M49: the in-app notifications (`GET /notifications?unread=&page=`),
/// newest first, 20 per page with infinite scroll.
///
/// * `notification.created` (via `UnreadCountCubit.notificationCreated`)
///   prepends the new row;
/// * opening or marking one read, deleting one: applied at once (CD9 allows
///   it for list edits), then confirmed by the server, and undone on failure;
/// * mark all read and delete all wait for the server;
/// * every unread-count change goes to [onUnreadCount] (the shell badge).
class NotificationsBloc extends Bloc<NotificationsEvent, NotificationsState> {
  NotificationsBloc({
    required this._repository,
    Stream<Map<String, dynamic>>? created,
    this._onUnreadCount,
    DateTime Function()? now,
    NotificationsFilter filter = NotificationsFilter.all,
  }) : _now = now ?? DateTime.now,
       super(NotificationsLoading(filter)) {
    on<NotificationsStarted>((_, emit) => _load(emit, state.filter));
    on<NotificationsFilterChanged>(_onFilterChanged);
    on<NotificationsRefreshed>(_onRefreshed);
    on<NotificationsNextPageRequested>(_onNextPage);
    on<NotificationsReceived>(_onReceived);
    on<NotificationOpened>(
      (event, emit) => _markRead(emit, event.notification.id),
    );
    on<NotificationMarkedRead>(
      (event, emit) => _markRead(emit, event.notificationId),
    );
    on<NotificationsAllMarkedRead>(_onAllMarkedRead);
    on<NotificationDeleted>(_onDeleted);
    on<NotificationsAllDeleted>(_onAllDeleted);
    _created = created?.listen(
      (payload) => add(NotificationsReceived(payload)),
    );
  }

  final NotificationsRepository _repository;
  final void Function(int count)? _onUnreadCount;
  final DateTime Function() _now;
  StreamSubscription<Map<String, dynamic>>? _created;

  /// Identifies the current list load: an older answer (after a filter
  /// switch) is dropped.
  int _generation = 0;
  int _outcomeId = 0;

  NotificationsOutcome _outcome(
    NotificationsOutcomeKind kind, [
    ApiException? error,
  ]) => NotificationsOutcome(kind, ++_outcomeId, error: error);

  void _publishUnread(int count) => _onUnreadCount?.call(count < 0 ? 0 : count);

  Future<void> _load(
    Emitter<NotificationsState> emit,
    NotificationsFilter filter, {
    bool keepVisible = false,
  }) async {
    final generation = ++_generation;
    if (!keepVisible) emit(NotificationsLoading(filter));
    try {
      final result = await _repository.list(
        unreadOnly: filter == NotificationsFilter.unread,
      );
      if (generation != _generation) return;
      emit(
        NotificationsLoaded(
          filter,
          items: result.page.items,
          page: result.page.meta.currentPage,
          hasMore: result.page.hasMore,
          unreadCount: result.unreadCount,
        ),
      );
      _publishUnread(result.unreadCount);
    } on ApiException catch (error) {
      if (generation != _generation) return;
      final current = state;
      if (keepVisible && current is NotificationsLoaded) {
        emit(
          current.copyWith(
            outcome: () => _outcome(NotificationsOutcomeKind.failed, error),
          ),
        );
      } else {
        emit(NotificationsFailure(filter, error));
      }
    }
  }

  Future<void> _onFilterChanged(
    NotificationsFilterChanged event,
    Emitter<NotificationsState> emit,
  ) async {
    if (event.filter == state.filter && state is! NotificationsFailure) return;
    await _load(emit, event.filter);
  }

  Future<void> _onRefreshed(
    NotificationsRefreshed event,
    Emitter<NotificationsState> emit,
  ) async {
    try {
      await _load(
        emit,
        state.filter,
        keepVisible: state is NotificationsLoaded,
      );
    } finally {
      event.done?.complete();
    }
  }

  Future<void> _onNextPage(
    NotificationsNextPageRequested event,
    Emitter<NotificationsState> emit,
  ) async {
    final current = state;
    if (current is! NotificationsLoaded ||
        !current.hasMore ||
        current.loadingMore) {
      return;
    }
    final generation = _generation;
    emit(current.copyWith(loadingMore: true, pageError: () => null));
    try {
      final result = await _repository.list(
        unreadOnly: current.filter == NotificationsFilter.unread,
        page: current.page + 1,
      );
      final latest = state;
      if (generation != _generation || latest is! NotificationsLoaded) return;
      // Rows prepended since page 1 shift the pages: skip repeats.
      final known = {for (final item in latest.items) item.id};
      emit(
        latest.copyWith(
          items: [
            ...latest.items,
            ...result.page.items.where((item) => !known.contains(item.id)),
          ],
          page: result.page.meta.currentPage,
          hasMore: result.page.hasMore,
          unreadCount: result.unreadCount,
          loadingMore: false,
        ),
      );
      _publishUnread(result.unreadCount);
    } on ApiException catch (error) {
      final latest = state;
      if (generation != _generation || latest is! NotificationsLoaded) return;
      emit(latest.copyWith(loadingMore: false, pageError: () => error));
    }
  }

  void _onReceived(
    NotificationsReceived event,
    Emitter<NotificationsState> emit,
  ) {
    final current = state;
    if (current is! NotificationsLoaded) return;
    final raw = event.payload['notification'];
    final count = event.payload['unread_count'];
    AppNotification? notification;
    if (raw is Json) {
      try {
        notification = AppNotification.fromJson(raw);
      } on FormatException catch (error) {
        debugPrint('Ignoring a malformed notification: $error');
      }
    }
    final isNew =
        notification != null &&
        current.items.every((item) => item.id != notification!.id);
    emit(
      current.copyWith(
        items: isNew ? [notification, ...current.items] : null,
        unreadCount: count is int ? count : null,
      ),
    );
  }

  Future<void> _markRead(
    Emitter<NotificationsState> emit,
    String notificationId,
  ) async {
    final current = state;
    if (current is! NotificationsLoaded) return;
    final index = current.items.indexWhere((item) => item.id == notificationId);
    if (index < 0 || current.items[index].isRead) return;
    final original = current.items[index];
    emit(
      current.copyWith(
        items: _replace(current.items, original.markedRead(_now().toUtc())),
        unreadCount: _atLeastZero(current.unreadCount - 1),
      ),
    );
    _publishUnread(current.unreadCount - 1);
    try {
      final confirmed = await _repository.markRead(notificationId);
      final latest = state;
      if (latest is NotificationsLoaded) {
        emit(latest.copyWith(items: _replace(latest.items, confirmed)));
      }
    } on ApiException catch (error) {
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      emit(
        latest.copyWith(
          items: _replace(latest.items, original),
          unreadCount: latest.unreadCount + 1,
          outcome: () => _outcome(NotificationsOutcomeKind.failed, error),
        ),
      );
      _publishUnread(latest.unreadCount + 1);
    }
  }

  Future<void> _onAllMarkedRead(
    NotificationsAllMarkedRead event,
    Emitter<NotificationsState> emit,
  ) async {
    final current = state;
    if (current is! NotificationsLoaded || current.busy) return;
    emit(current.copyWith(busy: true));
    try {
      final count = await _repository.markAllRead();
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      final at = _now().toUtc();
      emit(
        latest.copyWith(
          items: [for (final item in latest.items) item.markedRead(at)],
          unreadCount: count,
          busy: false,
          outcome: () => _outcome(NotificationsOutcomeKind.allMarkedRead),
        ),
      );
      _publishUnread(count);
    } on ApiException catch (error) {
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      emit(
        latest.copyWith(
          busy: false,
          outcome: () => _outcome(NotificationsOutcomeKind.failed, error),
        ),
      );
    }
  }

  Future<void> _onDeleted(
    NotificationDeleted event,
    Emitter<NotificationsState> emit,
  ) async {
    final current = state;
    if (current is! NotificationsLoaded) return;
    final index = current.items.indexWhere(
      (item) => item.id == event.notificationId,
    );
    if (index < 0) return;
    final removed = current.items[index];
    final unreadDelta = removed.isRead ? 0 : 1;
    emit(
      current.copyWith(
        items: [...current.items]..removeAt(index),
        unreadCount: _atLeastZero(current.unreadCount - unreadDelta),
      ),
    );
    _publishUnread(current.unreadCount - unreadDelta);
    try {
      await _repository.delete(removed.id);
      final latest = state;
      if (latest is NotificationsLoaded) {
        emit(
          latest.copyWith(
            outcome: () => _outcome(NotificationsOutcomeKind.deleted),
          ),
        );
      }
    } on ApiException catch (error) {
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      final restored = [...latest.items]
        ..insert(index.clamp(0, latest.items.length), removed);
      emit(
        latest.copyWith(
          items: restored,
          unreadCount: latest.unreadCount + unreadDelta,
          outcome: () => _outcome(NotificationsOutcomeKind.failed, error),
        ),
      );
      _publishUnread(latest.unreadCount + unreadDelta);
    }
  }

  Future<void> _onAllDeleted(
    NotificationsAllDeleted event,
    Emitter<NotificationsState> emit,
  ) async {
    final current = state;
    if (current is! NotificationsLoaded || current.busy) return;
    emit(current.copyWith(busy: true));
    try {
      await _repository.deleteAll();
      _generation++;
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      emit(
        NotificationsLoaded(
          latest.filter,
          items: const [],
          page: 1,
          hasMore: false,
          unreadCount: 0,
          outcome: _outcome(NotificationsOutcomeKind.allDeleted),
        ),
      );
      _publishUnread(0);
    } on ApiException catch (error) {
      final latest = state;
      if (latest is! NotificationsLoaded) return;
      emit(
        latest.copyWith(
          busy: false,
          outcome: () => _outcome(NotificationsOutcomeKind.failed, error),
        ),
      );
    }
  }

  static int _atLeastZero(int count) => count < 0 ? 0 : count;

  static List<AppNotification> _replace(
    List<AppNotification> items,
    AppNotification replacement,
  ) => [
    for (final item in items) item.id == replacement.id ? replacement : item,
  ];

  @override
  Future<void> close() async {
    await _created?.cancel();
    return super.close();
  }
}
