import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_action_cubits.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// What the publish sheet asks the detail screen to do next.
enum PublishFollowUp { edit, invite }

/// The outcome of [showPublishSheet]: the published competition, or a
/// follow-up the user chose from an error.
typedef PublishOutcome = ({Competition? published, PublishFollowUp? followUp});

/// M43: the publish sheet with the checklist hints, and every publish error
/// mapped as W15 step 8 — except `sponsorship_payment_required` and
/// `issuer_plan_required`, which show the web notices (SCREENS.md §3.2).
Future<PublishOutcome?> showPublishSheet(
  BuildContext context,
  Competition competition,
) {
  final repository = context.read<CompetitionsRepository>();
  return showBafoBottomSheet<PublishOutcome>(
    context,
    title: context.l10n.issuerPublishTitle,
    builder: (_) => BlocProvider(
      create: (_) =>
          PublishCubit(competitions: repository, competitionId: competition.id),
      child: PublishSheet(competition: competition),
    ),
  );
}

class PublishSheet extends StatelessWidget {
  const PublishSheet({required this.competition, super.key});

  final Competition competition;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<PublishCubit, PublishState>(
      listener: (context, state) {
        if (state is PublishSucceeded) {
          Navigator.of(
            context,
          ).pop<PublishOutcome>((published: state.competition, followUp: null));
        }
      },
      builder: (context, state) {
        final cubit = context.read<PublishCubit>();
        final busy = state is PublishInProgress || state is PublishSucceeded;
        final failure = state is PublishFailed ? state : null;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            SetupChecklist(competition: competition),
            const SizedBox(height: BafoSpacing.lg),
            Text(l10n.issuerPublishNote, style: theme.textTheme.bodyMedium),
            if (failure != null) ...[
              const SizedBox(height: BafoSpacing.lg),
              _PublishError(failure: failure),
            ],
            const SizedBox(height: BafoSpacing.xl),
            BafoButton(
              label: l10n.issuerPublishConfirm,
              icon: Icons.send_rounded,
              expand: true,
              loading: busy,
              onPressed: issuerOffline(context) ? null : cubit.publish,
            ),
          ],
        );
      },
    );
  }
}

class _PublishError extends StatelessWidget {
  const _PublishError({required this.failure});

  final PublishFailed failure;

  void _follow(BuildContext context, PublishFollowUp followUp) =>
      Navigator.of(context)
          .pop<PublishOutcome>((published: null, followUp: followUp));

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final error = failure.error;
    final missing = failure.missingInvitations;
    return switch (error.code) {
      'min_participants_not_met' => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InfoNotice(
            message: missing == null
                ? errorMessage(l10n, error)
                : l10n.issuerPublishMissingInvitations(missing),
            tone: StatusTone.warning,
            icon: Icons.group_add_outlined,
          ),
          const SizedBox(height: BafoSpacing.sm),
          BafoButton.outline(
            label: l10n.issuerActionInvite,
            icon: Icons.group_add_outlined,
            onPressed: () => _follow(context, PublishFollowUp.invite),
          ),
        ],
      ),
      'live_event_capacity_reached' => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InfoNotice(
            message: l10n.issuerPublishCapacity,
            tone: StatusTone.warning,
            icon: Icons.event_busy_outlined,
          ),
          const SizedBox(height: BafoSpacing.sm),
          BafoButton.outline(
            label: l10n.issuerActionEdit,
            icon: Icons.edit_outlined,
            onPressed: () => _follow(context, PublishFollowUp.edit),
          ),
        ],
      ),
      'validation_failed' => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InfoNotice(
            title: l10n.issuerPublishFixFields,
            message: [
              for (final messages in error.fieldErrors.values)
                if (messages.isNotEmpty) '• ${messages.first}',
            ].join('\n'),
            tone: StatusTone.danger,
            icon: Icons.error_outline_rounded,
          ),
          const SizedBox(height: BafoSpacing.sm),
          BafoButton.outline(
            label: l10n.issuerActionEdit,
            icon: Icons.edit_outlined,
            onPressed: () => _follow(context, PublishFollowUp.edit),
          ),
        ],
      ),
      _ => IssuerErrorNotice(error: error),
    };
  }
}

/// The pre-publish checklist (hints only; the server decides).
class SetupChecklist extends StatelessWidget {
  const SetupChecklist({required this.competition, super.key});

  final Competition competition;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final colors = context.semanticColors;
    final checks = setupChecklist(competition);
    String label(SetupItem item) => switch (item) {
      SetupItem.description => l10n.issuerChecklistDescription,
      SetupItem.schedule => l10n.issuerChecklistSchedule,
      SetupItem.startPrice => l10n.issuerChecklistStartPrice,
      SetupItem.categoryOtherText => l10n.issuerChecklistOtherText,
      SetupItem.invitations => l10n.issuerChecklistInvitations(
        competition.rules.minParticipants,
        competition.counts?.invitations ?? 0,
      ),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          header: true,
          child: Text(
            l10n.issuerChecklistTitle,
            style: theme.textTheme.titleSmall,
          ),
        ),
        const SizedBox(height: BafoSpacing.sm),
        for (final check in checks)
          MergeSemantics(
            child: Padding(
              padding: const EdgeInsetsDirectional.only(top: BafoSpacing.xs),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    check.done
                        ? Icons.check_circle_rounded
                        : Icons.radio_button_unchecked_rounded,
                    size: 20,
                    color: check.done
                        ? colors.success.foreground
                        : theme.colorScheme.onSurfaceVariant,
                    semanticLabel: check.done
                        ? l10n.commonRuleMet
                        : l10n.commonRuleNotMet,
                  ),
                  const SizedBox(width: BafoSpacing.sm),
                  Expanded(
                    child: Text(
                      label(check.item),
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
