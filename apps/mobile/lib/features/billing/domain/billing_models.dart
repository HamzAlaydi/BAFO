import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:equatable/equatable.dart';

/// `Subscription` (API.md §2.10), read-only on mobile. Mobile never shows
/// prices, links or purchase actions (SCREENS.md §3.2), so the amounts of a
/// paid subscription are not modelled here.
final class Subscription extends Equatable {
  const Subscription({
    required this.id,
    required this.plan,
    required this.source,
    required this.status,
    this.interval,
    this.seats,
    this.startsAt,
    this.endsAt,
    this.daysLeft,
    this.totalDays,
  });

  factory Subscription.fromJson(Json json) => Subscription(
    id: json.str('id'),
    plan: PlanRef.fromJson(json.obj('plan')),
    source: SubscriptionSource.parse(json['source']),
    interval: json.strOrNull('interval'),
    seats: json.intOrNull('seats'),
    status: SubscriptionStatus.parse(json['status']),
    startsAt: json.dateOrNull('starts_at'),
    endsAt: json.dateOrNull('ends_at'),
    daysLeft: json.intOrNull('days_left'),
    totalDays: json.intOrNull('total_days'),
  );

  final String id;
  final PlanRef plan;
  final SubscriptionSource source;

  /// `monthly` or `annual`; null for a trial or grant.
  final String? interval;
  final int? seats;
  final SubscriptionStatus status;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final int? daysLeft;
  final int? totalDays;

  @override
  List<Object?> get props => [
    id,
    plan,
    source,
    interval,
    seats,
    status,
    startsAt,
    endsAt,
    daysLeft,
    totalDays,
  ];
}

/// `GET /billing/subscription` (`billing.view`).
final class SubscriptionOverview extends Equatable {
  const SubscriptionOverview({
    this.current,
    this.upcoming,
    this.history = const [],
    this.trialAvailable = false,
    this.seatsUsed = 0,
    this.seatsTotal = 0,
  });

  factory SubscriptionOverview.fromJson(Json json) => SubscriptionOverview(
    current: json.parse('current', Subscription.fromJson),
    upcoming: json.parse('upcoming', Subscription.fromJson),
    history: json.list('history', Subscription.fromJson),
    trialAvailable: json.flag('trial_available'),
    seatsUsed: json.intOrNull('seats_used') ?? 0,
    seatsTotal: json.intOrNull('seats_total') ?? 0,
  );

  final Subscription? current;
  final Subscription? upcoming;
  final List<Subscription> history;

  /// Informational only: the trial starts on the web.
  final bool trialAvailable;
  final int seatsUsed;
  final int seatsTotal;

  @override
  List<Object?> get props => [
    current,
    upcoming,
    history,
    trialAvailable,
    seatsUsed,
    seatsTotal,
  ];
}

/// An invoice row (API.md §2.10), listed read-only. The PDF is downloaded on
/// the web (`billing.invoices_on_web`), so no amounts or links are modelled.
final class InvoiceSummary extends Equatable {
  const InvoiceSummary({
    required this.id,
    required this.number,
    this.type,
    this.issueDate,
    this.einvoiceStatus,
    this.issuedAt,
  });

  factory InvoiceSummary.fromJson(Json json) => InvoiceSummary(
    id: json.str('id'),
    number: json.str('number'),
    type: json.strOrNull('type'),
    issueDate: json.strOrNull('issue_date'),
    einvoiceStatus: json.strOrNull('einvoice_status'),
    issuedAt: json.dateOrNull('issued_at'),
  );

  final String id;

  /// `BAFO-INV-2026-000042` (an LTR island in Arabic text).
  final String number;

  /// `tax_invoice` or `credit_note`.
  final String? type;

  /// `YYYY-MM-DD`.
  final String? issueDate;

  /// `pending`, `cleared`, `rejected`, ...
  final String? einvoiceStatus;
  final DateTime? issuedAt;

  @override
  List<Object?> get props => [id, number, type, issueDate, einvoiceStatus, issuedAt];
}
