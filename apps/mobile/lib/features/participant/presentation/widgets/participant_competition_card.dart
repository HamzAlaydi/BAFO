import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/participant/presentation/widgets/offline_gate.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// A row of M16 (W23 `CompetitionCard`): issuer, title, chips, the
/// organisation's own offer and standing (only when projected), a
/// countdown, the result, and Join / Decline on invitations awaiting a
/// response. Nothing here names another participant or shows their price.
class ParticipantCompetitionCard extends StatelessWidget {
  const ParticipantCompetitionCard({
    required this.item,
    required this.onOpen,
    this.onJoin,
    this.onDecline,
    this.onAccessInfo,
    super.key,
  });

  final CompetitionListItem item;
  final VoidCallback onOpen;

  /// `join_required`: opens the invitation (M17) with the join sheet.
  final VoidCallback? onJoin;
  final VoidCallback? onDecline;

  /// `plan_required`: the information sheet (M20).
  final VoidCallback? onAccessInfo;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final access = item.access;
    final issuer = item.issuer;
    final myOffer = item.myOfferAmountMinor;
    final outcome = item.resultOutcome;
    final countdown = _countdown();
    final statusLabel = _statusLabel(l10n);

    return Semantics(
      container: true,
      label: l10n.competitionsParticipatingCardSemantics(
        item.title,
        statusLabel,
      ),
      child: BafoCard(
        onTap: onOpen,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (issuer != null)
              Row(
                children: [
                  OrgAvatar(
                    name: issuer.name,
                    logoUrl: issuer.logoUrl,
                    size: 28,
                    decorative: true,
                  ),
                  const SizedBox(width: BafoSpacing.sm),
                  Expanded(
                    child: Text(
                      issuer.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                  if (issuer.verified)
                    Icon(
                      Icons.verified_outlined,
                      size: 16,
                      semanticLabel: l10n.competitionsParticipantVerified,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                ],
              ),
            const SizedBox(height: BafoSpacing.sm),
            Text(
              item.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleMedium,
            ),
            if (item.referenceNo != null) ...[
              const SizedBox(height: BafoSpacing.xxs),
              Ltr(
                child: Text(
                  item.referenceNo!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
            const SizedBox(height: BafoSpacing.sm),
            Wrap(
              spacing: BafoSpacing.xs,
              runSpacing: BafoSpacing.xs,
              children: [
                CompetitionStatusChip(
                  status: item.status,
                  phase: item.phase,
                  effectiveCloseAt: item.effectiveCloseAt,
                ),
                DirectionChip(direction: item.direction, showRule: false),
                // Live or sealed, as on the issuer's cards (M33).
                FormatChip(format: item.format),
                if (access != null && access.state != AccessState.full)
                  AccessStateChip(state: access.state),
                if (access?.feesCovered ?? false) const FeesCoveredBadge(),
                StandingBadge(isLeading: item.isLeading),
                // `not_awarded` repeats the status chip.
                if (outcome == AwardOutcome.won ||
                    outcome == AwardOutcome.notSelected)
                  ResultChip(outcome: outcome!),
              ],
            ),
            if (myOffer != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              Text(
                l10n.competitionsParticipatingMyOffer(
                  ltrIsolate(
                    MoneyFormat.format(
                      Money(myOffer),
                      languageCode: context.languageCode,
                    ),
                  ),
                ),
                style: BafoTypography.tabular(
                  theme.textTheme.bodyMedium ?? const TextStyle(),
                ),
              ),
            ],
            if (countdown != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              countdown,
            ],
            if (_actions(l10n, offline: watchOffline(context))
                case final actions?) ...[
              const SizedBox(height: BafoSpacing.md),
              actions,
            ],
          ],
        ),
      ),
    );
  }

  /// The join deadline (a date) while the invitation awaits a response; a
  /// countdown to the close while live, or to the opening while scheduled
  /// (S3 targets). Lists tick each second.
  Widget? _countdown() {
    if (item.needsAction) {
      final deadline = item.joinDeadline ?? item.invitationCutoffAt;
      return deadline == null ? null : _JoinBy(deadline: deadline);
    }
    final (target, deadline) = switch (item.status) {
      CompetitionStatus.live => (CountdownTarget.closes, item.effectiveCloseAt),
      CompetitionStatus.scheduled => (
        CountdownTarget.opens,
        item.biddingOpensAt,
      ),
      _ => (CountdownTarget.closes, null),
    };
    if (deadline == null) return null;
    return _CardCountdown(target: target, deadline: deadline);
  }

  Widget? _actions(AppLocalizations l10n, {required bool offline}) {
    final state = item.access?.state;
    if (state == AccessState.joinRequired && onJoin != null) {
      return Row(
        children: [
          Expanded(
            child: BafoButton(
              label: l10n.invitationsJoinAction,
              onPressed: onJoin,
            ),
          ),
          const SizedBox(width: BafoSpacing.sm),
          Expanded(
            child: BafoButton.outline(
              label: l10n.invitationsDeclineAction,
              onPressed: offline ? null : onDecline,
            ),
          ),
        ],
      );
    }
    if (state == AccessState.planRequired) {
      return Row(
        children: [
          Expanded(
            child: BafoButton.tonal(
              label: l10n.invitationsPlanRequiredMore,
              icon: Icons.info_outline_rounded,
              onPressed: onAccessInfo,
            ),
          ),
          const SizedBox(width: BafoSpacing.sm),
          Expanded(
            child: BafoButton.outline(
              label: l10n.invitationsDeclineAction,
              onPressed: offline ? null : onDecline,
            ),
          ),
        ],
      );
    }
    return null;
  }

  String _statusLabel(AppLocalizations l10n) => switch (item.status) {
    CompetitionStatus.draft => l10n.competitionsStatusDraft,
    CompetitionStatus.scheduled => l10n.competitionsStatusScheduled,
    CompetitionStatus.live => switch (item.phase) {
      CompetitionPhase.finalWindow => l10n.competitionsStatusFinalWindow,
      CompetitionPhase.sealed => l10n.competitionsStatusLiveSealed,
      _ => l10n.competitionsStatusLive,
    },
    CompetitionStatus.closed => l10n.competitionsStatusClosed,
    CompetitionStatus.bafoRound => l10n.competitionsStatusBafoRound,
    CompetitionStatus.awarded => l10n.competitionsStatusAwarded,
    CompetitionStatus.notAwarded => l10n.competitionsStatusNotAwarded,
    CompetitionStatus.cancelled => l10n.competitionsStatusCancelled,
    CompetitionStatus.unknown => l10n.competitionsStatusUnknown,
  };
}

class _JoinBy extends StatelessWidget {
  const _JoinBy({required this.deadline});

  final DateTime deadline;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(
          Icons.event_available_outlined,
          size: 16,
          color: theme.colorScheme.onSurfaceVariant,
        ),
        const SizedBox(width: BafoSpacing.xs),
        Flexible(
          child: Text(
            context.l10n.invitationsJoinBy(
              BafoDateFormat.dateTime(deadline, context.languageCode),
            ),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ),
      ],
    );
  }
}

class _CardCountdown extends StatelessWidget {
  const _CardCountdown({required this.target, required this.deadline});

  final CountdownTarget target;
  final DateTime deadline;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final label = switch (target) {
      CountdownTarget.opens => l10n.competitionsCountdownOpensIn,
      CountdownTarget.closes => l10n.competitionsCountdownClosesIn,
      CountdownTarget.joinDeadline => l10n.competitionsCountdownJoinBefore,
      CountdownTarget.bafoCutoff => l10n.competitionsCountdownBafoEndsIn,
    };
    return Row(
      children: [
        Icon(
          Icons.schedule_rounded,
          size: 16,
          color: theme.colorScheme.onSurfaceVariant,
        ),
        const SizedBox(width: BafoSpacing.xs),
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(width: BafoSpacing.xs),
        Flexible(
          child: CountdownText(
            deadline: deadline,
            style: theme.textTheme.bodySmall,
            elapsedLabel: target == CountdownTarget.closes
                ? l10n.competitionsCountdownClosing
                : null,
          ),
        ),
      ],
    );
  }
}
