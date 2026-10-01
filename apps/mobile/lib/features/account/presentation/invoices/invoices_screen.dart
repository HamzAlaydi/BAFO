import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/account/presentation/invoices/invoices_cubit.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// The organisation's invoices, read-only (`billing.view`). Mobile sells
/// nothing and shows no amounts, links or PDF buttons (CD6): each row is the
/// number, date, type and e-invoice status, under
/// «الفواتير متاحة في لوحة تحكم بافو على الويب.».
class InvoicesScreen extends StatelessWidget {
  const InvoicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final canView =
        context.read<SessionCubit>().state.me?.can(Permissions.billingView) ??
        false;
    return BlocProvider(
      create: (context) => InvoicesCubit(
        billing: context.read<BillingRepository>(),
        canView: canView,
      )..load(),
      child: const _InvoicesView(),
    );
  }
}

class _InvoicesView extends StatelessWidget {
  const _InvoicesView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<InvoicesCubit>();
    return Scaffold(
      appBar: BafoAppBar(title: l10n.accountInvoicesTitle),
      body: BlocBuilder<InvoicesCubit, InvoicesState>(
        builder: (context, state) => switch (state) {
          InvoicesLoading() => const LoadingSkeletonList(),
          InvoicesForbidden() => const ForbiddenState(),
          InvoicesFailure(:final error) => ErrorState(
            error: error,
            onRetry: cubit.load,
          ),
          InvoicesLoaded() => RefreshIndicator(
            onRefresh: cubit.load,
            child: ListView.builder(
              key: const Key('invoices.list'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsetsDirectional.fromSTEB(
                BafoSpacing.page,
                BafoSpacing.lg,
                BafoSpacing.page,
                BafoSpacing.xxl,
              ),
              itemCount: state.invoices.length + 2,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return Padding(
                    padding: const EdgeInsetsDirectional.only(
                      bottom: BafoSpacing.lg,
                    ),
                    child: InfoNotice(
                      icon: Icons.receipt_long_outlined,
                      message: l10n.billingInvoicesOnWeb,
                    ),
                  );
                }
                if (index == state.invoices.length + 1) {
                  return _Footer(state: state);
                }
                return _InvoiceTile(invoice: state.invoices[index - 1]);
              },
            ),
          ),
        },
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.state});

  final InvoicesLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (state.invoices.isEmpty) {
      return EmptyState(
        icon: Icons.receipt_long_outlined,
        title: l10n.accountInvoicesEmptyTitle,
      );
    }
    if (!state.hasMore) return const SizedBox.shrink();
    final cubit = context.read<InvoicesCubit>();
    if (state.pageError != null) {
      return Center(
        child: BafoButton.text(
          label: l10n.commonActionsRetry,
          icon: Icons.refresh_rounded,
          onPressed: cubit.loadMore,
        ),
      );
    }
    if (!state.loadingMore) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!cubit.isClosed) cubit.loadMore();
      });
    }
    return const Padding(
      padding: EdgeInsetsDirectional.all(BafoSpacing.lg),
      child: Center(
        child: SizedBox.square(
          dimension: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }
}

class _InvoiceTile extends StatelessWidget {
  const _InvoiceTile({required this.invoice});

  final InvoiceSummary invoice;

  static (String, StatusTone)? statusOf(
    AppLocalizations l10n,
    String? status,
  ) => switch (status) {
    'cleared' => (l10n.accountInvoicesStatusCleared, StatusTone.success),
    'reported' => (l10n.accountInvoicesStatusReported, StatusTone.success),
    'pending' => (l10n.accountInvoicesStatusPending, StatusTone.info),
    'rejected' => (l10n.accountInvoicesStatusRejected, StatusTone.danger),
    'failed' => (l10n.accountInvoicesStatusFailed, StatusTone.danger),
    _ => null,
  };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final issued = _issuedOn(context);
    final type = invoice.type == 'credit_note'
        ? l10n.accountInvoicesTypeCredit
        : l10n.accountInvoicesTypeTax;
    final status = statusOf(l10n, invoice.einvoiceStatus);
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
      child: BafoCard(
        key: ValueKey('invoice.${invoice.id}'),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              invoice.type == 'credit_note'
                  ? Icons.undo_rounded
                  : Icons.receipt_long_outlined,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(width: BafoSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Ltr(
                    child: Text(
                      invoice.number,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ),
                  const SizedBox(height: BafoSpacing.xxs),
                  Text(
                    [type, ?issued].join(' · '),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            if (status != null) ...[
              const SizedBox(width: BafoSpacing.sm),
              StatusPill(label: status.$1, tone: status.$2),
            ],
          ],
        ),
      ),
    );
  }

  String? _issuedOn(BuildContext context) {
    final l10n = context.l10n;
    final at = invoice.issuedAt;
    if (at != null) {
      return l10n.accountInvoicesIssued(
        BafoDateFormat.longDate(at, context.languageCode),
      );
    }
    final date = DateTime.tryParse(invoice.issueDate ?? '');
    if (date == null) return null;
    // A calendar date (`YYYY-MM-DD`): noon UTC is the same day in Riyadh.
    return l10n.accountInvoicesIssued(
      BafoDateFormat.longDate(
        DateTime.utc(date.year, date.month, date.day, 12),
        context.languageCode,
      ),
    );
  }
}
