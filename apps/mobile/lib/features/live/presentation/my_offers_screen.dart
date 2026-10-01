import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/domain/offer_rules.dart';
import 'package:bafo/features/live/presentation/my_offers_cubit.dart';
import 'package:bafo/features/live/presentation/widgets/live_widgets.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M28: the organisation's own offers, newest first: sequence, time with
/// milliseconds (Riyadh), amount, stage, the voided badge, and the neutral
/// change against the previous own offer (never coloured by direction).
class MyOffersScreen extends StatelessWidget {
  const MyOffersScreen({required this.competitionId, super.key});

  final String competitionId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => MyOffersCubit(
      live: context.read<LiveRepository>(),
      hub: context.read<CompetitionChannelHub>(),
      competitionId: competitionId,
      organizationId: context.read<SessionCubit>().state.me?.organization.id,
    )..load(),
    child: _MyOffersView(competitionId: competitionId),
  );
}

class _MyOffersView extends StatelessWidget {
  const _MyOffersView({required this.competitionId});

  final String competitionId;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.competitionsParticipantMyOffers),
      body: BlocConsumer<MyOffersCubit, MyOffersState>(
        listener: (context, state) {
          // Not a participant: the overview explains the invitation.
          if (state is MyOffersUnavailable && !state.notFound) {
            context.replace(ParticipantPaths.competition(competitionId));
          }
        },
        builder: (context, state) => switch (state) {
          MyOffersInitial() ||
          MyOffersLoading() ||
          MyOffersUnavailable(notFound: false) => const LoadingSkeletonList(),
          MyOffersUnavailable() => NotFoundState(
            message: l10n.competitionsDetailNotFound,
          ),
          MyOffersFailure(:final error) => ErrorState(
            error: error,
            onRetry: context.read<MyOffersCubit>().load,
          ),
          MyOffersLoaded(:final offers) when offers.isEmpty => RefreshIndicator(
            onRefresh: context.read<MyOffersCubit>().refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: BafoSpacing.xxl),
                EmptyState(
                  icon: Icons.receipt_long_outlined,
                  title: l10n.offersMyEmptyTitle,
                  message: l10n.offersMyEmptyMessage,
                  action: BafoButton(
                    label: l10n.competitionsParticipantLiveRoom,
                    icon: Icons.bolt_rounded,
                    onPressed: () =>
                        context.push(ParticipantPaths.live(competitionId)),
                  ),
                ),
              ],
            ),
          ),
          MyOffersLoaded(:final offers) => RefreshIndicator(
            onRefresh: context.read<MyOffersCubit>().refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: BafoSpacing.pagePadding,
              itemCount: offers.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(height: BafoSpacing.sm),
              itemBuilder: (context, index) => _OfferRow(
                offer: offers[index],
                previous: _previousValid(offers, index),
              ),
            ),
          ),
        },
      ),
    );
  }

  /// The next older offer that was not voided (the list is newest first).
  static MyOffer? _previousValid(List<MyOffer> offers, int index) {
    for (var i = index + 1; i < offers.length; i++) {
      if (!offers[i].voided) return offers[i];
    }
    return null;
  }
}

class _OfferRow extends StatelessWidget {
  const _OfferRow({required this.offer, this.previous});

  final MyOffer offer;
  final MyOffer? previous;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurfaceVariant;
    final amount = MoneyFormat.format(
      Money(offer.amountMinor),
      languageCode: context.languageCode,
    );
    final change = offer.voided
        ? null
        : changeBps(offer.amountMinor, previous?.amountMinor);
    final pct = change == null || change == 0 ? null : formatBps(change);
    final changeLabel = pct == null
        ? null
        : (change! < 0
              ? l10n.offersMyChangeDown(pct)
              : l10n.offersMyChangeUp(pct));
    final amountStyle = BafoTypography.tabular(
      (theme.textTheme.titleMedium ?? const TextStyle()).copyWith(
        decoration: offer.voided ? TextDecoration.lineThrough : null,
      ),
    );
    return MergeSemantics(
      child: BafoCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    l10n.offersMySeq(offer.seq),
                    style: theme.textTheme.labelLarge?.copyWith(color: muted),
                  ),
                ),
                OfferStageChip(stage: offer.stage),
                if (offer.voided) ...[
                  const SizedBox(width: BafoSpacing.xs),
                  StatusPill(
                    label: l10n.offersVoided,
                    icon: Icons.block_rounded,
                  ),
                ],
              ],
            ),
            const SizedBox(height: BafoSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: Ltr(child: Text(amount, style: amountStyle)),
                  ),
                ),
                if (pct != null)
                  Semantics(
                    label: changeLabel,
                    excludeSemantics: true,
                    // Neutral grey; the arrow is numeric and never mirrored.
                    child: Ltr(
                      child: Text(
                        '${change! < 0 ? '↓' : '↑'} $pct',
                        style: BafoTypography.tabular(
                          theme.textTheme.bodySmall?.copyWith(color: muted) ??
                              const TextStyle(),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: BafoSpacing.xs),
            Ltr(
              child: Text(
                BafoDateFormat.machine(offer.acceptedAt),
                style: BafoTypography.tabular(
                  theme.textTheme.bodySmall?.copyWith(color: muted) ??
                      const TextStyle(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
