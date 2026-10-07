import 'dart:async';
import 'dart:convert';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class CreateCompetitionState extends Equatable {
  const CreateCompetitionState();

  @override
  List<Object?> get props => [];
}

final class CreateCompetitionInitial extends CreateCompetitionState {
  const CreateCompetitionInitial();
}

/// Loading the lookups (categories, regions, presets).
final class CreateCompetitionLoading extends CreateCompetitionState {
  const CreateCompetitionLoading();
}

/// The lookups could not be loaded.
final class CreateCompetitionFailure extends CreateCompetitionState {
  const CreateCompetitionFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class CreateCompetitionEditing extends CreateCompetitionState {
  const CreateCompetitionEditing({
    required this.lookups,
    required this.form,
    required this.pristine,
    required this.step,
    required this.auctionEnabled,
    this.problems = const {},
    this.serverErrors = const {},
    this.touched = const {},
    this.showAllProblems = false,
    this.restored = false,
    this.submitting = false,
    this.submitError,
  });

  final Lookups lookups;
  final CompetitionDraftForm form;

  /// The blank form of this session (the format is fixed to `live` when the
  /// `sealed_format` flag is off): [isDirty] compares against it.
  final CompetitionDraftForm pristine;
  final CreateStep step;

  /// `me.organization.features.auction_enabled` (S10).
  final bool auctionEnabled;

  /// The client problems that are shown now (FQ2): the touched fields'
  /// problems, or every problem of the step after "Next".
  final Map<DraftField, DraftProblem> problems;

  /// `validation_failed` messages bound to their fields (S7).
  final Map<DraftField, String> serverErrors;

  /// Fields the user left (blur) on this step: validated from then on.
  final Set<DraftField> touched;

  /// "Next" was tried with problems: every problem of the step is shown.
  final bool showAllProblems;

  /// The local autosave (FQ6) was restored into [form].
  final bool restored;
  final bool submitting;

  /// A business error of `POST /competitions` (form-level alert), or a
  /// validation error without a field on mobile.
  final ApiException? submitError;

  /// Whether the form differs from a blank one (unsaved-changes guard).
  bool get isDirty => form != pristine;

  Preset? get preset => form.presetIn(lookups.presets);

  CreateCompetitionEditing copyWith({
    CompetitionDraftForm? form,
    CreateStep? step,
    Map<DraftField, DraftProblem>? problems,
    Map<DraftField, String>? serverErrors,
    Set<DraftField>? touched,
    bool? showAllProblems,
    bool? restored,
    bool? submitting,
    ApiException? Function()? submitError,
  }) => CreateCompetitionEditing(
    lookups: lookups,
    form: form ?? this.form,
    pristine: pristine,
    step: step ?? this.step,
    auctionEnabled: auctionEnabled,
    problems: problems ?? this.problems,
    serverErrors: serverErrors ?? this.serverErrors,
    touched: touched ?? this.touched,
    showAllProblems: showAllProblems ?? this.showAllProblems,
    restored: restored ?? this.restored,
    submitting: submitting ?? this.submitting,
    submitError: submitError == null ? this.submitError : submitError(),
  );

  @override
  List<Object?> get props => [
    lookups,
    form,
    pristine,
    step,
    auctionEnabled,
    problems,
    serverErrors,
    touched,
    showAllProblems,
    restored,
    submitting,
    submitError,
  ];
}

/// The draft was created: open it (M38), which suggests Invite and
/// Attachments.
final class CreateCompetitionSaved extends CreateCompetitionState {
  const CreateCompetitionSaved(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

/// M34–M37: builds a draft from a preset tier, prices and a schedule, then
/// saves it with one `POST /competitions` (SCREENS.md CD4). Advanced rules
/// are web-only.
///
/// Release scope (RELEASE_SCOPE.md §2.6): without `sealed_format` the format
/// is fixed to `live` and never asked; the preset defaults to the standard
/// tier of the chosen direction. The typed form is kept in [PreferencesStore]
/// until the draft exists on the server (FQ6).
class CreateCompetitionCubit extends Cubit<CreateCompetitionState> {
  CreateCompetitionCubit({
    required this._competitions,
    required this._lookups,
    required this._now,
    required this.auctionEnabled,
    this.flags = FeatureFlags.none,
    this.drafts,
  }) : super(const CreateCompetitionInitial());

  final CompetitionsRepository _competitions;
  final LookupsRepository _lookups;

  /// Server time (`ServerClock.now`), for schedule checks.
  final DateTime Function() _now;
  final bool auctionEnabled;

  /// The release-scope flags (RELEASE_SCOPE.md §1.4).
  final FeatureFlags flags;

  /// Local autosave of the form (FQ6); null disables it.
  final PreferencesStore? drafts;

  bool get _sealedAllowed => flags.enabled(Feature.sealedFormat);

  Future<void> load() async {
    emit(const CreateCompetitionLoading());
    try {
      final lookups = await _lookups.lookups();
      if (isClosed) return;
      final pristine = CompetitionDraftForm(
        format: _sealedAllowed ? null : CompetitionFormat.live,
      );
      final saved = _readSavedDraft(lookups, pristine);
      final blank = CreateCompetitionEditing(
        lookups: lookups,
        form: pristine,
        pristine: pristine,
        step: CreateStep.type,
        auctionEnabled: auctionEnabled,
      );
      final form = _withPreset(blank, saved ?? pristine);
      emit(blank.copyWith(form: form, restored: saved != null));
    } on ApiException catch (error) {
      if (!isClosed) emit(CreateCompetitionFailure(error));
    }
  }

  CreateCompetitionEditing? get _editing {
    final current = state;
    return current is CreateCompetitionEditing ? current : null;
  }

  /// Direction radio cards. Auctions stay unavailable without the
  /// organisation flag (S10).
  void setDirection(Direction direction) {
    final current = _editing;
    if (current == null || current.submitting) return;
    if (direction == Direction.auction && !auctionEnabled) return;
    _apply(
      current,
      _withPreset(current, current.form.copyWith(direction: direction)),
    );
  }

  /// Format cards; sealed needs the `sealed_format` flag.
  void setFormat(CompetitionFormat format) {
    final current = _editing;
    if (current == null || current.submitting) return;
    if (format == CompetitionFormat.sealed && !_sealedAllowed) return;
    _apply(
      current,
      _withPreset(current, current.form.copyWith(format: format)),
    );
  }

  void selectPreset(String code) {
    final current = _editing;
    if (current == null || current.submitting) return;
    _apply(current, current.form.copyWith(presetCode: () => code));
  }

  /// Any other field (basics, prices, schedule).
  void update(CompetitionDraftForm Function(CompetitionDraftForm form) change) {
    final current = _editing;
    if (current == null || current.submitting) return;
    _apply(current, change(current.form));
  }

  /// FQ2: the user left [field] (blur); it is validated from now on.
  void touch(DraftField field) {
    final current = _editing;
    if (current == null || current.touched.contains(field)) return;
    final touched = {...current.touched, field};
    emit(
      current.copyWith(
        touched: touched,
        problems: _visibleProblems(current, current.form, touched: touched),
      ),
    );
  }

  /// Keeps the preset when it still matches, otherwise the standard tier of
  /// the direction and format (RELEASE_SCOPE.md §2.2), else the only
  /// matching preset (or none).
  CompetitionDraftForm _withPreset(
    CreateCompetitionEditing current,
    CompetitionDraftForm form,
  ) {
    final matching = form.presetsFor(current.lookups.presets);
    if (matching.any((preset) => preset.code == form.presetCode)) return form;
    final tiered = form.tieredPresetsFor(current.lookups.presets);
    final standard = tiered
        .where((preset) => preset.tier == PresetTier.standard)
        .firstOrNull;
    final fallback =
        standard ??
        (tiered.length == 1 ? tiered.single : null) ??
        (matching.length == 1 ? matching.single : null);
    return form.copyWith(presetCode: () => fallback?.code);
  }

  void _apply(CreateCompetitionEditing current, CompetitionDraftForm form) {
    emit(
      current.copyWith(
        form: form,
        problems: _visibleProblems(current, form),
        serverErrors: const {},
        submitError: () => null,
      ),
    );
    _persist(form, current.pristine);
  }

  Map<DraftField, DraftProblem> _visibleProblems(
    CreateCompetitionEditing current,
    CompetitionDraftForm form, {
    Set<DraftField>? touched,
    bool? showAll,
  }) {
    final all = form.validate(
      current.step,
      lookups: current.lookups,
      now: _now(),
    );
    if (showAll ?? current.showAllProblems) return all;
    final visible = touched ?? current.touched;
    return {
      for (final entry in all.entries)
        if (visible.contains(entry.key)) entry.key: entry.value,
    };
  }

  /// Validates the current step; moves on when it is complete.
  bool next() {
    final current = _editing;
    if (current == null || current.step == CreateStep.review) return false;
    final problems = current.form.validate(
      current.step,
      lookups: current.lookups,
      now: _now(),
    );
    if (problems.isNotEmpty) {
      emit(current.copyWith(problems: problems, showAllProblems: true));
      return false;
    }
    _moveTo(current, CreateStep.values[current.step.index + 1]);
    return true;
  }

  /// Returns false on the first step (the screen then leaves the wizard).
  bool back() {
    final current = _editing;
    if (current == null || current.step == CreateStep.type) return false;
    if (current.submitting) return true;
    _moveTo(current, CreateStep.values[current.step.index - 1]);
    return true;
  }

  /// "Edit" links of the review step (earlier steps only).
  void goTo(CreateStep step) {
    final current = _editing;
    if (current == null || current.submitting) return;
    if (step.index >= current.step.index) return;
    _moveTo(current, step);
  }

  /// The error summary (FQ8): any step, e.g. a server error on a later one.
  void jumpTo(CreateStep step) {
    final current = _editing;
    if (current == null || current.submitting || step == current.step) return;
    _moveTo(current, step);
  }

  void _moveTo(CreateCompetitionEditing current, CreateStep step) => emit(
    current.copyWith(
      step: step,
      problems: const {},
      touched: const {},
      showAllProblems: false,
      restored: false,
      submitError: () => null,
    ),
  );

  /// «ابدأ من جديد» on a restored form: forgets the local autosave.
  void startOver() {
    final current = _editing;
    if (current == null || current.submitting) return;
    _clearSavedDraft();
    emit(
      CreateCompetitionEditing(
        lookups: current.lookups,
        form: current.pristine,
        pristine: current.pristine,
        step: CreateStep.type,
        auctionEnabled: current.auctionEnabled,
      ),
    );
  }

  /// The user confirmed leaving the wizard: the autosave goes too.
  void discard() => _clearSavedDraft();

  /// M37 "Save draft": `POST /competitions`. No optimistic state: the
  /// draft exists only once the server answers (CD9).
  Future<void> submit() async {
    final current = _editing;
    if (current == null || current.submitting) return;
    final problems = current.form.validate(
      CreateStep.review,
      lookups: current.lookups,
      now: _now(),
    );
    final preset = current.preset;
    if (problems.isNotEmpty || preset == null) {
      final firstStep = problems.keys
          .map((field) => field.step)
          .fold<CreateStep>(
            CreateStep.review,
            (a, b) => b.index < a.index ? b : a,
          );
      emit(
        current.copyWith(
          step: firstStep,
          showAllProblems: true,
          problems: current.form.validate(
            firstStep,
            lookups: current.lookups,
            now: _now(),
          ),
        ),
      );
      return;
    }
    emit(current.copyWith(submitting: true, submitError: () => null));
    try {
      final competition = await _competitions.create(
        current.form.toInput(preset, current.lookups),
      );
      if (isClosed) return;
      _clearSavedDraft();
      emit(CreateCompetitionSaved(competition));
    } on ApiException catch (error) {
      if (isClosed) return;
      final (fields: serverErrors, :rest) = splitServerErrors(error);
      if (serverErrors.isEmpty) {
        emit(current.copyWith(submitting: false, submitError: () => error));
        return;
      }
      // Jump back to the first step with a field error (S7).
      final firstStep = serverErrors.keys
          .map((field) => field.step)
          .reduce((a, b) => b.index < a.index ? b : a);
      emit(
        current.copyWith(
          step: firstStep,
          submitting: false,
          serverErrors: serverErrors,
          submitError: () => rest,
        ),
      );
    }
  }

  // ── Local autosave (FQ6) ───────────────────────────────────────────────

  /// The saved form, re-checked against the lookups (ids that vanished are
  /// dropped) and the flags (sealed falls back to live); null when there is
  /// none or it is blank.
  CompetitionDraftForm? _readSavedDraft(
    Lookups lookups,
    CompetitionDraftForm pristine,
  ) {
    final raw = drafts?.getString(PreferenceKeys.issuerCreateDraft);
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) return null;
      var form = CompetitionDraftForm.fromJson(decoded);
      if (form.format == CompetitionFormat.sealed && !_sealedAllowed) {
        form = form.copyWith(format: CompetitionFormat.live);
      }
      if (form.format == null && !_sealedAllowed) {
        form = form.copyWith(format: CompetitionFormat.live);
      }
      if (form.categoryId != null &&
          lookups.categoryById(form.categoryId!) == null) {
        form = form.copyWith(categoryId: () => null);
      }
      if (form.regionId != null && lookups.regionById(form.regionId!) == null) {
        form = form.copyWith(regionId: () => null);
      }
      if (form.direction == Direction.auction && !auctionEnabled) {
        form = form.copyWith(direction: Direction.tender);
      }
      return form == pristine ? null : form;
    } on FormatException catch (error) {
      debugPrint('Ignoring a broken saved draft: $error');
      return null;
    }
  }

  void _persist(CompetitionDraftForm form, CompetitionDraftForm pristine) {
    final drafts = this.drafts;
    if (drafts == null) return;
    final write = form == pristine
        ? drafts.remove(PreferenceKeys.issuerCreateDraft)
        : drafts.setString(
            PreferenceKeys.issuerCreateDraft,
            jsonEncode(form.toJson()),
          );
    unawaited(
      write.catchError((Object error) {
        debugPrint('Saving the local draft failed: $error');
      }),
    );
  }

  void _clearSavedDraft() {
    final drafts = this.drafts;
    if (drafts == null) return;
    unawaited(
      drafts.remove(PreferenceKeys.issuerCreateDraft).catchError((
        Object error,
      ) {
        debugPrint('Clearing the local draft failed: $error');
      }),
    );
  }
}
