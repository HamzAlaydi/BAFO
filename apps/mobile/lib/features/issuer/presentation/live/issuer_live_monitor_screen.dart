import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_bloc.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M46 (`/competitions/:id/live` for the issuer): the read-only live
/// monitor — countdown and extensions, the leading offer, the reserve
/// indicator, the ranking as projected (sealed lock before unlock),
/// metrics, online count and the connection state. No actions (CD12).
class IssuerLiveMonitorScreen extends StatelessWidget {
  const IssuerLiveMonitorScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => IssuerLiveBloc(
      competitions: context.read<CompetitionsRepository>(),
      live: context.read<LiveRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      now: context.read<ServerClock>().now,
      competitionId: competitionId,
    )..add(const IssuerLiveOpened()),
    child: const _LiveView(),
  );
}

class _LiveView extends StatelessWidget {
  const _LiveView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<IssuerLiveBloc, IssuerLiveState>(
      listenWhen: (_, current) => current is IssuerLiveNotIssuer,
      listener: (context, state) {
        if (state is IssuerLiveNotIssuer) {
          leaveToDetail(context, state.competition.id);
        }
      },
      builder: (context, state) {
        final bloc = context.read<IssuerLiveBloc>();
        return Scaffold(
          appBar: BafoAppBar(
            title: l10n.issuerLiveTitle,
            actions: [
              if (state is IssuerLiveLoaded &&
                  state.competition.status != CompetitionStatus.draft)
                Padding(
                  padding: const EdgeInsetsDirectional.only(
                    end: BafoSpacing.lg,
                  ),
                  child: Center(
                    child: switch (state.connection) {
                      LiveConnection.connected ||
                      LiveConnection.connecting => LiveConnectionBanner(
                        connection: state.connection,
                        pollSeconds: 0,
                      ),
                      _ => const SizedBox.shrink(),
                    },
                  ),
                ),
            ],
          ),
          body: switch (state) {
            IssuerLiveLoading() ||
            IssuerLiveNotIssuer() => const LoadingSkeletonList(),
            IssuerLiveNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            IssuerLiveFailure(:final error) => ErrorState(
              error: error,
              onRetry: () => bloc.add(const IssuerLiveOpened()),
            ),
            IssuerLiveLoaded() => RefreshIndicator(
              onRefresh: () async => bloc.add(const IssuerLiveResumed()),
              child: _Monitor(state: state),
            ),
          },
        );
      },
    );
  }
}

class _Monitor extends StatelessWidget {
  const _Monitor({required this.state});

  final IssuerLiveLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final competition = state.competition;
    final snapshot = state.snapshot;
    if (competition.status == CompetitionStatus.draft) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          EmptyState(
            icon: Icons.monitor_heart_outlined,
            title: l10n.issuerLiveDraftTitle,
            message: l10n.issuerLiveDraftMessage,
          ),
        ],
      );
    }
    final metrics = snapshot?.metrics;
    final closeAt =
        snapshot?.effectiveCloseAt ?? competition.schedule.effectiveCloseAt;
    final slowPoll =
        closeAt == null ||
        closeAt.difference(context.read<ServerClock>().now()) >=
            const Duration(minutes: 5);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: BafoSpacing.pagePadding,
      children: [
        IssuerCompetitionHeader(
          competition: competition,
          effectiveCloseAt: snapshot?.effectiveCloseAt,
          extensionCount: snapshot?.extensionCount,
        ),
        if (state.connection == LiveConnection.reconnecting ||
            state.connection == LiveConnection.polling) ...[
          const SizedBox(height: BafoSpacing.md),
          LiveConnectionBanner(
            connection: state.connection,
            pollSeconds: slowPoll ? 10 : 3,
          ),
        ],
        if (state.snapshotError != null && snapshot == null) ...[
          const SizedBox(height: BafoSpacing.md),
          IssuerErrorNotice(error: state.snapshotError!),
        ],
        const SizedBox(height: BafoSpacing.lg),
        _Clock(state: state),
        if (snapshot != null) ...[
          const SizedBox(height: BafoSpacing.md),
          Semantics(
            label: l10n.issuerLiveOnline(snapshot.onlineParticipantsCount),
            excludeSemantics: true,
            child: Row(
              children: [
                Icon(
                  Icons.people_alt_outlined,
                  size: 18,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: BafoSpacing.xs),
                Expanded(
                  child: Text(
                    l10n.issuerLiveOnline(snapshot.onlineParticipantsCount),
                  ),
                ),
              ],
            ),
          ),
          if (snapshot.lastChange.kind == LastChangeKind.extension &&
              snapshot.lastChange.reason != null) ...[
            const SizedBox(height: BafoSpacing.md),
            InfoNotice(
              message: l10n.issuerLiveExtended(
                snapshot.lastChange.reason!.wire,
              ),
              icon: Icons.update_rounded,
            ),
          ],
          if (snapshot.bafo != null) ...[
            const SizedBox(height: BafoSpacing.md),
            _BafoCard(bafo: snapshot.bafo!),
          ],
          const SizedBox(height: BafoSpacing.md),
          _LeaderCard(competition: competition, snapshot: snapshot),
          if (metrics != null) ...[
            const SizedBox(height: BafoSpacing.md),
            MetricGrid(
              tiles: [
                MetricTile(
                  label: l10n.issuerCountOffers,
                  value: '${metrics.offersCount}',
                ),
                MetricTile(
                  label: l10n.issuerCountJoined,
                  value: '${metrics.participantsJoined}',
                ),
                MetricTile(
                  label: l10n.issuerCountWithOffers,
                  value: '${metrics.participantsWithOffers}',
                ),
                MetricTile(
                  label: l10n.issuerCountInvitations,
                  value: '${metrics.invitationsCount}',
                ),
              ],
            ),
          ],
          // Core (RELEASE_SCOPE.md §4.1): the ranking, without the offers
          // log (M47) behind it.
          SectionHeader(
            title: l10n.issuerLiveRankingTitle,
            actionLabel: context.surfaces.enabled(MobileSurface.issuerOffersLog)
                ? l10n.issuerNavOffers
                : null,
            onAction: () => context.push(IssuerPaths.offers(competition.id)),
          ),
          if (snapshot.ranking.isEmpty)
            Text(l10n.issuerLiveNoParticipants)
          else
            for (final entry in snapshot.ranking)
              Padding(
                padding: const EdgeInsetsDirectional.only(
                  bottom: BafoSpacing.sm,
                ),
                child: RankingTile(
                  entry: entry,
                  currency: competition.currency,
                ),
              ),
        ],
        if (competition.permissions.canExtend &&
            context.flags.enabled(Feature.extendCompetition)) ...[
          const SizedBox(height: BafoSpacing.md),
          WebOnlyNotice(message: l10n.issuerLiveExtendOnWeb),
        ],
        const SizedBox(height: BafoSpacing.xxl),
      ],
    );
  }
}

/// The countdown for the current status (S3 targets).
class _Clock extends StatelessWidget {
  const _Clock({required this.state});

  final IssuerLiveLoaded state;

  @override
  Widget build(BuildContext context) {
    final competition = state.competition;
    final snapshot = state.snapshot;
    final schedule = competition.schedule;
    final opensAt = snapshot?.biddingOpensAt ?? schedule.biddingOpensAt;
    final closeAt = snapshot?.effectiveCloseAt ?? schedule.effectiveCloseAt;
    final status = snapshot?.status ?? competition.status;
    final bafoCutoff =
        snapshot?.bafo?.cutoffAt ?? competition.bafoRound?.cutoffAt;
    final Widget? countdown = switch (status) {
      CompetitionStatus.scheduled when opensAt != null => CompetitionCountdown(
        target: CountdownTarget.opens,
        deadline: opensAt,
      ),
      CompetitionStatus.live when closeAt != null => CompetitionCountdown(
        target: CountdownTarget.closes,
        deadline: closeAt,
        extensionCount: snapshot?.extensionCount ?? schedule.extensionCount,
        hardStopAt: snapshot?.hardStopAt ?? schedule.hardStopAt,
        announce: true,
      ),
      CompetitionStatus.bafoRound when bafoCutoff != null =>
        CompetitionCountdown(
          target: CountdownTarget.bafoCutoff,
          deadline: bafoCutoff,
        ),
      _ => null,
    };
    if (countdown == null) return const SizedBox.shrink();
    return BafoCard(child: countdown);
  }
}

class _BafoCard extends StatelessWidget {
  const _BafoCard({required this.bafo});

  final IssuerBafoProgress bafo;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final inverse = context.semanticColors.inverse;
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
      decoration: BoxDecoration(
        color: inverse.background,
        borderRadius: BorderRadius.circular(BafoRadii.card),
      ),
      child: Row(
        children: [
          const BrandMark(size: 28),
          const SizedBox(width: BafoSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l10n.competitionsStatusBafoRound,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: inverse.foreground,
                  ),
                ),
                const SizedBox(height: BafoSpacing.xxs),
                Text(
                  l10n.issuerLiveBafoProgress(
                    bafo.submittedCount,
                    bafo.shortlistCount,
                  ),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: inverse.foreground,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The leading offer as projected, with a throttled polite announcement
/// when the leader changes (S9).
class _LeaderCard extends StatelessWidget {
  const _LeaderCard({required this.competition, required this.snapshot});

  final Competition competition;
  final IssuerLiveSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    final leader = snapshot.leader;
    final sealedLocked =
        leader == null &&
        (snapshot.phase == CompetitionPhase.sealed ||
            (competition.format == CompetitionFormat.sealed &&
                competition.schedule.offersOpenedAt == null));
    final reserveMet = snapshot.reserveMet;
    final improvement = snapshot.metrics.improvementVsStartBps;
    final direction = competition.direction.wire;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final label = leader == null
        ? null
        : participantLabel(l10n, leader.aliasNo, leader.organization.name);
    final announcement = leader == null
        ? null
        : l10n.issuerLiveLeaderAnnouncement(
            label!,
            MoneyFormat.format(
              Money(leader.amountMinor, currency: competition.currency),
              languageCode: language,
            ),
          );
    return ThrottledLiveRegion(
      message: announcement,
      child: BafoCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(
                  sealedLocked
                      ? Icons.lock_outline_rounded
                      : Icons.emoji_events_outlined,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: BafoSpacing.sm),
                Expanded(
                  child: Text(
                    l10n.issuerDetailLeadingOffer,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ],
            ),
            const SizedBox(height: BafoSpacing.md),
            if (leader != null) ...[
              AmountText(
                leader.amountMinor,
                currency: competition.currency,
                style: theme.textTheme.headlineSmall,
              ),
              const SizedBox(height: BafoSpacing.xs),
              Text(label!, style: theme.textTheme.bodyMedium),
              if (leader.acceptedAt != null)
                Text(
                  l10n.issuerLiveReceivedAt(
                    ltrIsolate(BafoDateFormat.machine(leader.acceptedAt!)),
                  ),
                  style: muted,
                ),
            ] else
              Text(
                sealedLocked
                    ? l10n.issuerLiveSealedLock
                    : l10n.issuerLiveNoOffers,
                style: theme.textTheme.bodyMedium,
              ),
            if (reserveMet != null) ...[
              const SizedBox(height: BafoSpacing.md),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: StatusPill(
                  label: reserveMet
                      ? l10n.issuerLiveReserveMet(direction)
                      : l10n.issuerLiveReserveNotMet(direction),
                  tone: reserveMet ? StatusTone.success : StatusTone.neutral,
                  icon: reserveMet
                      ? Icons.check_circle_outline_rounded
                      : Icons.radio_button_unchecked_rounded,
                ),
              ),
            ],
            if (improvement != null) ...[
              const SizedBox(height: BafoSpacing.md),
              KeyValueList(
                items: [
                  KeyValue(
                    l10n.issuerLiveImprovement(direction),
                    formatSignedBps(improvement),
                    ltr: true,
                  ),
                ],
              ),
            ],
            const SizedBox(height: BafoSpacing.sm),
            Text(l10n.commonPricesExcludeVat, style: muted),
          ],
        ),
      ),
    );
  }
}

/// A ranking row as projected: amounts and ranks are null while sealed
/// (then only "offer submitted" shows).
class RankingTile extends StatelessWidget {
  const RankingTile({required this.entry, required this.currency, super.key});

  final RankingEntry entry;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final current = entry.currentAmountMinor;
    final first = entry.firstAmountMinor;
    final rank = entry.rank;
    final lastOfferAt = entry.lastOfferAt;
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RankText(rank: rank),
          const SizedBox(width: BafoSpacing.xs),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  participantLabel(
                    l10n,
                    entry.aliasNo,
                    entry.organization.name,
                  ),
                  style: theme.textTheme.titleSmall,
                ),
                const SizedBox(height: BafoSpacing.xxs),
                if (current != null)
                  AmountText(
                    current,
                    currency: currency,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  Text(
                    entry.submitted
                        ? l10n.issuerLiveSubmitted
                        : l10n.issuerLiveNotSubmitted,
                    style: theme.textTheme.bodyMedium,
                  ),
                if (first != null)
                  Text(
                    l10n.issuerLiveFirstOffer(
                      ltrIsolate(
                        MoneyFormat.format(
                          Money(first, currency: currency),
                          languageCode: context.languageCode,
                        ),
                      ),
                    ),
                    style: muted,
                  ),
                Text(
                  [
                    l10n.issuerLiveOffersCount(entry.offersCount),
                    if (lastOfferAt != null)
                      l10n.issuerLiveLastOffer(
                        BafoDateFormat.time(lastOfferAt, context.languageCode),
                      ),
                  ].join(' · '),
                  style: muted,
                ),
                if (entry.bafo.shortlisted || entry.isLeader == true) ...[
                  const SizedBox(height: BafoSpacing.xs),
                  Wrap(
                    spacing: BafoSpacing.xs,
                    runSpacing: BafoSpacing.xs,
                    children: [
                      if (entry.isLeader == true)
                        StatusPill(
                          label: l10n.liveBadgeLeading,
                          tone: StatusTone.leading,
                          icon: Icons.check_circle_rounded,
                        ),
                      if (entry.bafo.shortlisted)
                        StatusPill(
                          label: entry.bafo.submitted
                              ? l10n.issuerLiveBafoSubmitted
                              : l10n.issuerLiveBafoShortlisted,
                          tone: StatusTone.inverse,
                          icon: Icons.workspace_premium_outlined,
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
