import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/features/participant/presentation/invitation_action_cubit.dart';
import 'package:bafo/features/participant/presentation/participating_list_bloc.dart';
import 'package:bafo/features/participant/presentation/widgets/invitation_sheets.dart';
import 'package:bafo/features/participant/presentation/widgets/participant_competition_card.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M16 «مشاركاتي»: competitions the organisation is invited to or takes
/// part in, with the Active / Ended / All segments, a direction filter and
/// title search. Refetched on resume and after a join or decline.
class ParticipatingListScreen extends StatelessWidget {
  const ParticipatingListScreen({super.key});

  @override
  Widget build(BuildContext context) => MultiBlocProvider(
    providers: [
      BlocProvider(
        create: (context) => ParticipatingListBloc(
          competitions: context.read<CompetitionsRepository>(),
        )..add(const ParticipatingListStarted()),
      ),
      BlocProvider(
        create: (context) => InvitationActionCubit(
          invitations: context.read<InvitationsRepository>(),
        ),
      ),
    ],
    child: const _ParticipatingListView(),
  );
}

class _ParticipatingListView extends StatefulWidget {
  const _ParticipatingListView();

  @override
  State<_ParticipatingListView> createState() => _ParticipatingListViewState();
}

class _ParticipatingListViewState extends State<_ParticipatingListView> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(
      onResume: () => context.read<ParticipatingListBloc>().add(
        const ParticipatingListRefreshed(),
      ),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    final bloc = context.read<ParticipatingListBloc>()
      ..add(const ParticipatingListRefreshed());
    await bloc.stream.firstWhere(
      (state) => state is! ParticipatingListLoaded || !state.refreshing,
    );
  }

  Future<void> _open(CompetitionListItem item, {bool join = false}) async {
    await context.push(
      join
          ? ParticipantPaths.join(item.id)
          : ParticipantPaths.competition(item.id),
    );
    if (mounted) {
      context.read<ParticipatingListBloc>().add(
        const ParticipatingListRefreshed(),
      );
    }
  }

  Future<void> _decline(CompetitionListItem item) async {
    final invitationId = item.invitationId;
    if (invitationId == null) return;
    await showDeclineSheet(
      context,
      invitationId: invitationId,
      cubit: context.read<InvitationActionCubit>(),
    );
  }

  Future<void> _accessInfo(CompetitionListItem item) async {
    final decline = await showAccessInfoSheet(
      context,
      state: item.access?.state ?? AccessState.unavailable,
      canDecline: item.invitationId != null,
    );
    if (decline && mounted) await _decline(item);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocListener<InvitationActionCubit, InvitationActionState>(
      listener: (context, state) {
        switch (state) {
          case InvitationDeclined():
            BafoToast.success(context, l10n.invitationsDeclineSucceeded);
            context.read<ParticipatingListBloc>().add(
              const ParticipatingListRefreshed(),
            );
            context.read<InvitationActionCubit>().reset();
          case InvitationActionFailed(:final error) when state.meansStale:
            BafoToast.error(context, errorMessage(l10n, error));
            context.read<ParticipatingListBloc>().add(
              const ParticipatingListRefreshed(),
            );
            context.read<InvitationActionCubit>().reset();
          default:
            break;
        }
      },
      child: Scaffold(
        appBar: BafoAppBar(title: l10n.navCompetitions),
        body: Column(
          children: [
            const _Filters(),
            Expanded(
              child: BlocBuilder<ParticipatingListBloc, ParticipatingListState>(
                builder: (context, state) => switch (state) {
                  ParticipatingListInitial() ||
                  ParticipatingListLoading() => const LoadingSkeletonList(),
                  ParticipatingListFailure(:final error) => ErrorState(
                    error: error,
                    onRetry: () => context.read<ParticipatingListBloc>().add(
                      const ParticipatingListStarted(),
                    ),
                  ),
                  ParticipatingListLoaded() => RefreshIndicator(
                    onRefresh: _refresh,
                    child: _List(
                      state: state,
                      onOpen: _open,
                      onJoin: (item) => _open(item, join: true),
                      onDecline: _decline,
                      onAccessInfo: _accessInfo,
                    ),
                  ),
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Filters extends StatelessWidget {
  const _Filters();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final filter = context.select<ParticipatingListBloc, ParticipatingFilter>(
      (bloc) => bloc.state.filter,
    );
    final bloc = context.read<ParticipatingListBloc>();
    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.sm,
        BafoSpacing.page,
        BafoSpacing.xs,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SegmentedFilter<CompetitionStatusGroup>(
            segments: [
              FilterSegment(
                value: CompetitionStatusGroup.active,
                label: l10n.competitionsParticipatingFilterActive,
              ),
              FilterSegment(
                value: CompetitionStatusGroup.ended,
                label: l10n.competitionsParticipatingFilterEnded,
              ),
              FilterSegment(
                value: CompetitionStatusGroup.all,
                label: l10n.competitionsParticipatingFilterAll,
              ),
            ],
            selected: filter.group,
            onChanged: (group) =>
                bloc.add(ParticipatingListGroupChanged(group)),
          ),
          const SizedBox(height: BafoSpacing.sm),
          SearchField(
            hint: l10n.competitionsParticipatingSearchHint,
            initialValue: filter.query,
            // The bloc debounces (300 ms).
            debounce: Duration.zero,
            onChanged: (query) =>
                bloc.add(ParticipatingListQueryChanged(query)),
          ),
          const SizedBox(height: BafoSpacing.sm),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final direction in [
                  null,
                  Direction.tender,
                  Direction.auction,
                ])
                  Padding(
                    padding: const EdgeInsetsDirectional.only(
                      end: BafoSpacing.xs,
                    ),
                    child: ChoiceChip(
                      label: Text(
                        direction == null
                            ? l10n.competitionsParticipatingDirectionAll
                            : l10n.competitionsDirectionLabel(direction.wire),
                      ),
                      avatar: direction == null
                          ? null
                          : Icon(
                              direction == Direction.tender
                                  ? Icons.south_rounded
                                  : Icons.north_rounded,
                              size: 16,
                            ),
                      showCheckmark: false,
                      selected: filter.direction == direction,
                      onSelected: (_) => bloc.add(
                        ParticipatingListDirectionChanged(direction),
                      ),
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

class _List extends StatefulWidget {
  const _List({
    required this.state,
    required this.onOpen,
    required this.onJoin,
    required this.onDecline,
    required this.onAccessInfo,
  });

  final ParticipatingListLoaded state;
  final ValueChanged<CompetitionListItem> onOpen;
  final ValueChanged<CompetitionListItem> onJoin;
  final ValueChanged<CompetitionListItem> onDecline;
  final ValueChanged<CompetitionListItem> onAccessInfo;

  @override
  State<_List> createState() => _ListState();
}

class _ListState extends State<_List> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_maybeLoadMore);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _maybeLoadMore() {
    if (!_scroll.hasClients) return;
    final position = _scroll.position;
    if (position.pixels >= position.maxScrollExtent - 320) {
      context.read<ParticipatingListBloc>().add(
        const ParticipatingListNextPageRequested(),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = widget.state;
    if (state.items.isEmpty) {
      final narrowed = state.filter.isNarrowed;
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: BafoSpacing.xxl),
          EmptyState(
            icon: narrowed
                ? Icons.search_off_rounded
                : Icons.mail_outline_rounded,
            title: narrowed
                ? l10n.competitionsParticipatingNoResultsTitle
                : l10n.competitionsParticipatingEmptyTitle,
            message: narrowed
                ? l10n.competitionsParticipatingNoResultsMessage
                : l10n.competitionsParticipatingEmptyMessage,
          ),
        ],
      );
    }
    final needsAction = state.needsActionCount;
    final header = needsAction > 0 ? 1 : 0;
    final footer = state.hasMore || state.loadMoreError != null ? 1 : 0;
    return ListView.separated(
      controller: _scroll,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.sm,
        BafoSpacing.page,
        BafoSpacing.xxl,
      ),
      itemCount: header + state.items.length + footer,
      separatorBuilder: (_, _) => const SizedBox(height: BafoSpacing.sm),
      itemBuilder: (context, index) {
        if (header == 1 && index == 0) {
          return InfoNotice(
            icon: Icons.mark_email_unread_outlined,
            message: l10n.competitionsParticipatingNeedsAction(needsAction),
          );
        }
        final position = index - header;
        if (position >= state.items.length) {
          return _Footer(state: state);
        }
        final item = state.items[position];
        return ParticipantCompetitionCard(
          key: ValueKey(item.id),
          item: item,
          onOpen: () => widget.onOpen(item),
          onJoin: () => widget.onJoin(item),
          onDecline: () => widget.onDecline(item),
          onAccessInfo: () => widget.onAccessInfo(item),
        );
      },
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.state});

  final ParticipatingListLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (state.loadMoreError != null) {
      return Column(
        children: [
          Text(l10n.competitionsParticipatingLoadMoreFailed),
          BafoButton.text(
            label: l10n.commonActionsRetry,
            onPressed: () => context.read<ParticipatingListBloc>().add(
              const ParticipatingListNextPageRequested(),
            ),
          ),
        ],
      );
    }
    // Loads the next page when it scrolls into view (short lists too).
    if (!state.loadingMore) {
      scheduleMicrotask(
        () => context.mounted
            ? context.read<ParticipatingListBloc>().add(
                const ParticipatingListNextPageRequested(),
              )
            : null,
      );
    }
    return Padding(
      padding: const EdgeInsets.all(BafoSpacing.lg),
      child: Center(
        child: SizedBox.square(
          dimension: 24,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            semanticsLabel: l10n.commonLoading,
          ),
        ),
      ),
    );
  }
}
