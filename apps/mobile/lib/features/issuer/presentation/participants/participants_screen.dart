import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/participants/invitations_cubit.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M41 (`/competitions/:id/participants`): invitations by status with
/// counts, resend and revoke (confirmed), the read-only sponsorship
/// counters, and "Invite more" while `can_invite`.
class ParticipantsScreen extends StatelessWidget {
  const ParticipantsScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => InvitationsCubit(
      competitions: context.read<CompetitionsRepository>(),
      invitations: context.read<InvitationsRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      competitionId: competitionId,
    )..load(),
    child: const _ParticipantsView(),
  );
}

class _ParticipantsView extends StatelessWidget {
  const _ParticipantsView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<InvitationsCubit, InvitationsState>(
      listenWhen: (previous, current) =>
          current is InvitationsNotIssuer ||
          (current is InvitationsLoaded &&
              (previous is! InvitationsLoaded ||
                  previous.lastAction != current.lastAction ||
                  previous.actionError != current.actionError)),
      listener: (context, state) {
        if (state is InvitationsNotIssuer) {
          leaveToDetail(context, state.competition.id);
          return;
        }
        if (state is! InvitationsLoaded) return;
        final error = state.actionError;
        if (error != null) {
          BafoToast.error(
            context,
            error.code == 'too_many_requests'
                ? l10n.issuerInvitationResendLimit
                : errorMessage(l10n, error),
          );
        }
        final action = state.lastAction;
        if (action != null) {
          BafoToast.success(context, switch (action.action) {
            InvitationAction.resent => l10n.issuerInvitationResent,
            InvitationAction.revoked => l10n.issuerInvitationRevoked,
            InvitationAction.removed => l10n.issuerInvitationRemoved,
          });
        }
      },
      builder: (context, state) {
        final cubit = context.read<InvitationsCubit>();
        final loaded = state is InvitationsLoaded ? state : null;
        final canInvite = loaded?.competition.permissions.canInvite ?? false;
        return Scaffold(
          appBar: BafoAppBar(title: l10n.issuerParticipantsTitle),
          floatingActionButton: canInvite
              ? FloatingActionButton.extended(
                  onPressed: () async {
                    await context.push(
                      IssuerPaths.invite(loaded!.competition.id),
                    );
                    if (context.mounted) await cubit.refresh();
                  },
                  icon: const Icon(Icons.group_add_outlined),
                  label: Text(l10n.issuerActionInviteMore),
                )
              : null,
          body: switch (state) {
            InvitationsLoading() ||
            InvitationsNotIssuer() => const LoadingSkeletonList(),
            InvitationsNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            InvitationsFailure(:final error) =>
              error.statusCode == 403
                  ? const ForbiddenState()
                  : ErrorState(error: error, onRetry: cubit.load),
            InvitationsLoaded() => RefreshIndicator(
              onRefresh: cubit.refresh,
              child: _Loaded(state: state),
            ),
          },
        );
      },
    );
  }
}

class _Loaded extends StatelessWidget {
  const _Loaded({required this.state});

  final InvitationsLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<InvitationsCubit>();
    final visible = state.visible;
    final sponsorship = state.sponsorship;
    final statuses = [
      for (final status in InvitationStatus.values)
        if (status != InvitationStatus.unknown && state.counts[status] != null)
          status,
    ];
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.md,
        BafoSpacing.page,
        BafoSpacing.xxxl * 2,
      ),
      children: [
        Text(
          state.competition.title,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: BafoSpacing.md),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              _FilterChip(
                label: l10n.issuerParticipantsFilterAll(state.total),
                selected: state.filter == null,
                onSelected: () => cubit.setFilter(null),
              ),
              for (final status in statuses)
                if ((state.counts[status] ?? 0) > 0 || state.filter == status)
                  _FilterChip(
                    label: l10n.issuerParticipantsFilterStatus(
                      l10n.issuerInvitationStatus(status.wire),
                      state.counts[status] ?? 0,
                    ),
                    selected: state.filter == status,
                    onSelected: () => cubit.setFilter(status),
                  ),
            ],
          ),
        ),
        if (sponsorship != null) ...[
          const SizedBox(height: BafoSpacing.md),
          SponsorshipCard(sponsorship: sponsorship),
        ],
        const SizedBox(height: BafoSpacing.md),
        if (state.invitations.isEmpty)
          EmptyState(
            icon: Icons.mail_outline_rounded,
            title: l10n.issuerParticipantsEmptyTitle,
            message: l10n.issuerParticipantsEmptyMessage,
          )
        else if (visible.isEmpty)
          Padding(
            padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
            child: Text(l10n.issuerParticipantsFilterEmpty),
          )
        else
          for (final invitation in visible)
            Padding(
              padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
              child: InvitationTile(
                invitation: invitation,
                competition: state.competition,
                busy: state.busy.contains(invitation.id),
              ),
            ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(end: BafoSpacing.xs),
    child: ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onSelected(),
    ),
  );
}

/// One invitation as the issuer sees it (W17 row).
class InvitationTile extends StatelessWidget {
  const InvitationTile({
    required this.invitation,
    required this.competition,
    this.busy = false,
    super.key,
  });

  final Invitation invitation;
  final Competition competition;
  final bool busy;

  Future<void> _revoke(BuildContext context) async {
    final l10n = context.l10n;
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.issuerRevokeTitle,
      message: l10n.issuerRevokeMessage,
      confirmLabel: l10n.issuerInvitationRevoke,
      destructive: true,
    );
    if (confirmed && context.mounted) {
      await context.read<InvitationsCubit>().revoke(invitation);
    }
  }

  Future<void> _remove(BuildContext context) async {
    final l10n = context.l10n;
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.issuerRemoveInviteeTitle,
      message: l10n.issuerRemoveInviteeMessage,
      confirmLabel: l10n.issuerInvitationRemove,
      destructive: true,
    );
    if (confirmed && context.mounted) {
      await context.read<InvitationsCubit>().removeDraft(invitation);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final name =
        invitation.organization?.name ??
        invitation.vendorName ??
        invitation.name ??
        invitation.email;
    final canManage =
        competition.permissions.canInvite && !issuerOffline(context);
    final pending = invitation.isPending;
    final draft = invitation.status == InvitationStatus.draft;
    final now = context.read<ServerClock>().now();
    String when(DateTime value) => BafoDateFormat.relative(
      value,
      now: now,
      languageCode: language,
      l10n: l10n,
    );
    final history = <String>[
      if (invitation.sentAt != null)
        l10n.issuerInvitationSentAt(when(invitation.sentAt!)),
      if (invitation.viewedAt != null)
        l10n.issuerInvitationViewedAt(when(invitation.viewedAt!)),
      if (invitation.joinedAt != null)
        l10n.issuerInvitationJoinedAt(when(invitation.joinedAt!)),
      if (invitation.declinedAt != null)
        l10n.issuerInvitationDeclinedAt(when(invitation.declinedAt!)),
      if ((invitation.declineReason ?? '').trim().isNotEmpty)
        l10n.issuerInvitationReason(invitation.declineReason!.trim()),
      if (invitation.revokedAt != null)
        l10n.issuerInvitationRevokedAt(when(invitation.revokedAt!)),
    ];
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              OrgAvatar(
                name: name,
                logoUrl: invitation.organization?.logoUrl,
                size: 36,
                decorative: true,
              ),
              const SizedBox(width: BafoSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: theme.textTheme.titleSmall),
                    if (invitation.name != null && invitation.name != name)
                      Text(invitation.name!, style: muted),
                    Ltr(child: Text(invitation.email, style: muted)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          Wrap(
            spacing: BafoSpacing.xs,
            runSpacing: BafoSpacing.xs,
            children: [
              InvitationStatusChip(status: invitation.status),
              if (invitation.aliasNo != null)
                StatusPill(
                  label: l10n.issuerParticipantAlias(invitation.aliasNo!),
                  icon: Icons.badge_outlined,
                ),
              if (invitation.coverage == Coverage.sponsored ||
                  invitation.sponsoredRequested)
                CoverageChip(coverage: invitation.coverage),
              if (invitation.passStatus != null)
                StatusPill(
                  label: l10n.issuerPassStatus(invitation.passStatus!.wire),
                  icon: Icons.confirmation_number_outlined,
                ),
            ],
          ),
          if (history.isNotEmpty) ...[
            const SizedBox(height: BafoSpacing.sm),
            Text(history.join(' · '), style: muted),
          ],
          if (canManage && (pending || draft)) ...[
            const SizedBox(height: BafoSpacing.xs),
            Wrap(
              alignment: WrapAlignment.end,
              spacing: BafoSpacing.xs,
              children: [
                if (busy)
                  const Padding(
                    padding: EdgeInsets.all(BafoSpacing.sm),
                    child: SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ),
                if (pending)
                  BafoButton.text(
                    label: l10n.issuerInvitationResend,
                    icon: Icons.forward_to_inbox_outlined,
                    onPressed: busy
                        ? null
                        : () => context.read<InvitationsCubit>().resend(
                            invitation,
                          ),
                  ),
                if (pending)
                  BafoButton.text(
                    label: l10n.issuerInvitationRevoke,
                    icon: Icons.block_rounded,
                    onPressed: busy ? null : () => _revoke(context),
                  ),
                if (draft)
                  BafoButton.text(
                    label: l10n.issuerInvitationRemove,
                    icon: Icons.delete_outline_rounded,
                    onPressed: busy ? null : () => _remove(context),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
