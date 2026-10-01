import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/presentation/subscription_status_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M58 `/billing`: the organisation's plan as it is. The app sells nothing
/// (CD6): no plan list, price, link, URL or purchase button on either
/// platform; only «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.».
class PlanStatusScreen extends StatelessWidget {
  const PlanStatusScreen({this.highlightInvoices = false, super.key});

  /// Opened from an `invoice.issued` notification: invoices are on the web.
  final bool highlightInvoices;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => SubscriptionStatusCubit(
      billing: context.read<BillingRepository>(),
      session: context.read<SessionCubit>(),
    )..load(),
    child: _PlanStatusView(highlightInvoices: highlightInvoices),
  );
}

class _PlanStatusView extends StatelessWidget {
  const _PlanStatusView({required this.highlightInvoices});

  final bool highlightInvoices;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.billingStatusTitle),
      body: BlocBuilder<SubscriptionStatusCubit, SubscriptionStatusState>(
        builder: (context, state) => switch (state) {
          SubscriptionStatusLoading() => const LoadingSkeletonList(itemCount: 2),
          SubscriptionStatusFailure(:final error) => ErrorState(
            error: error,
            onRetry: context.read<SubscriptionStatusCubit>().load,
          ),
          SubscriptionStatusLoaded(:final status) => ListView(
            padding: BafoSpacing.pagePadding,
            children: [
              if (highlightInvoices) ...[
                InfoNotice(
                  message: l10n.billingInvoicesOnWeb,
                  icon: Icons.receipt_long_outlined,
                ),
                const SizedBox(height: BafoSpacing.md),
              ],
              if (status.hasPlan)
                _PlanCard(status: status)
              else
                InfoNotice(
                  tone: StatusTone.warning,
                  icon: Icons.workspace_premium_outlined,
                  message: l10n.billingStatusNoPlan,
                ),
              const SizedBox(height: BafoSpacing.md),
              InfoNotice(message: l10n.billingManagedOnWeb),
              if (!highlightInvoices) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  tone: StatusTone.neutral,
                  icon: Icons.receipt_long_outlined,
                  message: l10n.billingInvoicesOnWeb,
                ),
              ],
            ],
          ),
        },
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.status});

  final PlanStatus status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final language = context.languageCode;
    final daysLeft = status.daysLeft;
    final totalDays = status.totalDays;
    final endsAt = status.endsAt;
    final upcoming = status.upcomingPlan;
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          KeyValueList(
            items: [
              KeyValue(l10n.billingStatusPlan, status.plan!.name),
              if (status.source != null)
                KeyValue(l10n.billingStatusSource, _source(l10n, status.source!)),
              if (status.status != null)
                KeyValue(l10n.billingStatusState, _status(l10n, status.status!)),
              if (endsAt != null)
                KeyValue(
                  l10n.billingStatusEnds,
                  BafoDateFormat.longDate(endsAt, language),
                ),
              KeyValue(
                l10n.billingStatusSeats,
                l10n.billingStatusSeatsValue(status.seatsUsed, status.seatsTotal),
              ),
              if (upcoming != null)
                KeyValue(l10n.billingStatusUpcoming, upcoming.name),
            ],
          ),
          if (daysLeft != null && totalDays != null && totalDays > 0) ...[
            const SizedBox(height: BafoSpacing.lg),
            Text(
              l10n.billingStatusDaysLeft(daysLeft),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: BafoSpacing.xs),
            MeterBar(
              value: daysLeft,
              max: totalDays,
              semanticsLabel: l10n.billingStatusDaysLeft(daysLeft),
            ),
          ],
        ],
      ),
    );
  }

  static String _source(AppLocalizations l10n, SubscriptionSource source) =>
      switch (source) {
        SubscriptionSource.paid => l10n.billingSourcePaid,
        SubscriptionSource.trial => l10n.billingSourceTrial,
        SubscriptionSource.grant => l10n.billingSourceGrant,
        SubscriptionSource.unknown => l10n.billingSubscriptionUnknown,
      };

  static String _status(AppLocalizations l10n, SubscriptionStatus status) =>
      switch (status) {
        SubscriptionStatus.active => l10n.billingSubscriptionActive,
        SubscriptionStatus.pendingPayment =>
          l10n.billingSubscriptionPendingPayment,
        SubscriptionStatus.expired => l10n.billingSubscriptionExpired,
        SubscriptionStatus.superseded => l10n.billingSubscriptionSuperseded,
        SubscriptionStatus.cancelled => l10n.billingSubscriptionCancelled,
        SubscriptionStatus.unknown => l10n.billingSubscriptionUnknown,
      };
}
