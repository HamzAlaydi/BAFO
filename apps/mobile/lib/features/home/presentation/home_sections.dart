import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// One stat tile of the home grid.
class HomeStat {
  const HomeStat({
    required this.label,
    required this.value,
    required this.icon,
    this.onTap,
    this.key,
  });

  final String label;
  final int value;
  final IconData icon;
  final VoidCallback? onTap;
  final Key? key;
}

/// Stat tiles in two columns. Rows grow with the text (200% text scale
/// wraps instead of clipping), and both tiles of a row share its height.
class HomeStatGrid extends StatelessWidget {
  const HomeStatGrid({required this.stats, super.key});

  final List<HomeStat> stats;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < stats.length; i += 2) {
      final pair = stats.skip(i).take(2).toList();
      rows.add(
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final (index, stat) in pair.indexed) ...[
                if (index > 0) const SizedBox(width: BafoSpacing.xs),
                Expanded(child: _tile(stat)),
              ],
              if (pair.length == 1) ...[
                const SizedBox(width: BafoSpacing.xs),
                const Expanded(child: SizedBox.shrink()),
              ],
            ],
          ),
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (index, row) in rows.indexed) ...[
          if (index > 0) const SizedBox(height: BafoSpacing.xs),
          row,
        ],
      ],
    );
  }

  Widget _tile(HomeStat stat) => StatTile(
    key: stat.key,
    label: stat.label,
    value: stat.value,
    icon: stat.icon,
    onTap: stat.onTap,
  );
}

/// A home alert (API.md §2.13 `alerts[]`) as an information notice. Mobile
/// never pairs these with a purchase action (SCREENS.md §3.2): entitlement
/// alerts add «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.».
/// Only the billing-profile alert opens a screen, the organisation form, and
/// only for users who may edit it.
class HomeAlertCard extends StatelessWidget {
  const HomeAlertCard({required this.alert, this.onCompleteProfile, super.key});

  final HomeAlert alert;

  /// Set when the user may edit the organisation (billing-profile alert).
  final VoidCallback? onCompleteProfile;

  /// Whether the app renders [code] (unknown codes are skipped).
  static bool renders(HomeAlertCode code) => code != HomeAlertCode.unknown;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final days = alert.daysLeft;
    final (
      String title,
      String? message,
      StatusTone tone,
      IconData icon,
    ) = switch (alert.code) {
      HomeAlertCode.subscriptionExpiring => (
        days == null
            ? l10n.homeAlertSubscriptionExpiringSoon
            : l10n.homeAlertSubscriptionExpiring(days),
        l10n.billingManagedOnWeb,
        StatusTone.warning,
        Icons.event_busy_outlined,
      ),
      HomeAlertCode.subscriptionExpired => (
        l10n.homeAlertSubscriptionExpired,
        l10n.billingManagedOnWeb,
        StatusTone.warning,
        Icons.event_busy_outlined,
      ),
      HomeAlertCode.planRequired => (
        l10n.homeAlertPlanRequired,
        l10n.billingManagedOnWeb,
        StatusTone.warning,
        Icons.workspace_premium_outlined,
      ),
      HomeAlertCode.trialAvailable => (
        l10n.homeAlertTrialAvailable,
        l10n.billingManagedOnWeb,
        StatusTone.info,
        Icons.workspace_premium_outlined,
      ),
      HomeAlertCode.billingProfileIncomplete => (
        l10n.homeAlertBillingProfileIncomplete,
        onCompleteProfile == null ? null : l10n.homeAlertBillingProfileAction,
        StatusTone.info,
        Icons.receipt_long_outlined,
      ),
      HomeAlertCode.unknown => ('', null, StatusTone.neutral, Icons.info),
    };
    final notice = message == null
        ? InfoNotice(message: title, tone: tone, icon: icon)
        : InfoNotice(title: title, message: message, tone: tone, icon: icon);
    final onTap = alert.code == HomeAlertCode.billingProfileIncomplete
        ? onCompleteProfile
        : null;
    if (onTap == null) return notice;
    return Semantics(
      button: true,
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(BafoRadii.card),
          onTap: onTap,
          child: notice,
        ),
      ),
    );
  }
}

/// The organisation's plan, read-only (M14 subscription card → M58). No
/// price, link or purchase action.
class HomeSubscriptionCard extends StatelessWidget {
  const HomeSubscriptionCard({
    required this.subscription,
    required this.teamMembers,
    required this.seatsTotal,
    this.onOpen,
    super.key,
  });

  final SubscriptionSummary? subscription;
  final int teamMembers;
  final int seatsTotal;
  final VoidCallback? onOpen;

  static StatusTone toneOf(SubscriptionStatus status) => switch (status) {
    SubscriptionStatus.active => StatusTone.success,
    SubscriptionStatus.pendingPayment ||
    SubscriptionStatus.expired => StatusTone.warning,
    _ => StatusTone.neutral,
  };

  static String statusLabel(AppLocalizations l10n, SubscriptionStatus status) =>
      switch (status) {
        SubscriptionStatus.active => l10n.billingSubscriptionActive,
        SubscriptionStatus.pendingPayment =>
          l10n.billingSubscriptionPendingPayment,
        SubscriptionStatus.expired => l10n.billingSubscriptionExpired,
        SubscriptionStatus.superseded => l10n.billingSubscriptionSuperseded,
        SubscriptionStatus.cancelled => l10n.billingSubscriptionCancelled,
        SubscriptionStatus.unknown => l10n.billingSubscriptionUnknown,
      };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final current = subscription;
    final daysLeft = current?.daysLeft;
    final totalDays = current?.totalDays;
    final endsAt = current?.endsAt;
    return Semantics(
      hint: onOpen == null ? null : l10n.homeSubscriptionOpen,
      child: BafoCard(
        key: const Key('home.subscription'),
        onTap: onOpen,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(
                  Icons.workspace_premium_outlined,
                  size: 20,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: BafoSpacing.sm),
                Expanded(
                  child: Text(
                    l10n.homeSubscriptionTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
                if (onOpen != null)
                  Icon(
                    Icons.chevron_right_rounded,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
              ],
            ),
            const SizedBox(height: BafoSpacing.md),
            if (current == null)
              Text(l10n.billingStatusNoPlan, style: theme.textTheme.bodyMedium)
            else ...[
              Wrap(
                spacing: BafoSpacing.sm,
                runSpacing: BafoSpacing.xs,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text(current.plan.name, style: theme.textTheme.titleMedium),
                  StatusPill(
                    label: statusLabel(l10n, current.status),
                    tone: toneOf(current.status),
                  ),
                ],
              ),
              if (endsAt != null) ...[
                const SizedBox(height: BafoSpacing.xs),
                Text(
                  l10n.homeSubscriptionEnds(
                    BafoDateFormat.longDate(endsAt, context.languageCode),
                  ),
                  style: muted,
                ),
              ],
              if (daysLeft != null && totalDays != null && totalDays > 0) ...[
                const SizedBox(height: BafoSpacing.md),
                Text(l10n.billingStatusDaysLeft(daysLeft), style: muted),
                const SizedBox(height: BafoSpacing.xs),
                MeterBar(
                  value: daysLeft,
                  max: totalDays,
                  semanticsLabel: l10n.billingStatusDaysLeft(daysLeft),
                ),
              ],
            ],
            if (seatsTotal > 0) ...[
              const Divider(height: BafoSpacing.xl),
              KeyValueList(
                items: [
                  KeyValue(
                    l10n.billingStatusSeats,
                    l10n.billingStatusSeatsValue(teamMembers, seatsTotal),
                  ),
                ],
              ),
            ],
            const SizedBox(height: BafoSpacing.md),
            Text(l10n.billingManagedOnWeb, style: muted),
          ],
        ),
      ),
    );
  }
}

/// A recent activity row: what happened (passive voice, so the text never
/// depends on the actor's gender), then who and when.
class ActivityTile extends StatelessWidget {
  const ActivityTile({
    required this.activity,
    required this.now,
    this.onTap,
    super.key,
  });

  final Activity activity;

  /// Server time, for the relative time.
  final DateTime now;
  final VoidCallback? onTap;

  static IconData iconOf(String action) => switch (action) {
    'competition.created' => Icons.edit_note_rounded,
    'competition.published' => Icons.campaign_outlined,
    'competition.cancelled' => Icons.block_rounded,
    'competition.closed' => Icons.flag_outlined,
    'award.issued' => Icons.emoji_events_outlined,
    'invitation.joined' => Icons.how_to_reg_outlined,
    'member.added' => Icons.person_add_alt_outlined,
    'subscription.activated' => Icons.workspace_premium_outlined,
    _ => Icons.history_rounded,
  };

  /// `home.activity.<action with . → _>` (API.md §2.13). The title is a
  /// first-strong isolate (S1): an English title keeps its punctuation in an
  /// Arabic sentence, and the other way round.
  static String textOf(AppLocalizations l10n, Activity activity) {
    final title = isolate(activity.subjectTitle ?? '');
    return switch (activity.action) {
      'competition.created' => l10n.homeActivityCompetitionCreated(title),
      'competition.published' => l10n.homeActivityCompetitionPublished(title),
      'competition.cancelled' => l10n.homeActivityCompetitionCancelled(title),
      'competition.closed' => l10n.homeActivityCompetitionClosed(title),
      'award.issued' => l10n.homeActivityAwardIssued(title),
      'invitation.joined' => l10n.homeActivityInvitationJoined(title),
      'member.added' => l10n.homeActivityMemberAdded,
      'subscription.activated' => l10n.homeActivitySubscriptionActivated,
      _ => l10n.homeActivityOther,
    };
  }

  /// The API names automatic actions "system".
  static String? actorOf(AppLocalizations l10n, Activity activity) {
    final name = activity.actorName?.trim();
    if (name == null || name.isEmpty) return null;
    return name == 'system' ? l10n.homeActivityActorSystem : name;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final time = BafoDateFormat.relative(
      activity.occurredAt,
      now: now,
      languageCode: context.languageCode,
      l10n: l10n,
    );
    final actor = actorOf(l10n, activity);
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsetsDirectional.symmetric(
        horizontal: BafoSpacing.page,
      ),
      leading: DecoratedBox(
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainer,
          shape: BoxShape.circle,
        ),
        child: Padding(
          padding: const EdgeInsetsDirectional.all(BafoSpacing.sm),
          child: Icon(
            iconOf(activity.action),
            size: 20,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ),
      title: Text(textOf(l10n, activity)),
      // The actor's name is isolated: an Arabic name in an English line
      // (or the reverse) must not pull the time into its run (S1).
      subtitle: Text([if (actor != null) isolate(actor), time].join(' · ')),
      trailing: onTap == null
          ? null
          : Icon(
              Icons.chevron_right_rounded,
              color: theme.colorScheme.onSurfaceVariant,
            ),
    );
  }
}

/// Wraps user-provided text of unknown direction (names, titles) in a
/// first-strong isolate for composed sentences (S1): the kit's
/// [bidiIsolate].
String isolate(String text) => bidiIsolate(text);
