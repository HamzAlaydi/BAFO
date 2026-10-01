import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/storage/token_store.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Who is using the app (CONVENTIONS.md §5.1 sealed-state style).
sealed class SessionState extends Equatable {
  const SessionState();

  bool get isAuthenticated => this is SessionAuthenticated;

  /// The signed-in user's `Me`, when known.
  Me? get me => switch (this) {
    SessionAuthenticated(:final me) => me,
    _ => null,
  };

  @override
  List<Object?> get props => [];
}

/// Not restored yet (app start).
final class SessionUnknown extends SessionState {
  const SessionUnknown();
}

/// A token is stored. [me] is null only when `GET /me` could not be reached
/// yet (an offline start); the app then shows cached screens and the offline
/// banner until [SessionCubit.refreshMe] succeeds.
final class SessionAuthenticated extends SessionState {
  const SessionAuthenticated([this.me]);

  @override
  final Me? me;

  @override
  List<Object?> get props => [me];
}

final class SessionUnauthenticated extends SessionState {
  const SessionUnauthenticated({this.expired = false, this.signedOut = false});

  /// The API rejected the stored token (not a user sign-out).
  final bool expired;

  /// The user signed out: the next sign-in starts at home, not on the page
  /// the previous session was on (no `from`).
  final bool signedOut;

  @override
  List<Object?> get props => [expired, signedOut];
}

/// Whether someone is signed in, and who (SCREENS.md §3.4: holds `Me`).
///
/// * [restore] runs at start-up: a stored token is checked with `GET /me`.
/// * [signIn] stores the token of an `AuthTokenPayload` (login, OTP verify).
/// * [refreshMe] re-reads `GET /me` (resume, after profile changes).
/// * [signOut] runs the sign-out hooks (device removal), revokes the token
///   server-side (best effort) and forgets it.
/// * Any API 401 on an authenticated request ([unauthorized]) ends the
///   session as expired.
class SessionCubit extends Cubit<SessionState> {
  SessionCubit({
    required this._tokens,
    required Stream<void> unauthorized,
    required this._fetchMe,
    this._revokeToken,
    this._beforeSignOut,
  }) : super(const SessionUnknown()) {
    _unauthorized = unauthorized.listen((_) => expire());
  }

  final TokenStore _tokens;
  final Future<Me> Function() _fetchMe;
  final Future<void> Function()? _revokeToken;

  /// Runs while the token is still valid, e.g. `DELETE /devices/{id}`.
  final Future<void> Function()? _beforeSignOut;
  late final StreamSubscription<void> _unauthorized;

  Future<void> restore() async {
    String? token;
    try {
      token = await _tokens.read();
    } on Object catch (error) {
      // A broken keystore entry must not lock the user out: start signed out.
      debugPrint('Token restore failed: $error');
    }
    if (token == null || token.isEmpty) {
      emit(const SessionUnauthenticated());
      return;
    }
    try {
      emit(SessionAuthenticated(await _fetchMe()));
    } on ApiException catch (error) {
      if (error.isUnauthenticated) {
        await _tokens.clear();
        emit(const SessionUnauthenticated(expired: true));
      } else {
        // Offline or a server problem: keep the session, load `Me` later.
        emit(const SessionAuthenticated());
      }
    }
  }

  Future<void> signIn(AuthTokenPayload payload) async {
    await _tokens.write(payload.token);
    emit(SessionAuthenticated(payload.me));
  }

  /// Re-reads `GET /me`. Failures keep the current state (a 401 ends the
  /// session through the interceptor).
  Future<void> refreshMe() async {
    if (!state.isAuthenticated) return;
    try {
      final me = await _fetchMe();
      if (state.isAuthenticated) emit(SessionAuthenticated(me));
    } on ApiException catch (error) {
      debugPrint('Refreshing me failed: ${error.code}');
    }
  }

  /// Applies a `Me` returned by another call (`PATCH /me`, avatar).
  void updateMe(Me me) {
    if (state.isAuthenticated) emit(SessionAuthenticated(me));
  }

  Future<void> signOut() async {
    for (final step in [_beforeSignOut, _revokeToken]) {
      if (step == null) continue;
      try {
        await step();
      } on Object catch (error) {
        // The local sign-out must succeed even offline.
        debugPrint('Sign-out step failed: $error');
      }
    }
    await _tokens.clear();
    emit(const SessionUnauthenticated(signedOut: true));
  }

  Future<void> expire() async {
    if (!state.isAuthenticated) return;
    await _tokens.clear();
    emit(const SessionUnauthenticated(expired: true));
  }

  @override
  Future<void> close() async {
    await _unauthorized.cancel();
    return super.close();
  }
}
