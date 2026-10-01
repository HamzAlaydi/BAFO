import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class MyOffersState extends Equatable {
  const MyOffersState();

  @override
  List<Object?> get props => [];
}

final class MyOffersInitial extends MyOffersState {
  const MyOffersInitial();
}

final class MyOffersLoading extends MyOffersState {
  const MyOffersLoading();
}

final class MyOffersLoaded extends MyOffersState {
  const MyOffersLoaded({required this.offers, this.refreshing = false});

  /// Newest first (API.md §1.6).
  final List<MyOffer> offers;
  final bool refreshing;

  @override
  List<Object?> get props => [offers, refreshing];
}

/// 404, or 403 `not_a_participant`: the page is not the viewer's.
final class MyOffersUnavailable extends MyOffersState {
  const MyOffersUnavailable({required this.notFound});

  final bool notFound;

  @override
  List<Object?> get props => [notFound];
}

final class MyOffersFailure extends MyOffersState {
  const MyOffersFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// M28: the organisation's own offers (`GET …/my-offers`), refreshed when a
/// participant snapshot says an offer was accepted or voided.
class MyOffersCubit extends Cubit<MyOffersState> {
  MyOffersCubit({
    required this._live,
    required this._hub,
    required this.competitionId,
    this.organizationId,
  }) : super(const MyOffersInitial());

  final LiveRepository _live;
  final CompetitionChannelHub _hub;
  final String competitionId;
  final String? organizationId;

  CompetitionChannel? _channel;
  final List<StreamSubscription<Object?>> _subscriptions = [];

  Future<void> load() async {
    emit(const MyOffersLoading());
    await _fetch();
    if (state is MyOffersLoaded) _follow();
  }

  Future<void> refresh() async {
    final current = state;
    if (current is MyOffersLoaded) {
      emit(MyOffersLoaded(offers: current.offers, refreshing: true));
    }
    await _fetch();
  }

  Future<void> _fetch() async {
    try {
      final offers = await _live.myOffers(competitionId);
      if (!isClosed) emit(MyOffersLoaded(offers: offers));
    } on ApiException catch (error) {
      if (isClosed) return;
      final current = state;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const MyOffersUnavailable(notFound: true));
      } else if (error.code == 'not_a_participant') {
        emit(const MyOffersUnavailable(notFound: false));
      } else if (current is MyOffersLoaded) {
        emit(MyOffersLoaded(offers: current.offers));
      } else {
        emit(MyOffersFailure(error));
      }
    }
  }

  /// The participant channel (the list is only reachable by participants).
  void _follow() {
    if (_channel != null || organizationId == null) return;
    final channel = _hub.acquire(
      competitionId: competitionId,
      role: ViewerRole.participant,
      organizationId: organizationId,
    );
    _channel = channel;
    _subscriptions
      ..add(
        channel.liveSnapshots.listen((json) {
          final kind = LastChange.fromJson(
            json['last_change'] is Map<String, dynamic>
                ? json['last_change'] as Map<String, dynamic>
                : const {},
          ).kind;
          if (kind == LastChangeKind.offer || kind == LastChangeKind.voided) {
            unawaited(refresh());
          }
        }),
      )
      ..add(channel.resyncRequests.listen((_) => unawaited(refresh())));
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
