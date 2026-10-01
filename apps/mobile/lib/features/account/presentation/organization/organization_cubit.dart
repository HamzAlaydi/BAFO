import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/account/presentation/action_outcome.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum OrganizationBusy { none, saving, uploadingLogo, removingLogo }

enum OrganizationResult { saved, logoUpdated, logoRemoved }

sealed class OrganizationState extends Equatable {
  const OrganizationState();

  @override
  List<Object?> get props => [];
}

final class OrganizationLoading extends OrganizationState {
  const OrganizationLoading();
}

final class OrganizationFailure extends OrganizationState {
  const OrganizationFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class OrganizationLoaded extends OrganizationState {
  const OrganizationLoaded(
    this.organization, {
    this.lookups,
    this.lookupsError,
    this.busy = OrganizationBusy.none,
    this.fieldErrors = const {},
    this.error,
    this.outcome,
  });

  final Organization organization;

  /// Regions and categories for the form (null until loaded).
  final Lookups? lookups;
  final ApiException? lookupsError;
  final OrganizationBusy busy;

  /// `PATCH /organization` validation messages by path (`name`,
  /// `national_address.postal_code`, …).
  final Map<String, List<String>> fieldErrors;

  /// A failure that is not about one field.
  final ApiException? error;
  final ActionOutcome<OrganizationResult>? outcome;

  bool get isBusy => busy != OrganizationBusy.none;

  String? fieldError(String path) {
    final messages = fieldErrors[path];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  OrganizationLoaded copyWith({
    Organization? organization,
    Lookups? lookups,
    ApiException? Function()? lookupsError,
    OrganizationBusy? busy,
    Map<String, List<String>>? fieldErrors,
    ApiException? Function()? error,
    ActionOutcome<OrganizationResult>? Function()? outcome,
  }) => OrganizationLoaded(
    organization ?? this.organization,
    lookups: lookups ?? this.lookups,
    lookupsError: lookupsError == null ? this.lookupsError : lookupsError(),
    busy: busy ?? this.busy,
    fieldErrors: fieldErrors ?? this.fieldErrors,
    error: error == null ? this.error : error(),
    outcome: outcome == null ? this.outcome : outcome(),
  );

  @override
  List<Object?> get props => [
    organization,
    lookups,
    lookupsError,
    busy,
    fieldErrors,
    error,
    outcome,
  ];
}

/// M55: `GET /organization` for everyone; `PATCH /organization` and the
/// logo calls need `organization.update` (the screen hides them otherwise;
/// the server still decides). After a change the session's `Me` is re-read,
/// since it embeds the organisation.
class OrganizationCubit extends Cubit<OrganizationState> with OutcomeIds {
  OrganizationCubit({
    required this._organizations,
    required this._lookups,
    required this._session,
  }) : super(const OrganizationLoading());

  final OrganizationRepository _organizations;
  final LookupsRepository _lookups;
  final SessionCubit _session;

  Future<void> load() async {
    if (state is OrganizationFailure) emit(const OrganizationLoading());
    try {
      final organization = await _organizations.organization();
      final latest = state;
      emit(
        latest is OrganizationLoaded
            ? latest.copyWith(organization: organization)
            : OrganizationLoaded(organization),
      );
    } on ApiException catch (error) {
      if (state is! OrganizationLoaded) emit(OrganizationFailure(error));
      return;
    }
    if (!isClosed) await loadLookups();
  }

  /// Regions and categories (the form needs them; the page does not).
  Future<void> loadLookups({bool refresh = false}) async {
    try {
      final lookups = await _lookups.lookups(refresh: refresh);
      final latest = state;
      if (latest is OrganizationLoaded) {
        emit(latest.copyWith(lookups: lookups, lookupsError: () => null));
      }
    } on ApiException catch (error) {
      final latest = state;
      if (latest is OrganizationLoaded) {
        emit(latest.copyWith(lookupsError: () => error));
      }
    }
  }

  Future<void> save(OrganizationUpdate update) => _run(
    OrganizationBusy.saving,
    () => _organizations.update(update),
    OrganizationResult.saved,
  );

  Future<void> uploadLogo(String filePath) => _run(
    OrganizationBusy.uploadingLogo,
    () => _organizations.uploadLogo(filePath),
    OrganizationResult.logoUpdated,
  );

  Future<void> removeLogo() => _run(
    OrganizationBusy.removingLogo,
    _organizations.deleteLogo,
    OrganizationResult.logoRemoved,
  );

  void fieldEdited(String path) {
    final current = state;
    if (current is! OrganizationLoaded ||
        !current.fieldErrors.containsKey(path)) {
      return;
    }
    emit(
      current.copyWith(fieldErrors: Map.of(current.fieldErrors)..remove(path)),
    );
  }

  Future<void> _run(
    OrganizationBusy busy,
    Future<Organization> Function() call,
    OrganizationResult result,
  ) async {
    final current = state;
    if (current is! OrganizationLoaded || current.isBusy) return;
    emit(
      current.copyWith(
        busy: busy,
        fieldErrors: const {},
        error: () => null,
        outcome: () => null,
      ),
    );
    try {
      final organization = await call();
      final latest = state as OrganizationLoaded;
      emit(
        latest.copyWith(
          organization: organization,
          busy: OrganizationBusy.none,
          outcome: () => outcome(result),
        ),
      );
      await _session.refreshMe();
    } on ApiException catch (error) {
      final latest = state as OrganizationLoaded;
      emit(
        latest.copyWith(
          busy: OrganizationBusy.none,
          fieldErrors: error.fieldErrors,
          error: () => error.fieldErrors.isEmpty ? error : null,
        ),
      );
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(OrganizationState state) {
    if (!isClosed) super.emit(state);
  }
}
