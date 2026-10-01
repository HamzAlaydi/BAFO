import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/data/issuer_report_repository.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

// ── Award (M48, read-only) ──────────────────────────────────────────────

sealed class AwardState extends Equatable {
  const AwardState();

  @override
  List<Object?> get props => [];
}

final class AwardLoading extends AwardState {
  const AwardLoading();
}

final class AwardFailure extends AwardState {
  const AwardFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class AwardNotFound extends AwardState {
  const AwardNotFound();
}

/// Not the issuer, or before the first close: back to the detail.
final class AwardUnavailable extends AwardState {
  const AwardUnavailable(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class AwardLoaded extends AwardState {
  const AwardLoaded({
    required this.competition,
    this.award,
    this.standings = const [],
    this.standingsError,
    this.refreshing = false,
  });

  final Competition competition;

  /// The current award (issued or revoked); null when there is none.
  final Award? award;

  /// Final standings (`GET …/offers`), ordered by rank.
  final List<ParticipantStandingRow> standings;
  final ApiException? standingsError;
  final bool refreshing;

  @override
  List<Object?> get props => [
    competition,
    award,
    standings,
    standingsError,
    refreshing,
  ];
}

/// M48: the evaluation and award view, read-only on mobile (CD5): the
/// award decision, BAFO, close without award and revoke happen on the web.
/// Refreshes on `live.updated` of kind `award`, `status` or `bafo`.
class AwardCubit extends Cubit<AwardState> {
  AwardCubit({
    required this._competitions,
    required this._live,
    required this._hub,
    required this.competitionId,
  }) : super(const AwardLoading());

  final CompetitionsRepository _competitions;
  final LiveRepository _live;
  final CompetitionChannelHub _hub;
  final String competitionId;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];

  Future<void> load() async {
    emit(const AwardLoading());
    await _fetch();
  }

  Future<void> refresh() async {
    final current = state;
    if (current is AwardLoaded) {
      emit(
        AwardLoaded(
          competition: current.competition,
          award: current.award,
          standings: current.standings,
          standingsError: current.standingsError,
          refreshing: true,
        ),
      );
    }
    await _fetch();
  }

  Future<void> _fetch() async {
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      if (competition.viewerRole != ViewerRole.issuer ||
          !competition.hasAwardSection) {
        emit(AwardUnavailable(competition));
        return;
      }
      _follow();
      final award = await _competitions.issuerAward(competitionId);
      List<ParticipantStandingRow> standings = const [];
      ApiException? standingsError;
      try {
        standings = await _live.standings(competitionId);
      } on ApiException catch (error) {
        standingsError = error;
      }
      if (isClosed) return;
      emit(
        AwardLoaded(
          competition: competition,
          award: award,
          standings: standings,
          standingsError: standingsError,
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      final current = state;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const AwardNotFound());
      } else if (current is AwardLoaded) {
        emit(
          AwardLoaded(
            competition: current.competition,
            award: current.award,
            standings: current.standings,
            standingsError: current.standingsError,
          ),
        );
      } else {
        emit(AwardFailure(error));
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
        channel.liveSnapshots.listen((json) {
          final change = json['last_change'];
          final kind = change is Map<String, dynamic>
              ? LastChangeKind.parse(change['kind'])
              : LastChangeKind.unknown;
          if (kind.requiresCompetitionRefetch) unawaited(refresh());
        }),
      )
      ..add(channel.competitionUpdates.listen((_) => refresh()))
      ..add(channel.resyncRequests.listen((_) => refresh()));
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

// ── Result report PDF ───────────────────────────────────────────────────

sealed class ResultReportState extends Equatable {
  const ResultReportState();

  @override
  List<Object?> get props => [];
}

final class ResultReportIdle extends ResultReportState {
  const ResultReportIdle();
}

/// Asking for the report, waiting for generation (202, polled every
/// [ResultReportCubit.pollInterval]) or downloading it.
final class ResultReportWorking extends ResultReportState {
  const ResultReportWorking({
    required this.locale,
    this.generating = false,
    this.progress,
  });

  final String locale;

  /// The server is generating it (202).
  final bool generating;

  /// Download progress 0..1 once the file is ready.
  final double? progress;

  @override
  List<Object?> get props => [locale, generating, progress];
}

/// The PDF was downloaded and handed to the system viewer.
final class ResultReportOpened extends ResultReportState {
  const ResultReportOpened({required this.locale, required this.result});

  final String locale;
  final FileOpenResult result;

  @override
  List<Object?> get props => [locale, result];
}

/// `report_not_available` (before the first close).
final class ResultReportUnavailable extends ResultReportState {
  const ResultReportUnavailable();
}

final class ResultReportFailed extends ResultReportState {
  const ResultReportFailed({
    required this.locale,
    this.error,
    this.timedOut = false,
  });

  final String locale;
  final ApiException? error;

  /// Still generating after [ResultReportCubit.pollTimeout].
  final bool timedOut;

  @override
  List<Object?> get props => [locale, error, timedOut];
}

/// The issuer's result PDF (ARCHITECTURE.md §7.14): `GET …/report?locale=`
/// → ready: authenticated download to the temporary directory, then the
/// system viewer (share from there); pending: poll every 3 s, up to 2
/// minutes.
class ResultReportCubit extends Cubit<ResultReportState> {
  ResultReportCubit({
    required this._reports,
    required this._downloads,
    required this.competitionId,
    this.pollInterval = const Duration(seconds: 3),
    this.pollTimeout = const Duration(minutes: 2),
  }) : super(const ResultReportIdle());

  final IssuerReportRepository _reports;
  final FileDownloadService _downloads;
  final String competitionId;
  final Duration pollInterval;
  final Duration pollTimeout;
  int _request = 0;

  Future<void> open(String locale) async {
    if (state is ResultReportWorking) return;
    final request = ++_request;
    emit(ResultReportWorking(locale: locale));
    final deadline = DateTime.now().add(pollTimeout);
    try {
      var report = await _reports.report(competitionId, locale: locale);
      while (report.status == 'pending') {
        if (isClosed || request != _request) return;
        if (DateTime.now().isAfter(deadline)) {
          emit(ResultReportFailed(locale: locale, timedOut: true));
          return;
        }
        emit(ResultReportWorking(locale: locale, generating: true));
        await Future<void>.delayed(pollInterval);
        if (isClosed || request != _request) return;
        report = await _reports.report(competitionId, locale: locale);
      }
      final file = report.file;
      if (report.status != 'ready' || file == null) {
        if (!isClosed) emit(ResultReportFailed(locale: locale));
        return;
      }
      if (isClosed) return;
      emit(ResultReportWorking(locale: locale, progress: 0));
      final path = await _downloads.download(
        file,
        onProgress: (received, total) {
          if (isClosed || request != _request) return;
          emit(
            ResultReportWorking(
              locale: locale,
              progress: total > 0 ? received / total : null,
            ),
          );
        },
      );
      final result = await _downloads.open(path, mimeType: file.mimeType);
      if (!isClosed) emit(ResultReportOpened(locale: locale, result: result));
    } on ApiException catch (error) {
      if (isClosed) return;
      if (error.code == 'report_not_available') {
        emit(const ResultReportUnavailable());
      } else {
        emit(ResultReportFailed(locale: locale, error: error));
      }
    } on Exception {
      // File system errors while saving the download.
      if (!isClosed) emit(ResultReportFailed(locale: locale));
    }
  }

  @override
  Future<void> close() {
    _request++;
    return super.close();
  }
}
