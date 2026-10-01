import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// The list filters of M16: the status group segment, the direction and
/// the title search.
final class ParticipatingFilter extends Equatable {
  const ParticipatingFilter({
    this.group = CompetitionStatusGroup.active,
    this.direction,
    this.query = '',
  });

  final CompetitionStatusGroup group;

  /// Null: tenders and auctions.
  final Direction? direction;
  final String query;

  bool get isNarrowed => direction != null || query.isNotEmpty;

  ParticipatingFilter copyWith({
    CompetitionStatusGroup? group,
    Direction? direction,
    bool clearDirection = false,
    String? query,
  }) => ParticipatingFilter(
    group: group ?? this.group,
    direction: clearDirection ? null : (direction ?? this.direction),
    query: query ?? this.query,
  );

  @override
  List<Object?> get props => [group, direction, query];
}

sealed class ParticipatingListState extends Equatable {
  const ParticipatingListState(this.filter);

  final ParticipatingFilter filter;

  @override
  List<Object?> get props => [filter];
}

final class ParticipatingListInitial extends ParticipatingListState {
  const ParticipatingListInitial([super.filter = const ParticipatingFilter()]);
}

final class ParticipatingListLoading extends ParticipatingListState {
  const ParticipatingListLoading(super.filter);
}

final class ParticipatingListLoaded extends ParticipatingListState {
  const ParticipatingListLoaded({
    required ParticipatingFilter filter,
    required this.items,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
    this.loadMoreError,
    this.refreshing = false,
  }) : super(filter);

  /// Loaded rows; within each page, rows needing the viewer's action
  /// (`join_required`, `plan_required`) come first (SCREENS.md G1).
  final List<CompetitionListItem> items;
  final int page;
  final bool hasMore;
  final bool loadingMore;
  final ApiException? loadMoreError;
  final bool refreshing;

  /// Invitations awaiting a response among the loaded rows.
  int get needsActionCount => items.where((item) => item.needsAction).length;

  ParticipatingListLoaded copyWith({
    List<CompetitionListItem>? items,
    int? page,
    bool? hasMore,
    bool? loadingMore,
    ApiException? loadMoreError,
    bool clearLoadMoreError = false,
    bool? refreshing,
  }) => ParticipatingListLoaded(
    filter: filter,
    items: items ?? this.items,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreError: clearLoadMoreError
        ? null
        : (loadMoreError ?? this.loadMoreError),
    refreshing: refreshing ?? this.refreshing,
  );

  @override
  List<Object?> get props => [
    filter,
    items,
    page,
    hasMore,
    loadingMore,
    loadMoreError,
    refreshing,
  ];
}

final class ParticipatingListFailure extends ParticipatingListState {
  const ParticipatingListFailure(super.filter, this.error);

  final ApiException error;

  @override
  List<Object?> get props => [filter, error];
}

sealed class ParticipatingListEvent {
  const ParticipatingListEvent();
}

final class ParticipatingListStarted extends ParticipatingListEvent {
  const ParticipatingListStarted();
}

final class ParticipatingListGroupChanged extends ParticipatingListEvent {
  const ParticipatingListGroupChanged(this.group);

  final CompetitionStatusGroup group;
}

final class ParticipatingListDirectionChanged extends ParticipatingListEvent {
  const ParticipatingListDirectionChanged(this.direction);

  /// Null: every direction.
  final Direction? direction;
}

/// Title search; applied after [ParticipatingListBloc.queryDebounce].
final class ParticipatingListQueryChanged extends ParticipatingListEvent {
  const ParticipatingListQueryChanged(this.query);

  final String query;
}

final class ParticipatingListNextPageRequested extends ParticipatingListEvent {
  const ParticipatingListNextPageRequested();
}

/// Pull to refresh, app resume, or after a join/decline from a card.
final class ParticipatingListRefreshed extends ParticipatingListEvent {
  const ParticipatingListRefreshed();
}

final class _QueryCommitted extends ParticipatingListEvent {
  const _QueryCommitted(this.query);

  final String query;
}

/// M16 "Participating": `GET /competitions?role=participant` with the
/// status group, direction and search filters, page by page (`has_more`).
///
/// A filter change reloads from page 1; answers to superseded requests are
/// dropped, so a slow response never overwrites a newer filter.
class ParticipatingListBloc
    extends Bloc<ParticipatingListEvent, ParticipatingListState> {
  ParticipatingListBloc({
    required this._competitions,
    this.queryDebounce = const Duration(milliseconds: 300),
    ParticipatingFilter initialFilter = const ParticipatingFilter(),
  }) : super(ParticipatingListInitial(initialFilter)) {
    on<ParticipatingListStarted>((event, emit) => _reload(state.filter, emit));
    on<ParticipatingListGroupChanged>(
      (event, emit) => _reload(state.filter.copyWith(group: event.group), emit),
    );
    on<ParticipatingListDirectionChanged>(
      (event, emit) => _reload(
        state.filter.copyWith(
          direction: event.direction,
          clearDirection: event.direction == null,
        ),
        emit,
      ),
    );
    on<ParticipatingListQueryChanged>(_onQueryChanged);
    on<_QueryCommitted>(
      (event, emit) => _reload(state.filter.copyWith(query: event.query), emit),
    );
    on<ParticipatingListNextPageRequested>(_onNextPage);
    on<ParticipatingListRefreshed>(_onRefreshed);
  }

  final CompetitionsRepository _competitions;

  /// CONVENTIONS.md §9.3: search debounces by 300 ms.
  final Duration queryDebounce;

  Timer? _debounce;

  /// Increases with every reload; older answers are ignored.
  int _generation = 0;

  void _onQueryChanged(
    ParticipatingListQueryChanged event,
    Emitter<ParticipatingListState> emit,
  ) {
    _debounce?.cancel();
    final query = event.query.trim();
    if (query == state.filter.query) return;
    _debounce = Timer(queryDebounce, () => add(_QueryCommitted(query)));
  }

  Future<Paged<CompetitionListItem>> _page(
    ParticipatingFilter filter,
    int page,
  ) => _competitions.list(
    role: CompetitionListRole.participant,
    group: filter.group,
    direction: filter.direction,
    query: filter.query.isEmpty ? null : filter.query,
    page: page,
  );

  /// G1: rows needing action first within a page, otherwise server order.
  static List<CompetitionListItem> _actionFirst(
    List<CompetitionListItem> rows,
  ) => [
    ...rows.where((item) => item.needsAction),
    ...rows.where((item) => !item.needsAction),
  ];

  Future<void> _reload(
    ParticipatingFilter filter,
    Emitter<ParticipatingListState> emit,
  ) async {
    final generation = ++_generation;
    emit(ParticipatingListLoading(filter));
    try {
      final page = await _page(filter, 1);
      if (generation != _generation) return;
      emit(
        ParticipatingListLoaded(
          filter: filter,
          items: _actionFirst(page.items),
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException catch (error) {
      if (generation != _generation) return;
      emit(ParticipatingListFailure(filter, error));
    }
  }

  Future<void> _onNextPage(
    ParticipatingListNextPageRequested event,
    Emitter<ParticipatingListState> emit,
  ) async {
    final current = state;
    if (current is! ParticipatingListLoaded ||
        !current.hasMore ||
        current.loadingMore ||
        current.refreshing) {
      return;
    }
    final generation = _generation;
    emit(current.copyWith(loadingMore: true, clearLoadMoreError: true));
    try {
      final next = await _page(current.filter, current.page + 1);
      final latest = state;
      if (generation != _generation || latest is! ParticipatingListLoaded) {
        return;
      }
      final known = {for (final item in latest.items) item.id};
      emit(
        latest.copyWith(
          items: [
            ...latest.items,
            ..._actionFirst(
              next.items.where((item) => !known.contains(item.id)).toList(),
            ),
          ],
          page: next.meta.currentPage,
          hasMore: next.hasMore,
          loadingMore: false,
        ),
      );
    } on ApiException catch (error) {
      final latest = state;
      if (generation != _generation || latest is! ParticipatingListLoaded) {
        return;
      }
      emit(latest.copyWith(loadingMore: false, loadMoreError: error));
    }
  }

  /// Reloads the first page while the current rows stay visible; a failure
  /// keeps them (S8 **X**: stale data stays under a refresh).
  Future<void> _onRefreshed(
    ParticipatingListRefreshed event,
    Emitter<ParticipatingListState> emit,
  ) async {
    final current = state;
    if (current is! ParticipatingListLoaded) {
      await _reload(current.filter, emit);
      return;
    }
    final generation = ++_generation;
    emit(current.copyWith(refreshing: true, loadingMore: false));
    try {
      final page = await _page(current.filter, 1);
      if (generation != _generation) return;
      emit(
        ParticipatingListLoaded(
          filter: current.filter,
          items: _actionFirst(page.items),
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException {
      if (generation != _generation) return;
      emit(current.copyWith(refreshing: false));
    }
  }

  @override
  Future<void> close() {
    _debounce?.cancel();
    return super.close();
  }
}
