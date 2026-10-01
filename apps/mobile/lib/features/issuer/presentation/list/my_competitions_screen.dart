import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/list/issued_list_bloc.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M33 "My competitions" (`/my-competitions`): Active / Drafts / Ended,
/// title search, infinite scroll, and "New competition" when the user may
/// create one (S10: `competitions.create` and `can_issue`).
class MyCompetitionsScreen extends StatelessWidget {
  const MyCompetitionsScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) =>
        IssuedListBloc(competitions: context.read<CompetitionsRepository>())
          ..add(const IssuedListStarted()),
    child: const _MyCompetitionsView(),
  );
}

class _MyCompetitionsView extends StatefulWidget {
  const _MyCompetitionsView();

  @override
  State<_MyCompetitionsView> createState() => _MyCompetitionsViewState();
}

class _MyCompetitionsViewState extends State<_MyCompetitionsView> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    // Refetch the visible list on resume (SCREENS.md §3.5).
    _lifecycle = AppLifecycleListener(
      onResume: () =>
          context.read<IssuedListBloc>().add(const IssuedListRefreshed()),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _open(String location) async {
    await context.push(location);
    if (mounted) {
      context.read<IssuedListBloc>().add(const IssuedListRefreshed());
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final me = context.select<SessionCubit, Me?>((cubit) => cubit.state.me);
    final mayCreate = me?.can(Permissions.competitionsCreate) ?? false;
    final canIssue = me?.canCreateCompetition ?? false;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.navMyCompetitions),
      floatingActionButton: canIssue
          ? FloatingActionButton.extended(
              onPressed: () => _open(IssuerPaths.create),
              icon: const Icon(Icons.add_rounded),
              label: Text(l10n.issuerListCreate),
            )
          : null,
      body: BlocBuilder<IssuedListBloc, IssuedListState>(
        builder: (context, state) {
          final bloc = context.read<IssuedListBloc>();
          return Column(
            children: [
              Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(
                  BafoSpacing.page,
                  BafoSpacing.md,
                  BafoSpacing.page,
                  0,
                ),
                child: Column(
                  children: [
                    if (mayCreate && !canIssue) ...[
                      InfoNotice(
                        title: l10n.issuerPlanRequired,
                        message: l10n.billingManagedOnWeb,
                        tone: StatusTone.warning,
                        icon: Icons.workspace_premium_outlined,
                      ),
                      const SizedBox(height: BafoSpacing.md),
                    ],
                    SegmentedFilter<CompetitionStatusGroup>(
                      segments: [
                        FilterSegment(
                          value: CompetitionStatusGroup.active,
                          label: l10n.issuerListSegmentActive,
                        ),
                        FilterSegment(
                          value: CompetitionStatusGroup.draft,
                          label: l10n.issuerListSegmentDrafts,
                        ),
                        FilterSegment(
                          value: CompetitionStatusGroup.ended,
                          label: l10n.issuerListSegmentEnded,
                        ),
                      ],
                      selected: state.group,
                      onChanged: (group) =>
                          bloc.add(IssuedListFilterChanged(group)),
                    ),
                    const SizedBox(height: BafoSpacing.sm),
                    SearchField(
                      hint: l10n.issuerListSearchHint,
                      initialValue: state.query,
                      onChanged: (value) =>
                          bloc.add(IssuedListQueryChanged(value)),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: BafoSpacing.sm),
              Expanded(
                child: switch (state) {
                  IssuedListInitial() ||
                  IssuedListLoading() => const LoadingSkeletonList(),
                  IssuedListFailure(:final error) => ErrorState(
                    error: error,
                    onRetry: () => bloc.add(const IssuedListStarted()),
                  ),
                  IssuedListLoaded() => RefreshIndicator(
                    onRefresh: () async {
                      bloc.add(const IssuedListRefreshed());
                      await bloc.stream.firstWhere(
                        (next) => next is! IssuedListLoaded || !next.refreshing,
                      );
                    },
                    child: state.items.isEmpty
                        ? _Empty(
                            state: state,
                            onCreate: canIssue
                                ? () => _open(IssuerPaths.create)
                                : null,
                          )
                        : _List(state: state, onOpen: _open),
                  ),
                },
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.state, this.onCreate});

  final IssuedListLoaded state;
  final VoidCallback? onCreate;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final searching = state.query.isNotEmpty;
    final (title, message) = searching
        ? (l10n.issuerListNoResultsTitle, l10n.issuerListNoResultsMessage)
        : switch (state.group) {
            CompetitionStatusGroup.draft => (
              l10n.issuerListEmptyDraftsTitle,
              l10n.issuerListEmptyDraftsMessage,
            ),
            CompetitionStatusGroup.ended => (
              l10n.issuerListEmptyEndedTitle,
              l10n.issuerListEmptyEndedMessage,
            ),
            _ => (
              l10n.issuerListEmptyActiveTitle,
              l10n.issuerListEmptyActiveMessage,
            ),
          };
    return LayoutBuilder(
      builder: (context, constraints) => SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: constraints.maxHeight),
          child: EmptyState(
            icon: searching
                ? Icons.search_off_rounded
                : Icons.campaign_outlined,
            title: title,
            message: message,
            action:
                !searching &&
                    onCreate != null &&
                    state.group != CompetitionStatusGroup.ended
                ? BafoButton(
                    label: l10n.issuerListCreate,
                    icon: Icons.add_rounded,
                    onPressed: onCreate,
                  )
                : null,
          ),
        ),
      ),
    );
  }
}

class _List extends StatelessWidget {
  const _List({required this.state, required this.onOpen});

  final IssuedListLoaded state;
  final Future<void> Function(String location) onOpen;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bloc = context.read<IssuedListBloc>();
    final footer = state.hasMore || state.loadMoreError != null;
    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification.metrics.extentAfter < 400) {
          bloc.add(const IssuedListNextPageRequested());
        }
        return false;
      },
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.page,
          BafoSpacing.sm,
          BafoSpacing.page,
          BafoSpacing.xxxl * 2,
        ),
        itemCount: state.items.length + (footer ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: BafoSpacing.sm),
        itemBuilder: (context, index) {
          if (index >= state.items.length) {
            if (state.loadMoreError != null) {
              return Center(
                child: BafoButton.text(
                  label: l10n.commonActionsRetry,
                  onPressed: () =>
                      bloc.add(const IssuedListNextPageRequested()),
                ),
              );
            }
            return const Padding(
              padding: EdgeInsets.all(BafoSpacing.lg),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          final item = state.items[index];
          return IssuerCompetitionCard(
            item: item,
            onTap: () => onOpen(IssuerPaths.competition(item.id)),
          );
        },
      ),
    );
  }
}

/// A row of M33 (the issuer variant of `CompetitionListItem`).
class IssuerCompetitionCard extends StatelessWidget {
  const IssuerCompetitionCard({
    required this.item,
    required this.onTap,
    super.key,
  });

  final CompetitionListItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final counts = item.counts;
    final reference = item.referenceNo;
    final closeAt = item.effectiveCloseAt;
    final opensAt = item.biddingOpensAt;
    final updatedAt = item.updatedAt;
    final leading = item.leadingAmountMinor;
    final isDraft = item.status == CompetitionStatus.draft;

    final Widget? timing = switch (item.status) {
      CompetitionStatus.live when closeAt != null => Wrap(
        spacing: BafoSpacing.xs,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(l10n.competitionsCountdownClosesIn, style: muted),
          CountdownText(
            deadline: closeAt,
            elapsedLabel: l10n.competitionsCountdownClosing,
            style: BafoTypography.tabular(
              theme.textTheme.bodyMedium ?? const TextStyle(),
            ),
          ),
        ],
      ),
      CompetitionStatus.scheduled when opensAt != null => Text(
        l10n.issuerListOpensAt(BafoDateFormat.dateTime(opensAt, language)),
        style: muted,
      ),
      CompetitionStatus.draft when updatedAt != null => Text(
        l10n.issuerListUpdatedAt(BafoDateFormat.longDate(updatedAt, language)),
        style: muted,
      ),
      _ => null,
    };

    return Semantics(
      button: true,
      child: BafoCard(
        onTap: onTap,
        padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              item.title,
              style: theme.textTheme.titleMedium,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              semanticsLabel: item.title,
            ),
            if (reference != null) ...[
              const SizedBox(height: BafoSpacing.xxs),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Ltr(child: Text(reference, style: muted)),
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
                  effectiveCloseAt: closeAt,
                ),
                DirectionChip(direction: item.direction, showRule: false),
                FormatChip(format: item.format),
              ],
            ),
            if (timing != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              timing,
            ],
            if (counts != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              Text(
                l10n.issuerListCounts(
                  counts.invitations,
                  counts.joined,
                  counts.participantsWithOffers,
                ),
                style: muted,
              ),
            ],
            if (!isDraft) ...[
              const SizedBox(height: BafoSpacing.xs),
              Wrap(
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text('${l10n.issuerDetailLeadingOffer}: ', style: muted),
                  if (leading != null)
                    AmountText(
                      leading,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    )
                  else
                    Text(l10n.issuerNoValue, style: muted),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
