import 'dart:async';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/create/draft_form_fields.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M34–M37 (`/my-competitions/new`): type and rules level → basics →
/// prices and schedule → review, then one `POST /competitions`. Advanced
/// rules are web-only (CD4).
///
/// Release scope (RELEASE_SCOPE.md §2.6, §4): the format cards need the
/// `sealed_format` flag (otherwise every competition is live), the preset
/// list is replaced by the tier cards, and the reserve price needs
/// `advanced_rules`.
class CreateCompetitionScreen extends StatelessWidget {
  const CreateCompetitionScreen({super.key});

  static PreferencesStore? _drafts(BuildContext context) {
    try {
      return context.read<PreferencesStore>();
    } on ProviderNotFoundException {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final clock = context.read<ServerClock>();
    final me = context.read<SessionCubit>().state.me;
    // S10 for deep links: the permission, then the plan (no purchase path).
    if (me != null && !me.canCreateCompetition) {
      final l10n = context.l10n;
      return Scaffold(
        appBar: BafoAppBar(title: l10n.issuerCreateTitle),
        body: me.can(Permissions.competitionsCreate)
            ? Padding(
                padding: BafoSpacing.pagePadding,
                child: InfoNotice(
                  title: l10n.issuerPlanRequired,
                  message: l10n.billingManagedOnWeb,
                  tone: StatusTone.warning,
                  icon: Icons.workspace_premium_outlined,
                ),
              )
            : const ForbiddenState(),
      );
    }
    return BlocProvider(
      create: (context) => CreateCompetitionCubit(
        competitions: context.read<CompetitionsRepository>(),
        lookups: context.read<LookupsRepository>(),
        now: clock.now,
        auctionEnabled: me?.organization.features.auctionEnabled ?? false,
        flags: context.flagsNow,
        drafts: _drafts(context),
      )..load(),
      child: const _CreateView(),
    );
  }
}

class _CreateView extends StatelessWidget {
  const _CreateView();

  Future<bool> _confirmDiscard(BuildContext context) => showConfirmDialog(
    context,
    title: context.l10n.issuerCreateDiscardTitle,
    message: context.l10n.issuerCreateDiscardMessage,
    confirmLabel: context.l10n.issuerDiscard,
    destructive: true,
  );

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<CreateCompetitionCubit, CreateCompetitionState>(
      listenWhen: (previous, current) => current is CreateCompetitionSaved,
      listener: (context, state) {
        if (state is CreateCompetitionSaved) {
          BafoToast.success(context, l10n.issuerCreateSaved);
          // The draft replaces the wizard, whoever opened it (M33, home).
          final router = GoRouter.of(context)..pop();
          unawaited(router.push(IssuerPaths.competition(state.competition.id)));
        }
      },
      builder: (context, state) {
        final cubit = context.read<CreateCompetitionCubit>();
        final editing = state is CreateCompetitionEditing ? state : null;
        // Unsaved-changes guard (S7): back steps through the wizard, and
        // leaving a started form asks first.
        return PopScope(
          canPop:
              editing == null ||
              (editing.step == CreateStep.type && !editing.isDirty),
          onPopInvokedWithResult: (didPop, _) async {
            if (didPop || editing == null || editing.submitting) return;
            if (editing.step != CreateStep.type) {
              cubit.back();
              return;
            }
            if (await _confirmDiscard(context) && context.mounted) {
              cubit.discard();
              GoRouter.of(context).pop();
            }
          },
          child: Scaffold(
            appBar: BafoAppBar(title: l10n.issuerCreateTitle),
            body: switch (state) {
              CreateCompetitionInitial() ||
              CreateCompetitionLoading() => const LoadingSkeletonList(),
              CreateCompetitionFailure(:final error) => ErrorState(
                error: error,
                onRetry: cubit.load,
              ),
              CreateCompetitionSaved() => const LoadingSkeletonList(),
              CreateCompetitionEditing() => _Wizard(state: state),
            },
          ),
        );
      },
    );
  }
}

class _Wizard extends StatefulWidget {
  const _Wizard({required this.state});

  final CreateCompetitionEditing state;

  @override
  State<_Wizard> createState() => _WizardState();
}

class _WizardState extends State<_Wizard> {
  final ScrollController _scroll = ScrollController();

  /// Scroll targets of the error summary (FQ8).
  final Map<DraftField, GlobalKey> _fieldKeys = {
    for (final field in DraftField.values) field: GlobalKey(),
  };

  @override
  void didUpdateWidget(_Wizard oldWidget) {
    super.didUpdateWidget(oldWidget);
    // FQ9: a new step starts at its heading. After the frame: a jump during
    // the build phase would notify the app bar's scroll listener mid-build.
    if (oldWidget.state.step != widget.state.step) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _scroll.hasClients) _scroll.jumpTo(0);
      });
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  String _stepTitle(AppLocalizations l10n) => switch (widget.state.step) {
    CreateStep.type => l10n.issuerCreateStepType,
    CreateStep.basics => l10n.issuerCreateStepBasics,
    CreateStep.schedule => l10n.issuerCreateStepSchedule,
    CreateStep.review => l10n.issuerCreateStepReview,
  };

  void _scrollTo(DraftField field) {
    final target = _fieldKeys[field]?.currentContext;
    if (target == null) return;
    unawaited(
      Scrollable.ensureVisible(
        target,
        alignment: 0.1,
        duration: const Duration(milliseconds: 200),
      ),
    );
  }

  /// FQ8: every shown problem and server error as a link to its field.
  List<FormErrorItem> _summary(BuildContext context) {
    final l10n = context.l10n;
    final state = widget.state;
    final cubit = context.read<CreateCompetitionCubit>();
    final direction = state.form.direction;
    final now = context.read<ServerClock>().now();
    final items = <FormErrorItem>[];
    void add(DraftField field, String message) {
      final otherStep = field.step != state.step;
      items.add(
        FormErrorItem(
          label: draftFieldLabel(l10n, field, direction),
          message: message,
          note: otherStep
              ? l10n.commonErrorSummaryOtherStep(field.step.number)
              : null,
          onTap: otherStep
              ? () => cubit.jumpTo(field.step)
              : () => _scrollTo(field),
        ),
      );
    }

    for (final entry in state.serverErrors.entries) {
      add(entry.key, entry.value);
    }
    if (state.showAllProblems) {
      for (final entry in state.problems.entries) {
        if (state.serverErrors.containsKey(entry.key)) continue;
        add(
          entry.key,
          draftProblemText(
            l10n,
            entry.key,
            entry.value,
            direction,
            duration: entry.key == DraftField.closesAt
                ? state.form.durationFrom(now)
                : null,
          ),
        );
      }
    }
    return items;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = widget.state;
    final cubit = context.read<CreateCompetitionCubit>();
    final clock = context.read<ServerClock>();
    final preset = state.preset;
    final flags = context.flags;
    final showReserve =
        flags.enabled(Feature.advancedRules) ||
        state.form.reservePriceMinor != null;
    final Widget body = switch (state.step) {
      CreateStep.type => _TypeStep(state: state, fieldKeys: _fieldKeys),
      CreateStep.basics => DraftFormFields(
        key: const ValueKey('basics'),
        section: DraftFormSection.basics,
        initial: state.form,
        form: state.form,
        lookups: state.lookups,
        problems: state.problems,
        serverErrors: state.serverErrors,
        granularityMinor: preset?.rules.amountGranularityMinor ?? 100,
        publishAt: clock.now(),
        onChanged: cubit.update,
        onTouched: cubit.touch,
        fieldKeys: _fieldKeys,
      ),
      CreateStep.schedule => DraftFormFields(
        key: const ValueKey('schedule'),
        section: DraftFormSection.schedule,
        initial: state.form,
        form: state.form,
        lookups: state.lookups,
        problems: state.problems,
        serverErrors: state.serverErrors,
        granularityMinor: preset?.rules.amountGranularityMinor ?? 100,
        publishAt: clock.now(),
        rulesForPreview: preset?.rules,
        showReserve: showReserve,
        onChanged: cubit.update,
        onTouched: cubit.touch,
        fieldKeys: _fieldKeys,
      ),
      CreateStep.review => _ReviewStep(state: state, showReserve: showReserve),
    };
    final summary = _summary(context);
    return Column(
      children: [
        Expanded(
          child: ListView(
            controller: _scroll,
            padding: BafoSpacing.pagePadding,
            children: [
              StepperHeader(
                current: state.step.number,
                total: CreateStep.count,
                title: _stepTitle(l10n),
                announce: true,
              ),
              if (state.restored) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  key: const Key('create.restored'),
                  message: l10n.issuerCreateDraftRestored,
                  icon: Icons.history_rounded,
                ),
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: BafoButton.text(
                    key: const Key('create.startOver'),
                    label: l10n.issuerCreateStartOver,
                    icon: Icons.restart_alt_rounded,
                    onPressed: cubit.startOver,
                  ),
                ),
              ],
              if (summary.isNotEmpty) ...[
                const SizedBox(height: BafoSpacing.md),
                FormErrorSummary(
                  key: const Key('create.errors'),
                  items: summary,
                ),
              ],
              const SizedBox(height: BafoSpacing.xl),
              body,
              if (state.submitError != null) ...[
                const SizedBox(height: BafoSpacing.lg),
                IssuerErrorNotice(error: state.submitError!),
              ],
              const SizedBox(height: BafoSpacing.xl),
            ],
          ),
        ),
        _Footer(state: state),
      ],
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.state});

  final CreateCompetitionEditing state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final cubit = context.read<CreateCompetitionCubit>();
    final review = state.step == CreateStep.review;
    final offline = issuerOffline(context);
    return Material(
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
              // FQ7: a disabled primary action always says why.
              if (review && offline) ...[
                Text(
                  l10n.commonOfflineActionsDisabled,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: BafoSpacing.sm),
              ],
              Row(
                children: [
                  if (state.step != CreateStep.type) ...[
                    Expanded(
                      child: BafoButton.outline(
                        label: l10n.commonActionsBack,
                        onPressed: state.submitting ? null : cubit.back,
                      ),
                    ),
                    const SizedBox(width: BafoSpacing.md),
                  ],
                  Expanded(
                    flex: 2,
                    child: BafoButton(
                      key: const Key('create.next'),
                      label: review
                          ? l10n.issuerReviewSave
                          : l10n.commonActionsNext,
                      loading: state.submitting,
                      expand: true,
                      onPressed: review
                          ? (offline ? null : cubit.submit)
                          : cubit.next,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Step 1: type, format, rules level (M34) ─────────────────────────────

class _TypeStep extends StatelessWidget {
  const _TypeStep({required this.state, required this.fieldKeys});

  final CreateCompetitionEditing state;
  final Map<DraftField, GlobalKey> fieldKeys;

  static IconData tierIcon(PresetTier? tier) => switch (tier) {
    PresetTier.simple => Icons.bolt_rounded,
    PresetTier.standard => Icons.balance_rounded,
    PresetTier.protected => Icons.shield_outlined,
    _ => Icons.tune_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final cubit = context.read<CreateCompetitionCubit>();
    final flags = context.flags;
    final showFormat = flags.enabled(Feature.sealedFormat);
    final showOtherPresets = flags.enabled(Feature.advancedRules);
    final form = state.form;
    final ready = form.direction != null && form.format != null;
    final tiered = ready
        ? form.tieredPresetsFor(state.lookups.presets)
        : const <Preset>[];
    final untiered = ready
        ? form.untieredPresetsFor(state.lookups.presets)
        : const <Preset>[];
    final problems = state.problems;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    String? problem(DraftField field) => problems[field] == null
        ? null
        : draftProblemText(l10n, field, problems[field]!, form.direction);

    Widget presetCard(Preset preset, {bool chips = false}) => ChoiceCard(
      key: ValueKey('preset-${preset.code}'),
      selected: form.presetCode == preset.code,
      icon: tierIcon(preset.tier),
      title: preset.name,
      subtitle: preset.description,
      badge: preset.tier == PresetTier.standard
          ? l10n.issuerPresetTierRecommended
          : null,
      chips: chips
          ? presetRuleChips(l10n, preset.rules, context.languageCode)
          : const [],
      onTap: () => cubit.selectPreset(preset.code),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        KeyedSubtree(
          key: fieldKeys[DraftField.direction],
          child: SectionHeading(title: l10n.issuerCreateDirectionTitle),
        ),
        for (final direction in const [
          Direction.tender,
          Direction.auction,
        ]) ...[
          ChoiceCard(
            key: ValueKey('direction-${direction.wire}'),
            selected: form.direction == direction,
            enabled: direction != Direction.auction || state.auctionEnabled,
            icon: direction == Direction.tender
                ? Icons.south_rounded
                : Icons.north_rounded,
            title: l10n.competitionsDirectionLabel(direction.wire),
            subtitle:
                '${l10n.competitionsDirectionRule(direction.wire)} · '
                '${l10n.issuerCreateIssuerRole(direction.wire)}',
            disabledReason: l10n.issuerCreateAuctionDisabled,
            onTap: () => cubit.setDirection(direction),
          ),
          const SizedBox(height: BafoSpacing.sm),
        ],
        if (problem(DraftField.direction) != null)
          _FieldError(problem(DraftField.direction)!),
        if (showFormat) ...[
          const SizedBox(height: BafoSpacing.lg),
          KeyedSubtree(
            key: fieldKeys[DraftField.format],
            child: SectionHeading(title: l10n.issuerCreateFormatTitle),
          ),
          for (final format in const [
            CompetitionFormat.live,
            CompetitionFormat.sealed,
          ]) ...[
            ChoiceCard(
              key: ValueKey('format-${format.wire}'),
              selected: form.format == format,
              icon: format == CompetitionFormat.sealed
                  ? Icons.lock_outline_rounded
                  : Icons.bolt_rounded,
              title: format == CompetitionFormat.sealed
                  ? l10n.competitionsFormatSealed
                  : l10n.competitionsFormatLive,
              subtitle: format == CompetitionFormat.sealed
                  ? l10n.issuerCreateFormatSealedHint
                  : l10n.issuerCreateFormatLiveHint,
              onTap: () => cubit.setFormat(format),
            ),
            const SizedBox(height: BafoSpacing.sm),
          ],
          if (problem(DraftField.format) != null)
            _FieldError(problem(DraftField.format)!),
        ],
        if (ready) ...[
          const SizedBox(height: BafoSpacing.lg),
          KeyedSubtree(
            key: fieldKeys[DraftField.preset],
            child: SectionHeading(
              title: tiered.isNotEmpty
                  ? l10n.issuerPresetTierTitle
                  : l10n.issuerCreatePresetTitle,
            ),
          ),
          if (tiered.isNotEmpty) ...[
            Text(l10n.issuerPresetTierHint, style: muted),
            const SizedBox(height: BafoSpacing.md),
          ],
          if (tiered.isEmpty && untiered.isEmpty)
            WebOnlyNotice(message: l10n.issuerCreateNoPreset)
          else ...[
            for (final preset in tiered) ...[
              presetCard(preset),
              const SizedBox(height: BafoSpacing.sm),
            ],
            // Without tier cards for this choice (a sealed format, or a
            // server without tiers) the templates are the cards themselves.
            if (tiered.isEmpty)
              for (final preset in untiered) ...[
                presetCard(preset, chips: true),
                const SizedBox(height: BafoSpacing.sm),
              ]
            else if (showOtherPresets && untiered.isNotEmpty) ...[
              const SizedBox(height: BafoSpacing.md),
              SectionHeading(title: l10n.issuerPresetTierOther),
              for (final preset in untiered) ...[
                presetCard(preset, chips: true),
                const SizedBox(height: BafoSpacing.sm),
              ],
            ],
          ],
          if (problem(DraftField.preset) != null &&
              (tiered.isNotEmpty || untiered.isNotEmpty))
            _FieldError(problem(DraftField.preset)!),
        ],
        const SizedBox(height: BafoSpacing.lg),
        WebOnlyNotice(message: l10n.issuerCreateAdvancedOnWeb),
      ],
    );
  }
}

/// Up to three short rule chips of a preset (W12 preset cards).
List<String> presetRuleChips(
  AppLocalizations l10n,
  Rules rules,
  String languageCode,
) {
  final chips = <String>[];
  final bps = rules.minStepBps;
  final stepMinor = rules.minStepMinor;
  if (bps != null) {
    chips.add(
      l10n.issuerPresetChipStep(formatSignedBps(bps).replaceFirst('+', '')),
    );
  } else if (stepMinor != null) {
    chips.add(
      l10n.issuerPresetChipStep(
        ltrIsolate(
          MoneyFormat.format(Money(stepMinor), languageCode: languageCode),
        ),
      ),
    );
  }
  chips.add(switch (rules.rankVisibility) {
    RankVisibility.full => l10n.issuerPresetChipRankFull,
    RankVisibility.leadingFlag => l10n.issuerPresetChipLeadingFlag,
    _ => l10n.issuerPresetChipRankHidden,
  });
  if (rules.showPrices) chips.add(l10n.issuerPresetChipPricesShown);
  if (rules.autoExtend.enabled) chips.add(l10n.issuerPresetChipAutoExtend);
  if (rules.bafoRound.enabled) chips.add(l10n.issuerPresetChipBafo);
  return chips.take(3).toList();
}

class _FieldError extends StatelessWidget {
  const _FieldError(this.message);

  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      liveRegion: true,
      child: Text(
        message,
        style: theme.textTheme.bodySmall?.copyWith(
          color: theme.colorScheme.error,
        ),
      ),
    );
  }
}

/// A radio card (direction, format, preset): single choice with an icon,
/// a title and a line of explanation. The whole card is the target
/// (≥ 48 dp, FQ10).
class ChoiceCard extends StatelessWidget {
  const ChoiceCard({
    required this.selected,
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.badge,
    this.chips = const [],
    this.enabled = true,
    this.disabledReason,
    super.key,
  });

  final bool selected;
  final IconData icon;
  final String title;
  final String? subtitle;

  /// A small pill next to the title («موصى به»).
  final String? badge;
  final List<String> chips;
  final bool enabled;

  /// Shown under a disabled card (e.g. auctions not enabled).
  final String? disabledReason;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: scheme.onSurfaceVariant,
    );
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      enabled: enabled,
      button: true,
      child: Opacity(
        opacity: enabled ? 1 : 0.6,
        child: Material(
          color: selected
              ? scheme.primaryContainer.withValues(alpha: 0.35)
              : scheme.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(BafoRadii.card),
            side: BorderSide(
              color: selected ? scheme.primary : scheme.outlineVariant,
              width: selected ? 2 : 1,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: enabled ? onTap : null,
            child: Padding(
              padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Direction glyphs are never mirrored (S1).
                  Icon(icon, color: scheme.onSurface),
                  const SizedBox(width: BafoSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: BafoSpacing.sm,
                          runSpacing: BafoSpacing.xs,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(title, style: theme.textTheme.titleSmall),
                            if (badge != null)
                              StatusPill(
                                label: badge!,
                                tone: StatusTone.success,
                                icon: Icons.star_rounded,
                              ),
                          ],
                        ),
                        if (subtitle != null && subtitle!.isNotEmpty) ...[
                          const SizedBox(height: BafoSpacing.xxs),
                          Text(subtitle!, style: muted),
                        ],
                        if (chips.isNotEmpty) ...[
                          const SizedBox(height: BafoSpacing.sm),
                          Wrap(
                            spacing: BafoSpacing.xs,
                            runSpacing: BafoSpacing.xs,
                            children: [
                              for (final chip in chips) StatusPill(label: chip),
                            ],
                          ),
                        ],
                        if (!enabled && disabledReason != null) ...[
                          const SizedBox(height: BafoSpacing.xs),
                          Text(disabledReason!, style: muted),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: BafoSpacing.sm),
                  ExcludeSemantics(
                    child: Icon(
                      selected
                          ? Icons.radio_button_checked_rounded
                          : Icons.radio_button_unchecked_rounded,
                      color: selected ? scheme.primary : scheme.outline,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ── Step 4: review (M37) ────────────────────────────────────────────────

class _ReviewStep extends StatelessWidget {
  const _ReviewStep({required this.state, required this.showReserve});

  final CreateCompetitionEditing state;
  final bool showReserve;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<CreateCompetitionCubit>();
    final language = context.languageCode;
    final form = state.form;
    final preset = state.preset;
    final category = form.categoryId == null
        ? null
        : state.lookups.categoryById(form.categoryId!);
    final region = form.regionId == null
        ? null
        : state.lookups.regionById(form.regionId!);
    final direction = (form.direction ?? Direction.unknown).wire;
    String money(int? minor) => minor == null
        ? l10n.issuerNoValue
        : MoneyFormat.format(Money(minor), languageCode: language);
    String date(DateTime? value) => value == null
        ? l10n.issuerNoValue
        : BafoDateFormat.deadline(value, language, l10n);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ReviewCard(
          title: l10n.issuerCreateStepType,
          onEdit: () => cubit.goTo(CreateStep.type),
          children: [
            Wrap(
              spacing: BafoSpacing.xs,
              runSpacing: BafoSpacing.xs,
              children: [
                if (form.direction != null)
                  DirectionChip(direction: form.direction!),
                if (form.format != null) FormatChip(format: form.format!),
              ],
            ),
            if (preset != null) ...[
              const SizedBox(height: BafoSpacing.md),
              KeyValueList(
                items: [
                  KeyValue(
                    preset.isTiered
                        ? l10n.issuerPresetTierTitle
                        : l10n.issuerCreatePresetTitle,
                    preset.name,
                  ),
                ],
              ),
              if ((preset.description ?? '').isNotEmpty) ...[
                const SizedBox(height: BafoSpacing.sm),
                Text(
                  preset.description!,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ],
          ],
        ),
        const SizedBox(height: BafoSpacing.md),
        _ReviewCard(
          title: l10n.issuerCreateStepBasics,
          onEdit: () => cubit.goTo(CreateStep.basics),
          children: [
            KeyValueList(
              items: [
                KeyValue(l10n.issuerFieldTitle, form.title.trim()),
                KeyValue(
                  l10n.competitionsDetailCategory,
                  (category?.isOther ?? false)
                      ? form.categoryOtherText.trim()
                      : category?.name ?? l10n.issuerNoValue,
                ),
                KeyValue(
                  l10n.competitionsDetailRegion,
                  region?.name ?? l10n.issuerNoValue,
                ),
                KeyValue(
                  l10n.issuerFieldDescription,
                  form.description.trim().isEmpty
                      ? l10n.issuerReviewNoDescription
                      : l10n.issuerReviewDescriptionAdded,
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: BafoSpacing.md),
        _ReviewCard(
          title: l10n.issuerCreateStepSchedule,
          onEdit: () => cubit.goTo(CreateStep.schedule),
          children: [
            KeyValueList(
              items: [
                KeyValue(
                  l10n.issuerFieldStartPrice(direction),
                  money(form.startPriceMinor),
                  ltr: form.startPriceMinor != null,
                ),
                if (showReserve)
                  KeyValue(
                    l10n.issuerFieldReservePrice(direction),
                    money(form.reservePriceMinor),
                    ltr: form.reservePriceMinor != null,
                  ),
                KeyValue(
                  l10n.competitionsScheduleOpensAt,
                  form.opensOnPublish
                      ? l10n.competitionsScheduleOpensOnPublish
                      : date(form.biddingOpensAt),
                ),
                KeyValue(
                  l10n.competitionsScheduleClosesAt,
                  date(form.scheduledCloseAt),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: BafoSpacing.lg),
        InfoNotice(message: l10n.issuerReviewDraftNote),
      ],
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({
    required this.title,
    required this.onEdit,
    required this.children,
  });

  final String title;
  final VoidCallback onEdit;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(title, style: theme.textTheme.titleSmall),
                ),
              ),
              BafoButton.text(
                label: context.l10n.issuerReviewEdit,
                icon: Icons.edit_outlined,
                onPressed: onEdit,
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          ...children,
        ],
      ),
    );
  }
}
