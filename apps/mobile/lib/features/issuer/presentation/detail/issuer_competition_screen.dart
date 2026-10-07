import 'dart:async';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_action_cubits.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/detail/publish_sheet.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M38, the issuer's view of `/competitions/:id` (CD1): header, schedule,
/// rules, counts, read-only sponsorship counters, documents, and the
/// actions the competition's `permissions` allow (edit, invite, documents,
/// publish, cancel, delete draft). Extend, BAFO, award, revoke and close
/// without award are web-only (CD5).
///
/// A caller who is not the issuer gets [ForbiddenState]: the participant
/// routes dispatch `/competitions/:id` by `viewer_role` before this screen.
class IssuerCompetitionScreen extends StatelessWidget {
  const IssuerCompetitionScreen({
    required this.competitionId,
    this.afterPublish,
    super.key,
  });

  final String competitionId;

  /// After a successful publish, e.g. the push explainer (M50), shown once.
  final Future<void> Function(BuildContext context)? afterPublish;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => IssuerCompetitionCubit(
      competitions: context.read<CompetitionsRepository>(),
      attachments: context.read<AttachmentsRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      competitionId: competitionId,
    )..load(),
    child: IssuerCompetitionView(afterPublish: afterPublish),
  );
}

/// The body of [IssuerCompetitionScreen] under an existing
/// [IssuerCompetitionCubit].
class IssuerCompetitionView extends StatefulWidget {
  const IssuerCompetitionView({this.afterPublish, super.key});

  final Future<void> Function(BuildContext context)? afterPublish;

  @override
  State<IssuerCompetitionView> createState() => _IssuerCompetitionViewState();
}

class _IssuerCompetitionViewState extends State<IssuerCompetitionView> {
  /// An action request is running (cancel): actions are disabled and a
  /// progress bar shows; nothing changes before the server answers (CD9).
  bool _busy = false;

  IssuerCompetitionCubit get _cubit => context.read<IssuerCompetitionCubit>();

  Future<void> _push(String location) async {
    await context.push(location);
    if (mounted) unawaited(_cubit.refresh());
  }

  Future<void> _publish(Competition competition) async {
    final outcome = await showPublishSheet(context, competition);
    if (!mounted || outcome == null) return;
    final published = outcome.published;
    if (published != null) {
      _cubit.applyCompetition(published);
      BafoToast.success(context, context.l10n.issuerPublishDone);
      await widget.afterPublish?.call(context);
      return;
    }
    switch (outcome.followUp) {
      case PublishFollowUp.edit:
        await _push(IssuerPaths.edit(competition.id));
      case PublishFollowUp.invite:
        await _push(IssuerPaths.invite(competition.id));
      case null:
        break;
    }
  }

  /// M44: reason sheet, then `POST …/cancel`.
  Future<void> _cancel(Competition competition) async {
    final l10n = context.l10n;
    final cancel = CancelCompetitionCubit(
      competitions: context.read<CompetitionsRepository>(),
      lookups: context.read<LookupsRepository>(),
      competitionId: competition.id,
    );
    setState(() => _busy = true);
    try {
      await cancel.loadReasons();
      if (!mounted) return;
      final loaded = cancel.state;
      if (loaded is! CancelCompetitionReady) {
        if (loaded is CancelCompetitionReasonsFailure) {
          BafoToast.error(context, errorMessage(l10n, loaded.error));
        }
        return;
      }
      setState(() => _busy = false);
      final choice = await showReasonPickerSheet(
        context,
        title: l10n.issuerCancelTitle,
        reasons: loaded.reasons,
        confirmLabel: l10n.issuerActionCancel,
      );
      if (choice == null || !mounted) return;
      setState(() => _busy = true);
      await cancel.cancel(reason: choice.reason, note: choice.note);
      if (!mounted) return;
      final result = cancel.state;
      if (result is CancelCompetitionDone) {
        _cubit.applyCompetition(result.competition);
        BafoToast.success(context, l10n.issuerCancelDone);
      } else if (result is CancelCompetitionReady && result.error != null) {
        BafoToast.error(context, errorMessage(l10n, result.error));
        unawaited(_cubit.refresh());
      }
    } finally {
      await cancel.close();
      if (mounted) setState(() => _busy = false);
    }
  }

  /// M45: confirm, then `DELETE /competitions/{id}`.
  Future<void> _delete() async {
    final l10n = context.l10n;
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.issuerDeleteTitle,
      message: l10n.issuerDeleteMessage,
      confirmLabel: l10n.issuerActionDeleteDraft,
      destructive: true,
    );
    if (confirmed && mounted) await _cubit.deleteDraft();
  }

  Future<void> _showActions(Competition competition) async {
    final l10n = context.l10n;
    final permissions = competition.permissions;
    final choice = await showBafoBottomSheet<_Action>(
      context,
      title: l10n.issuerActionsMenu,
      builder: (sheet) {
        // State changes need the server (S8): off while offline.
        final offline = issuerOffline(sheet);
        Widget item(
          _Action action,
          IconData icon,
          String label, {
          bool danger = false,
          bool mutation = false,
        }) {
          final enabled = !(mutation && offline);
          final color = danger && enabled
              ? Theme.of(sheet).colorScheme.error
              : null;
          return ListTile(
            enabled: enabled,
            leading: Icon(icon, color: color),
            title: Text(label, style: TextStyle(color: color)),
            subtitle: enabled ? null : Text(l10n.commonOfflineActionsDisabled),
            onTap: () => Navigator.of(sheet).pop(action),
          );
        }

        Widget webOnly(IconData icon, String label) => ListTile(
          enabled: false,
          leading: Icon(icon),
          title: Text(label),
          subtitle: Text(l10n.issuerWebOnly),
        );

        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (permissions.canPublish)
              item(
                _Action.publish,
                Icons.send_rounded,
                l10n.issuerActionPublish,
                mutation: true,
              ),
            if (permissions.canEdit)
              item(_Action.edit, Icons.edit_outlined, l10n.issuerActionEdit),
            if (permissions.canInvite)
              item(
                _Action.invite,
                Icons.group_add_outlined,
                l10n.issuerActionInvite,
              ),
            if (competition.acceptsAttachments)
              item(
                _Action.documents,
                Icons.attach_file_rounded,
                l10n.issuerActionDocuments,
              ),
            if (permissions.canExtend)
              webOnly(Icons.more_time_rounded, l10n.issuerActionExtend),
            if (permissions.canStartBafo)
              webOnly(
                Icons.workspace_premium_outlined,
                l10n.issuerActionStartBafo,
              ),
            if (permissions.canAward)
              webOnly(Icons.emoji_events_outlined, l10n.issuerActionAward),
            if (permissions.canRevokeAward)
              webOnly(Icons.undo_rounded, l10n.issuerActionRevokeAward),
            if (permissions.canCloseWithoutAward)
              webOnly(
                Icons.do_not_disturb_on_outlined,
                l10n.issuerActionCloseWithoutAward,
              ),
            if (permissions.canCancel)
              item(
                _Action.cancel,
                Icons.block_rounded,
                l10n.issuerActionCancel,
                danger: true,
                mutation: true,
              ),
            if (permissions.canDelete)
              item(
                _Action.delete,
                Icons.delete_outline_rounded,
                l10n.issuerActionDeleteDraft,
                danger: true,
                mutation: true,
              ),
          ],
        );
      },
    );
    if (!mounted || choice == null) return;
    switch (choice) {
      case _Action.publish:
        await _publish(competition);
      case _Action.edit:
        await _push(IssuerPaths.edit(competition.id));
      case _Action.invite:
        await _push(IssuerPaths.invite(competition.id));
      case _Action.documents:
        await _push(IssuerPaths.attachments(competition.id));
      case _Action.cancel:
        await _cancel(competition);
      case _Action.delete:
        await _delete();
    }
  }

  static bool _hasActions(Competition competition) {
    final p = competition.permissions;
    return p.canPublish ||
        p.canEdit ||
        p.canInvite ||
        p.canCancel ||
        p.canDelete ||
        competition.hasWebOnlyActions;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<IssuerCompetitionCubit, IssuerCompetitionState>(
      listener: (context, state) {
        if (state is IssuerCompetitionDeleted) {
          BafoToast.success(context, l10n.issuerDeleteDone);
          context.go(IssuerPaths.list);
        } else if (state is IssuerCompetitionLoaded &&
            state.actionError != null) {
          BafoToast.error(context, errorMessage(l10n, state.actionError));
        }
      },
      builder: (context, state) {
        final loaded = state is IssuerCompetitionLoaded ? state : null;
        final busy = _busy || (loaded?.deleting ?? false);
        return Scaffold(
          appBar: BafoAppBar(
            title: l10n.competitionsDetailTitle,
            bottom: busy
                ? const PreferredSize(
                    preferredSize: Size.fromHeight(4),
                    child: LinearProgressIndicator(minHeight: 4),
                  )
                : null,
            actions: [
              if (loaded != null && _hasActions(loaded.competition))
                IconButton(
                  tooltip: l10n.issuerActionsMenu,
                  icon: const Icon(Icons.more_vert_rounded),
                  onPressed: busy
                      ? null
                      : () => _showActions(loaded.competition),
                ),
            ],
          ),
          body: switch (state) {
            IssuerCompetitionInitial() ||
            IssuerCompetitionLoading() ||
            IssuerCompetitionDeleted() => const LoadingSkeletonList(),
            // Role guard: participants and invitees never reach this view.
            IssuerCompetitionNotIssuer() => const ForbiddenState(),
            IssuerCompetitionNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            IssuerCompetitionFailure(:final error) => ErrorState(
              error: error,
              onRetry: _cubit.load,
            ),
            IssuerCompetitionLoaded() => RefreshIndicator(
              onRefresh: _cubit.refresh,
              child: _Loaded(
                state: state,
                busy: busy,
                onPush: _push,
                onPublish: () => _publish(state.competition),
              ),
            ),
          },
        );
      },
    );
  }
}

enum _Action { publish, edit, invite, documents, cancel, delete }

class _Loaded extends StatelessWidget {
  const _Loaded({
    required this.state,
    required this.busy,
    required this.onPush,
    required this.onPublish,
  });

  final IssuerCompetitionLoaded state;
  final bool busy;
  final Future<void> Function(String location) onPush;
  final VoidCallback onPublish;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final competition = state.competition;
    final live = state.live;
    final id = competition.id;
    final permissions = competition.permissions;
    final isDraft = competition.status == CompetitionStatus.draft;
    final description = (competition.description ?? '').trim();
    final countdown = _countdown(context);
    final counts = competition.counts;
    final award = competition.award;
    final sponsorship = state.sponsorship;

    return ListView(
      padding: BafoSpacing.pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        IssuerCompetitionHeader(
          competition: competition,
          effectiveCloseAt: live?.effectiveCloseAt,
          extensionCount: live?.extensionCount,
        ),
        if (competition.source == 'api') ...[
          const SizedBox(height: BafoSpacing.sm),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: StatusPill(
              label: l10n.issuerDetailCreatedViaApi,
              icon: Icons.integration_instructions_outlined,
            ),
          ),
        ],
        if (countdown != null) ...[
          const SizedBox(height: BafoSpacing.lg),
          BafoCard(child: countdown),
        ],
        if (competition.cancellation != null) ...[
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(
            tone: StatusTone.danger,
            icon: Icons.block_rounded,
            message: _withNote(
              l10n.competitionsDetailCancelled(
                competition.cancellation!.reason.name,
              ),
              competition.cancellation!.note,
            ),
          ),
        ],
        if (competition.notAwarded != null) ...[
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(
            tone: StatusTone.neutral,
            icon: Icons.do_not_disturb_on_outlined,
            message: _withNote(
              l10n.competitionsDetailNotAwarded(
                competition.notAwarded!.reason.name,
              ),
              competition.notAwarded!.note,
            ),
          ),
        ],
        if (award != null && award.status == 'issued') ...[
          const SizedBox(height: BafoSpacing.md),
          BafoCard(
            onTap: () => onPush(IssuerPaths.award(id)),
            child: Row(
              children: [
                Icon(
                  Icons.emoji_events_outlined,
                  color: theme.colorScheme.primary,
                ),
                const SizedBox(width: BafoSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l10n.issuerAwardSummary(
                          participantLabel(
                            l10n,
                            award.aliasNo,
                            award.organization.name,
                          ),
                        ),
                        style: theme.textTheme.bodyMedium,
                      ),
                      const SizedBox(height: BafoSpacing.xxs),
                      AmountText(
                        award.amountMinor,
                        currency: competition.currency,
                        style: theme.textTheme.titleMedium,
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded),
              ],
            ),
          ),
        ],
        if (isDraft) ...[
          const SizedBox(height: BafoSpacing.lg),
          BafoCard(child: SetupChecklist(competition: competition)),
          const SizedBox(height: BafoSpacing.md),
          if (permissions.canPublish)
            BafoButton(
              label: l10n.issuerActionPublish,
              icon: Icons.send_rounded,
              expand: true,
              onPressed: busy || issuerOffline(context) ? null : onPublish,
            ),
          const SizedBox(height: BafoSpacing.sm),
          Row(
            children: [
              if (permissions.canInvite)
                Expanded(
                  child: BafoButton.outline(
                    label: l10n.issuerActionInvite,
                    icon: Icons.group_add_outlined,
                    onPressed: busy
                        ? null
                        : () => onPush(IssuerPaths.invite(id)),
                  ),
                ),
              if (permissions.canInvite && competition.acceptsAttachments)
                const SizedBox(width: BafoSpacing.sm),
              if (competition.acceptsAttachments)
                Expanded(
                  child: BafoButton.outline(
                    label: l10n.issuerActionDocuments,
                    icon: Icons.attach_file_rounded,
                    onPressed: busy
                        ? null
                        : () => onPush(IssuerPaths.attachments(id)),
                  ),
                ),
            ],
          ),
        ] else ...[
          const SizedBox(height: BafoSpacing.lg),
          _LeadingOffer(state: state),
        ],
        const SizedBox(height: BafoSpacing.lg),
        BafoCard(
          padding: const EdgeInsetsDirectional.symmetric(
            vertical: BafoSpacing.xs,
          ),
          child: Column(
            children: [
              if (competition.hasLiveSections)
                IssuerNavTile(
                  icon: Icons.monitor_heart_outlined,
                  label: l10n.issuerNavLive,
                  onTap: () => onPush(IssuerPaths.live(id)),
                ),
              if (competition.hasLiveSections)
                IssuerNavTile(
                  icon: Icons.receipt_long_outlined,
                  label: l10n.issuerNavOffers,
                  trailingText: counts == null ? null : '${counts.offers}',
                  onTap: () => onPush(IssuerPaths.offers(id)),
                ),
              IssuerNavTile(
                icon: Icons.groups_outlined,
                label: l10n.issuerNavParticipants,
                trailingText: counts == null ? null : '${counts.invitations}',
                onTap: () => onPush(IssuerPaths.participants(id)),
              ),
              if (competition.hasLiveSections)
                IssuerNavTile(
                  icon: Icons.forum_outlined,
                  label: l10n.issuerNavQa,
                  trailingText: counts == null ? null : '${counts.comments}',
                  onTap: () => onPush(IssuerPaths.qa(id)),
                ),
              if (competition.hasAwardSection)
                IssuerNavTile(
                  icon: Icons.emoji_events_outlined,
                  label: l10n.issuerNavAward,
                  onTap: () => onPush(IssuerPaths.award(id)),
                ),
              IssuerNavTile(
                icon: Icons.folder_outlined,
                label: l10n.issuerNavDocuments,
                trailingText: '${state.attachments.length}',
                onTap: () => onPush(IssuerPaths.attachments(id)),
              ),
            ],
          ),
        ),
        if (competition.hasWebOnlyActions) ...[
          const SizedBox(height: BafoSpacing.md),
          WebOnlyNotice(message: l10n.issuerWebOnlyActions),
        ],
        if (counts != null) ...[
          SectionHeader(title: l10n.issuerDetailCounts),
          MetricGrid(
            tiles: [
              MetricTile(
                label: l10n.issuerCountInvitations,
                value: '${counts.invitations}',
              ),
              MetricTile(
                label: l10n.issuerCountJoined,
                value: '${counts.joined}',
              ),
              MetricTile(
                label: l10n.issuerCountDeclined,
                value: '${counts.declined}',
              ),
              MetricTile(
                label: l10n.issuerCountWithOffers,
                value: '${counts.participantsWithOffers}',
              ),
              MetricTile(
                label: l10n.issuerCountOffers,
                value: '${counts.offers}',
              ),
              MetricTile(
                label: l10n.issuerCountComments,
                value: '${counts.comments}',
              ),
            ],
          ),
        ],
        // The sponsorship counters need the `sponsorship` flag
        // (RELEASE_SCOPE.md §4); the record itself still renders.
        if (sponsorship != null &&
            context.flags.enabled(Feature.sponsorship)) ...[
          const SizedBox(height: BafoSpacing.lg),
          SponsorshipCard(sponsorship: sponsorship),
        ],
        const SizedBox(height: BafoSpacing.lg),
        BafoCard(
          child: KeyValueList(
            items: [
              if (competition.category != null)
                KeyValue(
                  l10n.competitionsDetailCategory,
                  (competition.categoryOtherText ?? '').trim().isNotEmpty
                      ? competition.categoryOtherText!
                      : competition.category!.name,
                ),
              if (competition.region != null)
                KeyValue(
                  l10n.competitionsDetailRegion,
                  competition.region!.name,
                ),
              if (competition.createdByName != null)
                KeyValue(
                  l10n.issuerDetailCreatedBy,
                  competition.createdByName!,
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
        BafoCard(
          child: _Timeline(competition: competition, state: state),
        ),
        SectionHeader(
          title: l10n.competitionsDetailDocuments,
          actionLabel: competition.acceptsAttachments
              ? l10n.issuerDocumentsManage
              : null,
          onAction: () => onPush(IssuerPaths.attachments(id)),
        ),
        if (state.attachmentsError != null)
          ErrorState(
            error: state.attachmentsError,
            onRetry: context.read<IssuerCompetitionCubit>().refresh,
          )
        else if (state.attachments.isEmpty)
          Text(
            l10n.competitionsDetailNoDocuments,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          )
        else
          BafoCard(
            padding: const EdgeInsetsDirectional.symmetric(
              horizontal: BafoSpacing.sm,
            ),
            child: Column(
              children: [
                for (final (index, attachment)
                    in state.attachments.indexed) ...[
                  if (index > 0) const Divider(),
                  AttachmentTile(attachment: attachment),
                ],
              ],
            ),
          ),
        const SizedBox(height: BafoSpacing.xxl),
      ],
    );
  }

  static String _withNote(String text, String? note) {
    final trimmed = note?.trim() ?? '';
    return trimmed.isEmpty ? text : '$text\n$trimmed';
  }

  Widget? _countdown(BuildContext context) {
    final competition = state.competition;
    final live = state.live;
    final schedule = competition.schedule;
    final opensAt = live?.biddingOpensAt ?? schedule.biddingOpensAt;
    final closeAt = live?.effectiveCloseAt ?? schedule.effectiveCloseAt;
    final bafoCutoff = live?.bafo?.cutoffAt ?? competition.bafoRound?.cutoffAt;
    final cubit = context.read<IssuerCompetitionCubit>();
    return switch (competition.status) {
      CompetitionStatus.scheduled when opensAt != null => CompetitionCountdown(
        target: CountdownTarget.opens,
        deadline: opensAt,
        onElapsed: cubit.refresh,
      ),
      CompetitionStatus.live when closeAt != null => CompetitionCountdown(
        target: CountdownTarget.closes,
        deadline: closeAt,
        extensionCount: live?.extensionCount ?? competition.extensionCount,
        hardStopAt: live?.hardStopAt ?? schedule.hardStopAt,
        announce: true,
        onElapsed: cubit.refresh,
      ),
      CompetitionStatus.bafoRound when bafoCutoff != null =>
        CompetitionCountdown(
          target: CountdownTarget.bafoCutoff,
          deadline: bafoCutoff,
          onElapsed: cubit.refresh,
        ),
      _ => null,
    };
  }
}

/// The leading offer as projected (null while sealed and locked).
class _LeadingOffer extends StatelessWidget {
  const _LeadingOffer({required this.state});

  final IssuerCompetitionLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final competition = state.competition;
    final amount = state.leadingAmountMinor;
    final sealedLocked =
        competition.format == CompetitionFormat.sealed &&
        competition.schedule.offersOpenedAt == null &&
        amount == null;
    final offers = competition.counts?.offers ?? 0;
    return BafoCard(
      child: Row(
        children: [
          Icon(
            sealedLocked
                ? Icons.lock_outline_rounded
                : Icons.leaderboard_outlined,
            color: theme.colorScheme.onSurfaceVariant,
          ),
          const SizedBox(width: BafoSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l10n.issuerDetailLeadingOffer,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: BafoSpacing.xxs),
                if (amount != null)
                  AmountText(
                    amount,
                    currency: competition.currency,
                    style: theme.textTheme.titleLarge,
                  )
                else
                  Text(
                    sealedLocked
                        ? l10n.issuerLiveSealedLock
                        : offers == 0
                        ? l10n.issuerLiveNoOffers
                        : l10n.issuerNoValue,
                    style: theme.textTheme.bodyMedium,
                  ),
                const SizedBox(height: BafoSpacing.xxs),
                Text(
                  l10n.commonPricesExcludeVat,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
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

/// The schedule timeline (W14): published, opening, final window, join
/// deadline, closing (scheduled, effective, latest), extensions and the
/// outcome times.
class _Timeline extends StatelessWidget {
  const _Timeline({required this.competition, required this.state});

  final Competition competition;
  final IssuerCompetitionLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final language = context.languageCode;
    final schedule = competition.schedule;
    final live = state.live;
    String date(DateTime value) =>
        BafoDateFormat.deadline(value, language, l10n);
    final effectiveClose = live?.effectiveCloseAt ?? schedule.effectiveCloseAt;
    final hardStop = live?.hardStopAt ?? schedule.hardStopAt;
    final extensions = live?.extensionCount ?? schedule.extensionCount;
    final rows = <KeyValue>[
      if (schedule.publishedAt != null)
        KeyValue(l10n.issuerTimelinePublished, date(schedule.publishedAt!)),
      KeyValue(
        l10n.competitionsScheduleOpensAt,
        schedule.biddingOpensAt == null
            ? l10n.competitionsScheduleOpensOnPublish
            : date(schedule.biddingOpensAt!),
      ),
      if (schedule.finalWindowStartsAt != null)
        KeyValue(
          l10n.competitionsScheduleFinalWindow,
          date(schedule.finalWindowStartsAt!),
        ),
      if (schedule.invitationCutoffAt != null)
        KeyValue(
          l10n.competitionsScheduleJoinDeadline,
          date(schedule.invitationCutoffAt!),
        ),
      if (schedule.scheduledCloseAt != null)
        KeyValue(
          l10n.issuerTimelineScheduledClose,
          date(schedule.scheduledCloseAt!),
        ),
      if (effectiveClose != null && effectiveClose != schedule.scheduledCloseAt)
        KeyValue(l10n.issuerTimelineEffectiveClose, date(effectiveClose)),
      if (hardStop != null)
        KeyValue(l10n.issuerTimelineHardStop, date(hardStop)),
      if (extensions > 0)
        KeyValue(l10n.issuerTimelineExtensions, '$extensions', ltr: true),
      if (schedule.closedAt != null)
        KeyValue(l10n.issuerTimelineClosed, date(schedule.closedAt!)),
      if (schedule.awardedAt != null)
        KeyValue(l10n.issuerTimelineAwarded, date(schedule.awardedAt!)),
      if (schedule.notAwardedAt != null)
        KeyValue(l10n.issuerTimelineNotAwarded, date(schedule.notAwardedAt!)),
      if (schedule.cancelledAt != null)
        KeyValue(l10n.issuerTimelineCancelled, date(schedule.cancelledAt!)),
    ];
    return KeyValueList(items: rows);
  }
}
