import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/account/presentation/account_paths.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/team/team_cubit.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M56 Team: the seats meter and the members. Needs `team.manage` (S8
/// **F** otherwise). The owner's row and the viewer's own row are locked.
class TeamScreen extends StatelessWidget {
  const TeamScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final canManage =
        context.read<SessionCubit>().state.me?.can(Permissions.teamManage) ??
        false;
    return BlocProvider(
      create: (context) =>
          TeamCubit(team: context.read<TeamRepository>(), canManage: canManage)
            ..load(),
      child: const _TeamView(),
    );
  }
}

class _TeamView extends StatelessWidget {
  const _TeamView();

  Future<void> _open(
    BuildContext context,
    String location, {
    Object? extra,
  }) async {
    final cubit = context.read<TeamCubit>();
    final changed = await context.push<bool>(location, extra: extra);
    if (changed ?? false) await cubit.load();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = context.watch<TeamCubit>().state;
    final myMembershipId = context.select<SessionCubit, String?>(
      (cubit) => cubit.state.me?.membership.id,
    );
    return Scaffold(
      appBar: BafoAppBar(title: l10n.accountTeamTitle),
      floatingActionButton: state is TeamLoaded
          ? FloatingActionButton.extended(
              key: const Key('team.invite'),
              onPressed: () => unawaited(_open(context, AccountPaths.teamNew)),
              icon: const Icon(Icons.person_add_alt_1_outlined),
              label: Text(l10n.accountTeamInvite),
            )
          : null,
      body: switch (state) {
        TeamLoading() => const LoadingSkeletonList(),
        TeamForbidden() => const ForbiddenState(),
        TeamFailure(:final error) => ErrorState(
          error: error,
          onRetry: context.read<TeamCubit>().load,
        ),
        TeamLoaded(:final roster, :final refreshError) => RefreshIndicator(
          onRefresh: context.read<TeamCubit>().load,
          child: ListView(
            key: const Key('team.list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsetsDirectional.fromSTEB(
              BafoSpacing.page,
              BafoSpacing.lg,
              BafoSpacing.page,
              96,
            ),
            children: [
              if (refreshError != null) ...[
                InfoNotice(
                  tone: StatusTone.warning,
                  icon: Icons.sync_problem_rounded,
                  message: errorMessage(l10n, refreshError),
                ),
                const SizedBox(height: BafoSpacing.md),
              ],
              _SeatsCard(seats: roster.seats),
              const SizedBox(height: BafoSpacing.lg),
              BafoCard(
                padding: const EdgeInsetsDirectional.symmetric(
                  vertical: BafoSpacing.xs,
                ),
                child: Column(
                  children: [
                    for (final (index, member) in roster.members.indexed) ...[
                      if (index > 0) const Divider(height: 1, indent: 72),
                      _MemberTile(
                        member: member,
                        isSelf: member.id == myMembershipId,
                        onTap: () => unawaited(
                          _open(
                            context,
                            AccountPaths.teamMember(member.id),
                            extra: member,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (roster.members.length <= 1)
                EmptyState(
                  icon: Icons.group_add_outlined,
                  title: l10n.accountTeamEmptyTitle,
                  message: l10n.accountTeamEmptyMessage,
                ),
            ],
          ),
        ),
      },
    );
  }
}

class _SeatsCard extends StatelessWidget {
  const _SeatsCard({required this.seats});

  final Seats seats;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final value = l10n.billingStatusSeatsValue(seats.used, seats.total);
    return BafoCard(
      key: const Key('team.seats'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.accountTeamSeats,
                  style: theme.textTheme.titleSmall,
                ),
              ),
              Text(
                value,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          MeterBar(
            value: seats.used,
            max: seats.total,
            semanticsLabel: '${l10n.accountTeamSeats}: $value',
          ),
          if (seats.total > 0 && seats.full) ...[
            const SizedBox(height: BafoSpacing.md),
            InfoNotice(
              key: const Key('team.seatsFull'),
              tone: StatusTone.warning,
              icon: Icons.event_seat_outlined,
              title: l10n.accountTeamSeatsFull(seats.used, seats.total),
              message: l10n.billingManagedOnWeb,
            ),
          ],
        ],
      ),
    );
  }
}

class _MemberTile extends StatelessWidget {
  const _MemberTile({
    required this.member,
    required this.isSelf,
    required this.onTap,
  });

  final TeamMember member;
  final bool isSelf;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurfaceVariant;
    final locked = member.isOwner || isSelf;
    final language = context.languageCode;
    final date = switch (member.status) {
      MembershipStatus.invited when member.invitedAt != null =>
        l10n.accountTeamInvitedOn(
          BafoDateFormat.longDate(member.invitedAt!, language),
        ),
      _ when member.joinedAt != null => l10n.accountTeamJoinedOn(
        BafoDateFormat.longDate(member.joinedAt!, language),
      ),
      _ => null,
    };
    final flags = [
      if (member.canAward) l10n.accountTeamCanAward,
      if (member.canPurchase) l10n.accountTeamCanPurchase,
    ];
    final name = isSelf
        ? '${member.user.name} (${l10n.accountTeamYou})'
        : member.user.name;

    return ListTile(
      key: ValueKey('team.member.${member.id}'),
      onTap: locked ? null : onTap,
      contentPadding: const EdgeInsetsDirectional.symmetric(
        horizontal: BafoSpacing.lg,
        vertical: BafoSpacing.xs,
      ),
      leading: OrgAvatar(
        name: member.user.name,
        logoUrl: member.user.avatarUrl,
        size: 44,
        decorative: true,
      ),
      title: Text(name),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Ltr(
            child: Text(
              member.user.email,
              style: theme.textTheme.bodySmall?.copyWith(color: muted),
            ),
          ),
          const SizedBox(height: BafoSpacing.xs),
          Wrap(
            spacing: BafoSpacing.xs,
            runSpacing: BafoSpacing.xs,
            children: [
              StatusPill(
                label: roleLabel(l10n, member.role),
                icon: Icons.badge_outlined,
              ),
              if (member.status != MembershipStatus.active)
                StatusPill(
                  label: membershipStatusLabel(l10n, member.status),
                  tone: membershipStatusTone(member.status),
                  icon: member.status == MembershipStatus.invited
                      ? Icons.schedule_rounded
                      : Icons.pause_circle_outline_rounded,
                ),
            ],
          ),
          if (flags.isNotEmpty || date != null) ...[
            const SizedBox(height: BafoSpacing.xs),
            Text(
              [...flags, ?date].join(' · '),
              style: theme.textTheme.bodySmall?.copyWith(color: muted),
            ),
          ],
        ],
      ),
      trailing: locked
          ? Tooltip(
              message: l10n.accountTeamLocked,
              child: Icon(
                Icons.lock_outline_rounded,
                color: muted,
                semanticLabel: l10n.accountTeamLocked,
              ),
            )
          : Icon(Icons.chevron_right_rounded, color: muted),
    );
  }
}
