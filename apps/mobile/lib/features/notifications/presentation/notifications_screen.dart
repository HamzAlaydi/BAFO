import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/router/deep_link_router.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/notifications/presentation/notification_tile.dart';
import 'package:bafo/features/notifications/presentation/notifications_bloc.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M49 Notifications: All / Unread, infinite scroll, pull to refresh, tap →
/// the S11 route (through `DeepLinkRouter`), swipe or menu to delete, mark
/// all read and delete all. New notifications arrive on the user channel.
class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final unread = context.read<UnreadCountCubit>();
    return BlocProvider(
      create: (context) => NotificationsBloc(
        repository: context.read<NotificationsRepository>(),
        created: unread.notificationCreated,
        onUnreadCount: unread.set,
      )..add(const NotificationsStarted()),
      child: const _NotificationsView(),
    );
  }
}

enum _HeaderAction { deleteAll }

class _NotificationsView extends StatelessWidget {
  const _NotificationsView();

  Future<void> _deleteAll(BuildContext context) async {
    final l10n = context.l10n;
    final bloc = context.read<NotificationsBloc>();
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.notificationsDeleteAllConfirmTitle,
      message: l10n.notificationsDeleteAllConfirmMessage,
      confirmLabel: l10n.notificationsDeleteAll,
      destructive: true,
    );
    if (confirmed) bloc.add(const NotificationsAllDeleted());
  }

  void _onOutcome(BuildContext context, NotificationsOutcome outcome) {
    final l10n = context.l10n;
    switch (outcome.kind) {
      case NotificationsOutcomeKind.deleted:
        BafoToast.show(context, l10n.notificationsDeleted);
      case NotificationsOutcomeKind.allMarkedRead:
        BafoToast.success(context, l10n.notificationsAllMarkedRead);
      case NotificationsOutcomeKind.allDeleted:
        BafoToast.success(context, l10n.notificationsAllDeleted);
      case NotificationsOutcomeKind.failed:
        BafoToast.error(context, errorMessage(l10n, outcome.error));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = context.watch<NotificationsBloc>().state;
    final loaded = state is NotificationsLoaded ? state : null;
    final hasItems = loaded != null && loaded.items.isNotEmpty;
    final busy = loaded?.busy ?? false;

    return Scaffold(
      appBar: BafoAppBar(
        title: l10n.navNotifications,
        actions: [
          IconButton(
            key: const Key('notifications.markAllRead'),
            tooltip: l10n.notificationsMarkAllRead,
            icon: const Icon(Icons.done_all_rounded),
            onPressed: loaded != null && loaded.unreadCount > 0 && !busy
                ? () => context.read<NotificationsBloc>().add(
                    const NotificationsAllMarkedRead(),
                  )
                : null,
          ),
          PopupMenuButton<_HeaderAction>(
            key: const Key('notifications.menu'),
            tooltip: l10n.notificationsMoreActions,
            enabled: hasItems && !busy,
            onSelected: (_) => _deleteAll(context),
            itemBuilder: (_) => [
              PopupMenuItem(
                key: const Key('notifications.deleteAll'),
                value: _HeaderAction.deleteAll,
                child: Text(l10n.notificationsDeleteAll),
              ),
            ],
          ),
        ],
        bottom: busy
            ? const PreferredSize(
                preferredSize: Size.fromHeight(2),
                child: LinearProgressIndicator(minHeight: 2),
              )
            : null,
      ),
      body: BlocListener<NotificationsBloc, NotificationsState>(
        listenWhen: (previous, current) =>
            current is NotificationsLoaded &&
            current.outcome != null &&
            (previous is! NotificationsLoaded ||
                previous.outcome != current.outcome),
        listener: (context, state) =>
            _onOutcome(context, (state as NotificationsLoaded).outcome!),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(
                BafoSpacing.page,
                BafoSpacing.sm,
                BafoSpacing.page,
                BafoSpacing.sm,
              ),
              child: SegmentedFilter<NotificationsFilter>(
                key: const Key('notifications.filter'),
                selected: state.filter,
                segments: [
                  FilterSegment(
                    value: NotificationsFilter.all,
                    label: l10n.notificationsFilterAll,
                  ),
                  FilterSegment(
                    value: NotificationsFilter.unread,
                    label: l10n.notificationsFilterUnread,
                    count: loaded == null || loaded.unreadCount == 0
                        ? null
                        : loaded.unreadCount,
                  ),
                ],
                onChanged: (filter) => context.read<NotificationsBloc>().add(
                  NotificationsFilterChanged(filter),
                ),
              ),
            ),
            Expanded(
              child: switch (state) {
                NotificationsLoading() => const LoadingSkeletonList(),
                NotificationsFailure(:final error) => ErrorState(
                  error: error,
                  onRetry: () => context.read<NotificationsBloc>().add(
                    const NotificationsStarted(),
                  ),
                ),
                NotificationsLoaded() => _NotificationsList(state: state),
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationsList extends StatelessWidget {
  const _NotificationsList({required this.state});

  final NotificationsLoaded state;

  Future<void> _refresh(BuildContext context) {
    final done = Completer<void>();
    context.read<NotificationsBloc>().add(NotificationsRefreshed(done));
    return done.future;
  }

  /// S11: mark read (the bloc) and follow the route. Unknown routes map to
  /// this list, so there is nothing to open; tab roots are switched to,
  /// everything else is pushed by `DeepLinkRouter`.
  void _open(BuildContext context, AppNotification notification) {
    context.read<NotificationsBloc>().add(NotificationOpened(notification));
    final target = DeepLinkRouter.map(notification.route);
    if (target == AppRoutes.notifications) return;
    if (target == AppRoutes.home) {
      context.go(target);
      return;
    }
    unawaited(context.read<DeepLinkRouter>().open(notification.route));
  }

  void _act(
    BuildContext context,
    AppNotification notification,
    NotificationAction action,
  ) {
    final bloc = context.read<NotificationsBloc>();
    switch (action) {
      case NotificationAction.markRead:
        bloc.add(NotificationMarkedRead(notification.id));
      case NotificationAction.delete:
        bloc.add(NotificationDeleted(notification.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items = state.items;
    if (items.isEmpty) {
      final unreadFilter = state.filter == NotificationsFilter.unread;
      return RefreshIndicator(
        onRefresh: () => _refresh(context),
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: EmptyState(
                icon: unreadFilter
                    ? Icons.mark_email_read_outlined
                    : Icons.notifications_none_rounded,
                title: unreadFilter
                    ? l10n.notificationsUnreadEmptyTitle
                    : l10n.notificationsEmptyTitle,
                message: unreadFilter
                    ? l10n.notificationsUnreadEmptyMessage
                    : l10n.notificationsEmptyMessage,
              ),
            ),
          ),
        ),
      );
    }
    final now = context.read<ServerClock>().now();
    return RefreshIndicator(
      onRefresh: () => _refresh(context),
      child: ListView.separated(
        key: const Key('notifications.list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.xl),
        itemCount: items.length + 1,
        separatorBuilder: (_, index) => index < items.length - 1
            ? const Divider(height: 1)
            : const SizedBox.shrink(),
        itemBuilder: (context, index) {
          if (index == items.length) return _Footer(state: state);
          final notification = items[index];
          return NotificationTile(
            key: ValueKey(notification.id),
            notification: notification,
            now: now,
            onOpen: () => _open(context, notification),
            onAction: (action) => _act(context, notification, action),
          );
        },
      ),
    );
  }
}

/// The end of the list: asks for the next page when it comes into view,
/// shows a spinner while it loads and a retry when it failed.
class _Footer extends StatelessWidget {
  const _Footer({required this.state});

  final NotificationsLoaded state;

  @override
  Widget build(BuildContext context) {
    if (!state.hasMore) return const SizedBox(height: BafoSpacing.lg);
    final error = state.pageError;
    if (error != null) {
      return Padding(
        padding: BafoSpacing.pagePadding,
        child: Column(
          children: [
            Text(
              context.l10n.notificationsLoadMoreFailed,
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: BafoSpacing.xs),
            BafoButton.text(
              key: const Key('notifications.loadMoreRetry'),
              label: context.l10n.commonActionsRetry,
              icon: Icons.refresh_rounded,
              onPressed: () => context.read<NotificationsBloc>().add(
                const NotificationsNextPageRequested(),
              ),
            ),
          ],
        ),
      );
    }
    if (!state.loadingMore) {
      final bloc = context.read<NotificationsBloc>();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!bloc.isClosed) bloc.add(const NotificationsNextPageRequested());
      });
    }
    return const Padding(
      padding: EdgeInsetsDirectional.all(BafoSpacing.lg),
      child: Center(
        child: SizedBox.square(
          dimension: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }
}
