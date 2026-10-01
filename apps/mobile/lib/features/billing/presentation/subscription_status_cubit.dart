import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// What M58 shows: plan, source, status, dates and seats. Never a price.
final class PlanStatus extends Equatable {
  const PlanStatus({
    this.plan,
    this.source,
    this.status,
    this.endsAt,
    this.daysLeft,
    this.totalDays,
    this.seatsUsed = 0,
    this.seatsTotal = 0,
    this.upcomingPlan,
    this.upcomingStartsAt,
  });

  /// Null: no current subscription.
  final PlanRef? plan;
  final SubscriptionSource? source;
  final SubscriptionStatus? status;
  final DateTime? endsAt;
  final int? daysLeft;
  final int? totalDays;
  final int seatsUsed;
  final int seatsTotal;
  final PlanRef? upcomingPlan;
  final DateTime? upcomingStartsAt;

  bool get hasPlan => plan != null;

  @override
  List<Object?> get props => [
    plan,
    source,
    status,
    endsAt,
    daysLeft,
    totalDays,
    seatsUsed,
    seatsTotal,
    upcomingPlan,
    upcomingStartsAt,
  ];
}

sealed class SubscriptionStatusState extends Equatable {
  const SubscriptionStatusState();

  @override
  List<Object?> get props => [];
}

final class SubscriptionStatusLoading extends SubscriptionStatusState {
  const SubscriptionStatusLoading();
}

final class SubscriptionStatusLoaded extends SubscriptionStatusState {
  const SubscriptionStatusLoaded(this.status);

  final PlanStatus status;

  @override
  List<Object?> get props => [status];
}

final class SubscriptionStatusFailure extends SubscriptionStatusState {
  const SubscriptionStatusFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// M58 (entitlement only, SCREENS.md §3.2): `GET /billing/subscription`
/// with `billing.view`, otherwise `me.subscription`.
class SubscriptionStatusCubit extends Cubit<SubscriptionStatusState> {
  SubscriptionStatusCubit({required this._billing, required this._session})
    : super(const SubscriptionStatusLoading());

  final BillingRepository _billing;
  final SessionCubit _session;

  Future<void> load() async {
    emit(const SubscriptionStatusLoading());
    final me = _session.state.me;
    if (me == null || !me.can(Permissions.billingView)) {
      emit(SubscriptionStatusLoaded(_fromMe(me)));
      return;
    }
    try {
      final overview = await _billing.subscription();
      final current = overview.current;
      final upcoming = overview.upcoming;
      emit(
        SubscriptionStatusLoaded(
          PlanStatus(
            plan: current?.plan,
            source: current?.source,
            status: current?.status,
            endsAt: current?.endsAt,
            daysLeft: current?.daysLeft,
            totalDays: current?.totalDays,
            seatsUsed: overview.seatsUsed,
            seatsTotal: overview.seatsTotal,
            upcomingPlan: upcoming?.plan,
            upcomingStartsAt: upcoming?.startsAt,
          ),
        ),
      );
    } on ApiException catch (error) {
      emit(SubscriptionStatusFailure(error));
    }
  }

  static PlanStatus _fromMe(Me? me) {
    final subscription = me?.subscription;
    return PlanStatus(
      plan: subscription?.plan,
      source: subscription?.source,
      status: subscription?.status,
      endsAt: subscription?.endsAt,
      daysLeft: subscription?.daysLeft,
      totalDays: subscription?.totalDays,
      seatsUsed: me?.entitlements.seatsUsed ?? 0,
      seatsTotal: me?.entitlements.seatsTotal ?? 0,
    );
  }
}
