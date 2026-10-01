import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

// ── Events ──────────────────────────────────────────────────────────────

sealed class IssuedListEvent extends Equatable {
  const IssuedListEvent();

  @override
  List<Object?> get props => [];
}

/// First load of the current segment.
final class IssuedListStarted extends IssuedListEvent {
  const IssuedListStarted();
}

/// Segment Active / Drafts / Ended (`status_group`).
final class IssuedListFilterChanged extends IssuedListEvent {
  const IssuedListFilterChanged(this.group);

  final CompetitionStatusGroup group;

  @override
  List<Object?> get props => [group];
}

/// Title search, debounced by 300 ms (CONVENTIONS.md §9.3).
final class IssuedListQueryChanged extends IssuedListEvent {
  const IssuedListQueryChanged(this.query);

  final String query;

  @override
  List<Object?> get props => [query];
}

/// Infinite scroll reached the end (`has_more`).
final class IssuedListNextPageRequested extends IssuedListEvent {
  const IssuedListNextPageRequested();
}

/// Pull to refresh, app resume, or back from a detail screen.
final class IssuedListRefreshed extends IssuedListEvent {
  const IssuedListRefreshed();
}

// ── States ──────────────────────────────────────────────────────────────

sealed class IssuedListState extends Equatable {
  const IssuedListState({required this.group, required this.query});

  final CompetitionStatusGroup group;
  final String query;

  @override
  List<Object?> get props => [group, query];
}

final class IssuedListInitial extends IssuedListState {
  const IssuedListInitial({
    super.group = CompetitionStatusGroup.active,
    super.query = '',
  });
}

final class IssuedListLoading extends IssuedListState {
  const IssuedListLoading({required super.group, required super.query});
}

final class IssuedListLoaded extends IssuedListState {
  const IssuedListLoaded({
    required super.group,
    required super.query,
    required this.items,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
    this.loadMoreError,
    this.refreshing = false,
  });

  final List<CompetitionListItem> items;
  final int page;
  final bool hasMore;
  final bool loadingMore;
  final ApiException? loadMoreError;
  final bool refreshing;

  IssuedListLoaded copyWith({
    List<CompetitionListItem>? items,
    int? page,
    bool? hasMore,
    bool? loadingMore,
    ApiException? Function()? loadMoreError,
    bool? refreshing,
  }) => IssuedListLoaded(
    group: group,
    query: query,
    items: items ?? this.items,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreError: loadMoreError == null ? this.loadMoreError : loadMoreError(),
    refreshing: refreshing ?? this.refreshing,
  );

  @override
  List<Object?> get props => [
    ...super.props,
    items,
    page,
    hasMore,
    loadingMore,
    loadMoreError,
    refreshing,
  ];
}

final class IssuedListFailure extends IssuedListState {
  const IssuedListFailure({
    required super.group,
    required super.query,
    required this.error,
  });

  final ApiException error;

  @override
  List<Object?> get props => [...super.props, error];
}

// ── Bloc ────────────────────────────────────────────────────────────────

/// M33 "My competitions": `GET /competitions?role=issuer&status_group=&q=`
/// with page pagination (`has_more`).
///
/// Every reload bumps a generation counter, so a slow answer for an old
/// segment or query never replaces a newer one (restartable semantics
/// without extra dependencies).
class IssuedListBloc extends Bloc<IssuedListEvent, IssuedListState> {
  IssuedListBloc({
    required this._competitions,
    CompetitionStatusGroup initialGroup = CompetitionStatusGroup.active,
    this.debounce = const Duration(milliseconds: 300),
  }) : super(IssuedListInitial(group: initialGroup)) {
    on<IssuedListStarted>((_, emit) => _reload(emit, state.group, state.query));
    on<IssuedListFilterChanged>((event, emit) async {
      if (event.group == state.group && state is IssuedListLoaded) return;
      await _reload(emit, event.group, state.query);
    });
    on<IssuedListQueryChanged>(_onQuery);
    on<IssuedListNextPageRequested>(_onNextPage);
    on<IssuedListRefreshed>(_onRefresh);
  }

  final CompetitionsRepository _competitions;
  final Duration debounce;
  int _generation = 0;

  Future<void> _onQuery(
    IssuedListQueryChanged event,
    Emitter<IssuedListState> emit,
  ) async {
    final query = event.query.trim();
    if (query == state.query) return;
    final generation = ++_generation;
    await Future<void>.delayed(debounce);
    if (generation != _generation || isClosed) return;
    await _reload(emit, state.group, query, generation: generation);
  }

  Future<void> _reload(
    Emitter<IssuedListState> emit,
    CompetitionStatusGroup group,
    String query, {
    int? generation,
  }) async {
    final current = generation ?? ++_generation;
    emit(IssuedListLoading(group: group, query: query));
    try {
      final page = await _competitions.list(
        role: CompetitionListRole.issuer,
        group: group,
        query: query,
      );
      if (current != _generation) return;
      emit(
        IssuedListLoaded(
          group: group,
          query: query,
          items: page.items,
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException catch (error) {
      if (current != _generation) return;
      emit(IssuedListFailure(group: group, query: query, error: error));
    }
  }

  Future<void> _onNextPage(
    IssuedListNextPageRequested event,
    Emitter<IssuedListState> emit,
  ) async {
    final current = state;
    if (current is! IssuedListLoaded ||
        !current.hasMore ||
        current.loadingMore ||
        current.refreshing) {
      return;
    }
    final generation = _generation;
    emit(current.copyWith(loadingMore: true, loadMoreError: () => null));
    try {
      final page = await _competitions.list(
        role: CompetitionListRole.issuer,
        group: current.group,
        query: current.query,
        page: current.page + 1,
      );
      if (generation != _generation) return;
      final known = {for (final item in current.items) item.id};
      emit(
        current.copyWith(
          items: [
            ...current.items,
            for (final item in page.items)
              if (!known.contains(item.id)) item,
          ],
          page: page.meta.currentPage,
          hasMore: page.hasMore,
          loadingMore: false,
        ),
      );
    } on ApiException catch (error) {
      if (generation != _generation) return;
      emit(current.copyWith(loadingMore: false, loadMoreError: () => error));
    }
  }

  Future<void> _onRefresh(
    IssuedListRefreshed event,
    Emitter<IssuedListState> emit,
  ) async {
    final current = state;
    if (current is! IssuedListLoaded) {
      return _reload(emit, current.group, current.query);
    }
    final generation = ++_generation;
    emit(current.copyWith(refreshing: true));
    try {
      final page = await _competitions.list(
        role: CompetitionListRole.issuer,
        group: current.group,
        query: current.query,
      );
      if (generation != _generation) return;
      emit(
        IssuedListLoaded(
          group: current.group,
          query: current.query,
          items: page.items,
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException {
      if (generation != _generation) return;
      // A failed refresh keeps the stale rows visible (S8 X).
      emit(current.copyWith(refreshing: false));
    }
  }
}
