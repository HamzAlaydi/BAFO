import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/issuer/presentation/offers_log/offers_log_bloc.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M47 (`/competitions/:id/offers`): every accepted offer by `seq`, with
/// the time in milliseconds (Riyadh), the participant, the amount («مغلق»
/// while sealed), the stage and the channel.
class OffersLogScreen extends StatelessWidget {
  const OffersLogScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => OffersLogBloc(
      competitions: context.read<CompetitionsRepository>(),
      live: context.read<LiveRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      competitionId: competitionId,
    )..add(const OffersLogStarted()),
    child: const _LogView(),
  );
}

class _LogView extends StatelessWidget {
  const _LogView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<OffersLogBloc, OffersLogState>(
      listenWhen: (_, current) => current is OffersLogNotIssuer,
      listener: (context, state) {
        if (state is OffersLogNotIssuer) {
          leaveToDetail(context, state.competition.id);
        }
      },
      builder: (context, state) {
        final bloc = context.read<OffersLogBloc>();
        return Scaffold(
          appBar: BafoAppBar(title: l10n.issuerOffersTitle),
          body: switch (state) {
            OffersLogLoading() ||
            OffersLogNotIssuer() => const LoadingSkeletonList(),
            OffersLogNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            OffersLogFailure(:final error) =>
              error.statusCode == 403
                  ? const ForbiddenState()
                  : ErrorState(
                      error: error,
                      onRetry: () => bloc.add(const OffersLogStarted()),
                    ),
            OffersLogLoaded() => RefreshIndicator(
              onRefresh: () async =>
                  bloc.add(const OffersLogStarted(silent: true)),
              child: state.entries.isEmpty
                  ? ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        EmptyState(
                          icon: Icons.receipt_long_outlined,
                          title: l10n.issuerOffersEmptyTitle,
                          message: l10n.issuerOffersEmptyMessage,
                        ),
                      ],
                    )
                  : _Entries(state: state),
            ),
          },
        );
      },
    );
  }
}

class _Entries extends StatelessWidget {
  const _Entries({required this.state});

  final OffersLogLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bloc = context.read<OffersLogBloc>();
    final footer = state.hasMore || state.loadMoreError != null;
    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification.metrics.extentAfter < 400) {
          bloc.add(const OffersLogNextPageRequested());
        }
        return false;
      },
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: BafoSpacing.pagePadding,
        itemCount: state.entries.length + 1 + (footer ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: BafoSpacing.sm),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Text(
              l10n.commonPricesExcludeVat,
              style: Theme.of(context).textTheme.bodySmall,
            );
          }
          final position = index - 1;
          if (position >= state.entries.length) {
            return state.loadMoreError != null
                ? Center(
                    child: BafoButton.text(
                      label: l10n.commonActionsRetry,
                      onPressed: () =>
                          bloc.add(const OffersLogNextPageRequested()),
                    ),
                  )
                : const Padding(
                    padding: EdgeInsets.all(BafoSpacing.lg),
                    child: Center(child: CircularProgressIndicator()),
                  );
          }
          return OfferLogTile(
            entry: state.entries[position],
            currency: state.competition.currency,
          );
        },
      ),
    );
  }
}

/// One accepted offer (issuer projection; sealed amounts are null).
class OfferLogTile extends StatelessWidget {
  const OfferLogTile({required this.entry, required this.currency, super.key});

  final OfferLogEntry entry;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final amount = entry.amountMinor;
    final channel = entry.channel;
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Ltr(
                child: Text(
                  '#${entry.seq}',
                  style: theme.textTheme.labelLarge,
                  semanticsLabel: l10n.issuerOfferSeq(entry.seq),
                ),
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Text(
                  participantLabel(l10n, entry.aliasNo, entry.organizationName),
                  style: theme.textTheme.titleSmall,
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.xs),
          Row(
            children: [
              Expanded(
                child: amount == null
                    ? Row(
                        children: [
                          Icon(
                            Icons.lock_outline_rounded,
                            size: 16,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                          const SizedBox(width: BafoSpacing.xs),
                          Text(l10n.issuerOfferSealed),
                        ],
                      )
                    : Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: AmountText(
                          amount,
                          currency: currency,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontWeight: FontWeight.w600,
                            decoration: entry.voided
                                ? TextDecoration.lineThrough
                                : null,
                          ),
                        ),
                      ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.xs),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: Ltr(
              child: Text(
                BafoDateFormat.machine(entry.acceptedAt),
                style: muted,
              ),
            ),
          ),
          const SizedBox(height: BafoSpacing.xs),
          Wrap(
            spacing: BafoSpacing.xs,
            runSpacing: BafoSpacing.xs,
            children: [
              StatusPill(label: l10n.issuerOfferStage(entry.stage.wire)),
              if (channel != null)
                StatusPill(
                  label: l10n.issuerOfferChannel(channel),
                  icon: channel == 'api'
                      ? Icons.integration_instructions_outlined
                      : channel == 'web'
                      ? Icons.language_rounded
                      : Icons.smartphone_rounded,
                ),
              if (entry.voided)
                // Neutral: red is for destructive actions and errors (S2).
                StatusPill(
                  label: l10n.issuerOfferVoided,
                  icon: Icons.block_rounded,
                ),
            ],
          ),
        ],
      ),
    );
  }
}
