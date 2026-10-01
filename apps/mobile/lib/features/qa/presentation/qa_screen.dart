import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/participant/presentation/widgets/offline_gate.dart';
import 'package:bafo/features/qa/presentation/qa_bloc.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M31: the Q&A of a competition for participants and the issuer.
///
/// Authors appear exactly as the server projects them: the issuer by name
/// with the «طارح المنافسة» badge; the viewer's own organisation as «أنتم»;
/// other participants by alias only («المتنافس 3»), with the organisation
/// name only where the payload carries it (the issuer's view).
class QaScreen extends StatelessWidget {
  const QaScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) =>
      _QaProvider(competitionId: competitionId);
}

class _QaProvider extends StatefulWidget {
  const _QaProvider({required this.competitionId});

  final String competitionId;

  @override
  State<_QaProvider> createState() => _QaProviderState();
}

class _QaProviderState extends State<_QaProvider> {
  final ScrollController _scroll = ScrollController();

  /// Away from the newest comments (the top of the list).
  bool get _away => _scroll.hasClients && _scroll.offset > 120;

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => QaBloc(
      competitions: context.read<CompetitionsRepository>(),
      comments: context.read<CommentsRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      competitionId: widget.competitionId,
      organizationId: context.read<SessionCubit>().state.me?.organization.id,
      isReaderAway: () => _away,
    )..add(const QaStarted()),
    child: _QaView(scroll: _scroll),
  );
}

class _QaView extends StatefulWidget {
  const _QaView({required this.scroll});

  final ScrollController scroll;

  @override
  State<_QaView> createState() => _QaViewState();
}

class _QaViewState extends State<_QaView> {
  @override
  void initState() {
    super.initState();
    widget.scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    widget.scroll.removeListener(_onScroll);
    super.dispose();
  }

  void _onScroll() {
    final scroll = widget.scroll;
    if (!scroll.hasClients) return;
    final bloc = context.read<QaBloc>();
    if (scroll.offset <= 120) bloc.add(const QaUnseenCleared());
    if (scroll.position.pixels >= scroll.position.maxScrollExtent - 320) {
      bloc.add(const QaNextPageRequested());
    }
  }

  Future<void> _compose(QaLoaded state, {Comment? replyTo}) async {
    final l10n = context.l10n;
    final bloc = context.read<QaBloc>();
    final posted = await showQaComposeSheet(
      context,
      competitionId: state.competition.id,
      comments: context.read<CommentsRepository>(),
      mode: replyTo != null
          ? QaComposeMode.reply
          : (state.isIssuer
                ? QaComposeMode.announcement
                : QaComposeMode.question),
      parentId: replyTo?.id,
    );
    if (!mounted) return;
    switch (posted) {
      case QaComposePosted(:final comment):
        bloc.add(QaCommentPosted(comment));
        BafoToast.success(
          context,
          replyTo != null
              ? l10n.qaReplyPosted
              : (state.isIssuer ? l10n.qaAnnouncementPosted : l10n.qaPosted),
        );
      case QaComposeClosed():
        // `comments_closed`: the status changed; reload the page state.
        bloc.add(const QaRefreshed());
      case null:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocBuilder<QaBloc, QaState>(
      builder: (context, state) {
        final loaded = state is QaLoaded ? state : null;
        return Scaffold(
          appBar: BafoAppBar(title: l10n.qaTitle),
          floatingActionButton: loaded != null && loaded.canComment
              ? FloatingActionButton.extended(
                  key: const Key('qa-compose'),
                  onPressed: () => unawaited(_compose(loaded)),
                  icon: Icon(
                    loaded.isIssuer
                        ? Icons.campaign_outlined
                        : Icons.help_outline_rounded,
                  ),
                  label: Text(
                    loaded.isIssuer ? l10n.qaAnnounceAction : l10n.qaAskAction,
                  ),
                )
              : null,
          body: switch (state) {
            QaInitial() || QaLoading() => const LoadingSkeletonList(),
            QaNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            QaFailure(:final error) => ErrorState(
              error: error,
              onRetry: () => context.read<QaBloc>().add(const QaStarted()),
            ),
            QaLoaded() => Stack(
              children: [
                RefreshIndicator(
                  onRefresh: () async {
                    final bloc = context.read<QaBloc>()
                      ..add(const QaRefreshed());
                    await bloc.stream.firstWhere(
                      (s) => s is! QaLoaded || !s.refreshing,
                    );
                  },
                  child: _Threads(
                    state: state,
                    scroll: widget.scroll,
                    onReply: (thread) =>
                        unawaited(_compose(state, replyTo: thread)),
                  ),
                ),
                if (state.unseen > 0)
                  PositionedDirectional(
                    top: BafoSpacing.sm,
                    start: 0,
                    end: 0,
                    child: Center(
                      child: Semantics(
                        liveRegion: true,
                        child: ActionChip(
                          avatar: const Icon(
                            Icons.arrow_upward_rounded,
                            size: 16,
                          ),
                          label: Text(l10n.qaNewMessages(state.unseen)),
                          onPressed: () {
                            unawaited(
                              widget.scroll.animateTo(
                                0,
                                duration:
                                    MediaQuery.disableAnimationsOf(context)
                                    ? const Duration(milliseconds: 1)
                                    : const Duration(milliseconds: 300),
                                curve: Curves.easeOut,
                              ),
                            );
                            context.read<QaBloc>().add(const QaUnseenCleared());
                          },
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          },
        );
      },
    );
  }
}

class _Threads extends StatelessWidget {
  const _Threads({
    required this.state,
    required this.scroll,
    required this.onReply,
  });

  final QaLoaded state;
  final ScrollController scroll;
  final ValueChanged<Comment> onReply;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final closed = !state.canComment;
    final header = closed ? 1 : 0;
    final footer = state.hasMore || state.loadMoreError != null ? 1 : 0;
    if (state.threads.isEmpty) {
      return ListView(
        controller: scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: BafoSpacing.pagePadding,
        children: [
          if (closed) const _ClosedNotice(),
          const SizedBox(height: BafoSpacing.xl),
          EmptyState(
            icon: Icons.forum_outlined,
            title: l10n.qaEmptyTitle,
            message: l10n.qaEmptyMessage,
          ),
        ],
      );
    }
    return ListView.separated(
      controller: scroll,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.page,
        BafoSpacing.page,
        96,
      ),
      itemCount: header + state.threads.length + footer,
      separatorBuilder: (_, _) => const SizedBox(height: BafoSpacing.md),
      itemBuilder: (context, index) {
        if (closed && index == 0) return const _ClosedNotice();
        final position = index - header;
        if (position >= state.threads.length) {
          if (state.loadMoreError != null) {
            return Center(
              child: BafoButton.text(
                label: l10n.commonActionsRetry,
                onPressed: () =>
                    context.read<QaBloc>().add(const QaNextPageRequested()),
              ),
            );
          }
          return Padding(
            padding: const EdgeInsets.all(BafoSpacing.md),
            child: Center(
              child: SizedBox.square(
                dimension: 24,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  semanticsLabel: l10n.commonLoading,
                ),
              ),
            ),
          );
        }
        final thread = state.threads[position];
        return QaThreadTile(
          key: ValueKey(thread.id),
          thread: thread,
          highlighted: state.highlighted,
          onReply: state.canReplyTo(thread) ? () => onReply(thread) : null,
        );
      },
    );
  }
}

class _ClosedNotice extends StatelessWidget {
  const _ClosedNotice();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return InfoNotice(
      tone: StatusTone.neutral,
      icon: Icons.lock_outline_rounded,
      title: l10n.qaClosedTitle,
      message: l10n.qaClosedBody,
    );
  }
}

/// The author line of a comment, from the server projection only.
String qaAuthorLabel(AppLocalizations l10n, CommentAuthor author) =>
    switch (author.kind) {
      CommentAuthorKind.issuer =>
        author.organizationName ?? l10n.competitionsDetailIssuer,
      CommentAuthorKind.me => l10n.qaAuthorMe,
      CommentAuthorKind.participant => switch ((
        author.aliasNo,
        author.organizationName,
      )) {
        (final int alias, final String name) => l10n.qaAuthorParticipantNamed(
          alias,
          name,
        ),
        (final int alias, null) => l10n.qaAuthorParticipant(alias),
        _ => l10n.qaAuthorUnknown,
      },
      CommentAuthorKind.unknown => l10n.qaAuthorUnknown,
    };

/// W18 `QaThread`: a question or announcement with its replies.
class QaThreadTile extends StatelessWidget {
  const QaThreadTile({
    required this.thread,
    this.highlighted = const {},
    this.onReply,
    super.key,
  });

  final Comment thread;
  final Set<String> highlighted;
  final VoidCallback? onReply;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final announcement =
        thread.parentId == null &&
        thread.author.kind == CommentAuthorKind.issuer;
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _CommentBody(
            comment: thread,
            highlighted: highlighted.contains(thread.id),
            badge: announcement
                ? StatusPill(
                    label: l10n.qaAnnouncement,
                    tone: StatusTone.info,
                    icon: Icons.campaign_outlined,
                  )
                : null,
          ),
          if (thread.replies.isNotEmpty) ...[
            const SizedBox(height: BafoSpacing.sm),
            Semantics(
              label: l10n.qaRepliesCount(thread.replies.length),
              child: Container(
                padding: const EdgeInsetsDirectional.only(
                  start: BafoSpacing.md,
                ),
                decoration: BoxDecoration(
                  border: BorderDirectional(
                    start: BorderSide(
                      color: theme.colorScheme.outlineVariant,
                      width: 2,
                    ),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final reply in thread.replies)
                      Padding(
                        padding: const EdgeInsetsDirectional.only(
                          top: BafoSpacing.sm,
                        ),
                        child: _CommentBody(
                          comment: reply,
                          highlighted: highlighted.contains(reply.id),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
          if (onReply != null)
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: BafoButton.text(
                label: l10n.qaReplyAction,
                icon: Icons.reply_rounded,
                onPressed: onReply,
              ),
            ),
        ],
      ),
    );
  }
}

class _CommentBody extends StatelessWidget {
  const _CommentBody({
    required this.comment,
    required this.highlighted,
    this.badge,
  });

  final Comment comment;
  final bool highlighted;
  final Widget? badge;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final author = comment.author;
    final issuer = author.kind == CommentAuthorKind.issuer;
    final time = BafoDateFormat.relative(
      comment.createdAt,
      now: context.read<ServerClock>().now(),
      languageCode: context.languageCode,
      l10n: l10n,
    );
    return AnimatedContainer(
      duration: MediaQuery.disableAnimationsOf(context)
          ? Duration.zero
          : const Duration(milliseconds: 300),
      padding: const EdgeInsetsDirectional.all(BafoSpacing.xs),
      decoration: BoxDecoration(
        color: highlighted ? theme.colorScheme.surfaceContainerLow : null,
        borderRadius: BorderRadius.circular(BafoRadii.sm),
      ),
      child: MergeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              spacing: BafoSpacing.xs,
              runSpacing: BafoSpacing.xxs,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  qaAuthorLabel(l10n, author),
                  style: theme.textTheme.labelLarge,
                ),
                if (issuer)
                  StatusPill(
                    label: l10n.competitionsDetailIssuer,
                    tone: StatusTone.success,
                    icon: Icons.verified_outlined,
                  ),
                ?badge,
                Text(
                  time,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
            const SizedBox(height: BafoSpacing.xs),
            Text(comment.body, style: theme.textTheme.bodyMedium),
          ],
        ),
      ),
    );
  }
}

enum QaComposeMode { question, announcement, reply }

/// The outcome of the ask/reply sheet.
sealed class QaComposeResult {
  const QaComposeResult();
}

final class QaComposePosted extends QaComposeResult {
  const QaComposePosted(this.comment);

  final Comment comment;
}

/// `comments_closed`: Q&A closed while the sheet was open.
final class QaComposeClosed extends QaComposeResult {
  const QaComposeClosed();
}

/// M32: 1–2000 characters with a counter.
Future<QaComposeResult?> showQaComposeSheet(
  BuildContext context, {
  required String competitionId,
  required CommentsRepository comments,
  required QaComposeMode mode,
  String? parentId,
}) {
  final l10n = context.l10n;
  return showBafoBottomSheet<QaComposeResult>(
    context,
    title: switch (mode) {
      QaComposeMode.question => l10n.qaAskAction,
      QaComposeMode.announcement => l10n.qaAnnounceAction,
      QaComposeMode.reply => l10n.qaReplyTitle,
    },
    builder: (_) => BlocProvider(
      create: (_) => QaComposerCubit(
        comments: comments,
        competitionId: competitionId,
        parentId: parentId,
      ),
      child: _ComposeSheet(mode: mode),
    ),
  );
}

class _ComposeSheet extends StatefulWidget {
  const _ComposeSheet({required this.mode});

  final QaComposeMode mode;

  @override
  State<_ComposeSheet> createState() => _ComposeSheetState();
}

class _ComposeSheetState extends State<_ComposeSheet> {
  final TextEditingController _body = TextEditingController();
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  void _send() {
    if (!(_form.currentState?.validate() ?? false)) return;
    unawaited(context.read<QaComposerCubit>().post(_body.text));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<QaComposerCubit, QaComposerState>(
      listener: (context, state) {
        if (state is QaComposerPosted) {
          Navigator.of(context).pop(QaComposePosted(state.comment));
        } else if (state is QaComposerIdle &&
            state.error?.code == 'comments_closed') {
          BafoToast.error(context, errorMessage(l10n, state.error));
          Navigator.of(context).pop(const QaComposeClosed());
        }
      },
      builder: (context, state) {
        final busy = state is QaComposerPosting;
        final error = state is QaComposerIdle ? state.error : null;
        return Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              BafoTextField(
                key: const Key('qa-body'),
                label: switch (widget.mode) {
                  QaComposeMode.question => l10n.qaQuestionLabel,
                  QaComposeMode.announcement => l10n.qaAnnouncementLabel,
                  QaComposeMode.reply => l10n.qaReplyLabel,
                },
                helperText: widget.mode == QaComposeMode.question
                    ? l10n.qaQuestionHelper
                    : null,
                controller: _body,
                required: true,
                autofocus: true,
                enabled: !busy,
                maxLines: 6,
                minLines: 3,
                maxLength: QaComposerCubit.maxLength,
                textCapitalization: TextCapitalization.sentences,
                validator: (value) => (value ?? '').trim().isEmpty
                    ? l10n.validationRequired
                    : null,
              ),
              if (error != null) ...[
                const SizedBox(height: BafoSpacing.sm),
                InfoNotice(
                  tone: StatusTone.danger,
                  icon: Icons.error_outline_rounded,
                  message: errorMessage(l10n, error),
                ),
              ],
              const SizedBox(height: BafoSpacing.lg),
              BafoButton(
                key: const Key('qa-send'),
                label: l10n.qaSend,
                icon: Icons.send_rounded,
                loading: busy,
                expand: true,
                onPressed: watchOffline(context) ? null : _send,
              ),
            ],
          ),
        );
      },
    );
  }
}
