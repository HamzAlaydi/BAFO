import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class ParticipantCompetitionState extends Equatable {
  const ParticipantCompetitionState();

  @override
  List<Object?> get props => [];
}

final class ParticipantCompetitionInitial extends ParticipantCompetitionState {
  const ParticipantCompetitionInitial();
}

final class ParticipantCompetitionLoading extends ParticipantCompetitionState {
  const ParticipantCompetitionLoading();
}

/// 404: never reveals whether the competition exists (S8 **N**).
final class ParticipantCompetitionNotFound extends ParticipantCompetitionState {
  const ParticipantCompetitionNotFound();
}

final class ParticipantCompetitionFailure extends ParticipantCompetitionState {
  const ParticipantCompetitionFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class ParticipantCompetitionLoaded extends ParticipantCompetitionState {
  const ParticipantCompetitionLoaded({
    required this.competition,
    this.attachments = const [],
    this.attachmentsError,
    this.live,
    this.award,
    this.refreshing = false,
  });

  /// The projection the server returned for the viewer (invitee,
  /// participant, or issuer when an issuer opens the page).
  final Competition competition;

  /// Documents and links the viewer may see (invitees: the invitation
  /// documents of the teaser).
  final List<Attachment> attachments;
  final ApiException? attachmentsError;

  /// The newest participant snapshot applied (`v` guard): the standing
  /// summary follows `live.updated` while the page is open.
  final ParticipantLiveSnapshot? live;

  /// `GET …/award` as a participant, once a result exists (the winner's
  /// message).
  final ParticipantAwardView? award;
  final bool refreshing;

  ParticipantCompetitionLoaded copyWith({
    Competition? competition,
    List<Attachment>? attachments,
    ApiException? attachmentsError,
    bool clearAttachmentsError = false,
    ParticipantLiveSnapshot? live,
    ParticipantAwardView? award,
    bool? refreshing,
  }) => ParticipantCompetitionLoaded(
    competition: competition ?? this.competition,
    attachments: attachments ?? this.attachments,
    attachmentsError: clearAttachmentsError
        ? null
        : (attachmentsError ?? this.attachmentsError),
    live: live ?? this.live,
    award: award ?? this.award,
    refreshing: refreshing ?? this.refreshing,
  );

  @override
  List<Object?> get props => [
    competition,
    attachments,
    attachmentsError,
    live,
    award,
    refreshing,
  ];
}

/// `/competitions/:id` for invitees (M17) and participants (M21).
///
/// Loads the projection, the documents and, once there is a result, the
/// participant's award view. A participant follows its channel:
/// `competition.updated` refetches, `live.updated` refreshes the standing
/// summary (through the `v` gate). An invitee has no channel and refetches
/// on resume. After a join ([applyCompetition]) the page re-renders as a
/// participant and subscribes.
class ParticipantCompetitionCubit extends Cubit<ParticipantCompetitionState> {
  ParticipantCompetitionCubit({
    required this._competitions,
    required this._attachments,
    required this._hub,
    required this.competitionId,
    this.organizationId,
  }) : super(const ParticipantCompetitionInitial());

  final CompetitionsRepository _competitions;
  final AttachmentsRepository _attachments;
  final CompetitionChannelHub _hub;
  final String competitionId;

  /// The viewer's organisation (participant channel name).
  final String? organizationId;

  CompetitionChannel? _channel;
  ViewerRole? _channelRole;
  final List<StreamSubscription<Object?>> _subscriptions = [];

  Future<void> load() async {
    emit(const ParticipantCompetitionLoading());
    await _fetch();
  }

  /// Pull to refresh, `competition.updated`, resume: the data stays visible.
  Future<void> refresh() async {
    final current = state;
    if (current is ParticipantCompetitionLoaded) {
      emit(current.copyWith(refreshing: true));
    }
    await _fetch();
  }

  /// The join response (the participant projection): render it at once,
  /// then load the participant documents.
  Future<void> applyCompetition(Competition competition) async {
    final current = state;
    if (current is ParticipantCompetitionLoaded) {
      emit(current.copyWith(competition: competition, refreshing: true));
    }
    await _show(competition);
  }

  Future<void> _fetch() async {
    try {
      final competition = await _competitions.show(competitionId);
      await _show(competition);
    } on ApiException catch (error) {
      if (isClosed) return;
      final current = state;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const ParticipantCompetitionNotFound());
      } else if (current is ParticipantCompetitionLoaded) {
        // A failed refresh keeps the stale data (S8 X).
        emit(current.copyWith(refreshing: false));
      } else {
        emit(ParticipantCompetitionFailure(error));
      }
    }
  }

  Future<void> _show(Competition competition) async {
    if (competition.isIssuerView) {
      // The issuer's own page (M38) takes over: no channel, no documents.
      if (!isClosed) {
        emit(ParticipantCompetitionLoaded(competition: competition));
      }
      return;
    }
    _follow(competition);
    final documents = _documents(competition);
    final award = _award(competition);
    final (attachments, attachmentsError) = await documents;
    final awardView = await award;
    if (isClosed) return;
    final current = state;
    final previousLive = current is ParticipantCompetitionLoaded
        ? current.live
        : null;
    final block = competition.liveJson;
    final live =
        competition.isParticipantView &&
            block != null &&
            (_channel?.acceptSnapshot(block) ?? false)
        ? ParticipantLiveSnapshot.fromJson(block)
        : (competition.isParticipantView ? previousLive : null);
    emit(
      ParticipantCompetitionLoaded(
        competition: competition,
        attachments: attachments,
        attachmentsError: attachmentsError,
        live: live,
        award: awardView,
      ),
    );
  }

  Future<(List<Attachment>, ApiException?)> _documents(
    Competition competition,
  ) async {
    if (competition.isInviteeView) {
      return (competition.invitationDocuments, null);
    }
    try {
      return (await _attachments.list(competitionId), null);
    } on ApiException catch (error) {
      return (const <Attachment>[], error);
    }
  }

  /// The winner's message comes only from `GET …/award` (M29).
  Future<ParticipantAwardView?> _award(Competition competition) async {
    if (!competition.isParticipantView ||
        competition.status != CompetitionStatus.awarded) {
      return null;
    }
    try {
      return await _competitions.participantAward(competitionId);
    } on ApiException {
      return null;
    }
  }

  /// Acquires the channel for the current role; re-acquires after a join
  /// (invitee → participant).
  void _follow(Competition competition) {
    final role = competition.viewerRole;
    if (_channel != null && _channelRole == role) return;
    final previous = _channel;
    final previousSubscriptions = [..._subscriptions];
    _subscriptions.clear();
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: role,
      organizationId: organizationId,
    );
    _channel = channel;
    _channelRole = role;
    _subscriptions
      ..add(channel.competitionUpdates.listen((_) => unawaited(refresh())))
      ..add(channel.resyncRequests.listen((_) => unawaited(refresh())))
      ..add(channel.liveSnapshots.listen(_onSnapshot));
    unawaited(_releaseOld(previous, previousSubscriptions));
  }

  Future<void> _releaseOld(
    CompetitionChannel? channel,
    List<StreamSubscription<Object?>> subscriptions,
  ) async {
    for (final subscription in subscriptions) {
      await subscription.cancel();
    }
    await channel?.release();
  }

  void _onSnapshot(Json json) {
    final current = state;
    if (current is! ParticipantCompetitionLoaded ||
        !current.competition.isParticipantView) {
      return;
    }
    final snapshot = ParticipantLiveSnapshot.fromJson(json);
    emit(current.copyWith(live: snapshot));
    // A new status or result changes the permissions: refetch.
    if (snapshot.status != current.competition.status ||
        snapshot.lastChange.kind.requiresCompetitionRefetch) {
      unawaited(refresh());
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
