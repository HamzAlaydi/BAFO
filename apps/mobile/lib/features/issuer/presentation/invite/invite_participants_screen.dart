import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/issuer/domain/staged_invitee.dart';
import 'package:bafo/features/issuer/presentation/invite/invite_participants_cubit.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M40 (`/competitions/:id/invite`): Suggestions / E-mail / Vendors,
/// staged chips, then one all-or-nothing `POST …/invitations`. Fees are
/// never paid on mobile (SCREENS.md §3.2).
class InviteParticipantsScreen extends StatelessWidget {
  const InviteParticipantsScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => InviteParticipantsCubit(
      competitions: context.read<CompetitionsRepository>(),
      invitations: context.read<InvitationsRepository>(),
      vendors: context.read<VendorsRepository>(),
      competitionId: competitionId,
    )..load(),
    child: const _InviteView(),
  );
}

class _InviteView extends StatelessWidget {
  const _InviteView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<InviteParticipantsCubit, InviteParticipantsState>(
      listener: (context, state) {
        switch (state) {
          case InviteSent(:final invitations):
            final draft =
                invitations.isNotEmpty &&
                invitations.every(
                  (row) => row.status == InvitationStatus.draft,
                );
            BafoToast.success(
              context,
              draft ? l10n.issuerInviteAddedToDraft : l10n.issuerInviteDone,
            );
            context.pop(true);
          case InviteUnavailable(:final competition):
            leaveToDetail(context, competition.id);
          default:
            break;
        }
      },
      builder: (context, state) {
        final cubit = context.read<InviteParticipantsCubit>();
        final ready = state is InviteReady ? state : null;
        return PopScope(
          canPop: ready == null || ready.staged.isEmpty,
          onPopInvokedWithResult: (didPop, _) async {
            if (didPop || ready == null || ready.submitting) return;
            final discard = await showConfirmDialog(
              context,
              title: l10n.issuerInviteDiscardTitle,
              message: l10n.issuerInviteDiscardMessage,
              confirmLabel: l10n.issuerDiscard,
              destructive: true,
            );
            if (discard && context.mounted) GoRouter.of(context).pop();
          },
          child: Scaffold(
            appBar: BafoAppBar(title: l10n.issuerInviteTitle),
            body: switch (state) {
              InviteLoading() ||
              InviteUnavailable() ||
              InviteSent() => const LoadingSkeletonList(),
              InviteFailure(:final error) => ErrorState(
                error: error,
                onRetry: cubit.load,
              ),
              InviteReady() => _Ready(state: state),
            },
          ),
        );
      },
    );
  }
}

class _Ready extends StatelessWidget {
  const _Ready({required this.state});

  final InviteReady state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<InviteParticipantsCubit>();
    final draft = state.competition.status == CompetitionStatus.draft;
    final count = state.staged.length;
    final offline = issuerOffline(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(
            BafoSpacing.page,
            BafoSpacing.md,
            BafoSpacing.page,
            0,
          ),
          child: SegmentedFilter<InviteTab>(
            segments: [
              FilterSegment(
                value: InviteTab.suggestions,
                label: l10n.issuerInviteTabSuggestions,
              ),
              FilterSegment(
                value: InviteTab.email,
                label: l10n.issuerInviteTabEmail,
              ),
              FilterSegment(
                value: InviteTab.vendors,
                label: l10n.issuerInviteTabVendors,
              ),
            ],
            selected: state.tab,
            onChanged: cubit.selectTab,
          ),
        ),
        Expanded(
          child: ListView(
            padding: BafoSpacing.pagePadding,
            children: [
              switch (state.tab) {
                InviteTab.suggestions => _SuggestionsTab(state: state),
                InviteTab.email => _EmailTab(state: state),
                InviteTab.vendors => _VendorsTab(state: state),
              },
              const SizedBox(height: BafoSpacing.xl),
              _StagedSection(state: state),
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
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (state.canSendWithoutFees) ...[
                    BafoButton.outline(
                      label: l10n.issuerInviteSendWithoutFees,
                      onPressed: state.submitting || offline
                          ? null
                          : cubit.sendWithoutCoveringFees,
                    ),
                    const SizedBox(height: BafoSpacing.sm),
                  ],
                  BafoButton(
                    label: count == 0
                        ? l10n.issuerInviteChooseFirst
                        : draft
                        ? l10n.issuerInviteAdd(count)
                        : l10n.issuerInviteSend(count),
                    icon: Icons.send_rounded,
                    expand: true,
                    loading: state.submitting,
                    onPressed: count == 0 || offline ? null : cubit.submit,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SuggestionsTab extends StatelessWidget {
  const _SuggestionsTab({required this.state});

  final InviteReady state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<InviteParticipantsCubit>();
    final search = state.suggestions;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SearchField(
          hint: l10n.issuerInviteSearchSuggestions,
          initialValue: search.query,
          onChanged: cubit.searchSuggestions,
        ),
        const SizedBox(height: BafoSpacing.md),
        if (search.loading && search.items.isEmpty)
          const LoadingSkeleton(height: 72)
        else if (search.error != null)
          ErrorState(
            error: search.error,
            onRetry: () => cubit.searchSuggestions(search.query),
          )
        else if (search.items.isEmpty)
          _Hint(l10n.issuerInviteNoSuggestions)
        else
          for (final suggestion in search.items)
            _SuggestionTile(
              suggestion: suggestion,
              selected: state.isStaged('org:${suggestion.organization.id}'),
              onTap: () => cubit.toggleSuggestion(suggestion),
            ),
      ],
    );
  }
}

class _SuggestionTile extends StatelessWidget {
  const _SuggestionTile({
    required this.suggestion,
    required this.selected,
    required this.onTap,
  });

  final Suggestion suggestion;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final organization = suggestion.organization;
    return MergeSemantics(
      child: CheckboxListTile(
        value: selected,
        onChanged: (_) => onTap(),
        contentPadding: EdgeInsetsDirectional.zero,
        controlAffinity: ListTileControlAffinity.trailing,
        secondary: OrgAvatar(
          name: organization.name,
          logoUrl: organization.logoUrl,
          decorative: true,
        ),
        title: Row(
          children: [
            Flexible(child: Text(organization.name)),
            if (organization.verified) ...[
              const SizedBox(width: BafoSpacing.xs),
              Icon(
                Icons.verified_rounded,
                size: 16,
                color: theme.colorScheme.primary,
                semanticLabel: l10n.issuerInviteVerified,
              ),
            ],
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsetsDirectional.only(top: BafoSpacing.xs),
          child: Wrap(
            spacing: BafoSpacing.xs,
            runSpacing: BafoSpacing.xs,
            children: [
              if (suggestion.region != null)
                StatusPill(label: suggestion.region!.name),
              if (suggestion.matchesCategory)
                StatusPill(
                  label: l10n.issuerInviteMatchCategory,
                  tone: StatusTone.info,
                  icon: Icons.category_outlined,
                ),
              if (suggestion.matchesRegion)
                StatusPill(
                  label: l10n.issuerInviteMatchRegion,
                  tone: StatusTone.info,
                  icon: Icons.place_outlined,
                ),
              if (suggestion.hasActivePlan)
                StatusPill(
                  label: l10n.issuerInviteHasPlan,
                  tone: StatusTone.success,
                  icon: Icons.workspace_premium_outlined,
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmailTab extends StatefulWidget {
  const _EmailTab({required this.state});

  final InviteReady state;

  @override
  State<_EmailTab> createState() => _EmailTabState();
}

class _EmailTabState extends State<_EmailTab> {
  final TextEditingController _emails = TextEditingController();

  @override
  void dispose() {
    _emails.dispose();
    super.dispose();
  }

  void _add() {
    final text = _emails.text;
    if (text.trim().isEmpty) return;
    context.read<InviteParticipantsCubit>().addEmails(text);
    final (:valid, :invalid) = splitEmails(text);
    // Keep only what could not be added, so the user can fix it.
    _emails.text = invalid.join('\n');
    if (valid.isNotEmpty) {
      BafoToast.show(
        context,
        context.l10n.issuerInviteEmailsAdded(valid.length),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final invalid = widget.state.invalidEmails;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        BafoTextField(
          label: l10n.issuerInviteEmailsLabel,
          controller: _emails,
          helperText: l10n.issuerInviteEmailsHelper,
          errorText: invalid.isEmpty
              ? null
              : l10n.issuerInviteEmailsInvalid(
                  invalid
                      .map(ltrIsolate)
                      .join(context.languageCode == 'ar' ? '، ' : ', '),
                ),
          keyboardType: TextInputType.emailAddress,
          textDirection: TextDirection.ltr,
          minLines: 3,
          maxLines: 6,
        ),
        const SizedBox(height: BafoSpacing.sm),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: BafoButton.tonal(
            label: l10n.issuerInviteEmailsAdd,
            icon: Icons.add_rounded,
            onPressed: _add,
          ),
        ),
      ],
    );
  }
}

class _VendorsTab extends StatelessWidget {
  const _VendorsTab({required this.state});

  final InviteReady state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<InviteParticipantsCubit>();
    final search = state.vendors;
    final forbidden = search.error?.statusCode == 403;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SearchField(
          hint: l10n.issuerInviteSearchVendors,
          initialValue: search.query,
          onChanged: cubit.searchVendors,
        ),
        const SizedBox(height: BafoSpacing.md),
        if (search.loading && search.items.isEmpty)
          const LoadingSkeleton(height: 72)
        else if (forbidden)
          const ForbiddenState()
        else if (search.error != null)
          ErrorState(
            error: search.error,
            onRetry: () => cubit.searchVendors(search.query),
          )
        else if (search.items.isEmpty)
          _Hint(l10n.issuerInviteNoVendors)
        else
          for (final vendor in search.items)
            MergeSemantics(
              child: CheckboxListTile(
                value: state.isStaged('vendor:${vendor.id}'),
                onChanged: (_) => cubit.toggleVendor(vendor),
                contentPadding: EdgeInsetsDirectional.zero,
                controlAffinity: ListTileControlAffinity.trailing,
                title: Text(vendor.name),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Ltr(child: Text(vendor.email)),
                    if (vendor.linkedOrganization != null) ...[
                      const SizedBox(height: BafoSpacing.xs),
                      StatusPill(
                        label: l10n.issuerInviteVendorRegistered,
                        tone: StatusTone.success,
                        icon: Icons.verified_outlined,
                      ),
                    ],
                  ],
                ),
              ),
            ),
      ],
    );
  }
}

class _StagedSection extends StatelessWidget {
  const _StagedSection({required this.state});

  final InviteReady state;

  String? _rowError(AppLocalizations l10n, InviteRowError? error) {
    if (error == null) return null;
    return switch (error.code) {
      'invitation_duplicate' => l10n.issuerInviteRowDuplicate,
      'cannot_invite_own_organization' => l10n.issuerInviteRowOwnOrganization,
      'vendor_blocked' => l10n.issuerInviteRowVendorBlocked,
      'vendor_not_found' => l10n.issuerInviteRowVendorNotFound,
      _ => error.message.isEmpty ? l10n.errorsValidationFailed : error.message,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final cubit = context.read<InviteParticipantsCubit>();
    final error = state.submitError;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          header: true,
          child: Text(
            l10n.issuerInviteStaged(state.staged.length),
            style: theme.textTheme.titleMedium,
          ),
        ),
        if (state.selectedMode) ...[
          const SizedBox(height: BafoSpacing.sm),
          InfoNotice(
            message: l10n.issuerInviteFreeSlots(state.freeSlots),
            icon: Icons.confirmation_number_outlined,
          ),
        ],
        if (state.rowErrors.isNotEmpty) ...[
          const SizedBox(height: BafoSpacing.sm),
          Semantics(
            liveRegion: true,
            child: InfoNotice(
              message: l10n.issuerInviteNothingSent,
              tone: StatusTone.danger,
              icon: Icons.error_outline_rounded,
            ),
          ),
        ],
        if (error != null) ...[
          const SizedBox(height: BafoSpacing.sm),
          if (error.code == 'sponsorship_payment_required')
            InfoNotice(
              message: l10n.issuerInviteFeesOnWeb,
              icon: Icons.confirmation_number_outlined,
            )
          else
            IssuerErrorNotice(error: error),
        ],
        const SizedBox(height: BafoSpacing.sm),
        if (state.staged.isEmpty)
          _Hint(l10n.issuerInviteStagedEmpty)
        else
          for (final row in state.staged)
            _StagedTile(
              row: row,
              error: _rowError(l10n, state.rowErrors[row.key]),
              selectedMode: state.selectedMode,
              canSponsor: row.sponsored || state.canSponsorMore,
              enabled: !state.submitting,
              onRemove: () => cubit.remove(row.key),
              onSponsored: (value) =>
                  cubit.setSponsored(row.key, sponsored: value),
            ),
      ],
    );
  }
}

class _StagedTile extends StatelessWidget {
  const _StagedTile({
    required this.row,
    required this.error,
    required this.selectedMode,
    required this.canSponsor,
    required this.enabled,
    required this.onRemove,
    required this.onSponsored,
  });

  final StagedInvitee row;
  final String? error;
  final bool selectedMode;
  final bool canSponsor;
  final bool enabled;
  final VoidCallback onRemove;
  final ValueChanged<bool> onSponsored;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final colors = error == null
        ? null
        : StatusTone.danger.resolve(context.semanticColors);
    final isEmail = row.source == InviteeSource.email;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
      child: BafoCard(
        color: colors?.background,
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.md,
          BafoSpacing.sm,
          BafoSpacing.xs,
          BafoSpacing.sm,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(
                  switch (row.source) {
                    InviteeSource.suggestion => Icons.apartment_rounded,
                    InviteeSource.vendor => Icons.store_outlined,
                    InviteeSource.email => Icons.alternate_email_rounded,
                  },
                  size: 20,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: BafoSpacing.sm),
                Expanded(
                  child: isEmail
                      ? Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: Ltr(child: Text(row.label)),
                        )
                      : Text(row.label),
                ),
                IconButton(
                  tooltip: l10n.issuerInviteRemove(row.label),
                  icon: const Icon(Icons.close_rounded),
                  onPressed: enabled ? onRemove : null,
                ),
              ],
            ),
            if (selectedMode)
              SwitchListTile(
                value: row.sponsored,
                onChanged: enabled && canSponsor ? onSponsored : null,
                contentPadding: EdgeInsetsDirectional.zero,
                title: Text(l10n.issuerInviteCoverFees),
              ),
            if (error != null)
              Semantics(
                liveRegion: true,
                child: Text(
                  error!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors?.foreground,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Hint extends StatelessWidget {
  const _Hint(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: BafoSpacing.md),
      child: Text(
        text,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
    );
  }
}
