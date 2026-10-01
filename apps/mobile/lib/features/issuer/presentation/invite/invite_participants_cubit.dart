import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/issuer/domain/staged_invitee.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// The segments of M40.
enum InviteTab { suggestions, email, vendors }

/// A search list (suggestions or vendors) inside [InviteReady].
final class InviteSearch<T> extends Equatable {
  const InviteSearch({
    this.items = const [],
    this.query = '',
    this.loading = false,
    this.loaded = false,
    this.error,
  });

  final List<T> items;
  final String query;
  final bool loading;
  final bool loaded;
  final ApiException? error;

  @override
  List<Object?> get props => [items, query, loading, loaded, error];
}

/// A row error of the all-or-nothing request, keyed by the staged row.
typedef InviteRowError = ({String? code, String message});

sealed class InviteParticipantsState extends Equatable {
  const InviteParticipantsState();

  @override
  List<Object?> get props => [];
}

final class InviteLoading extends InviteParticipantsState {
  const InviteLoading();
}

final class InviteFailure extends InviteParticipantsState {
  const InviteFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// Not the issuer, or inviting is not allowed now (`can_invite` false).
final class InviteUnavailable extends InviteParticipantsState {
  const InviteUnavailable(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class InviteReady extends InviteParticipantsState {
  const InviteReady({
    required this.competition,
    this.sponsorship,
    this.tab = InviteTab.suggestions,
    this.suggestions = const InviteSearch<Suggestion>(),
    this.vendors = const InviteSearch<Vendor>(),
    this.staged = const [],
    this.invalidEmails = const [],
    this.rowErrors = const {},
    this.submitting = false,
    this.submitError,
  });

  final Competition competition;

  /// Read-only; null when the mode is none.
  final Sponsorship? sponsorship;
  final InviteTab tab;
  final InviteSearch<Suggestion> suggestions;
  final InviteSearch<Vendor> vendors;
  final List<StagedInvitee> staged;

  /// Pieces of the last pasted text that are not e-mail addresses.
  final List<String> invalidEmails;

  /// By [StagedInvitee.key].
  final Map<String, InviteRowError> rowErrors;
  final bool submitting;
  final ApiException? submitError;

  /// `selected` mode: rows may be covered, within the funded free slots
  /// (mobile never buys passes, SCREENS.md §3.2).
  bool get selectedMode => sponsorship?.mode == 'selected';

  int get freeSlots => sponsorship?.counts.freeSlots ?? 0;

  int get sponsoredCount => staged.where((row) => row.sponsored).length;

  bool get canSponsorMore => selectedMode && sponsoredCount < freeSlots;

  /// The fees must be paid on the web before these rows can be sent.
  bool get feesPaidOnWeb => submitError?.code == 'sponsorship_payment_required';

  /// "Send without covering fees" re-posts with `sponsored: false`
  /// (`selected` mode only).
  bool get canSendWithoutFees => feesPaidOnWeb && selectedMode;

  bool isStaged(String key) => staged.any((row) => row.key == key);

  InviteReady copyWith({
    InviteTab? tab,
    InviteSearch<Suggestion>? suggestions,
    InviteSearch<Vendor>? vendors,
    List<StagedInvitee>? staged,
    List<String>? invalidEmails,
    Map<String, InviteRowError>? rowErrors,
    bool? submitting,
    ApiException? Function()? submitError,
  }) => InviteReady(
    competition: competition,
    sponsorship: sponsorship,
    tab: tab ?? this.tab,
    suggestions: suggestions ?? this.suggestions,
    vendors: vendors ?? this.vendors,
    staged: staged ?? this.staged,
    invalidEmails: invalidEmails ?? this.invalidEmails,
    rowErrors: rowErrors ?? this.rowErrors,
    submitting: submitting ?? this.submitting,
    submitError: submitError == null ? this.submitError : submitError(),
  );

  @override
  List<Object?> get props => [
    competition,
    sponsorship,
    tab,
    suggestions,
    vendors,
    staged,
    invalidEmails,
    rowErrors,
    submitting,
    submitError,
  ];
}

/// The invitations were created (status `draft` on a draft, `sent`
/// otherwise).
final class InviteSent extends InviteParticipantsState {
  const InviteSent(this.invitations, {this.feesCovered = true});

  final List<Invitation> invitations;

  /// False after "Send without covering fees".
  final bool feesCovered;

  @override
  List<Object?> get props => [invitations, feesCovered];
}

/// M40: suggestions, pasted e-mails and the vendor directory are staged
/// as chips, then sent in one `POST …/invitations` (all or nothing).
class InviteParticipantsCubit extends Cubit<InviteParticipantsState> {
  InviteParticipantsCubit({
    required this._competitions,
    required this._invitations,
    required this._vendors,
    required this.competitionId,
  }) : super(const InviteLoading());

  final CompetitionsRepository _competitions;
  final InvitationsRepository _invitations;
  final VendorsRepository _vendors;
  final String competitionId;

  Future<void> load() async {
    emit(const InviteLoading());
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      if (competition.viewerRole != ViewerRole.issuer ||
          !competition.permissions.canInvite) {
        emit(InviteUnavailable(competition));
        return;
      }
      Sponsorship? sponsorship;
      if (competition.sponsorship != null) {
        try {
          final value = await _competitions.sponsorship(competitionId);
          sponsorship = value.isNone ? null : value;
        } on ApiException {
          sponsorship = null;
        }
      }
      if (isClosed) return;
      emit(InviteReady(competition: competition, sponsorship: sponsorship));
      await searchSuggestions('');
    } on ApiException catch (error) {
      if (!isClosed) emit(InviteFailure(error));
    }
  }

  InviteReady? get _ready {
    final current = state;
    return current is InviteReady ? current : null;
  }

  void selectTab(InviteTab tab) {
    final current = _ready;
    if (current == null) return;
    emit(current.copyWith(tab: tab));
    if (tab == InviteTab.vendors && !current.vendors.loaded) {
      searchVendors('');
    }
  }

  /// `GET …/suggestions?q=` (the field debounces).
  Future<void> searchSuggestions(String query) async {
    final current = _ready;
    if (current == null) return;
    emit(
      current.copyWith(
        suggestions: InviteSearch(
          items: current.suggestions.items,
          query: query,
          loading: true,
          loaded: current.suggestions.loaded,
        ),
      ),
    );
    try {
      final items = await _competitions.suggestions(
        competitionId,
        query: query,
      );
      final latest = _ready;
      if (latest == null || latest.suggestions.query != query) return;
      emit(
        latest.copyWith(
          suggestions: InviteSearch(items: items, query: query, loaded: true),
        ),
      );
    } on ApiException catch (error) {
      final latest = _ready;
      if (latest == null || latest.suggestions.query != query) return;
      emit(
        latest.copyWith(
          suggestions: InviteSearch(
            items: latest.suggestions.items,
            query: query,
            loaded: true,
            error: error,
          ),
        ),
      );
    }
  }

  /// `GET /vendors?q=` (active vendors only; blocked ones never appear).
  Future<void> searchVendors(String query) async {
    final current = _ready;
    if (current == null) return;
    emit(
      current.copyWith(
        vendors: InviteSearch(
          items: current.vendors.items,
          query: query,
          loading: true,
          loaded: current.vendors.loaded,
        ),
      ),
    );
    try {
      final page = await _vendors.search(query: query);
      final latest = _ready;
      if (latest == null || latest.vendors.query != query) return;
      emit(
        latest.copyWith(
          vendors: InviteSearch(items: page.items, query: query, loaded: true),
        ),
      );
    } on ApiException catch (error) {
      final latest = _ready;
      if (latest == null || latest.vendors.query != query) return;
      emit(
        latest.copyWith(
          vendors: InviteSearch(
            items: latest.vendors.items,
            query: query,
            loaded: true,
            error: error,
          ),
        ),
      );
    }
  }

  void toggleSuggestion(Suggestion suggestion) => _toggle(
    StagedInvitee(
      source: InviteeSource.suggestion,
      label: suggestion.organization.name,
      organizationId: suggestion.organization.id,
    ),
  );

  void toggleVendor(Vendor vendor) => _toggle(
    StagedInvitee(
      source: InviteeSource.vendor,
      label: vendor.name,
      vendorId: vendor.id,
    ),
  );

  void _toggle(StagedInvitee row) {
    final current = _ready;
    if (current == null || current.submitting) return;
    final staged = current.isStaged(row.key)
        ? [
            for (final existing in current.staged)
              if (existing.key != row.key) existing,
          ]
        : [...current.staged, row];
    _replaceStaged(current, staged);
  }

  /// Splits a pasted list; invalid pieces are returned to the field.
  void addEmails(String text) {
    final current = _ready;
    if (current == null || current.submitting) return;
    final (:valid, :invalid) = splitEmails(text);
    final staged = [...current.staged];
    for (final email in valid) {
      final row = StagedInvitee.email(email);
      if (!staged.any((existing) => existing.key == row.key)) staged.add(row);
    }
    _replaceStaged(current, staged, invalidEmails: invalid);
  }

  void remove(String key) {
    final current = _ready;
    if (current == null || current.submitting) return;
    _replaceStaged(current, [
      for (final row in current.staged)
        if (row.key != key) row,
    ]);
  }

  /// Covers one row's fees (`selected` mode, within the free slots).
  void setSponsored(String key, {required bool sponsored}) {
    final current = _ready;
    if (current == null || current.submitting || !current.selectedMode) return;
    if (sponsored && !current.canSponsorMore) return;
    _replaceStaged(current, [
      for (final row in current.staged)
        row.key == key ? row.withSponsored(sponsored) : row,
    ]);
  }

  void _replaceStaged(
    InviteReady current,
    List<StagedInvitee> staged, {
    List<String>? invalidEmails,
  }) {
    final keys = {for (final row in staged) row.key};
    emit(
      current.copyWith(
        staged: staged,
        invalidEmails: invalidEmails ?? current.invalidEmails,
        rowErrors: {
          for (final entry in current.rowErrors.entries)
            if (keys.contains(entry.key)) entry.key: entry.value,
        },
        submitError: () => null,
      ),
    );
  }

  /// `POST …/invitations`. Nothing is created on any error.
  Future<void> submit({bool coverFees = true}) async {
    final current = _ready;
    if (current == null || current.submitting || current.staged.isEmpty) {
      return;
    }
    emit(
      current.copyWith(
        submitting: true,
        rowErrors: const {},
        submitError: () => null,
      ),
    );
    final rows = current.staged;
    try {
      final created = await _invitations.invite(competitionId, [
        for (final row in rows) row.toInput(coverFees: coverFees),
      ]);
      if (!isClosed) emit(InviteSent(created, feesCovered: coverFees));
    } on ApiException catch (error) {
      if (isClosed) return;
      final byIndex = invitationRowErrors(
        fieldErrors: error.fieldErrors,
        details: error.details,
      );
      final rowErrors = <String, InviteRowError>{
        for (final entry in byIndex.entries)
          if (entry.key < rows.length) rows[entry.key].key: entry.value,
      };
      emit(
        current.copyWith(
          submitting: false,
          rowErrors: rowErrors,
          submitError: () => rowErrors.isEmpty ? error : null,
        ),
      );
    }
  }

  /// `selected` mode after `sponsorship_payment_required`: the same rows
  /// without covered fees.
  Future<void> sendWithoutCoveringFees() async {
    final current = _ready;
    if (current == null || !current.canSendWithoutFees) return;
    await submit(coverFees: false);
  }
}
