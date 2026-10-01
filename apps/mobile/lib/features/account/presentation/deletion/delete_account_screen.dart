import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/deletion/account_deletion_cubit.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M61 Delete account (as W28, a store requirement): the scope (the owner
/// deletes the organisation, others their own user), what is kept, then
/// the password and an optional reason. A pending request shows its date
/// and can be cancelled. Blockers link to their competitions.
class DeleteAccountScreen extends StatelessWidget {
  const DeleteAccountScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) =>
        AccountDeletionCubit(context.read<AccountDeletionRepository>())..load(),
    child: const _DeleteAccountView(),
  );
}

class _DeleteAccountView extends StatelessWidget {
  const _DeleteAccountView();

  void _onState(BuildContext context, AccountDeletionState state) {
    if (state is! AccountDeletionLoaded) return;
    final l10n = context.l10n;
    final error = state.error;
    if (error != null) BafoToast.error(context, errorMessage(l10n, error));
    switch (state.outcome?.kind) {
      case DeletionResult.requested:
        BafoToast.success(context, l10n.accountDeleteRequested);
      case DeletionResult.cancelled:
        BafoToast.success(context, l10n.accountDeleteCancelled);
      case null:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<AccountDeletionCubit>();
    return Scaffold(
      appBar: BafoAppBar(title: l10n.accountDeleteTitle),
      body: BlocConsumer<AccountDeletionCubit, AccountDeletionState>(
        listenWhen: (previous, current) =>
            current is AccountDeletionLoaded &&
            (previous is! AccountDeletionLoaded ||
                previous.outcome != current.outcome ||
                (current.error != null && previous.error != current.error)),
        listener: _onState,
        builder: (context, state) => switch (state) {
          AccountDeletionLoading() => const LoadingSkeletonList(itemCount: 2),
          AccountDeletionFailure(:final error) => ErrorState(
            error: error,
            onRetry: cubit.load,
          ),
          AccountDeletionLoaded(:final pending?) => _PendingView(
            pending: pending,
            cancelling: state.busy == DeletionBusy.cancelling,
          ),
          AccountDeletionLoaded() => _RequestForm(state: state),
        },
      ),
    );
  }
}

class _PendingView extends StatelessWidget {
  const _PendingView({required this.pending, required this.cancelling});

  final AccountDeletionRequest pending;
  final bool cancelling;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final at = pending.scheduledFor;
    return ListView(
      padding: BafoSpacing.pagePadding,
      children: [
        InfoNotice(
          key: const Key('deletion.pending'),
          tone: StatusTone.warning,
          icon: Icons.schedule_rounded,
          title: at == null
              ? null
              : l10n.accountDeletePending(
                  BafoDateFormat.longDate(at, context.languageCode),
                ),
          message: pending.scope == DeletionScope.organization
              ? l10n.accountDeleteScopeOrganization
              : l10n.accountDeleteScopeUser,
        ),
        const SizedBox(height: BafoSpacing.md),
        Text(
          l10n.accountDeleteCancelWindow,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: BafoSpacing.xl),
        BafoButton(
          key: const Key('deletion.cancel'),
          label: l10n.accountDeleteCancel,
          icon: Icons.undo_rounded,
          expand: true,
          loading: cancelling,
          onPressed: cancelling
              ? null
              : () => unawaited(context.read<AccountDeletionCubit>().cancel()),
        ),
      ],
    );
  }
}

class _RequestForm extends StatefulWidget {
  const _RequestForm({required this.state});

  final AccountDeletionLoaded state;

  @override
  State<_RequestForm> createState() => _RequestFormState();
}

class _RequestFormState extends State<_RequestForm> {
  final _form = GlobalKey<FormState>();
  final _password = TextEditingController();
  final _reason = TextEditingController();

  @override
  void dispose() {
    _password.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _submit(String scopeText) async {
    if (!(_form.currentState?.validate() ?? false)) return;
    final l10n = context.l10n;
    final cubit = context.read<AccountDeletionCubit>();
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.accountDeleteConfirmTitle,
      message: scopeText,
      confirmLabel: l10n.accountDeleteTitle,
      destructive: true,
    );
    if (!confirmed) return;
    await cubit.request(password: _password.text, reason: _reason.text);
  }

  static String? _passwordErrorText(
    AppLocalizations l10n,
    ApiException? error,
  ) {
    if (error == null) return null;
    if (error.code == 'password_incorrect') return l10n.errorsPasswordIncorrect;
    return error.fieldError('password') ?? errorMessage(l10n, error);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final state = widget.state;
    final ownsOrganization = context.select<SessionCubit, bool>(
      (cubit) => cubit.state.me?.can(Permissions.deleteOrganization) ?? false,
    );
    final scopeText = ownsOrganization
        ? l10n.accountDeleteScopeOrganization
        : l10n.accountDeleteScopeUser;
    final busy = state.busy == DeletionBusy.requesting;

    return Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.page,
          BafoSpacing.lg,
          BafoSpacing.page,
          BafoSpacing.xxl,
        ),
        children: [
          InfoNotice(
            key: const Key('deletion.scope'),
            tone: StatusTone.warning,
            icon: Icons.warning_amber_rounded,
            message: scopeText,
          ),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.accountDeleteCancelWindow,
            style: theme.textTheme.bodyMedium,
          ),
          FormSectionTitle(l10n.accountDeleteKeptTitle),
          Text(l10n.accountDeleteKept, style: theme.textTheme.bodyMedium),
          if (state.blockers.isNotEmpty) ...[
            const SizedBox(height: BafoSpacing.xl),
            _Blockers(blockers: state.blockers),
          ],
          const SizedBox(height: BafoSpacing.xl),
          PasswordField(
            key: const Key('deletion.password'),
            controller: _password,
            errorText: _passwordErrorText(l10n, state.passwordError),
            textInputAction: TextInputAction.next,
            validator: (value) => Validators.required(value, l10n),
          ),
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            key: const Key('deletion.reason'),
            label: l10n.accountDeleteReason,
            controller: _reason,
            optional: true,
            minLines: 2,
            maxLines: 4,
            maxLength: 1000,
          ),
          const SizedBox(height: BafoSpacing.xl),
          BafoButton.destructive(
            key: const Key('deletion.submit'),
            label: l10n.accountDeleteTitle,
            icon: Icons.person_remove_outlined,
            expand: true,
            loading: busy,
            onPressed: busy ? null : () => unawaited(_submit(scopeText)),
          ),
        ],
      ),
    );
  }
}

class _Blockers extends StatelessWidget {
  const _Blockers({required this.blockers});

  final List<DeletionBlocker> blockers;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Column(
      key: const Key('deletion.blockers'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        InfoNotice(
          tone: StatusTone.danger,
          icon: Icons.block_rounded,
          title: l10n.accountDeleteBlockedTitle,
          message: l10n.accountDeleteBlockedMessage,
        ),
        const SizedBox(height: BafoSpacing.sm),
        BafoCard(
          padding: const EdgeInsetsDirectional.symmetric(
            vertical: BafoSpacing.xs,
          ),
          child: Column(
            children: [
              for (final (index, blocker) in blockers.indexed) ...[
                if (index > 0) const Divider(height: 1),
                ListTile(
                  leading: Icon(
                    blocker.type == 'issued_competition'
                        ? Icons.campaign_outlined
                        : Icons.local_offer_outlined,
                    color: muted,
                  ),
                  title: Text(blocker.title),
                  subtitle: Text(
                    blocker.type == 'issued_competition'
                        ? l10n.accountDeleteBlockerIssued
                        : l10n.accountDeleteBlockerParticipation,
                  ),
                  trailing: blocker.competitionId.isEmpty
                      ? null
                      : Icon(Icons.chevron_right_rounded, color: muted),
                  onTap: blocker.competitionId.isEmpty
                      ? null
                      : () => context.push(
                          AppRoutes.competition(blocker.competitionId),
                        ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
