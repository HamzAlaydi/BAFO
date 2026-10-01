import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class HomeState extends Equatable {
  const HomeState();

  @override
  List<Object?> get props => [];
}

/// First load, nothing to show yet.
final class HomeLoading extends HomeState {
  const HomeLoading();
}

/// [home] is on screen. While [refreshing] it stays visible; a failed
/// refresh keeps it and sets [refreshError] (SCREENS.md S8: stale data under
/// a warning banner).
final class HomeLoaded extends HomeState {
  const HomeLoaded(this.home, {this.refreshing = false, this.refreshError});

  final Home home;
  final bool refreshing;
  final ApiException? refreshError;

  @override
  List<Object?> get props => [home, refreshing, refreshError];
}

/// The first load failed.
final class HomeFailure extends HomeState {
  const HomeFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// M14: `GET /home`, refetched on pull to refresh, on resume and when a
/// notification arrives on the user channel (debounced, as W10 does).
class HomeCubit extends Cubit<HomeState> {
  HomeCubit({
    required this._home,
    Stream<Object?>? notifications,
    this._debounce = const Duration(seconds: 2),
  }) : super(const HomeLoading()) {
    _notifications = notifications?.listen((_) => _scheduleRefresh());
  }

  final HomeRepository _home;
  final Duration _debounce;
  StreamSubscription<Object?>? _notifications;
  Timer? _pending;
  Future<void>? _inFlight;

  /// Loads (or reloads) the dashboard. Data already on screen stays visible.
  Future<void> load() => _inFlight ??= _fetch().whenComplete(() {
    _inFlight = null;
  });

  /// Pull to refresh, resume: same as [load].
  Future<void> refresh() => load();

  Future<void> _fetch() async {
    final current = state;
    if (current is HomeLoaded) {
      emit(HomeLoaded(current.home, refreshing: true));
    } else if (current is HomeFailure) {
      emit(const HomeLoading());
    }
    try {
      final home = await _home.home();
      if (!isClosed) emit(HomeLoaded(home));
    } on ApiException catch (error) {
      if (isClosed) return;
      emit(
        current is HomeLoaded
            ? HomeLoaded(current.home, refreshError: error)
            : HomeFailure(error),
      );
    }
  }

  void _scheduleRefresh() {
    _pending?.cancel();
    _pending = Timer(_debounce, () {
      if (!isClosed) unawaited(load());
    });
  }

  @override
  Future<void> close() async {
    _pending?.cancel();
    await _notifications?.cancel();
    return super.close();
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(HomeState state) {
    if (!isClosed) super.emit(state);
  }
}
