import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Which fields a status lets the issuer change (API.md §1.4 PATCH).
enum EditScope {
  /// Draft: everything mobile creates, including prices and schedule.
  draft,

  /// Scheduled: basics plus the schedule; rules are fixed.
  scheduled,

  /// Live: title and description only.
  live,

  /// Anything else: nothing (409 `competition_not_editable`).
  none;

  static EditScope of(Competition competition) {
    if (!competition.permissions.canEdit) return none;
    return switch (competition.status) {
      CompetitionStatus.draft => draft,
      CompetitionStatus.scheduled => scheduled,
      CompetitionStatus.live => live,
      _ => none,
    };
  }

  bool allows(DraftField field) => switch (this) {
    draft => true,
    scheduled =>
      field != DraftField.startPrice &&
          field != DraftField.reservePrice &&
          field.step != CreateStep.type,
    live => field == DraftField.title || field == DraftField.description,
    none => false,
  };
}

sealed class EditCompetitionState extends Equatable {
  const EditCompetitionState();

  @override
  List<Object?> get props => [];
}

final class EditCompetitionInitial extends EditCompetitionState {
  const EditCompetitionInitial();
}

final class EditCompetitionLoading extends EditCompetitionState {
  const EditCompetitionLoading();
}

final class EditCompetitionFailure extends EditCompetitionState {
  const EditCompetitionFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class EditCompetitionNotFound extends EditCompetitionState {
  const EditCompetitionNotFound();
}

/// Not the issuer, or the status allows no edits: back to the detail.
final class EditCompetitionUnavailable extends EditCompetitionState {
  const EditCompetitionUnavailable(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class EditCompetitionEditing extends EditCompetitionState {
  const EditCompetitionEditing({
    required this.competition,
    required this.lookups,
    required this.form,
    required this.scope,
    this.problems = const {},
    this.serverErrors = const {},
    this.saving = false,
    this.saveError,
  });

  final Competition competition;
  final Lookups lookups;
  final CompetitionDraftForm form;
  final EditScope scope;
  final Map<DraftField, DraftProblem> problems;
  final Map<DraftField, String> serverErrors;
  final bool saving;

  /// `competition_not_editable` (`details.fields`) or another business
  /// error, shown above the form.
  final ApiException? saveError;

  bool get isDirty => form != EditCompetitionCubit.formOf(competition);

  EditCompetitionEditing copyWith({
    CompetitionDraftForm? form,
    Map<DraftField, DraftProblem>? problems,
    Map<DraftField, String>? serverErrors,
    bool? saving,
    ApiException? Function()? saveError,
  }) => EditCompetitionEditing(
    competition: competition,
    lookups: lookups,
    form: form ?? this.form,
    scope: scope,
    problems: problems ?? this.problems,
    serverErrors: serverErrors ?? this.serverErrors,
    saving: saving ?? this.saving,
    saveError: saveError == null ? this.saveError : saveError(),
  );

  @override
  List<Object?> get props => [
    competition,
    lookups,
    form,
    scope,
    problems,
    serverErrors,
    saving,
    saveError,
  ];
}

final class EditCompetitionSaved extends EditCompetitionState {
  const EditCompetitionSaved(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

/// M39: `PATCH /competitions/{id}` with the fields the status allows
/// (W16): drafts also change prices and schedule; scheduled competitions
/// change basics and schedule; live ones the title and description.
class EditCompetitionCubit extends Cubit<EditCompetitionState> {
  EditCompetitionCubit({
    required this._competitions,
    required this._lookups,
    required this._now,
    required this.competitionId,
  }) : super(const EditCompetitionInitial());

  final CompetitionsRepository _competitions;
  final LookupsRepository _lookups;
  final DateTime Function() _now;
  final String competitionId;

  /// The form as the competition stands on the server.
  static CompetitionDraftForm formOf(Competition competition) =>
      CompetitionDraftForm(
        direction: competition.direction,
        format: competition.format,
        presetCode: competition.presetCode,
        title: competition.title,
        description: competition.description ?? '',
        categoryId: competition.category?.id,
        categoryOtherText: competition.categoryOtherText ?? '',
        regionId: competition.region?.id,
        startPriceMinor: competition.rules.startPriceMinor,
        reservePriceMinor: competition.rules.reservePriceMinor,
        opensOnPublish: competition.schedule.biddingOpensAt == null,
        biddingOpensAt: competition.schedule.biddingOpensAt,
        scheduledCloseAt: competition.schedule.scheduledCloseAt,
      );

  Future<void> load() async {
    emit(const EditCompetitionLoading());
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      final scope = EditScope.of(competition);
      if (competition.viewerRole != ViewerRole.issuer ||
          scope == EditScope.none) {
        emit(EditCompetitionUnavailable(competition));
        return;
      }
      final lookups = await _lookups.lookups();
      if (isClosed) return;
      emit(
        EditCompetitionEditing(
          competition: competition,
          lookups: lookups,
          form: formOf(competition),
          scope: scope,
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const EditCompetitionNotFound());
      } else {
        emit(EditCompetitionFailure(error));
      }
    }
  }

  void update(CompetitionDraftForm Function(CompetitionDraftForm form) change) {
    final current = state;
    if (current is! EditCompetitionEditing || current.saving) return;
    final form = change(current.form);
    emit(
      current.copyWith(
        form: form,
        problems: current.problems.isEmpty
            ? const {}
            : _problems(current, form),
        serverErrors: const {},
        saveError: () => null,
      ),
    );
  }

  Map<DraftField, DraftProblem> _problems(
    EditCompetitionEditing current,
    CompetitionDraftForm form,
  ) {
    final problems = <DraftField, DraftProblem>{
      ...form.validate(
        CreateStep.basics,
        lookups: current.lookups,
        now: _now(),
      ),
      ...form.validate(
        CreateStep.schedule,
        lookups: current.lookups,
        now: _now(),
      ),
    };
    problems.removeWhere((field, _) => !current.scope.allows(field));
    return problems;
  }

  /// The PATCH body for the scope (the full `rules` with `preset_code` for
  /// drafts, SCREENS.md G4).
  static Json patchBody(
    Competition competition,
    CompetitionDraftForm form,
    EditScope scope,
    Lookups lookups,
  ) {
    final description = form.description.trim();
    final category = form.categoryId == null
        ? null
        : lookups.categoryById(form.categoryId!);
    return {
      'title': form.title.trim(),
      'description': description.isEmpty ? null : description,
      if (scope == EditScope.draft || scope == EditScope.scheduled) ...{
        'category_id': form.categoryId,
        'category_other_text': (category?.isOther ?? false)
            ? form.categoryOtherText.trim()
            : null,
        'region_id': form.regionId,
        'bidding_opens_at': form.effectiveOpensAt == null
            ? null
            : toApiTime(form.effectiveOpensAt!),
        'scheduled_close_at': form.scheduledCloseAt == null
            ? null
            : toApiTime(form.scheduledCloseAt!),
      },
      if (scope == EditScope.draft) ...{
        'preset_code': competition.presetCode,
        'rules': competition.rules
            .copyWith(
              startPriceMinor: () => form.startPriceMinor,
              reservePriceMinor: () => form.reservePriceMinor,
            )
            .toJson(),
      },
    };
  }

  Future<void> save() async {
    final current = state;
    if (current is! EditCompetitionEditing || current.saving) return;
    final problems = _problems(current, current.form);
    if (problems.isNotEmpty) {
      emit(current.copyWith(problems: problems));
      return;
    }
    emit(current.copyWith(saving: true, saveError: () => null));
    try {
      final saved = await _competitions.update(
        competitionId,
        patchBody(
          current.competition,
          current.form,
          current.scope,
          current.lookups,
        ),
      );
      if (!isClosed) emit(EditCompetitionSaved(saved));
    } on ApiException catch (error) {
      if (isClosed) return;
      final (fields: serverErrors, :rest) = splitServerErrors(error);
      emit(
        current.copyWith(
          saving: false,
          serverErrors: serverErrors,
          saveError: () => rest,
        ),
      );
    }
  }
}
