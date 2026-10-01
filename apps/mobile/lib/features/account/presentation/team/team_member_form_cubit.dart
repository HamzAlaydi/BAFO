import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/features/account/presentation/action_outcome.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum TeamMemberBusy {
  none,
  loading,
  saving,
  resending,
  deactivating,
  reactivating,
  removing,
}

enum TeamMemberResult {
  invited,
  saved,
  resent,
  deactivated,
  reactivated,
  removed,
}

final class TeamMemberFormState extends Equatable {
  const TeamMemberFormState({
    this.member,
    this.busy = TeamMemberBusy.none,
    this.notFound = false,
    this.loadError,
    this.fieldErrors = const {},
    this.error,
    this.seatLimit,
    this.outcome,
  });

  /// The member being edited; null while inviting a new one.
  final TeamMember? member;
  final TeamMemberBusy busy;

  /// The membership id is not in the roster.
  final bool notFound;
  final ApiException? loadError;

  /// Validation messages by path (`email`, `name`, `phone`, …).
  final Map<String, List<String>> fieldErrors;

  /// A business error (`cannot_modify_owner`, network, …).
  final ApiException? error;

  /// `seat_limit_reached` with `details.seats` (SCREENS.md §3.2: shown with
  /// «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.», no upgrade).
  final Seats? seatLimit;
  final ActionOutcome<TeamMemberResult>? outcome;

  bool get isBusy => busy != TeamMemberBusy.none;

  String? fieldError(String path) {
    final messages = fieldErrors[path];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  TeamMemberFormState copyWith({
    TeamMember? member,
    TeamMemberBusy? busy,
    bool? notFound,
    ApiException? Function()? loadError,
    Map<String, List<String>>? fieldErrors,
    ApiException? Function()? error,
    Seats? Function()? seatLimit,
    ActionOutcome<TeamMemberResult>? Function()? outcome,
  }) => TeamMemberFormState(
    member: member ?? this.member,
    busy: busy ?? this.busy,
    notFound: notFound ?? this.notFound,
    loadError: loadError == null ? this.loadError : loadError(),
    fieldErrors: fieldErrors ?? this.fieldErrors,
    error: error == null ? this.error : error(),
    seatLimit: seatLimit == null ? this.seatLimit : seatLimit(),
    outcome: outcome == null ? this.outcome : outcome(),
  );

  @override
  List<Object?> get props => [
    member,
    busy,
    notFound,
    loadError,
    fieldErrors,
    error,
    seatLimit,
    outcome,
  ];
}

/// M57: invite a member (`POST /team/members`), or edit one
/// (`PATCH`, `DELETE`, `POST …/resend-invitation`). Every change waits for
/// the server (no optimistic state).
class TeamMemberFormCubit extends Cubit<TeamMemberFormState> with OutcomeIds {
  TeamMemberFormCubit({
    required this._team,
    this._membershipId,
    TeamMember? member,
  }) : isNew = _membershipId == null && member == null,
       super(TeamMemberFormState(member: member));

  final TeamRepository _team;

  /// Set when editing (from the route).
  final String? _membershipId;

  /// Inviting (no membership yet), as opposed to editing.
  final bool isNew;

  /// Editing a deep link without the member at hand: find it in the roster.
  Future<void> load() async {
    final id = _membershipId;
    if (id == null || state.member != null) return;
    emit(
      state.copyWith(
        busy: TeamMemberBusy.loading,
        loadError: () => null,
        notFound: false,
      ),
    );
    try {
      final roster = await _team.members();
      final member = roster.members.where((m) => m.id == id).firstOrNull;
      emit(TeamMemberFormState(member: member, notFound: member == null));
    } on ApiException catch (error) {
      emit(state.copyWith(busy: TeamMemberBusy.none, loadError: () => error));
    }
  }

  Future<void> invite(TeamMemberInvite invite) => _run(
    TeamMemberBusy.saving,
    () => _team.invite(invite),
    TeamMemberResult.invited,
  );

  Future<void> update({
    required MembershipRole role,
    required bool canAward,
    required bool canPurchase,
  }) {
    final member = state.member;
    if (member == null) return Future.value();
    return _run(
      TeamMemberBusy.saving,
      () => _team.update(
        member.id,
        role: role,
        canAward: canAward,
        canPurchase: canPurchase,
      ),
      TeamMemberResult.saved,
    );
  }

  Future<void> setActive({required bool active}) {
    final member = state.member;
    if (member == null) return Future.value();
    return _run(
      active ? TeamMemberBusy.reactivating : TeamMemberBusy.deactivating,
      () => _team.update(
        member.id,
        status: active ? MembershipStatus.active : MembershipStatus.inactive,
      ),
      active ? TeamMemberResult.reactivated : TeamMemberResult.deactivated,
    );
  }

  Future<void> resend() {
    final member = state.member;
    if (member == null) return Future.value();
    return _run(TeamMemberBusy.resending, () async {
      await _team.resendInvitation(member.id);
      return null;
    }, TeamMemberResult.resent);
  }

  Future<void> remove() {
    final member = state.member;
    if (member == null) return Future.value();
    return _run(TeamMemberBusy.removing, () async {
      await _team.remove(member.id);
      return null;
    }, TeamMemberResult.removed);
  }

  /// Drops the server error of [path] once the user edits the field.
  void fieldEdited(String path) {
    if (!state.fieldErrors.containsKey(path)) return;
    emit(state.copyWith(fieldErrors: Map.of(state.fieldErrors)..remove(path)));
  }

  Future<void> _run(
    TeamMemberBusy busy,
    Future<TeamMember?> Function() call,
    TeamMemberResult result,
  ) async {
    if (state.isBusy) return;
    emit(
      state.copyWith(
        busy: busy,
        fieldErrors: const {},
        error: () => null,
        seatLimit: () => null,
        outcome: () => null,
      ),
    );
    try {
      final member = await call();
      emit(
        state.copyWith(
          member: member,
          busy: TeamMemberBusy.none,
          outcome: () => outcome(result),
        ),
      );
    } on ApiException catch (error) {
      final seats = error.details['seats'];
      final limit = error.code == 'seat_limit_reached'
          ? (seats is Map<String, dynamic>
                ? Seats.fromJson(seats)
                : const Seats(used: 0, total: 0))
          : null;
      emit(
        state.copyWith(
          busy: TeamMemberBusy.none,
          fieldErrors: error.fieldErrors,
          error: () =>
              limit == null && error.fieldErrors.isEmpty ? error : null,
          seatLimit: () => limit,
        ),
      );
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(TeamMemberFormState state) {
    if (!isClosed) super.emit(state);
  }
}
