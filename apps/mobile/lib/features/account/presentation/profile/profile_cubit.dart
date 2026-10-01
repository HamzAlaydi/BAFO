import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/account/presentation/action_outcome.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum ProfileBusy { none, saving, uploadingAvatar, removingAvatar }

enum ProfileResult { saved, avatarUpdated, avatarRemoved }

final class ProfileState extends Equatable {
  const ProfileState({
    this.busy = ProfileBusy.none,
    this.fieldErrors = const {},
    this.error,
    this.outcome,
  });

  final ProfileBusy busy;

  /// Server validation messages by field path (`name`, `phone`).
  final Map<String, List<String>> fieldErrors;

  /// A failure that is not about one field (network, 413, file type, …).
  final ApiException? error;
  final ActionOutcome<ProfileResult>? outcome;

  bool get isBusy => busy != ProfileBusy.none;

  String? fieldError(String path) {
    final messages = fieldErrors[path];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  @override
  List<Object?> get props => [busy, fieldErrors, error, outcome];
}

/// M52: `PATCH /me` (name, phone), `POST/DELETE /me/avatar`. Nothing is
/// shown as saved before the server answers; the returned `Me` goes to the
/// session.
class ProfileCubit extends Cubit<ProfileState> with OutcomeIds {
  ProfileCubit({required this._account, required this._session})
    : super(const ProfileState());

  final AccountRepository _account;
  final SessionCubit _session;

  /// [phone] is E.164 (`+9665XXXXXXXX`).
  Future<void> save({required String name, required String phone}) => _run(
    ProfileBusy.saving,
    () => _account.updateMe(name: name.trim(), phone: phone),
    ProfileResult.saved,
  );

  Future<void> uploadAvatar(String filePath) => _run(
    ProfileBusy.uploadingAvatar,
    () => _account.uploadAvatar(filePath),
    ProfileResult.avatarUpdated,
  );

  Future<void> removeAvatar() => _run(
    ProfileBusy.removingAvatar,
    _account.deleteAvatar,
    ProfileResult.avatarRemoved,
  );

  /// Drops the server error of [path] once the user edits the field.
  void fieldEdited(String path) {
    if (!state.fieldErrors.containsKey(path)) return;
    emit(
      ProfileState(
        fieldErrors: Map.of(state.fieldErrors)..remove(path),
        error: state.error,
      ),
    );
  }

  Future<void> _run(
    ProfileBusy busy,
    Future<Me> Function() call,
    ProfileResult result,
  ) async {
    if (state.isBusy) return;
    emit(ProfileState(busy: busy));
    try {
      _session.updateMe(await call());
      emit(ProfileState(outcome: outcome(result)));
    } on ApiException catch (error) {
      final fields = error.fieldErrors;
      emit(
        ProfileState(fieldErrors: fields, error: fields.isEmpty ? error : null),
      );
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(ProfileState state) {
    if (!isClosed) super.emit(state);
  }
}
