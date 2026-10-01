import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/issuer/presentation/create/draft_form_fields.dart';
import 'package:bafo/features/issuer/presentation/edit/edit_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M39 (`/competitions/:id/edit`): the fields the status allows (W16).
class EditCompetitionScreen extends StatelessWidget {
  const EditCompetitionScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => EditCompetitionCubit(
      competitions: context.read<CompetitionsRepository>(),
      lookups: context.read<LookupsRepository>(),
      now: context.read<ServerClock>().now,
      competitionId: competitionId,
    )..load(),
    child: const _EditView(),
  );
}

class _EditView extends StatelessWidget {
  const _EditView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<EditCompetitionCubit, EditCompetitionState>(
      listener: (context, state) {
        switch (state) {
          case EditCompetitionSaved():
            BafoToast.success(context, l10n.issuerEditSaved);
            context.pop(true);
          case EditCompetitionUnavailable(:final competition):
            // Role and status guard: back to the detail.
            leaveToDetail(context, competition.id);
          default:
            break;
        }
      },
      builder: (context, state) {
        final cubit = context.read<EditCompetitionCubit>();
        final editing = state is EditCompetitionEditing ? state : null;
        return PopScope(
          canPop: editing == null || !editing.isDirty,
          onPopInvokedWithResult: (didPop, _) async {
            if (didPop || editing == null || editing.saving) return;
            final discard = await showConfirmDialog(
              context,
              title: l10n.issuerEditDiscardTitle,
              message: l10n.issuerEditDiscardMessage,
              confirmLabel: l10n.issuerDiscard,
              destructive: true,
            );
            if (discard && context.mounted) GoRouter.of(context).pop();
          },
          child: Scaffold(
            appBar: BafoAppBar(title: l10n.issuerEditTitle),
            body: switch (state) {
              EditCompetitionInitial() ||
              EditCompetitionLoading() ||
              EditCompetitionUnavailable() ||
              EditCompetitionSaved() => const LoadingSkeletonList(),
              EditCompetitionNotFound() => NotFoundState(
                message: l10n.competitionsDetailNotFound,
              ),
              EditCompetitionFailure(:final error) => ErrorState(
                error: error,
                onRetry: cubit.load,
              ),
              EditCompetitionEditing() => _Form(state: state),
            },
          ),
        );
      },
    );
  }
}

class _Form extends StatelessWidget {
  const _Form({required this.state});

  final EditCompetitionEditing state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<EditCompetitionCubit>();
    final clock = context.read<ServerClock>();
    final scope = state.scope;
    final initial = EditCompetitionCubit.formOf(state.competition);
    final saveError = state.saveError;
    final notEditable = saveError?.details['fields'];
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: BafoSpacing.pagePadding,
            children: [
              if (scope != EditScope.draft) ...[
                InfoNotice(message: l10n.issuerEditNotifyNote),
                const SizedBox(height: BafoSpacing.sm),
                WebOnlyNotice(
                  message: scope == EditScope.live
                      ? l10n.issuerEditLiveScope
                      : l10n.issuerEditRulesFixed,
                ),
              ] else
                WebOnlyNotice(message: l10n.issuerEditTypeOnWeb),
              const SizedBox(height: BafoSpacing.xl),
              DraftFormFields(
                section: DraftFormSection.basics,
                initial: initial,
                form: state.form,
                lookups: state.lookups,
                problems: state.problems,
                serverErrors: state.serverErrors,
                granularityMinor:
                    state.competition.rules.amountGranularityMinor,
                publishAt: clock.now(),
                isEnabled: scope.allows,
                onChanged: cubit.update,
              ),
              if (scope != EditScope.live) ...[
                const SizedBox(height: BafoSpacing.xl),
                DraftFormFields(
                  section: DraftFormSection.schedule,
                  initial: initial,
                  form: state.form,
                  lookups: state.lookups,
                  problems: state.problems,
                  serverErrors: state.serverErrors,
                  granularityMinor:
                      state.competition.rules.amountGranularityMinor,
                  publishAt: clock.now(),
                  rulesForPreview: scope == EditScope.draft
                      ? state.competition.rules
                      : null,
                  isEnabled: scope.allows,
                  onChanged: cubit.update,
                ),
              ],
              if (saveError != null) ...[
                const SizedBox(height: BafoSpacing.lg),
                IssuerErrorNotice(error: saveError),
                if (notEditable is List && notEditable.isNotEmpty) ...[
                  const SizedBox(height: BafoSpacing.xs),
                  Ltr(
                    child: Text(
                      notEditable.join(', '),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                ],
              ],
              const SizedBox(height: BafoSpacing.xl),
            ],
          ),
        ),
        Material(
          elevation: 3,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(
                BafoSpacing.page,
                BafoSpacing.md,
                BafoSpacing.page,
                BafoSpacing.md,
              ),
              child: BafoButton(
                label: l10n.commonActionsSave,
                expand: true,
                loading: state.saving,
                onPressed: state.isDirty && !issuerOffline(context)
                    ? cubit.save
                    : null,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
