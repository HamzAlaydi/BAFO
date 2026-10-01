import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// M29: the participant's result, from the server projection only
/// (ARCHITECTURE.md §7.9): won (with the winning amount when published),
/// not selected, not awarded, or hidden when the competition does not
/// publish results. Cancellation is shown by the page itself.
class ResultPanel extends StatelessWidget {
  const ResultPanel({
    required this.status,
    this.result,
    this.messageToWinner,
    this.currency = Money.sar,
    super.key,
  });

  final CompetitionStatus status;
  final ParticipantResult? result;

  /// `GET …/award` → `message_to_winner` (the winner only).
  final String? messageToWinner;
  final String currency;

  /// Whether there is anything to show for [status].
  static bool appliesTo(CompetitionStatus status) =>
      status == CompetitionStatus.awarded ||
      status == CompetitionStatus.notAwarded;

  @override
  Widget build(BuildContext context) {
    if (!appliesTo(status)) return const SizedBox.shrink();
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final outcome = result?.outcome;
    final winning = result?.winningAmountMinor;
    final (message, tone, icon) = switch (outcome) {
      AwardOutcome.won => (
        l10n.competitionsParticipantResultWon,
        StatusTone.leading,
        Icons.emoji_events_outlined,
      ),
      AwardOutcome.notSelected => (
        l10n.competitionsParticipantResultNotSelected,
        StatusTone.neutral,
        Icons.remove_circle_outline_rounded,
      ),
      AwardOutcome.notAwarded => (
        l10n.competitionsParticipantResultNotAwarded,
        StatusTone.neutral,
        Icons.do_not_disturb_on_outlined,
      ),
      AwardOutcome.unknown || null => (
        l10n.competitionsParticipantResultHidden,
        StatusTone.neutral,
        Icons.visibility_off_outlined,
      ),
    };
    final note = messageToWinner?.trim();
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    l10n.competitionsParticipantResultTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ),
              if (outcome != null && outcome != AwardOutcome.unknown)
                ResultChip(outcome: outcome),
            ],
          ),
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(message: message, tone: tone, icon: icon),
          if (winning != null) ...[
            const SizedBox(height: BafoSpacing.md),
            Text(
              l10n.competitionsParticipantResultWinningAmount(
                ltrIsolate(
                  MoneyFormat.format(
                    Money(winning, currency: currency),
                    languageCode: context.languageCode,
                  ),
                ),
              ),
              style: theme.textTheme.bodyLarge?.copyWith(
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
          if (note != null && note.isNotEmpty) ...[
            const SizedBox(height: BafoSpacing.md),
            Text(
              l10n.competitionsParticipantResultMessage,
              style: theme.textTheme.labelLarge?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: BafoSpacing.xs),
            Text(note, style: theme.textTheme.bodyMedium),
          ],
        ],
      ),
    );
  }
}

/// M30: the server's rules summary lines in a sheet.
Future<void> showRulesSheet(
  BuildContext context,
  List<String> lines,
) => showBafoBottomSheet<void>(
  context,
  title: context.l10n.rulesSummaryTitle,
  builder: (sheetContext) {
    final theme = Theme.of(sheetContext);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final line in lines)
          Padding(
            padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsetsDirectional.only(
                    top: 7,
                    end: BafoSpacing.sm,
                  ),
                  child: Icon(
                    Icons.circle,
                    size: 6,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                Expanded(child: Text(line, style: theme.textTheme.bodyMedium)),
              ],
            ),
          ),
      ],
    );
  },
);

/// An amount as an LTR island inside a translated sentence.
String formatAmountIsolated(
  BuildContext context,
  int amountMinor, {
  String currency = Money.sar,
}) => ltrIsolate(
  MoneyFormat.format(
    Money(amountMinor, currency: currency),
    languageCode: context.languageCode,
  ),
);
