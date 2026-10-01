import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class QaState extends Equatable {
  const QaState();

  @override
  List<Object?> get props => [];
}

final class QaInitial extends QaState {
  const QaInitial();
}

final class QaLoading extends QaState {
  const QaLoading();
}

/// 404: never reveals the competition.
final class QaNotFound extends QaState {
  const QaNotFound();
}

final class QaFailure extends QaState {
  const QaFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class QaLoaded extends QaState {
  const QaLoaded({
    required this.competition,
    required this.threads,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
    this.loadMoreError,
    this.unseen = 0,
    this.highlighted = const {},
    this.refreshing = false,
  });

  final Competition competition;

  /// Top-level comments newest first, each with its replies oldest first.
  final List<Comment> threads;
  final int page;
  final bool hasMore;
  final bool loadingMore;
  final ApiException? loadMoreError;

  /// Comments received while the reader was scrolled away («{n} رسائل
  /// جديدة»).
  final int unseen;

  /// Ids received live, briefly highlighted.
  final Set<String> highlighted;
  final bool refreshing;

  /// Posting is allowed by the server (`scheduled` or `live`, and the
  /// viewer's role); otherwise the page is read-only.
  bool get canComment => competition.permissions.canComment;

  /// The composer's top-level action: a participant asks, the issuer
  /// announces.
  bool get isIssuer => competition.isIssuerView;

  /// Reply buttons: the issuer on every thread; a participant only under
  /// its own organisation's questions (API.md §1.4).
  bool canReplyTo(Comment thread) =>
      canComment &&
      thread.parentId == null &&
      (isIssuer || thread.author.kind == CommentAuthorKind.me);

  QaLoaded copyWith({
    Competition? competition,
    List<Comment>? threads,
    int? page,
    bool? hasMore,
    bool? loadingMore,
    ApiException? loadMoreError,
    bool clearLoadMoreError = false,
    int? unseen,
    Set<String>? highlighted,
    bool? refreshing,
  }) => QaLoaded(
    competition: competition ?? this.competition,
    threads: threads ?? this.threads,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreError: clearLoadMoreError
        ? null
        : (loadMoreError ?? this.loadMoreError),
    unseen: unseen ?? this.unseen,
    highlighted: highlighted ?? this.highlighted,
    refreshing: refreshing ?? this.refreshing,
  );

  @override
  List<Object?> get props => [
    competition,
    threads,
    page,
    hasMore,
    loadingMore,
    loadMoreError,
    unseen,
    highlighted,
    refreshing,
  ];
}

sealed class QaEvent {
  const QaEvent();
}

final class QaStarted extends QaEvent {
  const QaStarted();
}

final class QaNextPageRequested extends QaEvent {
  const QaNextPageRequested();
}

final class QaRefreshed extends QaEvent {
  const QaRefreshed();
}

/// A comment the viewer posted (from the ask/reply sheet).
final class QaCommentPosted extends QaEvent {
  const QaCommentPosted(this.comment);

  final Comment comment;
}

/// `comment.created` on the viewer's channel.
final class QaCommentReceived extends QaEvent {
  const QaCommentReceived(this.comment, {this.readerAway = false});

  final Comment comment;

  /// The reader is scrolled away from the top: count it as unseen.
  final bool readerAway;
}

/// The reader scrolled back to the newest comments.
final class QaUnseenCleared extends QaEvent {
  const QaUnseenCleared();
}

final class _ChannelComment extends QaEvent {
  const _ChannelComment(this.json);

  final Json json;
}

/// M31: the competition's Q&A for the issuer and participants.
///
/// Comments come from `GET …/comments` (paginated by top-level comment) and
/// `comment.created`; both are de-duplicated by id, and a reply goes under
/// its `parent_id` (SCREENS.md S4). Authors are rendered exactly as the
/// server projects them (other participants by alias only).
class QaBloc extends Bloc<QaEvent, QaState> {
  QaBloc({
    required this._competitions,
    required this._comments,
    required this._hub,
    required this.competitionId,
    this.organizationId,
    this.isReaderAway,
  }) : super(const QaInitial()) {
    on<QaStarted>(_onStarted);
    on<QaNextPageRequested>(_onNextPage);
    on<QaRefreshed>(_onRefreshed);
    on<QaCommentPosted>((event, emit) => _insert(event.comment, emit));
    on<QaCommentReceived>(
      (event, emit) =>
          _insert(event.comment, emit, live: true, away: event.readerAway),
    );
    on<QaUnseenCleared>(_onUnseenCleared);
    on<_ChannelComment>(_onChannelComment);
  }

  final CompetitionsRepository _competitions;
  final CommentsRepository _comments;
  final CompetitionChannelHub _hub;
  final String competitionId;
  final String? organizationId;

  /// Asked when a live comment arrives: is the reader away from the top?
  final bool Function()? isReaderAway;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];

  Future<void> _onStarted(QaStarted event, Emitter<QaState> emit) async {
    emit(const QaLoading());
    try {
      final (competition, page) = await _loadFirstPage();
      _follow(competition);
      emit(
        QaLoaded(
          competition: competition,
          threads: _dedupe(page.items),
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException catch (error) {
      emit(
        error.statusCode == 404 || error.code == 'not_found'
            ? const QaNotFound()
            : QaFailure(error),
      );
    }
  }

  /// The competition (role, `can_comment`) and the first page, in parallel.
  Future<(Competition, Paged<Comment>)> _loadFirstPage() async {
    final page = _comments.list(competitionId);
    try {
      final competition = await _competitions.show(competitionId);
      return (competition, await page);
    } on Object {
      // Both failed or the first did: the other error is not unhandled.
      page.ignore();
      rethrow;
    }
  }

  void _follow(Competition competition) {
    if (_channel != null) return;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: competition.viewerRole,
      organizationId: organizationId,
    );
    _channel = channel;
    _subscriptions
      ..add(
        channel.commentsCreated.listen((json) => add(_ChannelComment(json))),
      )
      ..add(channel.resyncRequests.listen((_) => add(const QaRefreshed())))
      ..add(
        // The status may have changed (e.g. comments closed at the close).
        channel.competitionUpdates.listen((_) => add(const QaRefreshed())),
      );
  }

  void _onChannelComment(_ChannelComment event, Emitter<QaState> emit) {
    final Comment comment;
    try {
      comment = Comment.fromJson(event.json);
    } on FormatException {
      return;
    }
    _insert(comment, emit, live: true, away: isReaderAway?.call() ?? false);
  }

  Future<void> _onNextPage(
    QaNextPageRequested event,
    Emitter<QaState> emit,
  ) async {
    final current = state;
    if (current is! QaLoaded || !current.hasMore || current.loadingMore) return;
    emit(current.copyWith(loadingMore: true, clearLoadMoreError: true));
    try {
      final next = await _comments.list(competitionId, page: current.page + 1);
      final latest = state;
      if (latest is! QaLoaded) return;
      emit(
        latest.copyWith(
          threads: _dedupe([...latest.threads, ...next.items]),
          page: next.meta.currentPage,
          hasMore: next.hasMore,
          loadingMore: false,
        ),
      );
    } on ApiException catch (error) {
      final latest = state;
      if (latest is QaLoaded) {
        emit(latest.copyWith(loadingMore: false, loadMoreError: error));
      }
    }
  }

  /// Reloads the first page and the competition (status, `can_comment`),
  /// keeping what is on screen until the answer arrives.
  Future<void> _onRefreshed(QaRefreshed event, Emitter<QaState> emit) async {
    final current = state;
    if (current is! QaLoaded) {
      if (current is QaFailure) add(const QaStarted());
      return;
    }
    emit(current.copyWith(refreshing: true));
    try {
      final (competition, page) = await _loadFirstPage();
      final latest = state;
      if (latest is! QaLoaded) return;
      emit(
        latest.copyWith(
          competition: competition,
          threads: _dedupe(page.items),
          page: page.meta.currentPage,
          hasMore: page.hasMore,
          refreshing: false,
          unseen: 0,
          clearLoadMoreError: true,
        ),
      );
    } on ApiException {
      final latest = state;
      if (latest is QaLoaded) emit(latest.copyWith(refreshing: false));
    }
  }

  void _onUnseenCleared(QaUnseenCleared event, Emitter<QaState> emit) {
    final current = state;
    if (current is QaLoaded && current.unseen > 0) {
      emit(current.copyWith(unseen: 0));
    }
  }

  /// Inserts [comment] once: a top-level comment first (newest first), a
  /// reply at the end of its thread (oldest first). A reply whose thread is
  /// not loaded yet is left for the page that holds it.
  void _insert(
    Comment comment,
    Emitter<QaState> emit, {
    bool live = false,
    bool away = false,
  }) {
    final current = state;
    if (current is! QaLoaded) return;
    if (_contains(current.threads, comment.id)) return;
    final parentId = comment.parentId;
    final List<Comment> threads;
    if (parentId == null) {
      threads = [comment, ...current.threads];
    } else {
      final index = current.threads.indexWhere((t) => t.id == parentId);
      if (index < 0) return;
      final parent = current.threads[index];
      threads = [...current.threads]
        ..[index] = parent.withReplies([...parent.replies, comment]);
    }
    emit(
      current.copyWith(
        threads: threads,
        unseen: live && away ? current.unseen + 1 : current.unseen,
        highlighted: live
            ? {...current.highlighted, comment.id}
            : current.highlighted,
      ),
    );
  }

  static bool _contains(List<Comment> threads, String id) => threads.any(
    (thread) => thread.id == id || thread.replies.any((r) => r.id == id),
  );

  /// Pages can overlap when new top-level comments shift the pagination.
  static List<Comment> _dedupe(List<Comment> threads) {
    final seen = <String>{};
    return [
      for (final thread in threads)
        if (seen.add(thread.id)) thread,
    ];
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

sealed class QaComposerState extends Equatable {
  const QaComposerState();

  @override
  List<Object?> get props => [];
}

final class QaComposerIdle extends QaComposerState {
  const QaComposerIdle({this.error});

  final ApiException? error;

  @override
  List<Object?> get props => [error];
}

final class QaComposerPosting extends QaComposerState {
  const QaComposerPosting();
}

final class QaComposerPosted extends QaComposerState {
  const QaComposerPosted(this.comment);

  final Comment comment;

  @override
  List<Object?> get props => [comment];
}

/// M32: posts a question, an announcement or a reply (1–2000 characters).
class QaComposerCubit extends Cubit<QaComposerState> {
  QaComposerCubit({
    required this._comments,
    required this.competitionId,
    this.parentId,
  }) : super(const QaComposerIdle());

  /// Server limit on `body` (API.md §1.4).
  static const int maxLength = 2000;

  final CommentsRepository _comments;
  final String competitionId;

  /// The thread replied to; null for a top-level question or announcement.
  final String? parentId;

  Future<void> post(String body) async {
    final text = body.trim();
    if (text.isEmpty || text.length > maxLength) return;
    if (state is QaComposerPosting) return;
    emit(const QaComposerPosting());
    try {
      final comment = await _comments.post(
        competitionId,
        body: text,
        parentId: parentId,
      );
      if (!isClosed) emit(QaComposerPosted(comment));
    } on ApiException catch (error) {
      if (!isClosed) emit(QaComposerIdle(error: error));
    }
  }
}
