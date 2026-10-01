import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/data/issuer_report_repository.dart';
import 'package:bafo/features/issuer/presentation/award/award_cubits.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M48 (`/competitions/:id/award`), read-only: final standings, the award
/// when there is one, the not-awarded reason, and the result PDF. The
/// decision itself (award, BAFO, close without award, revoke) is taken on
/// the web (CD5).
class AwardScreen extends StatelessWidget {
  const AwardScreen({required this.competitionId, super.key});

  final String competitionId;

  static IssuerReportRepository _reports(BuildContext context) {
    try {
      return context.read<IssuerReportRepository>();
    } on ProviderNotFoundException {
      return ApiIssuerReportRepository(context.read<ApiClient>());
    }
  }

  @override
  Widget build(BuildContext context) => MultiBlocProvider(
    providers: [
      BlocProvider(
        create: (context) => AwardCubit(
          competitions: context.read<CompetitionsRepository>(),
          live: context.read<LiveRepository>(),
          hub: context.read<CompetitionChannelHub>(),
          competitionId: competitionId,
        )..load(),
      ),
      BlocProvider(
        create: (context) => ResultReportCubit(
          reports: _reports(context),
          downloads: context.read<FileDownloadService>(),
          competitionId: competitionId,
        ),
      ),
    ],
    child: const _AwardView(),
  );
}

class _AwardView extends StatelessWidget {
  const _AwardView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<AwardCubit, AwardState>(
      listenWhen: (_, current) => current is AwardUnavailable,
      listener: (context, state) {
        if (state is AwardUnavailable) {
          leaveToDetail(context, state.competition.id);
        }
      },
      builder: (context, state) {
        final cubit = context.read<AwardCubit>();
        return Scaffold(
          appBar: BafoAppBar(title: l10n.issuerAwardTitle),
          body: switch (state) {
            AwardLoading() || AwardUnavailable() => const LoadingSkeletonList(),
            AwardNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            AwardFailure(:final error) => ErrorState(
              error: error,
              onRetry: cubit.load,
            ),
            AwardLoaded() => RefreshIndicator(
              onRefresh: cubit.refresh,
              child: _Loaded(state: state),
            ),
          },
        );
      },
    );
  }
}

class _Loaded extends StatelessWidget {
  const _Loaded({required this.state});

  final AwardLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final competition = state.competition;
    final award = state.award;
    final status = competition.status;
    final notAwarded = competition.notAwarded;
    final bafoRound = competition.bafoRound;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: BafoSpacing.pagePadding,
      children: [
        IssuerCompetitionHeader(competition: competition),
        const SizedBox(height: BafoSpacing.lg),
        if (status == CompetitionStatus.bafoRound && bafoRound != null) ...[
          BafoCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (bafoRound.cutoffAt != null)
                  CompetitionCountdown(
                    target: CountdownTarget.bafoCutoff,
                    deadline: bafoRound.cutoffAt!,
                    onElapsed: context.read<AwardCubit>().refresh,
                  ),
                if (bafoRound.shortlistCount != null) ...[
                  const SizedBox(height: BafoSpacing.sm),
                  Text(
                    l10n.issuerLiveBafoProgress(
                      bafoRound.submittedCount ?? 0,
                      bafoRound.shortlistCount!,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (notAwarded != null) ...[
          InfoNotice(
            tone: StatusTone.neutral,
            icon: Icons.do_not_disturb_on_outlined,
            message: [
              l10n.competitionsDetailNotAwarded(notAwarded.reason.name),
              if ((notAwarded.note ?? '').trim().isNotEmpty)
                notAwarded.note!.trim(),
            ].join('\n'),
          ),
          const SizedBox(height: BafoSpacing.md),
        ],
        if (award != null)
          _AwardCard(award: award, competition: competition)
        else if (status == CompetitionStatus.closed ||
            status == CompetitionStatus.bafoRound)
          WebOnlyNotice(message: l10n.issuerAwardOnWeb),
        if ((award != null && award.status == 'revoked') ||
            competition.permissions.canRevokeAward) ...[
          const SizedBox(height: BafoSpacing.md),
          WebOnlyNotice(message: l10n.issuerAwardChangesOnWeb),
        ],
        const SizedBox(height: BafoSpacing.lg),
        const _ReportSection(),
        SectionHeader(title: l10n.issuerAwardStandings),
        if (state.standingsError != null)
          ErrorState(
            error: state.standingsError,
            onRetry: context.read<AwardCubit>().refresh,
          )
        else if (state.standings.isEmpty)
          Text(l10n.issuerLiveNoParticipants, style: theme.textTheme.bodyMedium)
        else
          for (final row in state.standings)
            Padding(
              padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
              child: StandingTile(
                row: row,
                currency: competition.currency,
                awarded:
                    award?.status == 'issued' &&
                    award?.participantId == row.participantId,
              ),
            ),
        const SizedBox(height: BafoSpacing.xxl),
      ],
    );
  }
}

class _AwardCard extends StatelessWidget {
  const _AwardCard({required this.award, required this.competition});

  final Award award;
  final Competition competition;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    final organization = award.organization;
    final direction = competition.direction.wire;
    String yesNo(bool? value) => value == null
        ? l10n.issuerNoValue
        : value
        ? l10n.issuerYes
        : l10n.issuerNo;
    final revoked = award.status == 'revoked';
    final justification = [
      award.justificationReason?.name,
      award.justificationText,
    ].whereType<String>().where((text) => text.trim().isNotEmpty).join(' · ');
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                revoked ? Icons.undo_rounded : Icons.emoji_events_outlined,
                color: revoked
                    ? theme.colorScheme.onSurfaceVariant
                    : theme.colorScheme.primary,
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    revoked
                        ? l10n.issuerAwardRevokedTitle
                        : l10n.issuerAwardWinner,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.md),
          Text(
            participantLabel(l10n, award.aliasNo, organization.name),
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: BafoSpacing.xs),
          AmountText(
            award.amountMinor,
            currency: award.currency,
            style: theme.textTheme.headlineSmall,
          ),
          Text(
            l10n.commonPricesExcludeVat,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          if (revoked) ...[
            const SizedBox(height: BafoSpacing.md),
            InfoNotice(
              tone: StatusTone.neutral,
              message: l10n.issuerAwardRevoked(
                award.revokedAt == null
                    ? l10n.issuerNoValue
                    : BafoDateFormat.dateTime(award.revokedAt!, language),
                award.revokeReason ?? l10n.issuerNoValue,
              ),
            ),
          ],
          const SizedBox(height: BafoSpacing.md),
          KeyValueList(
            items: [
              if (organization.crNumber != null)
                KeyValue(l10n.issuerAwardCr, organization.crNumber!, ltr: true),
              if (organization.vatNumber != null)
                KeyValue(
                  l10n.issuerAwardVat,
                  organization.vatNumber!,
                  ltr: true,
                ),
              KeyValue(l10n.issuerAwardLeading, yesNo(award.isLeadingOffer)),
              if (award.rankAtAward != null)
                KeyValue(
                  l10n.issuerAwardRank,
                  '${award.rankAtAward}',
                  ltr: true,
                ),
              if (award.reserveMet != null)
                KeyValue(
                  l10n.issuerLiveReserveMet(direction),
                  yesNo(award.reserveMet),
                ),
              if (justification.isNotEmpty)
                KeyValue(l10n.issuerAwardJustification, justification),
              if ((award.messageToWinner ?? '').trim().isNotEmpty)
                KeyValue(
                  l10n.issuerAwardMessage,
                  award.messageToWinner!.trim(),
                ),
              if ((award.internalNotes ?? '').trim().isNotEmpty)
                KeyValue(l10n.issuerAwardNotes, award.internalNotes!.trim()),
              if (award.awardedByName != null)
                KeyValue(l10n.issuerAwardBy, award.awardedByName!),
              if (award.awardedAt != null)
                KeyValue(
                  l10n.issuerAwardAt,
                  BafoDateFormat.deadline(award.awardedAt!, language, l10n),
                ),
              if (award.offerAcceptedAt != null)
                KeyValue(
                  l10n.issuerAwardOfferAt,
                  BafoDateFormat.machine(award.offerAcceptedAt!),
                  ltr: true,
                ),
              if (award.erpSyncStatus != null)
                KeyValue(
                  l10n.issuerAwardErpSync,
                  l10n.issuerAwardErpStatus(award.erpSyncStatus!),
                ),
              if (award.ledgerHeadHash != null)
                KeyValue(
                  l10n.issuerAwardLedgerHash,
                  _shortHash(award.ledgerHeadHash!),
                  ltr: true,
                ),
            ],
          ),
        ],
      ),
    );
  }

  /// The ledger hash is long; the first and last characters identify it.
  static String _shortHash(String hash) => hash.length <= 20
      ? hash
      : '${hash.substring(0, 10)}…${hash.substring(hash.length - 8)}';
}

/// A final-standings row (`ParticipantStandingRow`, issuer projection).
class StandingTile extends StatelessWidget {
  const StandingTile({
    required this.row,
    required this.currency,
    this.awarded = false,
    super.key,
  });

  final ParticipantStandingRow row;
  final String currency;
  final bool awarded;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final rank = row.rank;
    final current = row.currentAmountMinor;
    final first = row.firstAmountMinor;
    final change = row.changeRatioBps;
    final organization = row.organization;
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RankText(rank: rank),
          const SizedBox(width: BafoSpacing.xs),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  participantLabel(l10n, row.aliasNo, organization.name),
                  style: theme.textTheme.titleSmall,
                ),
                const SizedBox(height: BafoSpacing.xxs),
                if (current != null)
                  AmountText(
                    current,
                    currency: currency,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  Text(
                    row.submitted
                        ? l10n.issuerLiveSubmitted
                        : l10n.issuerLiveNotSubmitted,
                  ),
                if (first != null)
                  Text(
                    l10n.issuerLiveFirstOffer(
                      ltrIsolate(
                        MoneyFormat.format(
                          Money(first, currency: currency),
                          languageCode: context.languageCode,
                        ),
                      ),
                    ),
                    style: muted,
                  ),
                Text(
                  [
                    l10n.issuerLiveOffersCount(row.offersCount),
                    if (change != null)
                      l10n.issuerAwardChange(
                        ltrIsolate(formatSignedBps(change)),
                      ),
                  ].join(' · '),
                  style: muted,
                ),
                for (final contact in [organization.email, organization.phone])
                  if (contact != null && contact.isNotEmpty)
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Ltr(child: Text(contact, style: muted)),
                    ),
                if (awarded ||
                    row.isLeader == true ||
                    row.bafo.shortlisted) ...[
                  const SizedBox(height: BafoSpacing.xs),
                  Wrap(
                    spacing: BafoSpacing.xs,
                    runSpacing: BafoSpacing.xs,
                    children: [
                      if (awarded)
                        StatusPill(
                          label: l10n.competitionsStatusAwarded,
                          tone: StatusTone.primary,
                          icon: Icons.emoji_events_outlined,
                        ),
                      if (row.isLeader == true)
                        StatusPill(
                          label: l10n.liveBadgeLeading,
                          tone: StatusTone.leading,
                          icon: Icons.check_circle_rounded,
                        ),
                      if (row.bafo.shortlisted)
                        StatusPill(
                          label: row.bafo.submitted
                              ? l10n.issuerLiveBafoSubmitted
                              : l10n.issuerLiveBafoShortlisted,
                          tone: StatusTone.inverse,
                          icon: Icons.workspace_premium_outlined,
                        ),
                      CoverageChip(coverage: row.coverage),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The result PDF in Arabic or English: authenticated download, then the
/// system viewer (from which it can be shared).
class _ReportSection extends StatelessWidget {
  const _ReportSection();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<ResultReportCubit, ResultReportState>(
      listener: (context, state) {
        switch (state) {
          case ResultReportOpened(:final result):
            if (result == FileOpenResult.noAppToOpen) {
              BafoToast.show(context, l10n.commonNoAppToOpen);
            } else if (result == FileOpenResult.failed) {
              BafoToast.error(context, l10n.commonDownloadFailed);
            }
          case ResultReportFailed(:final timedOut, :final error):
            BafoToast.error(
              context,
              timedOut
                  ? l10n.issuerReportTimeout
                  : error != null && error.isConnectivity
                  ? errorMessage(l10n, error)
                  : l10n.issuerReportFailed,
            );
          default:
            break;
        }
      },
      builder: (context, state) {
        if (state is ResultReportUnavailable) {
          return InfoNotice(
            message: l10n.errorsReportNotAvailable,
            tone: StatusTone.neutral,
          );
        }
        final cubit = context.read<ResultReportCubit>();
        final working = state is ResultReportWorking ? state : null;
        Widget button(String locale, String label) => Expanded(
          child: BafoButton.outline(
            label: label,
            icon: Icons.picture_as_pdf_outlined,
            loading: working?.locale == locale,
            onPressed: working == null ? () => cubit.open(locale) : null,
          ),
        );
        return BafoCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(
                header: true,
                child: Text(
                  l10n.issuerReportTitle,
                  style: theme.textTheme.titleSmall,
                ),
              ),
              const SizedBox(height: BafoSpacing.xs),
              Text(
                l10n.issuerReportShareHint,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: BafoSpacing.md),
              Row(
                children: [
                  button('ar', l10n.issuerReportArabic),
                  const SizedBox(width: BafoSpacing.sm),
                  button('en', l10n.issuerReportEnglish),
                ],
              ),
              if (working != null) ...[
                const SizedBox(height: BafoSpacing.md),
                Semantics(
                  liveRegion: true,
                  child: Text(
                    working.generating
                        ? l10n.issuerReportGenerating
                        : l10n.commonDownloading,
                    style: theme.textTheme.bodySmall,
                  ),
                ),
                const SizedBox(height: BafoSpacing.xs),
                LinearProgressIndicator(value: working.progress),
              ],
            ],
          ),
        );
      },
    );
  }
}
