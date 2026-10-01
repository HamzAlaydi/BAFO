import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:equatable/equatable.dart';
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
    required this.step,
    required this.auctionEnabled,
    this.problems = const {},
    this.serverErrors = const {},
    this.submitting = false,
    this.submitError,
  });

  final Lookups lookups;
  final CompetitionDraftForm form;
  final CreateStep step;

  /// `me.organization.features.auction_enabled` (S10).
  final bool auctionEnabled;

  /// Client problems of the current step, shown after "Next".
  final Map<DraftField, DraftProblem> problems;

  /// `validation_failed` messages bound to their fields (S7).
  final Map<DraftField, String> serverErrors;
  final bool submitting;

  /// A business error of `POST /competitions` (form-level alert), or a
  /// validation error without a field on mobile.
  final ApiException? submitError;

  /// Whether the form differs from a blank one (unsaved-changes guard).
  bool get isDirty => form != const CompetitionDraftForm();

  Preset? get preset => form.presetIn(lookups.presets);

  CreateCompetitionEditing copyWith({
    CompetitionDraftForm? form,
    CreateStep? step,
    Map<DraftField, DraftProblem>? problems,
    Map<DraftField, String>? serverErrors,
    bool? submitting,
    ApiException? Function()? submitError,
  }) => CreateCompetitionEditing(
    lookups: lookups,
    form: form ?? this.form,
    step: step ?? this.step,
    auctionEnabled: auctionEnabled,
    problems: problems ?? this.problems,
    serverErrors: serverErrors ?? this.serverErrors,
    submitting: submitting ?? this.submitting,
    submitError: submitError == null ? this.submitError : submitError(),
  );

  @override
  List<Object?> get props => [
    lookups,
    form,
    step,
    auctionEnabled,
    problems,
    serverErrors,
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

/// M34–M37: builds a draft from a preset, prices and a schedule, then saves
/// it with one `POST /competitions` (SCREENS.md CD4). Advanced rules are
/// web-only.
class CreateCompetitionCubit extends Cubit<CreateCompetitionState> {
  CreateCompetitionCubit({
    required this._competitions,
    required this._lookups,
    required this._now,
    required this.auctionEnabled,
  }) : super(const CreateCompetitionInitial());

  final CompetitionsRepository _competitions;
  final LookupsRepository _lookups;

  /// Server time (`ServerClock.now`), for schedule checks.
  final DateTime Function() _now;
  final bool auctionEnabled;

  Future<void> load() async {
    emit(const CreateCompetitionLoading());
    try {
      final lookups = await _lookups.lookups();
      if (isClosed) return;
      emit(
        CreateCompetitionEditing(
          lookups: lookups,
          form: const CompetitionDraftForm(),
          step: CreateStep.type,
          auctionEnabled: auctionEnabled,
        ),
      );
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

  void setFormat(CompetitionFormat format) {
    final current = _editing;
    if (current == null || current.submitting) return;
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

  /// Keeps the preset when it still matches, otherwise picks the only
  /// matching one (or none).
  CompetitionDraftForm _withPreset(
    CreateCompetitionEditing current,
    CompetitionDraftForm form,
  ) {
    final matching = form.presetsFor(current.lookups.presets);
    if (matching.any((preset) => preset.code == form.presetCode)) return form;
    return form.copyWith(
      presetCode: () => matching.length == 1 ? matching.single.code : null,
    );
  }

  void _apply(CreateCompetitionEditing current, CompetitionDraftForm form) {
    // Once problems are showing, they follow the edits.
    final problems = current.problems.isEmpty
        ? const <DraftField, DraftProblem>{}
        : form.validate(current.step, lookups: current.lookups, now: _now());
    emit(
      current.copyWith(
        form: form,
        problems: problems,
        serverErrors: const {},
        submitError: () => null,
      ),
    );
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
      emit(current.copyWith(problems: problems));
      return false;
    }
    emit(
      current.copyWith(
        step: CreateStep.values[current.step.index + 1],
        problems: const {},
      ),
    );
    return true;
  }

  /// Returns false on the first step (the screen then leaves the wizard).
  bool back() {
    final current = _editing;
    if (current == null || current.step == CreateStep.type) return false;
    if (current.submitting) return true;
    emit(
      current.copyWith(
        step: CreateStep.values[current.step.index - 1],
        problems: const {},
        submitError: () => null,
      ),
    );
    return true;
  }

  /// "Edit" links of the review step (earlier steps only).
  void goTo(CreateStep step) {
    final current = _editing;
    if (current == null || current.submitting) return;
    if (step.index >= current.step.index) return;
    emit(current.copyWith(step: step, problems: const {}));
  }

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
      if (!isClosed) emit(CreateCompetitionSaved(competition));
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
}
