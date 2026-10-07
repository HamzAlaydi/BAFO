import 'dart:async';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/features/home/presentation/home_cubit.dart';
import 'package:bafo/features/home/presentation/home_sections.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Where the home screen links to. Competition and billing paths are the
/// canonical client routes (CONVENTIONS.md §4.3, SCREENS.md §3.1).
abstract final class HomeLinks {
  /// M34, the issuer creation wizard.
  static const String createCompetition = '${AppRoutes.myCompetitions}/new';

  /// M55, where the billing-profile fields are completed.
  static const String organization = '${AppRoutes.account}/organization?edit=1';
}

/// M14 Home: stat tiles for the organisation's roles, the plan (read-only),
/// alerts without purchase actions, recent activity and quick actions.
/// Core (RELEASE_SCOPE.md §4.1) keeps the role tiles, the alerts and the
/// quick actions only.
///
/// Refetches on pull to refresh, on resume and (debounced) when a
/// notification arrives on the user channel.
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => HomeCubit(
      home: context.read<HomeRepository>(),
      notifications: context.read<UnreadCountCubit>().notificationCreated,
    )..load(),
    child: const _HomeView(),
  );
}

class _HomeView extends StatefulWidget {
  const _HomeView();

  @override
  State<_HomeView> createState() => _HomeViewState();
}

class _HomeViewState extends State<_HomeView> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(
      onResume: () => unawaited(context.read<HomeCubit>().refresh()),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.navHome, showLogo: true),
      body: BlocBuilder<HomeCubit, HomeState>(
        builder: (context, state) => switch (state) {
          HomeLoading() => const _HomeSkeleton(),
          HomeFailure(:final error) => ErrorState(
            error: error,
            onRetry: context.read<HomeCubit>().load,
          ),
          HomeLoaded() => _HomeContent(state: state),
        },
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: BafoSpacing.pagePadding,
    physics: const NeverScrollableScrollPhysics(),
    children: [
      const LoadingSkeleton(width: 180, height: 24),
      const SizedBox(height: BafoSpacing.lg),
      for (var row = 0; row < 2; row++) ...[
        const Row(
          children: [
            Expanded(
              child: LoadingSkeleton(height: 96, radius: BafoRadii.card),
            ),
            SizedBox(width: BafoSpacing.xs),
            Expanded(
              child: LoadingSkeleton(height: 96, radius: BafoRadii.card),
            ),
          ],
        ),
        const SizedBox(height: BafoSpacing.xs),
      ],
      const SizedBox(height: BafoSpacing.lg),
      const LoadingSkeleton(height: 140, radius: BafoRadii.card),
    ],
  );
}

class _HomeContent extends StatelessWidget {
  const _HomeContent({required this.state});

  final HomeLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final home = state.home;
    final me = context.select<SessionCubit, Me?>((cubit) => cubit.state.me);
    final now = context.read<ServerClock>().now();
    // Release scope (RELEASE_SCOPE.md §4.1): core keeps the role cards and
    // the quick actions; the plan card (Account tab), recent activity, the
    // 30-day offer tile and the billing-profile alert are home extras.
    final surfaces = context.surfaces;
    final extras = surfaces.enabled(MobileSurface.homeExtras);
    final canEditOrganization =
        (me?.can(Permissions.organizationUpdate) ?? false) &&
        surfaces.enabled(MobileSurface.organizationManagement);

    final issuer = home.issuer;
    final participant = home.participant;
    final issuerTotal =
        issuer.activeCompetitions +
        issuer.draftCompetitions +
        issuer.liveNow +
        issuer.awaitingAward +
        issuer.offersReceived30d;
    final participantTotal =
        participant.pendingInvitations +
        participant.activeParticipations +
        participant.offersSubmitted30d +
        participant.awardsWon;
    // Every organisation can take part; the issuer side shows when it is
    // used or the user may create competitions (S10). Core: only when the
    // organisation can issue now (`can_issue`) or has issued before.
    final showIssuer =
        issuerTotal > 0 ||
        (extras
            ? me?.can(Permissions.competitionsCreate) ?? false
            : me?.canCreateCompetition ?? false);
    final issuerFirst = showIssuer && issuerTotal >= participantTotal;

    void goIssued() => context.go(AppRoutes.myCompetitions);
    void goParticipating() => context.go(AppRoutes.competitions);

    final issuerSection = [
      _SectionTitle(l10n.homeIssuerSectionTitle),
      HomeStatGrid(
        stats: [
          HomeStat(
            key: const Key('home.stat.activeCompetitions'),
            label: l10n.homeStatsActiveCompetitions,
            value: issuer.activeCompetitions,
            icon: Icons.campaign_outlined,
            onTap: goIssued,
          ),
          HomeStat(
            label: l10n.homeStatsLiveNow,
            value: issuer.liveNow,
            icon: Icons.radio_button_checked_rounded,
            onTap: goIssued,
          ),
          HomeStat(
            label: l10n.homeStatsAwaitingAward,
            value: issuer.awaitingAward,
            icon: Icons.emoji_events_outlined,
            onTap: goIssued,
          ),
          HomeStat(
            label: l10n.homeStatsDraftCompetitions,
            value: issuer.draftCompetitions,
            icon: Icons.edit_note_rounded,
            onTap: goIssued,
          ),
          if (extras)
            HomeStat(
              key: const Key('home.stat.offersReceived30d'),
              label: l10n.homeStatsOffersReceived30d,
              value: issuer.offersReceived30d,
              icon: Icons.local_offer_outlined,
              onTap: goIssued,
            ),
        ],
      ),
    ];
    final participantSection = [
      _SectionTitle(l10n.homeParticipantSectionTitle),
      HomeStatGrid(
        stats: [
          HomeStat(
            key: const Key('home.stat.pendingInvitations'),
            label: l10n.homeStatsPendingInvitations,
            value: participant.pendingInvitations,
            icon: Icons.mail_outline_rounded,
            onTap: goParticipating,
          ),
          HomeStat(
            label: l10n.homeStatsActiveParticipations,
            value: participant.activeParticipations,
            icon: Icons.groups_outlined,
            onTap: goParticipating,
          ),
          HomeStat(
            label: l10n.homeStatsOffersSubmitted30d,
            value: participant.offersSubmitted30d,
            icon: Icons.local_offer_outlined,
            onTap: goParticipating,
          ),
          HomeStat(
            label: l10n.homeStatsAwardsWon,
            value: participant.awardsWon,
            icon: Icons.emoji_events_outlined,
            onTap: goParticipating,
          ),
        ],
      ),
    ];

    final alerts = home.alerts
        .where(
          (alert) =>
              HomeAlertCard.renders(alert.code) &&
              (extras || alert.code != HomeAlertCode.billingProfileIncomplete),
        )
        .toList();
    final name = me?.user.name.trim() ?? '';

    return RefreshIndicator(
      onRefresh: context.read<HomeCubit>().refresh,
      child: ListView(
        key: const Key('home.list'),
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.page,
          BafoSpacing.lg,
          BafoSpacing.page,
          BafoSpacing.xxl,
        ),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Semantics(
            header: true,
            child: Text(
              name.isEmpty
                  ? l10n.homeGreetingFallback
                  : l10n.homeGreeting(isolate(name)),
              style: theme.textTheme.headlineSmall,
            ),
          ),
          if (me != null) ...[
            const SizedBox(height: BafoSpacing.xxs),
            Text(
              me.organization.name,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
          if (state.refreshError != null) ...[
            const SizedBox(height: BafoSpacing.md),
            InfoNotice(
              tone: StatusTone.warning,
              icon: Icons.sync_problem_rounded,
              title: l10n.homeRefreshFailed,
              message: errorMessage(l10n, state.refreshError),
            ),
          ],
          for (final alert in alerts) ...[
            const SizedBox(height: BafoSpacing.md),
            HomeAlertCard(
              alert: alert,
              onCompleteProfile: canEditOrganization
                  ? () => context.go(HomeLinks.organization)
                  : null,
            ),
          ],
          const SizedBox(height: BafoSpacing.sm),
          if (issuerFirst) ...[
            ...issuerSection,
            ...participantSection,
          ] else ...[
            ...participantSection,
            if (showIssuer) ...issuerSection,
          ],
          _SectionTitle(l10n.homeQuickActionsTitle),
          _QuickActions(me: me, issuerFirst: issuerFirst),
          if (extras) ...[
            const SizedBox(height: BafoSpacing.lg),
            HomeSubscriptionCard(
              subscription: home.subscription,
              teamMembers: home.teamMembers,
              seatsTotal: home.seatsTotal,
              onOpen: () => context.push(AppRoutes.billing),
            ),
            _SectionTitle(l10n.homeActivityTitle),
            if (home.activities.isEmpty)
              Padding(
                key: const Key('home.activity'),
                padding: const EdgeInsetsDirectional.symmetric(
                  vertical: BafoSpacing.md,
                ),
                child: Text(
                  l10n.homeActivityEmpty,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              )
            else
              BafoCard(
                key: const Key('home.activity'),
                padding: const EdgeInsetsDirectional.symmetric(
                  vertical: BafoSpacing.xs,
                ),
                child: Column(
                  children: [
                    for (final (index, activity)
                        in home.activities.indexed) ...[
                      if (index > 0) const Divider(height: 1),
                      ActivityTile(
                        activity: activity,
                        now: now,
                        onTap: _activityTarget(activity) == null
                            ? null
                            : () => context.push(_activityTarget(activity)!),
                      ),
                    ],
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }

  /// Where an activity row leads. Awards carry the award id, not the
  /// competition id, so they have no target (handoff A-7).
  static String? _activityTarget(Activity activity) {
    final id = activity.subjectId;
    return switch (activity.subjectType) {
      'competition' when id != null && id.isNotEmpty => AppRoutes.competition(
        id,
      ),
      'subscription' => AppRoutes.billing,
      _ => null,
    };
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(
      top: BafoSpacing.xl,
      bottom: BafoSpacing.sm,
    ),
    child: Semantics(
      header: true,
      child: Text(title, style: Theme.of(context).textTheme.titleMedium),
    ),
  );
}

/// Quick actions by role (S10): creating needs `competitions.create` **and**
/// `can_issue`; without a plan the entitlement text shows instead of the
/// button, with no purchase action.
class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.me, required this.issuerFirst});

  final Me? me;
  final bool issuerFirst;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final user = me;
    final mayCreate = user?.can(Permissions.competitionsCreate) ?? false;
    final canIssue = user?.canCreateCompetition ?? false;

    final create = !mayCreate
        ? null
        : canIssue
        ? BafoButton(
            key: const Key('home.createCompetition'),
            label: l10n.homeActionCreateCompetition,
            icon: Icons.add_rounded,
            expand: true,
            onPressed: () => context.go(HomeLinks.createCompetition),
          )
        : InfoNotice(
            key: const Key('home.createNeedsPlan'),
            tone: StatusTone.warning,
            icon: Icons.workspace_premium_outlined,
            title: l10n.homeCreateNeedsPlan,
            message: l10n.billingManagedOnWeb,
          );
    final participating = issuerFirst
        ? BafoButton.outline(
            label: l10n.homeActionParticipating,
            icon: Icons.local_offer_outlined,
            expand: true,
            onPressed: () => context.go(AppRoutes.competitions),
          )
        : BafoButton(
            label: l10n.homeActionParticipating,
            icon: Icons.local_offer_outlined,
            expand: true,
            onPressed: () => context.go(AppRoutes.competitions),
          );
    final issued = BafoButton.outline(
      label: l10n.homeActionMyCompetitions,
      icon: Icons.campaign_outlined,
      expand: true,
      onPressed: () => context.go(AppRoutes.myCompetitions),
    );

    final ordered = issuerFirst
        ? <Widget>[?create, issued, participating]
        : <Widget>[participating, ?create, if (mayCreate) issued];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (index, action) in ordered.indexed) ...[
          if (index > 0) const SizedBox(height: BafoSpacing.sm),
          action,
        ],
      ],
    );
  }
}
