import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:equatable/equatable.dart';

/// Issuer stat tiles of `GET /home`.
final class IssuerStats extends Equatable {
  const IssuerStats({
    this.activeCompetitions = 0,
    this.draftCompetitions = 0,
    this.liveNow = 0,
    this.awaitingAward = 0,
    this.offersReceived30d = 0,
  });

  factory IssuerStats.fromJson(Json json) => IssuerStats(
    activeCompetitions: json.intOrNull('active_competitions') ?? 0,
    draftCompetitions: json.intOrNull('draft_competitions') ?? 0,
    liveNow: json.intOrNull('live_now') ?? 0,
    awaitingAward: json.intOrNull('awaiting_award') ?? 0,
    offersReceived30d: json.intOrNull('offers_received_30d') ?? 0,
  );

  final int activeCompetitions;
  final int draftCompetitions;
  final int liveNow;
  final int awaitingAward;
  final int offersReceived30d;

  @override
  List<Object?> get props => [
    activeCompetitions,
    draftCompetitions,
    liveNow,
    awaitingAward,
    offersReceived30d,
  ];
}

/// Participant stat tiles of `GET /home`.
final class ParticipantStats extends Equatable {
  const ParticipantStats({
    this.pendingInvitations = 0,
    this.activeParticipations = 0,
    this.offersSubmitted30d = 0,
    this.awardsWon = 0,
  });

  factory ParticipantStats.fromJson(Json json) => ParticipantStats(
    pendingInvitations: json.intOrNull('pending_invitations') ?? 0,
    activeParticipations: json.intOrNull('active_participations') ?? 0,
    offersSubmitted30d: json.intOrNull('offers_submitted_30d') ?? 0,
    awardsWon: json.intOrNull('awards_won') ?? 0,
  );

  final int pendingInvitations;
  final int activeParticipations;
  final int offersSubmitted30d;
  final int awardsWon;

  @override
  List<Object?> get props => [
    pendingInvitations,
    activeParticipations,
    offersSubmitted30d,
    awardsWon,
  ];
}

/// Home alert codes (API.md §2.13). Mobile shows them without purchase
/// actions (SCREENS.md §3.2).
enum HomeAlertCode implements WireEnum {
  subscriptionExpiring('subscription_expiring'),
  subscriptionExpired('subscription_expired'),
  planRequired('plan_required'),
  trialAvailable('trial_available'),
  billingProfileIncomplete('billing_profile_incomplete'),
  unknown('unknown');

  const HomeAlertCode(this.wire);

  @override
  final String wire;

  static HomeAlertCode parse(Object? raw) => parseWire(values, raw, unknown);
}

final class HomeAlert extends Equatable {
  const HomeAlert({required this.code, this.params = const {}});

  factory HomeAlert.fromJson(Json json) => HomeAlert(
    code: HomeAlertCode.parse(json['code']),
    params: json.looseMap('params'),
  );

  final HomeAlertCode code;

  /// e.g. `{days_left: 3}`. The API sends `[]` when empty.
  final Map<String, Object?> params;

  int? get daysLeft {
    final value = params['days_left'];
    return value is int ? value : null;
  }

  @override
  List<Object?> get props => [code, params];
}

/// A recent audit entry of the organisation. Render it with
/// `home.activity.<action with . → _>` (ARB `homeActivity…`).
final class Activity extends Equatable {
  const Activity({
    required this.id,
    required this.action,
    required this.occurredAt,
    this.actorName,
    this.subjectType,
    this.subjectId,
    this.subjectTitle,
  });

  factory Activity.fromJson(Json json) {
    final actor = json.objOrNull('actor') ?? const {};
    final subject = json.objOrNull('subject') ?? const {};
    return Activity(
      id: json.str('id'),
      action: json.str('action'),
      occurredAt: json.date('occurred_at'),
      actorName: actor.strOrNull('name'),
      subjectType: subject.strOrNull('type'),
      subjectId: subject.strOrNull('id'),
      subjectTitle: subject.strOrNull('title'),
    );
  }

  final String id;

  /// e.g. `competition.published`.
  final String action;
  final DateTime occurredAt;
  final String? actorName;

  /// `competition`, `award`, `membership`, `subscription`, ...
  final String? subjectType;
  final String? subjectId;
  final String? subjectTitle;

  @override
  List<Object?> get props => [
    id,
    action,
    occurredAt,
    actorName,
    subjectType,
    subjectId,
    subjectTitle,
  ];
}

/// `GET /home` (API.md §2.13).
final class Home extends Equatable {
  const Home({
    this.issuer = const IssuerStats(),
    this.participant = const ParticipantStats(),
    this.teamMembers = 0,
    this.seatsTotal = 0,
    this.subscription,
    this.alerts = const [],
    this.activities = const [],
  });

  factory Home.fromJson(Json json) {
    final team = json.objOrNull('team') ?? const {};
    return Home(
      issuer: json.parse('issuer', IssuerStats.fromJson) ?? const IssuerStats(),
      participant:
          json.parse('participant', ParticipantStats.fromJson) ??
          const ParticipantStats(),
      teamMembers: team.intOrNull('members') ?? 0,
      seatsTotal: team.intOrNull('seats_total') ?? 0,
      subscription: json.parse('subscription', SubscriptionSummary.fromJson),
      alerts: json.list('alerts', HomeAlert.fromJson),
      activities: json.list('activities', Activity.fromJson),
    );
  }

  final IssuerStats issuer;
  final ParticipantStats participant;
  final int teamMembers;
  final int seatsTotal;

  /// Null when there is no current subscription.
  final SubscriptionSummary? subscription;
  final List<HomeAlert> alerts;
  final List<Activity> activities;

  @override
  List<Object?> get props => [
    issuer,
    participant,
    teamMembers,
    seatsTotal,
    subscription,
    alerts,
    activities,
  ];
}
