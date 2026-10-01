import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/features/participant/presentation/invitation_action_cubit.dart';
import 'package:bafo/features/participant/presentation/participant_competition_cubit.dart';
import 'package:bafo/features/participant/presentation/widgets/invitation_sheets.dart';
import 'package:bafo/features/participant/presentation/widgets/offline_gate.dart';
import 'package:bafo/features/participant/presentation/widgets/result_panel.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Builds the page of a viewer these screens do not serve (the issuer),
/// once the projection says who is looking (SCREENS.md CD1).
typedef CompetitionViewBuilder = Widget Function(
  BuildContext context,
  String competitionId,
);

/// `/competitions/:id` for invitees (M17) and participants (M21).
///
/// The page renders strictly from the projection the server returns. An
/// issuer who opens the same path gets [issuerView].
class ParticipantCompetitionScreen extends StatelessWidget {
  const ParticipantCompetitionScreen({
    required this.competitionId,
    required this.issuerView,
    this.openJoin = false,
    this.afterJoin,
    super.key,
  });

  final String competitionId;
  final CompetitionViewBuilder issuerView;

  /// Opens the join sheet once the invitation has loaded (list "Join").
  final bool openJoin;

  /// After a successful join, e.g. the push explainer (M50), shown once.
  final Future<void> Function(BuildContext context)? afterJoin;

  @override
  Widget build(BuildContext context) => MultiBlocProvider(
    providers: [
      BlocProvider(
        create: (context) => ParticipantCompetitionCubit(
          competitions: context.read<CompetitionsRepository>(),
          attachments: context.read<AttachmentsRepository>(),
          hub: context.read<CompetitionChannelHub>(),
          competitionId: competitionId,
          organizationId: context
              .read<SessionCubit>()
              .state
              .me
              ?.organization
              .id,
        )..load(),
      ),
      BlocProvider(
        create: (context) => InvitationActionCubit(
          invitations: context.read<InvitationsRepository>(),
        ),
      ),
    ],
    child:
        BlocBuilder<ParticipantCompetitionCubit, ParticipantCompetitionState>(
          buildWhen: (previous, current) =>
              _isIssuer(previous) != _isIssuer(current),
          builder: (context, state) => _isIssuer(state)
              ? issuerView(context, competitionId)
              : _ParticipantView(openJoin: openJoin, afterJoin: afterJoin),
        ),
  );

  static bool _isIssuer(ParticipantCompetitionState state) =>
      state is ParticipantCompetitionLoaded && state.competition.isIssuerView;
}

class _ParticipantView extends StatefulWidget {
  const _ParticipantView({required this.openJoin, this.afterJoin});

  final bool openJoin;
  final Future<void> Function(BuildContext context)? afterJoin;

  @override
  State<_ParticipantView> createState() => _ParticipantViewState();
}

class _ParticipantViewState extends State<_ParticipantView> {
  bool _joinOffered = false;

  Future<void> _join(Competition competition) => showJoinSheet(
    context,
    competition: competition,
    cubit: context.read<InvitationActionCubit>(),
  );

  Future<void> _decline(Competition competition) async {
    final invitationId = competition.invitation?.id;
    if (invitationId == null) return;
    await showDeclineSheet(
      context,
      invitationId: invitationId,
      cubit: context.read<InvitationActionCubit>(),
    );
  }

  Future<void> _accessInfo(Competition competition) async {
    final access = competition.access;
    final decline = await showAccessInfoSheet(
      context,
      state: access?.state ?? AccessState.unavailable,
      unavailableMessage: unavailableReason(
        context.l10n,
        status: competition.status,
        invitationStatus: competition.invitation?.status,
      ),
      canDecline: competition.permissions.canDecline,
    );
    if (decline && mounted) await _decline(competition);
  }

  /// The list's "Join" opens the sheet once the invitation is loaded.
  void _maybeOfferJoin(ParticipantCompetitionState state) {
    if (!widget.openJoin || _joinOffered) return;
    if (state is! ParticipantCompetitionLoaded) return;
    _joinOffered = true;
    final competition = state.competition;
    if (!competition.isInviteeView) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (competition.permissions.canJoin) {
        unawaited(_join(competition));
      } else if (competition.access?.state == AccessState.planRequired) {
        unawaited(_accessInfo(competition));
      }
    });
  }

  Future<void> _onAction(
    BuildContext context,
    InvitationActionState state,
  ) async {
    final l10n = context.l10n;
    final page = context.read<ParticipantCompetitionCubit>();
    final actions = context.read<InvitationActionCubit>();
    switch (state) {
      case InvitationJoined(:final competition):
        BafoToast.success(context, l10n.invitationsJoinSucceeded);
        actions.reset();
        await page.applyCompetition(competition);
        if (context.mounted) await widget.afterJoin?.call(context);
      case InvitationDeclined():
        BafoToast.success(context, l10n.invitationsDeclineSucceeded);
        actions.reset();
        await page.refresh();
      case InvitationActionFailed(:final error) when state.meansStale:
        actions.reset();
        if (state.planRequired) {
          final current = page.state;
          await page.refresh();
          if (current is ParticipantCompetitionLoaded && context.mounted) {
            final refreshed = page.state;
            await _accessInfo(
              refreshed is ParticipantCompetitionLoaded
                  ? refreshed.competition
                  : current.competition,
            );
          }
        } else {
          BafoToast.error(context, errorMessage(l10n, error));
          await page.refresh();
        }
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocListener<InvitationActionCubit, InvitationActionState>(
      listener: (context, state) => unawaited(_onAction(context, state)),
      child: Scaffold(
        appBar: BafoAppBar(title: l10n.competitionsDetailTitle),
        body:
            BlocConsumer<
              ParticipantCompetitionCubit,
              ParticipantCompetitionState
            >(
              listener: (_, state) => _maybeOfferJoin(state),
              builder: (context, state) => switch (state) {
                ParticipantCompetitionInitial() ||
                ParticipantCompetitionLoading() => const LoadingSkeletonList(),
                ParticipantCompetitionNotFound() => NotFoundState(
                  message: l10n.competitionsDetailNotFound,
                ),
                ParticipantCompetitionFailure(:final error) => ErrorState(
                  error: error,
                  onRetry: context.read<ParticipantCompetitionCubit>().load,
                ),
                ParticipantCompetitionLoaded() => RefreshIndicator(
                  onRefresh: context
                      .read<ParticipantCompetitionCubit>()
                      .refresh,
                  child: _Loaded(
                    state: state,
                    onJoin: () => _join(state.competition),
                    onDecline: () => _decline(state.competition),
                    onAccessInfo: () => _accessInfo(state.competition),
                  ),
                ),
              },
            ),
      ),
    );
  }
}

class _Loaded extends StatelessWidget {
  const _Loaded({
    required this.state,
    required this.onJoin,
    required this.onDecline,
    required this.onAccessInfo,
  });

  final ParticipantCompetitionLoaded state;
  final VoidCallback onJoin;
  final VoidCallback onDecline;
  final VoidCallback onAccessInfo;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final competition = state.competition;
    final participant = competition.isParticipantView;
    final live = state.live;
    final description = competition.description?.trim() ?? '';

    return ListView(
      padding: BafoSpacing.pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Header(competition: competition, live: live),
        const SizedBox(height: BafoSpacing.lg),
        if (_countdown(competition, live) case final countdown?) ...[
          BafoCard(child: countdown),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (competition.isInviteeView) ...[
          _AccessStateCard(
            competition: competition,
            onJoin: onJoin,
            onDecline: onDecline,
            onAccessInfo: onAccessInfo,
          ),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (participant) ...[
          _StandingSummary(competition: competition, live: live),
          const SizedBox(height: BafoSpacing.md),
          _ParticipantLinks(competition: competition),
          const SizedBox(height: BafoSpacing.md),
          if (ResultPanel.appliesTo(competition.status)) ...[
            ResultPanel(
              status: competition.status,
              result: competition.result ?? live?.result,
              messageToWinner: state.award?.messageToWinner,
              currency: competition.currency,
            ),
            const SizedBox(height: BafoSpacing.md),
          ],
        ],
        if (competition.cancellation case final cancellation?) ...[
          InfoNotice(
            tone: StatusTone.danger,
            icon: Icons.block_rounded,
            message: l10n.competitionsDetailCancelled(cancellation.reason.name),
          ),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (competition.notAwarded case final notAwarded?) ...[
          InfoNotice(
            tone: StatusTone.neutral,
            message: l10n.competitionsDetailNotAwarded(notAwarded.reason.name),
          ),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (competition.issuer case final issuer?)
          BafoCard(
            child: Row(
              children: [
                OrgAvatar(
                  name: issuer.name,
                  logoUrl: issuer.logoUrl,
                  decorative: true,
                ),
                const SizedBox(width: BafoSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l10n.competitionsDetailIssuer,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      Text(issuer.name, style: theme.textTheme.titleSmall),
                    ],
                  ),
                ),
                if (issuer.verified)
                  StatusPill(
                    label: l10n.competitionsParticipantVerified,
                    tone: StatusTone.success,
                    icon: Icons.verified_outlined,
                  ),
              ],
            ),
          ),
        const SizedBox(height: BafoSpacing.md),
        BafoCard(
          child: KeyValueList(
            items: [
              if (competition.referenceNo != null)
                KeyValue(
                  l10n.competitionsDetailReference,
                  competition.referenceNo!,
                  ltr: true,
                ),
              if (competition.category != null)
                KeyValue(
                  l10n.competitionsDetailCategory,
                  competition.categoryOtherText ?? competition.category!.name,
                ),
              if (competition.region != null)
                KeyValue(
                  l10n.competitionsDetailRegion,
                  competition.region!.name,
                ),
            ],
          ),
        ),
        if (description.isNotEmpty) ...[
          SectionHeader(title: l10n.competitionsDetailDescription),
          Text(description, style: theme.textTheme.bodyMedium),
        ],
        if (competition.rulesSummary.isNotEmpty) ...[
          const SizedBox(height: BafoSpacing.lg),
          RulesSummaryCard(lines: competition.rulesSummary),
        ],
        SectionHeader(title: l10n.competitionsDetailSchedule),
        _ScheduleCard(competition: competition, live: live),
        SectionHeader(
          title: competition.isInviteeView
              ? l10n.invitationsDocumentsTitle
              : l10n.competitionsDetailDocuments,
        ),
        _Documents(state: state),
        const SizedBox(height: BafoSpacing.xxl),
      ],
    );
  }

  /// S3 targets: opens (scheduled), closes (live, with extensions and the
  /// latest possible close), the BAFO cutoff, or the invitee's join
  /// deadline.
  Widget? _countdown(Competition competition, ParticipantLiveSnapshot? live) {
    final schedule = competition.schedule;
    if (competition.isInviteeView) {
      final deadline =
          competition.access?.joinDeadline ?? schedule.invitationCutoffAt;
      if (competition.access?.state == AccessState.joinRequired &&
          deadline != null) {
        return _JoinDeadline(deadline: deadline);
      }
    }
    final closeAt = live?.effectiveCloseAt ?? schedule.effectiveCloseAt;
    return switch (competition.status) {
      CompetitionStatus.scheduled when schedule.biddingOpensAt != null =>
        CompetitionCountdown(
          target: CountdownTarget.opens,
          deadline: schedule.biddingOpensAt!,
        ),
      CompetitionStatus.live when closeAt != null => CompetitionCountdown(
        target: CountdownTarget.closes,
        deadline: closeAt,
        extensionCount: competition.extensionCount,
        hardStopAt: live?.hardStopAt ?? schedule.hardStopAt,
      ),
      CompetitionStatus.bafoRound
          when (live?.bafo?.cutoffAt ?? competition.bafoRound?.cutoffAt) !=
              null =>
        CompetitionCountdown(
          target: CountdownTarget.bafoCutoff,
          deadline: (live?.bafo?.cutoffAt ?? competition.bafoRound?.cutoffAt)!,
        ),
      _ => null,
    };
  }
}

/// M17: «الانضمام قبل …» as a date (Riyadh time) with the time left.
class _JoinDeadline extends StatelessWidget {
  const _JoinDeadline({required this.deadline});

  final DateTime deadline;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l10n.invitationsJoinBy(
            BafoDateFormat.deadline(deadline, context.languageCode, l10n),
          ),
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: BafoSpacing.xs),
        CountdownText(
          deadline: deadline,
          template: l10n.commonCountdownRemaining,
          style: theme.textTheme.titleMedium,
        ),
      ],
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.competition, this.live});

  final Competition competition;
  final ParticipantLiveSnapshot? live;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final access = competition.access;
    final outcome = competition.result?.outcome ?? live?.result?.outcome;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          header: true,
          child: Text(competition.title, style: theme.textTheme.titleLarge),
        ),
        const SizedBox(height: BafoSpacing.md),
        Wrap(
          spacing: BafoSpacing.xs,
          runSpacing: BafoSpacing.xs,
          children: [
            CompetitionStatusChip(
              status: live?.status ?? competition.status,
              phase: live?.phase ?? competition.phase,
              effectiveCloseAt:
                  live?.effectiveCloseAt ??
                  competition.schedule.effectiveCloseAt,
              extensionCount: competition.extensionCount,
            ),
            DirectionChip(direction: competition.direction),
            FormatChip(format: competition.format),
            if (access != null && access.state != AccessState.full)
              AccessStateChip(state: access.state),
            if (access?.feesCovered ?? false) const FeesCoveredBadge(),
            // `not_awarded` repeats the status chip.
            if (outcome == AwardOutcome.won ||
                outcome == AwardOutcome.notSelected)
              ResultChip(outcome: outcome!),
          ],
        ),
        if (access?.feesCovered ?? false) ...[
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.sponsorshipCoveredBy(access!.sponsorName ?? ''),
            style: theme.textTheme.bodySmall,
          ),
        ],
      ],
    );
  }
}

/// M17 `AccessStateCard` (W14 table): what the invitee can do, from
/// `access` and `permissions` only.
class _AccessStateCard extends StatelessWidget {
  const _AccessStateCard({
    required this.competition,
    required this.onJoin,
    required this.onDecline,
    required this.onAccessInfo,
  });

  final Competition competition;
  final VoidCallback onJoin;
  final VoidCallback onDecline;
  final VoidCallback onAccessInfo;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final access = competition.access;
    final permissions = competition.permissions;
    final invitation = competition.invitation;
    final issuer = competition.issuer?.name;
    final state = access?.state ?? AccessState.unavailable;
    final offline = watchOffline(context);

    final Widget body = switch (state) {
      AccessState.joinRequired when access?.feesCovered ?? false => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const FeesCoveredBadge(),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.invitationsSponsoredOnly(access?.sponsorName ?? issuer ?? ''),
            style: theme.textTheme.bodyMedium,
          ),
        ],
      ),
      AccessState.joinRequired => Text(
        l10n.invitationsOwnPlan,
        style: theme.textTheme.bodyMedium,
      ),
      AccessState.planRequired => InfoNotice(
        tone: StatusTone.warning,
        icon: Icons.workspace_premium_outlined,
        message: l10n.invitationsPlanRequiredBody,
      ),
      _ => InfoNotice(
        tone: StatusTone.neutral,
        message: unavailableReason(
          l10n,
          status: competition.status,
          invitationStatus: invitation?.status,
        ),
      ),
    };

    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    l10n.invitationsCardTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ),
            ],
          ),
          if (issuer != null) ...[
            const SizedBox(height: BafoSpacing.sm),
            Text(
              l10n.invitationsInvitedBy(issuer),
              style: theme.textTheme.bodyMedium,
            ),
          ],
          if (invitation?.sentAt case final sentAt?) ...[
            const SizedBox(height: BafoSpacing.xxs),
            Text(
              l10n.invitationsSentAt(
                BafoDateFormat.dateTime(sentAt, context.languageCode),
              ),
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
          const SizedBox(height: BafoSpacing.md),
          body,
          if (state == AccessState.planRequired) ...[
            const SizedBox(height: BafoSpacing.sm),
            InfoNotice(message: l10n.billingManagedOnWeb),
          ],
          if (permissions.canJoin ||
              permissions.canDecline ||
              state == AccessState.planRequired) ...[
            const SizedBox(height: BafoSpacing.lg),
            Row(
              children: [
                if (permissions.canJoin)
                  Expanded(
                    child: BafoButton(
                      key: const Key('invitee-join'),
                      label: l10n.invitationsJoinAction,
                      onPressed: offline ? null : onJoin,
                    ),
                  )
                else if (state == AccessState.planRequired)
                  Expanded(
                    child: BafoButton.tonal(
                      label: l10n.invitationsPlanRequiredMore,
                      icon: Icons.info_outline_rounded,
                      onPressed: onAccessInfo,
                    ),
                  ),
                if (permissions.canDecline) ...[
                  const SizedBox(width: BafoSpacing.sm),
                  Expanded(
                    child: BafoButton.outline(
                      key: const Key('invitee-decline'),
                      label: l10n.invitationsDeclineAction,
                      onPressed: offline ? null : onDecline,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// M21 "my standing": the S2 standing, my current offer and the next bound,
/// from the live snapshot only.
class _StandingSummary extends StatelessWidget {
  const _StandingSummary({required this.competition, this.live});

  final Competition competition;
  final ParticipantLiveSnapshot? live;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final snapshot = live;
    final status = snapshot?.status ?? competition.status;
    final myOffer = snapshot?.myOffer;
    final sealed =
        competition.format == CompetitionFormat.sealed ||
        snapshot?.phase == CompetitionPhase.sealed;
    final alias = competition.participation?.aliasNo;

    final Widget? statusLine = switch (status) {
      CompetitionStatus.scheduled => InfoNotice(
        tone: StatusTone.info,
        message: l10n.competitionsParticipantScheduled,
      ),
      CompetitionStatus.live when sealed => InfoNotice(
        tone: StatusTone.neutral,
        icon: Icons.lock_outline_rounded,
        message: myOffer == null ? l10n.liveSealedNoOffer : l10n.liveSealedBody,
      ),
      CompetitionStatus.live => StandingBanner(
        direction: competition.direction,
        hasOffer: myOffer != null,
        isLeading: snapshot?.isLeading,
        rank: snapshot?.rank,
        rankedCount: snapshot?.rankedCount,
      ),
      CompetitionStatus.closed => InfoNotice(
        tone: StatusTone.neutral,
        icon: Icons.assignment_turned_in_outlined,
        message: l10n.competitionsParticipantEvaluation,
      ),
      CompetitionStatus.bafoRound when snapshot?.bafo?.shortlisted ?? false =>
        InfoNotice(
          tone: StatusTone.inverse,
          icon: Icons.workspace_premium_outlined,
          message: snapshot!.bafo!.submitted
              ? l10n.bafoSubmitted
              : l10n.bafoInvite(
                  snapshot.bafo!.cutoffAt == null
                      ? ''
                      : BafoDateFormat.deadline(
                          snapshot.bafo!.cutoffAt!,
                          context.languageCode,
                          l10n,
                        ),
                ),
        ),
      CompetitionStatus.bafoRound => InfoNotice(
        tone: StatusTone.neutral,
        message: l10n.liveComposerNotShortlisted,
      ),
      _ => null,
    };

    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(
            header: true,
            child: Text(
              l10n.competitionsParticipantStandingTitle,
              style: theme.textTheme.titleSmall,
            ),
          ),
          if (alias != null) ...[
            const SizedBox(height: BafoSpacing.xxs),
            Text(
              l10n.competitionsParticipantAlias(alias),
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
          if (statusLine != null) ...[
            const SizedBox(height: BafoSpacing.md),
            statusLine,
          ],
          const SizedBox(height: BafoSpacing.md),
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.competitionsParticipantCurrentOffer,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
              if (myOffer != null)
                Ltr(
                  child: MoneyText(
                    Money(myOffer.amountMinor, currency: competition.currency),
                    style: theme.textTheme.titleMedium,
                  ),
                )
              else
                Text('—', style: theme.textTheme.titleMedium),
            ],
          ),
          if (snapshot?.requiredNextAmountMinor case final next?)
            if (snapshot!.acceptingOffers) ...[
              const SizedBox(height: BafoSpacing.sm),
              Text(
                l10n.offersHintRequiredNext(
                  competition.direction.wire,
                  formatAmountIsolated(
                    context,
                    next,
                    currency: competition.currency,
                  ),
                ),
                style: theme.textTheme.bodySmall,
              ),
            ],
        ],
      ),
    );
  }
}

class _ParticipantLinks extends StatelessWidget {
  const _ParticipantLinks({required this.competition});

  final Competition competition;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final id = competition.id;
    final liveOpen =
        competition.status == CompetitionStatus.live ||
        competition.status == CompetitionStatus.bafoRound;
    final live = liveOpen
        ? BafoButton(
            key: const Key('open-live-room'),
            label: l10n.competitionsParticipantLiveRoom,
            icon: Icons.bolt_rounded,
            expand: true,
            onPressed: () => context.push(ParticipantPaths.live(id)),
          )
        : BafoButton.outline(
            key: const Key('open-live-room'),
            label: l10n.competitionsParticipantLiveRoom,
            icon: Icons.bolt_rounded,
            expand: true,
            onPressed: () => context.push(ParticipantPaths.live(id)),
          );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        live,
        const SizedBox(height: BafoSpacing.sm),
        Row(
          children: [
            Expanded(
              child: BafoButton.outline(
                key: const Key('open-qa'),
                label: l10n.competitionsParticipantQa,
                icon: Icons.forum_outlined,
                onPressed: () => context.push(ParticipantPaths.qa(id)),
              ),
            ),
            const SizedBox(width: BafoSpacing.sm),
            Expanded(
              child: BafoButton.outline(
                key: const Key('open-my-offers'),
                label: l10n.competitionsParticipantMyOffers,
                icon: Icons.receipt_long_outlined,
                onPressed: () => context.push(ParticipantPaths.myOffers(id)),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.competition, this.live});

  final Competition competition;
  final ParticipantLiveSnapshot? live;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final language = context.languageCode;
    final schedule = competition.schedule;
    String? date(DateTime? value) =>
        value == null ? null : BafoDateFormat.deadline(value, language, l10n);
    final opens = date(live?.biddingOpensAt ?? schedule.biddingOpensAt);
    final finalWindow = date(schedule.finalWindowStartsAt);
    final closes = date(live?.effectiveCloseAt ?? schedule.effectiveCloseAt);
    final joinBy = date(schedule.invitationCutoffAt);
    return BafoCard(
      child: KeyValueList(
        items: [
          if (opens != null) KeyValue(l10n.competitionsScheduleOpensAt, opens),
          if (finalWindow != null)
            KeyValue(l10n.competitionsScheduleFinalWindow, finalWindow),
          if (closes != null)
            KeyValue(l10n.competitionsScheduleClosesAt, closes),
          if (joinBy != null && competition.isInviteeView)
            KeyValue(l10n.competitionsScheduleJoinDeadline, joinBy),
        ],
      ),
    );
  }
}

class _Documents extends StatelessWidget {
  const _Documents({required this.state});

  final ParticipantCompetitionLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    if (state.attachmentsError != null) {
      return ErrorState(
        error: state.attachmentsError,
        onRetry: context.read<ParticipantCompetitionCubit>().refresh,
      );
    }
    if (state.attachments.isEmpty) {
      return Text(
        l10n.competitionsDetailNoDocuments,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      );
    }
    return BafoCard(
      padding: const EdgeInsetsDirectional.symmetric(
        horizontal: BafoSpacing.sm,
      ),
      child: Column(
        children: [
          for (final (index, attachment) in state.attachments.indexed) ...[
            if (index > 0) const Divider(),
            AttachmentTile(attachment: attachment),
          ],
        ],
      ),
    );
  }
}
