import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter/semantics.dart';
import 'package:material_ui/material_ui.dart';

/// Row actions of a notification.
enum NotificationAction { markRead, delete }

/// One in-app notification (M49): type icon, title, body, relative time and
/// an unread dot. Swipe towards the start edge to delete; the same actions
/// are in the row menu and in the semantics actions (screen readers).
class NotificationTile extends StatelessWidget {
  const NotificationTile({
    required this.notification,
    required this.now,
    required this.onOpen,
    required this.onAction,
    super.key,
  });

  final AppNotification notification;

  /// Server time, for the relative time.
  final DateTime now;
  final VoidCallback onOpen;
  final ValueChanged<NotificationAction> onAction;

  /// The type icon of SCREENS.md S11, or null for the BAFO round types,
  /// which show the brand mark.
  static IconData? iconOf(String type) => switch (type) {
    'competition.invited' ||
    'invitation.joined' ||
    'invitation.declined' => Icons.mail_outline_rounded,
    'competition.updated' => Icons.sync_rounded,
    'competition.opened' ||
    'competition.final_window_started' ||
    'competition.closing_soon' ||
    'competition.extended' => Icons.timer_outlined,
    'competition.closed' ||
    'competition.cancelled' ||
    'competition.not_awarded' => Icons.flag_outlined,
    'offer.received' || 'offer.voided' => Icons.local_offer_outlined,
    'standing.lost_lead' => Icons.leaderboard_outlined,
    'bafo.invited' || 'bafo.ended' => null,
    'comment.created' => Icons.chat_bubble_outline_rounded,
    _ when type.startsWith('award.') => Icons.emoji_events_outlined,
    _
        when type.startsWith('subscription.') ||
            type.startsWith('payment.') ||
            type.startsWith('invoice.') ||
            type.startsWith('voucher.') ||
            type.startsWith('sponsorship.') =>
      Icons.receipt_long_outlined,
    _
        when type.startsWith('webhook.') ||
            type.startsWith('import.') ||
            type.startsWith('export.') =>
      Icons.power_outlined,
    _ => Icons.notifications_none_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final unread = !notification.isRead;
    final time = BafoDateFormat.relative(
      notification.createdAt,
      now: now,
      languageCode: context.languageCode,
      l10n: l10n,
    );
    final icon = iconOf(notification.type);

    final tile = Semantics(
      button: true,
      onTap: onOpen,
      label: [
        if (unread) l10n.notificationsUnreadLabel,
        notification.title,
        if (notification.body.isNotEmpty) notification.body,
        time,
      ].join('. '),
      customSemanticsActions: {
        if (unread)
          CustomSemanticsAction(label: l10n.notificationsMarkRead): () =>
              onAction(NotificationAction.markRead),
        CustomSemanticsAction(label: l10n.notificationsDelete): () =>
            onAction(NotificationAction.delete),
      },
      excludeSemantics: true,
      child: InkWell(
        onTap: onOpen,
        child: Container(
          color: unread
              ? scheme.primaryContainer.withValues(alpha: 0.35)
              : null,
          padding: const EdgeInsetsDirectional.fromSTEB(
            BafoSpacing.page,
            BafoSpacing.md,
            BafoSpacing.xs,
            BafoSpacing.md,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              DecoratedBox(
                decoration: BoxDecoration(
                  color: scheme.surfaceContainer,
                  shape: BoxShape.circle,
                ),
                child: SizedBox.square(
                  dimension: 40,
                  child: Center(
                    child: icon == null
                        ? const BrandMark(size: 24, excludeFromSemantics: true)
                        : Icon(icon, size: 20, color: scheme.onSurfaceVariant),
                  ),
                ),
              ),
              const SizedBox(width: BafoSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      notification.title,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: unread ? FontWeight.w700 : FontWeight.w500,
                      ),
                    ),
                    if (notification.body.isNotEmpty) ...[
                      const SizedBox(height: BafoSpacing.xxs),
                      Text(
                        notification.body,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                    const SizedBox(height: BafoSpacing.xs),
                    Row(
                      children: [
                        if (unread) ...[
                          Container(
                            key: const Key('notification.unreadDot'),
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: scheme.primary,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: BafoSpacing.xs),
                        ],
                        Flexible(
                          child: Text(
                            time,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              PopupMenuButton<NotificationAction>(
                tooltip: l10n.notificationsMoreActions,
                icon: Icon(
                  Icons.more_vert_rounded,
                  color: scheme.onSurfaceVariant,
                ),
                onSelected: onAction,
                itemBuilder: (_) => [
                  if (unread)
                    PopupMenuItem(
                      value: NotificationAction.markRead,
                      child: Text(l10n.notificationsMarkRead),
                    ),
                  PopupMenuItem(
                    value: NotificationAction.delete,
                    child: Text(l10n.notificationsDelete),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    return Dismissible(
      key: ValueKey('notification-${notification.id}'),
      direction: DismissDirection.endToStart,
      onDismissed: (_) => onAction(NotificationAction.delete),
      background: ColoredBox(
        color: scheme.error,
        child: Align(
          alignment: AlignmentDirectional.centerEnd,
          child: Padding(
            padding: const EdgeInsetsDirectional.only(end: BafoSpacing.xl),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.delete_outline_rounded, color: scheme.onError),
                const SizedBox(width: BafoSpacing.xs),
                Text(
                  l10n.notificationsDelete,
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: scheme.onError,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      child: tile,
    );
  }
}
