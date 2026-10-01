import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

// ── Events ──────────────────────────────────────────────────────────────

sealed class OffersLogEvent extends Equatable {
  const OffersLogEvent();

  @override
  List<Object?> get props => [];
}

final class OffersLogStarted extends OffersLogEvent {
  const OffersLogStarted({this.silent = false});

  /// Reload in place (a void or an unlock changed existing rows): the
  /// current rows stay visible until the new ones arrive.
  final bool silent;

  @override
  List<Object?> get props => [silent];
}

final class OffersLogNextPageRequested extends OffersLogEvent {
  const OffersLogNextPageRequested();
}

/// `offer.accepted` on the issuer channel: an `OfferLogEntry`.
final class OffersLogOfferReceived extends OffersLogEvent {
  const OffersLogOfferReceived(this.entry);

  final Map<String, dynamic> entry;

  @override
  List<Object?> get props => [entry];
}

/// A `seq` gap (or a resync): fetch `after_seq = lastSeq` and merge.
final class OffersLogGapDetected extends OffersLogEvent {
  const OffersLogGapDetected();
}

// ── States ──────────────────────────────────────────────────────────────

sealed class OffersLogState extends Equatable {
  const OffersLogState();

  @override
  List<Object?> get props => [];
}

final class OffersLogLoading extends OffersLogState {
  const OffersLogLoading();
}

final class OffersLogFailure extends OffersLogState {
  const OffersLogFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class OffersLogNotFound extends OffersLogState {
  const OffersLogNotFound();
}

/// Not the issuer: back to the shared detail (role guard).
final class OffersLogNotIssuer extends OffersLogState {
  const OffersLogNotIssuer(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class OffersLogLoaded extends OffersLogState {
  const OffersLogLoaded({
    required this.competition,
    required this.entries,
    required this.lastSeq,
    required this.hasMore,
    this.loadingMore = false,
    this.loadMoreError,
  });

  final Competition competition;

  /// Ascending `seq`, unique by `id`. Sealed amounts are null until the
  /// offers open (shown as «مغلق»).
  final List<OfferLogEntry> entries;

  /// The highest `seq` fetched in order (the `after_seq` cursor).
  final int lastSeq;
  final bool hasMore;
  final bool loadingMore;
  final ApiException? loadMoreError;

  OffersLogLoaded copyWith({
    Competition? competition,
    List<OfferLogEntry>? entries,
    int? lastSeq,
    bool? hasMore,
    bool? loadingMore,
    ApiException? Function()? loadMoreError,
  }) => OffersLogLoaded(
    competition: competition ?? this.competition,
    entries: entries ?? this.entries,
    lastSeq: lastSeq ?? this.lastSeq,
    hasMore: hasMore ?? this.hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreError: loadMoreError == null ? this.loadMoreError : loadMoreError(),
  );

  @override
  List<Object?> get props => [
    competition,
    entries,
    lastSeq,
    hasMore,
    loadingMore,
    loadMoreError,
  ];
}

// ── Bloc ────────────────────────────────────────────────────────────────

/// M47, the issuer's offers log: `GET …/offers/log?after_seq=&limit=`
/// pages in `seq` order, plus `offer.accepted` appended by `seq` and
/// de-duplicated by `id`. A realtime `seq` beyond `lastSeq + 1` fetches the
/// gap (SCREENS.md S4).
class OffersLogBloc extends Bloc<OffersLogEvent, OffersLogState> {
  OffersLogBloc({
    required this._competitions,
    required this._live,
    required this._hub,
    required this.competitionId,
    this.pageSize = 200,
  }) : super(const OffersLogLoading()) {
    on<OffersLogStarted>(_onStarted);
    on<OffersLogNextPageRequested>(_onNextPage);
    on<OffersLogOfferReceived>(_onOffer);
    on<OffersLogGapDetected>(_onGap);
  }

  final CompetitionsRepository _competitions;
  final LiveRepository _live;
  final CompetitionChannelHub _hub;
  final String competitionId;
  final int pageSize;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];
  bool _fetchingGap = false;

  Future<void> _onStarted(
    OffersLogStarted event,
    Emitter<OffersLogState> emit,
  ) async {
    final previous = state;
    final silent = event.silent && previous is OffersLogLoaded;
    if (!silent) emit(const OffersLogLoading());
    try {
      final competition = await _competitions.show(competitionId);
      if (competition.viewerRole != ViewerRole.issuer) {
        emit(OffersLogNotIssuer(competition));
        return;
      }
      _follow();
      final page = await _live.offersLog(competitionId, limit: pageSize);
      emit(
        OffersLogLoaded(
          competition: competition,
          entries: _merge(const [], page.entries),
          lastSeq: page.lastSeq,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException catch (error) {
      if (silent) return;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const OffersLogNotFound());
      } else {
        emit(OffersLogFailure(error));
      }
    }
  }

  void _follow() {
    if (_channel != null) return;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: ViewerRole.issuer,
    );
    _channel = channel;
    _subscriptions
      ..add(
        channel.offersAccepted.listen(
          (json) => add(OffersLogOfferReceived(json)),
        ),
      )
      ..add(
        channel.resyncRequests.listen((_) => add(const OffersLogGapDetected())),
      )
      // A void or a sealed unlock changes existing rows: reload from 0.
      ..add(
        channel.liveSnapshots.listen((json) {
          final change = json['last_change'];
          final kind = change is Map<String, dynamic>
              ? LastChangeKind.parse(change['kind'])
              : LastChangeKind.unknown;
          if (kind == LastChangeKind.voided || kind == LastChangeKind.status) {
            add(const OffersLogStarted(silent: true));
          }
        }),
      );
  }

  /// Merges by `id` and sorts by `seq`.
  static List<OfferLogEntry> _merge(
    List<OfferLogEntry> current,
    List<OfferLogEntry> incoming,
  ) {
    final byId = {for (final entry in current) entry.id: entry};
    for (final entry in incoming) {
      byId[entry.id] = entry;
    }
    return byId.values.toList()..sort((a, b) => a.seq.compareTo(b.seq));
  }

  Future<void> _onNextPage(
    OffersLogNextPageRequested event,
    Emitter<OffersLogState> emit,
  ) async {
    final current = state;
    if (current is! OffersLogLoaded ||
        !current.hasMore ||
        current.loadingMore) {
      return;
    }
    emit(current.copyWith(loadingMore: true, loadMoreError: () => null));
    try {
      final page = await _live.offersLog(
        competitionId,
        afterSeq: current.lastSeq,
        limit: pageSize,
      );
      final latest = state;
      if (latest is! OffersLogLoaded) return;
      emit(
        latest.copyWith(
          entries: _merge(latest.entries, page.entries),
          lastSeq: page.lastSeq > latest.lastSeq
              ? page.lastSeq
              : latest.lastSeq,
          hasMore: page.hasMore,
          loadingMore: false,
        ),
      );
    } on ApiException catch (error) {
      final latest = state;
      if (latest is OffersLogLoaded) {
        emit(latest.copyWith(loadingMore: false, loadMoreError: () => error));
      }
    }
  }

  void _onOffer(OffersLogOfferReceived event, Emitter<OffersLogState> emit) {
    final current = state;
    if (current is! OffersLogLoaded) return;
    final OfferLogEntry entry;
    try {
      entry = OfferLogEntry.fromJson(event.entry);
    } on FormatException {
      return;
    }
    // Older pages are still to come: paging will bring it in order.
    if (current.hasMore) return;
    if (entry.seq <= current.lastSeq) {
      // Already known (or a re-delivery): replace by id.
      if (current.entries.any((row) => row.id == entry.id)) {
        emit(current.copyWith(entries: _merge(current.entries, [entry])));
      }
      return;
    }
    if (entry.seq == current.lastSeq + 1) {
      emit(
        current.copyWith(
          entries: _merge(current.entries, [entry]),
          lastSeq: entry.seq,
        ),
      );
      return;
    }
    add(const OffersLogGapDetected());
  }

  Future<void> _onGap(
    OffersLogGapDetected event,
    Emitter<OffersLogState> emit,
  ) async {
    final current = state;
    if (current is! OffersLogLoaded || _fetchingGap) return;
    _fetchingGap = true;
    try {
      var cursor = current.lastSeq;
      var entries = current.entries;
      var hasMore = true;
      // Fetch everything after the cursor (bounded pages).
      for (var guard = 0; hasMore && guard < 10; guard++) {
        final page = await _live.offersLog(
          competitionId,
          afterSeq: cursor,
          limit: 500,
        );
        entries = _merge(entries, page.entries);
        hasMore = page.hasMore;
        if (page.lastSeq <= cursor) break;
        cursor = page.lastSeq;
      }
      final latest = state;
      if (latest is OffersLogLoaded) {
        emit(
          latest.copyWith(
            entries: _merge(latest.entries, entries),
            lastSeq: cursor > latest.lastSeq ? cursor : latest.lastSeq,
            hasMore: hasMore,
          ),
        );
      }
    } on ApiException {
      // The next event or resync retries.
    } finally {
      _fetchingGap = false;
    }
  }

  @override
  Future<void> close() async {
    for (final subscription in _subscriptions) {
      await subscription.cancel();
    }
    await _channel?.release();
    return super.close();
  }
}
