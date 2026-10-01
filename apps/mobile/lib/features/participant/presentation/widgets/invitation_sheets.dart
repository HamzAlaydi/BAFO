import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/features/participant/presentation/invitation_action_cubit.dart';
import 'package:bafo/features/participant/presentation/widgets/offline_gate.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Why joining is not available (W14 `unavailable` row): from the
/// invitation status first, then the competition status, else the deadline.
String unavailableReason(
  AppLocalizations l10n, {
  required CompetitionStatus status,
  InvitationStatus? invitationStatus,
}) => switch (invitationStatus) {
  InvitationStatus.declined => l10n.invitationsUnavailableDeclined,
  InvitationStatus.expired => l10n.invitationsUnavailableExpired,
  InvitationStatus.revoked => l10n.invitationsUnavailableRevoked,
  _ =>
    status == CompetitionStatus.scheduled || status == CompetitionStatus.live
        ? l10n.invitationsUnavailableDeadline
        : l10n.invitationsUnavailableClosed,
};

/// M18: the rules summary, a link to the competition rules (M13), the
/// required terms checkbox and the sponsored line when covered. The result
/// arrives through [cubit] (the page listens); the sheet closes itself when
/// the join succeeded or the page must explain a refusal.
Future<void> showJoinSheet(
  BuildContext context, {
  required Competition competition,
  required InvitationActionCubit cubit,
}) => showBafoBottomSheet<void>(
  context,
  title: context.l10n.invitationsJoinTitle,
  builder: (_) => BlocProvider.value(
    value: cubit,
    child: _JoinSheet(competition: competition),
  ),
);

class _JoinSheet extends StatefulWidget {
  const _JoinSheet({required this.competition});

  final Competition competition;

  @override
  State<_JoinSheet> createState() => _JoinSheetState();
}

class _JoinSheetState extends State<_JoinSheet> {
  bool _accepted = false;
  bool _showTermsError = false;

  void _join() {
    final invitationId = widget.competition.invitation?.id;
    if (invitationId == null) return;
    if (!_accepted) {
      setState(() => _showTermsError = true);
      return;
    }
    context.read<InvitationActionCubit>().join(invitationId);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final access = widget.competition.access;
    return BlocConsumer<InvitationActionCubit, InvitationActionState>(
      listener: (context, state) {
        final done =
            state is InvitationJoined ||
            (state is InvitationActionFailed &&
                state.action == InvitationAction.join &&
                state.meansStale);
        if (done) Navigator.of(context).pop();
      },
      builder: (context, state) {
        final busy = state is InvitationActionInProgress;
        final failure =
            state is InvitationActionFailed &&
                state.action == InvitationAction.join &&
                !state.meansStale
            ? state.error
            : null;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.invitationsJoinIntro, style: theme.textTheme.bodyMedium),
            const SizedBox(height: BafoSpacing.md),
            if (access?.feesCovered ?? false) ...[
              const Align(
                alignment: AlignmentDirectional.centerStart,
                child: FeesCoveredBadge(),
              ),
              const SizedBox(height: BafoSpacing.sm),
              Text(
                l10n.invitationsSponsoredOnly(access?.sponsorName ?? ''),
                style: theme.textTheme.bodyMedium,
              ),
            ] else
              Text(l10n.invitationsOwnPlan, style: theme.textTheme.bodyMedium),
            if (widget.competition.rulesSummary.isNotEmpty) ...[
              const SizedBox(height: BafoSpacing.lg),
              RulesSummaryCard(lines: widget.competition.rulesSummary),
            ],
            const SizedBox(height: BafoSpacing.md),
            ConsentCheckbox(
              value: _accepted,
              label: l10n.invitationsJoinAcceptTerms,
              errorText: _showTermsError && !_accepted
                  ? l10n.invitationsJoinTermsRequired
                  : null,
              onChanged: (value) => setState(() {
                _accepted = value;
                if (value) _showTermsError = false;
              }),
              onRead: () => context.push(ParticipantPaths.competitionRules),
            ),
            if (failure != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              InfoNotice(
                tone: StatusTone.danger,
                icon: Icons.error_outline_rounded,
                message: errorMessage(l10n, failure),
              ),
            ],
            const SizedBox(height: BafoSpacing.lg),
            BafoButton(
              key: const Key('join-confirm'),
              label: l10n.invitationsJoinConfirm,
              loading: busy,
              expand: true,
              onPressed: watchOffline(context) ? null : _join,
            ),
          ],
        );
      },
    );
  }
}

/// M19: an optional reason (≤ 500) and a red confirmation naming the
/// action.
Future<void> showDeclineSheet(
  BuildContext context, {
  required String invitationId,
  required InvitationActionCubit cubit,
}) => showBafoBottomSheet<void>(
  context,
  title: context.l10n.invitationsDeclineTitle,
  builder: (_) => BlocProvider.value(
    value: cubit,
    child: _DeclineSheet(invitationId: invitationId),
  ),
);

class _DeclineSheet extends StatefulWidget {
  const _DeclineSheet({required this.invitationId});

  final String invitationId;

  @override
  State<_DeclineSheet> createState() => _DeclineSheetState();
}

class _DeclineSheetState extends State<_DeclineSheet> {
  final TextEditingController _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<InvitationActionCubit, InvitationActionState>(
      listener: (context, state) {
        final done =
            state is InvitationDeclined ||
            (state is InvitationActionFailed &&
                state.action == InvitationAction.decline &&
                state.meansStale);
        if (done) Navigator.of(context).pop();
      },
      builder: (context, state) {
        final busy = state is InvitationActionInProgress;
        final failure =
            state is InvitationActionFailed &&
                state.action == InvitationAction.decline &&
                !state.meansStale
            ? state.error
            : null;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            InfoNotice(
              tone: StatusTone.warning,
              icon: Icons.info_outline_rounded,
              message: l10n.invitationsDeclineMessage,
            ),
            const SizedBox(height: BafoSpacing.lg),
            BafoTextField(
              key: const Key('decline-reason'),
              label: l10n.invitationsDeclineReasonLabel,
              helperText: l10n.invitationsDeclineReasonHelper,
              controller: _reason,
              optional: true,
              maxLines: 4,
              minLines: 2,
              maxLength: 500,
              enabled: !busy,
            ),
            if (failure != null) ...[
              const SizedBox(height: BafoSpacing.sm),
              InfoNotice(
                tone: StatusTone.danger,
                icon: Icons.error_outline_rounded,
                message: errorMessage(l10n, failure),
              ),
            ],
            const SizedBox(height: BafoSpacing.lg),
            BafoButton.destructive(
              key: const Key('decline-confirm'),
              label: l10n.invitationsDeclineConfirm,
              loading: busy,
              expand: true,
              onPressed: watchOffline(context)
                  ? null
                  : () => context.read<InvitationActionCubit>().decline(
                      widget.invitationId,
                      reason: _reason.text,
                    ),
            ),
            const SizedBox(height: BafoSpacing.sm),
            BafoButton.text(
              label: l10n.commonActionsCancel,
              expand: true,
              onPressed: busy ? null : () => Navigator.of(context).pop(),
            ),
          ],
        );
      },
    );
  }
}

/// M20: why joining needs a plan (`plan_required`) or is unavailable. Pure
/// information: mobile never sells a plan (SCREENS.md §3.2) — no price, no
/// link, no purchase button. Resolves to true when the user chose to
/// decline instead.
Future<bool> showAccessInfoSheet(
  BuildContext context, {
  required AccessState state,
  String? unavailableMessage,
  bool canDecline = false,
}) async {
  final l10n = context.l10n;
  final planRequired = state == AccessState.planRequired;
  final decline = await showBafoBottomSheet<bool>(
    context,
    title: planRequired
        ? l10n.invitationsAccessInfoTitle
        : l10n.invitationsAccessInfoUnavailableTitle,
    builder: (sheetContext) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (planRequired) ...[
          InfoNotice(
            tone: StatusTone.warning,
            icon: Icons.workspace_premium_outlined,
            message: l10n.invitationsAccessInfoPlanRequired,
          ),
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(message: l10n.billingManagedOnWeb),
        ] else
          InfoNotice(
            tone: StatusTone.neutral,
            message: unavailableMessage ?? l10n.invitationsUnavailableClosed,
          ),
        if (canDecline) ...[
          const SizedBox(height: BafoSpacing.lg),
          Text(
            l10n.invitationsAccessInfoDeclineHint,
            style: Theme.of(sheetContext).textTheme.bodyMedium,
          ),
          const SizedBox(height: BafoSpacing.md),
          BafoButton.outline(
            label: l10n.invitationsDeclineAction,
            expand: true,
            onPressed: () => Navigator.of(sheetContext).pop(true),
          ),
        ],
      ],
    ),
  );
  return decline ?? false;
}
