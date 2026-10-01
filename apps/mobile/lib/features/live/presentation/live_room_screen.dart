import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/live/data/clock_sync_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/presentation/live_room_bloc.dart';
import 'package:bafo/features/live/presentation/offer_submit_cubit.dart';
import 'package:bafo/features/live/presentation/widgets/live_widgets.dart';
import 'package:bafo/features/live/presentation/widgets/offer_composer.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/features/participant/presentation/widgets/result_panel.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Builds the page of a viewer the room does not serve (the issuer's live
/// monitor, M46), once the projection says who is looking.
typedef LiveViewBuilder = Widget Function(
  BuildContext context,
  String competitionId,
);

/// M23: the participant live room (W19 participant content, one column,
/// the composer pinned above the keyboard). Invitees go back to the
/// overview; an issuer gets [issuerView].
class LiveRoomScreen extends StatelessWidget {
  const LiveRoomScreen({
    required this.competitionId,
    this.issuerView,
    this.clockSync,
    this.timings = const LiveRoomTimings(),
    super.key,
  });

  final String competitionId;
  final LiveViewBuilder? issuerView;

  /// Defaults to `GET /time` through the app's [ApiClient].
  final ClockSyncRepository? clockSync;
  final LiveRoomTimings timings;

  @override
  Widget build(BuildContext context) {
    final network = context.read<NetworkStatusCubit>();
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => LiveRoomBloc(
            competitions: context.read<CompetitionsRepository>(),
            live: context.read<LiveRepository>(),
            clockSync:
                clockSync ?? ApiClockSyncRepository(context.read<ApiClient>()),
            hub: context.read<CompetitionChannelHub>(),
            clock: context.read<ServerClock>(),
            competitionId: competitionId,
            organizationId: context
                .read<SessionCubit>()
                .state
                .me
                ?.organization
                .id,
            offline: network.stream.map((status) => status.isOffline),
            initiallyOffline: network.state.isOffline,
            timings: timings,
          )..add(const LiveRoomOpened()),
        ),
        BlocProvider(
          create: (context) {
            final room = context.read<LiveRoomBloc>();
            return OfferSubmitCubit(
              live: context.read<LiveRepository>(),
              competitionId: competitionId,
              onAccepted: (submission) =>
                  room.add(LiveRoomOfferAccepted(submission)),
              onStale: () => room.add(const LiveRoomRefreshRequested()),
            );
          },
        ),
      ],
      child: BlocBuilder<LiveRoomBloc, LiveRoomState>(
        buildWhen: (previous, current) => _issuer(previous) != _issuer(current),
        builder: (context, state) {
          final builder = issuerView;
          if (_issuer(state) && builder != null) {
            return builder(context, competitionId);
          }
          return _LiveRoomView(
            competitionId: competitionId,
            hasIssuerView: issuerView != null,
          );
        },
      ),
    );
  }

  static bool _issuer(LiveRoomState state) =>
      state is LiveRoomUnavailable && state.competition.isIssuerView;
}

class _LiveRoomView extends StatefulWidget {
  const _LiveRoomView({
    required this.competitionId,
    required this.hasIssuerView,
  });

  final String competitionId;

  /// The issuer's monitor replaces this view; without one, an issuer goes
  /// to the overview like any other non-participant.
  final bool hasIssuerView;

  @override
  State<_LiveRoomView> createState() => _LiveRoomViewState();
}

class _LiveRoomViewState extends State<_LiveRoomView> {
  late final AppLifecycleListener _lifecycle;

  /// False while another page (My offers, the rules) covers the room. The
  /// navigator turns tickers off below an opaque route; a sheet or dialog
  /// on top (the confirm sheet) keeps the room visible.
  bool _routeVisible = true;
  bool _appVisible = true;

  @override
  void initState() {
    super.initState();
    // S4 heartbeat: only while the room is on screen. Stop at once in the
    // background or under another page, restart on return.
    _lifecycle = AppLifecycleListener(
      onHide: () => _setVisible(app: false),
      onShow: () => _setVisible(app: true),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _setVisible(route: TickerMode.valuesOf(context).enabled);
  }

  void _setVisible({bool? app, bool? route}) {
    final before = _appVisible && _routeVisible;
    _appVisible = app ?? _appVisible;
    _routeVisible = route ?? _routeVisible;
    final after = _appVisible && _routeVisible;
    if (before == after) return;
    context.read<LiveRoomBloc>().add(
      after ? const LiveRoomResumed() : const LiveRoomPaused(),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    final bloc = context.read<LiveRoomBloc>()
      ..add(const LiveRoomRefreshRequested());
    await bloc.stream.firstWhere(
      (state) => state is! LiveRoomLoaded || !state.refreshing,
    );
  }

  /// S5 success: haptics, then the receipt (sealed), the BAFO line or the
  /// time-stamped toast, once the confirm sheet has closed (it closes on the
  /// same state and must not take the receipt with it).
  void _onSubmit(BuildContext context, OfferSubmitState state) {
    if (state is! OfferAccepted) return;
    unawaited(HapticFeedback.mediumImpact());
    final offer = state.submission.offer;
    final room = context.read<LiveRoomBloc>().state;
    final currency = room is LiveRoomLoaded
        ? room.competition.currency
        : Money.sar;
    context.read<OfferSubmitCubit>().acknowledge();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final l10n = this.context.l10n;
      switch (offer.stage) {
        case OfferStage.sealed:
          unawaited(showSealedReceipt(this.context, offer, currency: currency));
        case OfferStage.bafo:
          BafoToast.success(this.context, l10n.bafoSubmitted);
        default:
          BafoToast.success(
            this.context,
            l10n.offersSubmitted(
              ltrIsolate(BafoDateFormat.timeWithMillis(offer.acceptedAt)),
            ),
          );
      }
    });
  }

  void _onRoom(BuildContext context, LiveRoomState state) {
    // Invitees (and anyone else without a participation) see the overview.
    if (state is LiveRoomUnavailable &&
        !(state.competition.isIssuerView && widget.hasIssuerView)) {
      context.replace(ParticipantPaths.competition(widget.competitionId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return MultiBlocListener(
      listeners: [
        BlocListener<OfferSubmitCubit, OfferSubmitState>(listener: _onSubmit),
        BlocListener<LiveRoomBloc, LiveRoomState>(listener: _onRoom),
      ],
      child: BlocBuilder<LiveRoomBloc, LiveRoomState>(
        builder: (context, state) {
          final loaded = state is LiveRoomLoaded ? state : null;
          return Scaffold(
            appBar: BafoAppBar(
              title: l10n.liveRoomTitle,
              actions: [
                if (loaded != null &&
                    loaded.competition.rulesSummary.isNotEmpty)
                  IconButton(
                    tooltip: l10n.rulesSummaryTitle,
                    icon: const Icon(Icons.rule_rounded),
                    onPressed: () => showRulesSheet(
                      context,
                      loaded.competition.rulesSummary,
                    ),
                  ),
                if (loaded != null)
                  IconButton(
                    tooltip: l10n.liveMyOffersLink,
                    icon: const Icon(Icons.history_rounded),
                    onPressed: () => context.push(
                      ParticipantPaths.myOffers(widget.competitionId),
                    ),
                  ),
              ],
            ),
            body: switch (state) {
              LiveRoomInitial() ||
              LiveRoomLoading() ||
              LiveRoomUnavailable() => const LoadingSkeletonList(),
              LiveRoomNotFound() => NotFoundState(
                message: l10n.competitionsDetailNotFound,
              ),
              LiveRoomFailure(:final error) => ErrorState(
                error: error,
                onRetry: () =>
                    context.read<LiveRoomBloc>().add(const LiveRoomOpened()),
              ),
              LiveRoomLoaded() => Column(
                children: [
                  ConnectionBanner(
                    connection: state.connection,
                    pollInterval: state.pollInterval,
                  ),
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _refresh,
                      child: _RoomContent(room: state),
                    ),
                  ),
                  _ComposerDock(room: state),
                ],
              ),
            },
          );
        },
      ),
    );
  }
}

class _RoomContent extends StatelessWidget {
  const _RoomContent({required this.room});

  final LiveRoomLoaded room;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final competition = room.competition;
    final snapshot = room.snapshot;
    final currency = competition.currency;
    final sealed =
        competition.format == CompetitionFormat.sealed ||
        snapshot.phase == CompetitionPhase.sealed;
    final myOffer = snapshot.myOffer;
    final leading = snapshot.leadingAmountMinor;
    final ladder = snapshot.ladder;
    final bafo = snapshot.bafo;

    return ListView(
      padding: BafoSpacing.pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Header(room: room),
        if (room.extension case final notice?) ...[
          const SizedBox(height: BafoSpacing.sm),
          ExtensionNoticeBanner(notice: notice),
        ],
        if (room.slowConnection) ...[
          const SizedBox(height: BafoSpacing.sm),
          InfoNotice(
            tone: StatusTone.warning,
            icon: Icons.network_check_rounded,
            message: l10n.liveHintSlowConnection,
          ),
        ],
        if (room.finalSeconds && room.closeAt != null) ...[
          const SizedBox(height: BafoSpacing.sm),
          InfoNotice(
            tone: StatusTone.info,
            icon: Icons.dns_outlined,
            message: l10n.liveHintServerTiming,
          ),
        ],
        const SizedBox(height: BafoSpacing.md),
        ..._standing(context, sealed: sealed),
        if (snapshot.phase == CompetitionPhase.initial) ...[
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.liveInitialPhaseNote,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
        if (leading != null) ...[
          const SizedBox(height: BafoSpacing.md),
          _LeadingAmount(amountMinor: leading, currency: currency),
        ],
        if (ladder != null && ladder.isNotEmpty) ...[
          const SizedBox(height: BafoSpacing.md),
          LadderList(ladder: ladder, currency: currency),
        ],
        if (bafo != null &&
            snapshot.status == CompetitionStatus.bafoRound &&
            bafo.shortlisted) ...[
          const SizedBox(height: BafoSpacing.md),
          BafoBanner(
            bafo: bafo,
            direction: snapshot.direction,
            currency: currency,
          ),
        ],
        if (myOffer != null) ...[
          const SizedBox(height: BafoSpacing.md),
          MyOfferCard(
            offer: myOffer,
            count: snapshot.myOffersCount,
            currency: currency,
            onHistory: () =>
                context.push(ParticipantPaths.myOffers(competition.id)),
          ),
        ],
        if (ResultPanel.appliesTo(snapshot.status)) ...[
          const SizedBox(height: BafoSpacing.md),
          ResultPanel(
            status: snapshot.status,
            result: snapshot.result ?? competition.result,
            currency: currency,
          ),
        ],
        if (competition.cancellation case final cancellation?) ...[
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(
            tone: StatusTone.danger,
            icon: Icons.block_rounded,
            message: l10n.competitionsDetailCancelled(cancellation.reason.name),
          ),
        ],
        const SizedBox(height: BafoSpacing.xl),
      ],
    );
  }

  /// The S2 standing for the stage: live (banner), sealed (lock panel),
  /// BAFO (shortlisted or not), evaluation, or not open yet.
  List<Widget> _standing(BuildContext context, {required bool sealed}) {
    final l10n = context.l10n;
    final snapshot = room.snapshot;
    final Widget? widget = switch (snapshot.status) {
      CompetitionStatus.scheduled => InfoNotice(
        tone: StatusTone.info,
        icon: Icons.event_outlined,
        message: l10n.liveNotLiveYet,
      ),
      CompetitionStatus.live when sealed => SealedLockPanel(
        hasOffer: snapshot.myOffer != null,
      ),
      CompetitionStatus.live => StandingBanner(
        direction: snapshot.direction,
        hasOffer: snapshot.myOffer != null,
        isLeading: snapshot.isLeading,
        rank: snapshot.rank,
        rankedCount: snapshot.rankedCount,
      ),
      CompetitionStatus.bafoRound when !(snapshot.bafo?.shortlisted ?? false) =>
        InfoNotice(
          tone: StatusTone.neutral,
          message: l10n.liveComposerNotShortlisted,
        ),
      CompetitionStatus.closed => InfoNotice(
        tone: StatusTone.neutral,
        icon: Icons.assignment_turned_in_outlined,
        message: l10n.competitionsParticipantEvaluation,
      ),
      _ => null,
    };
    return widget == null ? const [] : [widget];
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.room});

  final LiveRoomLoaded room;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final competition = room.competition;
    final snapshot = room.snapshot;
    final closeAt = room.closeAt;
    final opensAt = snapshot.biddingOpensAt;
    final Widget? countdown = switch (snapshot.status) {
      CompetitionStatus.live when closeAt != null => CompetitionCountdown(
        target: CountdownTarget.closes,
        deadline: closeAt,
        extensionCount: snapshot.extensionCount,
        hardStopAt: snapshot.hardStopAt,
        announce: true,
      ),
      CompetitionStatus.bafoRound when closeAt != null => CompetitionCountdown(
        target: CountdownTarget.bafoCutoff,
        deadline: closeAt,
        announce: true,
      ),
      CompetitionStatus.scheduled when opensAt != null => CompetitionCountdown(
        target: CountdownTarget.opens,
        deadline: opensAt,
      ),
      _ => null,
    };
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    competition.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium,
                  ),
                ),
              ),
              const SizedBox(width: BafoSpacing.sm),
              ConnectionIndicator(connection: room.connection),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          Wrap(
            spacing: BafoSpacing.xs,
            runSpacing: BafoSpacing.xs,
            children: [
              CompetitionStatusChip(
                status: snapshot.status,
                phase: snapshot.phase,
                effectiveCloseAt: snapshot.effectiveCloseAt,
                extensionCount: snapshot.extensionCount,
              ),
              DirectionChip(direction: snapshot.direction),
              if (competition.format == CompetitionFormat.sealed)
                FormatChip(format: competition.format),
              if (competition.access?.feesCovered ?? false)
                const FeesCoveredBadge(),
            ],
          ),
          if (countdown != null) ...[
            const SizedBox(height: BafoSpacing.md),
            countdown,
          ],
        ],
      ),
    );
  }
}

/// «العرض المتصدر: {amount}» when the competition shows prices; a polite,
/// throttled live region with a short highlight on change.
class _LeadingAmount extends StatelessWidget {
  const _LeadingAmount({required this.amountMinor, required this.currency});

  final int amountMinor;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final amount = MoneyFormat.format(
      Money(amountMinor, currency: currency),
      languageCode: context.languageCode,
    );
    final label = l10n.liveLeadingAmount(ltrIsolate(amount));
    return ThrottledLiveRegion(
      label: label,
      child: HighlightOnChange(
        value: amountMinor,
        child: BafoCard(
          child: Row(
            children: [
              Icon(
                Icons.flag_outlined,
                size: 20,
                color: theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Text(
                  label,
                  style: BafoTypography.tabular(
                    theme.textTheme.titleSmall ?? const TextStyle(),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The composer pinned at the bottom, or the reason there is none.
class _ComposerDock extends StatelessWidget {
  const _ComposerDock({required this.room});

  final LiveRoomLoaded room;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final snapshot = room.snapshot;
    final Widget content;
    if (snapshot.acceptingOffers) {
      content = OfferComposer(room: room);
    } else {
      final opensAt = snapshot.biddingOpensAt;
      final String? reason = switch (snapshot.status) {
        CompetitionStatus.scheduled || CompetitionStatus.live
            when opensAt != null &&
                context.read<ServerClock>().remainingUntil(opensAt) >
                    Duration.zero =>
          null,
        CompetitionStatus.live when room.deadlinePassed =>
          l10n.competitionsCountdownClosing,
        CompetitionStatus.bafoRound when snapshot.bafo?.submitted ?? false =>
          l10n.bafoSubmitted,
        CompetitionStatus.bafoRound => l10n.liveComposerNotShortlisted,
        CompetitionStatus.live
            when room.closeAt != null &&
                context.read<ServerClock>().hasPassed(room.closeAt!) =>
          l10n.competitionsCountdownClosing,
        CompetitionStatus.live => l10n.errorsOfferNotAccepting,
        _ => l10n.liveComposerClosed,
      };
      if (reason == null && opensAt != null) {
        content = CountdownText(
          deadline: opensAt,
          style: theme.textTheme.bodyMedium,
          template: l10n.liveComposerOpensIn,
          onElapsed: () => context.read<LiveRoomBloc>().add(
            const LiveRoomRefreshRequested(),
          ),
        );
      } else {
        content = Text(
          reason ?? l10n.liveComposerClosed,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        );
      }
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        border: BorderDirectional(
          top: BorderSide(color: theme.colorScheme.outlineVariant),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(
            BafoSpacing.page,
            BafoSpacing.md,
            BafoSpacing.page,
            BafoSpacing.md,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(context).height * 0.6,
            ),
            child: SingleChildScrollView(child: content),
          ),
        ),
      ),
    );
  }
}
