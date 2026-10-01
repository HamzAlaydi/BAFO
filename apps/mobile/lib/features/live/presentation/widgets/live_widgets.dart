import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/presentation/live_room_bloc.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// A polite live region whose announced text changes at most once every
/// [interval] (S9: standing and price changes are not read on every tick);
/// the visible child updates at once.
class ThrottledLiveRegion extends StatefulWidget {
  const ThrottledLiveRegion({
    required this.label,
    required this.child,
    this.interval = const Duration(seconds: 10),
    super.key,
  });

  final String label;
  final Widget child;
  final Duration interval;

  @override
  State<ThrottledLiveRegion> createState() => _ThrottledLiveRegionState();
}

class _ThrottledLiveRegionState extends State<ThrottledLiveRegion> {
  String? _announced;
  bool _cooling = false;
  Timer? _cooldown;

  @override
  void dispose() {
    _cooldown?.cancel();
    super.dispose();
  }

  String _current() {
    final announced = _announced;
    if (announced == null || (announced != widget.label && !_cooling)) {
      _announced = widget.label;
      _cooling = true;
      _cooldown?.cancel();
      _cooldown = Timer(widget.interval, () {
        _cooling = false;
        if (mounted) setState(() {});
      });
      return widget.label;
    }
    return announced;
  }

  @override
  Widget build(BuildContext context) => Semantics(
    container: true,
    liveRegion: true,
    label: _current(),
    excludeSemantics: true,
    child: widget.child,
  );
}

/// A 300 ms tint when [value] changes (S9 motion), none under reduced
/// motion.
class HighlightOnChange extends StatefulWidget {
  const HighlightOnChange({
    required this.value,
    required this.child,
    super.key,
  });

  final Object? value;
  final Widget child;

  @override
  State<HighlightOnChange> createState() => _HighlightOnChangeState();
}

class _HighlightOnChangeState extends State<HighlightOnChange> {
  bool _highlighted = false;
  Timer? _timer;

  @override
  void didUpdateWidget(HighlightOnChange oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value &&
        !MediaQuery.disableAnimationsOf(context)) {
      _highlighted = true;
      _timer?.cancel();
      _timer = Timer(const Duration(milliseconds: 300), () {
        if (mounted) setState(() => _highlighted = false);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.disableAnimationsOf(context);
    return AnimatedContainer(
      duration: reduce ? Duration.zero : const Duration(milliseconds: 300),
      decoration: BoxDecoration(
        color: _highlighted
            ? Theme.of(context).colorScheme.surfaceContainerHigh
            : Colors.transparent,
        borderRadius: BorderRadius.circular(BafoRadii.sm),
      ),
      child: widget.child,
    );
  }
}

/// The S4 banner: reconnecting, polling, offline. Nothing while connected.
class ConnectionBanner extends StatelessWidget {
  const ConnectionBanner({
    required this.connection,
    this.pollInterval,
    super.key,
  });

  final LiveConnection connection;
  final Duration? pollInterval;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final String? message = switch (connection) {
      LiveConnection.reconnecting => l10n.liveConnectionReconnecting,
      LiveConnection.polling => l10n.liveConnectionPolling(
        (pollInterval ?? const Duration(seconds: 10)).inSeconds,
      ),
      LiveConnection.offline => l10n.liveConnectionOffline,
      _ => null,
    };
    if (message == null) return const SizedBox.shrink();
    final colors =
        (connection == LiveConnection.polling
                ? StatusTone.neutral
                : StatusTone.warning)
            .resolve(context.semanticColors);
    return Semantics(
      container: true,
      liveRegion: true,
      child: Material(
        color: colors.background,
        child: Padding(
          padding: const EdgeInsetsDirectional.symmetric(
            horizontal: BafoSpacing.page,
            vertical: BafoSpacing.sm,
          ),
          child: Row(
            children: [
              Icon(
                connection == LiveConnection.offline
                    ? Icons.wifi_off_rounded
                    : Icons.sync_problem_rounded,
                size: 18,
                color: colors.foreground,
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: colors.foreground),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A small dot and label for the header: connecting, live, or degraded.
class ConnectionIndicator extends StatelessWidget {
  const ConnectionIndicator({required this.connection, super.key});

  final LiveConnection connection;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final semantic = context.semanticColors;
    final theme = Theme.of(context);
    final (String label, Color color, bool spinner) = switch (connection) {
      LiveConnection.connecting => (
        l10n.liveConnectionConnecting,
        theme.colorScheme.onSurfaceVariant,
        true,
      ),
      LiveConnection.connected || LiveConnection.grace => (
        l10n.liveConnectionLive,
        semantic.leading.foreground,
        false,
      ),
      LiveConnection.reconnecting => (
        l10n.liveConnectionReconnecting,
        semantic.warning.foreground,
        true,
      ),
      LiveConnection.polling => (
        l10n.liveConnectionReconnecting,
        semantic.warning.foreground,
        false,
      ),
      LiveConnection.offline => (
        l10n.liveConnectionOffline,
        semantic.warning.foreground,
        false,
      ),
    };
    return MergeSemantics(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (spinner)
            SizedBox.square(
              dimension: 10,
              child: CircularProgressIndicator(strokeWidth: 1.5, color: color),
            )
          else
            DecoratedBox(
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              child: const SizedBox.square(dimension: 8),
            ),
          const SizedBox(width: BafoSpacing.xs),
          Text(
            label,
            style: theme.textTheme.labelSmall?.copyWith(color: color),
          ),
        ],
      ),
    );
  }
}

/// `offers.stage.*` (neutral).
class OfferStageChip extends StatelessWidget {
  const OfferStageChip({required this.stage, super.key});

  final OfferStage stage;

  static String labelOf(AppLocalizations l10n, OfferStage stage) =>
      switch (stage) {
        OfferStage.sealed => l10n.offersStageSealed,
        OfferStage.initial => l10n.offersStageInitial,
        OfferStage.live => l10n.offersStageLive,
        OfferStage.bafo => l10n.offersStageBafo,
        OfferStage.unknown => l10n.offersStageLive,
      };

  @override
  Widget build(BuildContext context) => StatusPill(
    label: labelOf(context.l10n, stage),
    icon: stage == OfferStage.sealed ? Icons.lock_outline_rounded : null,
  );
}

/// The own current offer (W19 `MyOfferCard`).
class MyOfferCard extends StatelessWidget {
  const MyOfferCard({
    required this.offer,
    required this.count,
    this.currency = Money.sar,
    this.onHistory,
    super.key,
  });

  final OwnOffer offer;
  final int count;
  final String currency;
  final VoidCallback? onHistory;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final amount = MoneyFormat.format(
      Money(offer.amountMinor, currency: currency),
      languageCode: context.languageCode,
    );
    final time = BafoDateFormat.timeWithMillis(offer.acceptedAt);
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
                    l10n.liveMyOfferTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ),
              OfferStageChip(stage: offer.stage),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          HighlightOnChange(
            value: offer.id,
            child: Semantics(
              label: amount,
              excludeSemantics: true,
              child: Align(
                alignment: AlignmentDirectional.centerStart,
                child: Ltr(
                  child: MoneyText(
                    Money(offer.amountMinor, currency: currency),
                    style: theme.textTheme.headlineSmall,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: BafoSpacing.xs),
          Text(
            l10n.liveMyOfferReceivedAt(ltrIsolate(time)),
            style: BafoTypography.tabular(
              theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ) ??
                  const TextStyle(),
            ),
          ),
          Text(
            l10n.liveMyOfferCount(count),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          if (onHistory != null)
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: BafoButton.text(
                label: l10n.liveMyOffersLink,
                icon: Icons.history_rounded,
                onPressed: onHistory,
              ),
            ),
        ],
      ),
    );
  }
}

/// The ladder, only when the snapshot carries it (`rank_visibility = full`
/// and `show_prices`): other participants by alias only, the viewer as
/// «أنتم».
class LadderList extends StatelessWidget {
  const LadderList({
    required this.ladder,
    this.currency = Money.sar,
    super.key,
  });

  final List<LadderEntry> ladder;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final semantic = context.semanticColors;
    return BafoCard(
      padding: const EdgeInsetsDirectional.symmetric(
        horizontal: BafoSpacing.lg,
        vertical: BafoSpacing.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(
            header: true,
            child: Text(
              l10n.liveLadderTitle,
              style: theme.textTheme.titleSmall,
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          for (final (index, entry) in ladder.indexed)
            _LadderRow(
              rank: index + 1,
              name: entry.isMe
                  ? l10n.liveLadderYou
                  : l10n.liveLadderParticipant(entry.aliasNo),
              amount: MoneyFormat.format(
                Money(entry.amountMinor, currency: currency),
                languageCode: context.languageCode,
              ),
              isMe: entry.isMe,
              highlight: semantic.leading.background,
            ),
        ],
      ),
    );
  }
}

class _LadderRow extends StatelessWidget {
  const _LadderRow({
    required this.rank,
    required this.name,
    required this.amount,
    required this.isMe,
    required this.highlight,
  });

  final int rank;
  final String name;
  final String amount;
  final bool isMe;
  final Color highlight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = isMe
        ? theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700)
        : theme.textTheme.bodyMedium;
    return Semantics(
      label: context.l10n.liveLadderRowSemantics(rank, name, amount),
      excludeSemantics: true,
      child: Container(
        margin: const EdgeInsetsDirectional.only(bottom: BafoSpacing.xs),
        padding: const EdgeInsetsDirectional.symmetric(
          horizontal: BafoSpacing.sm,
          vertical: BafoSpacing.sm,
        ),
        decoration: BoxDecoration(
          color: isMe ? highlight : null,
          borderRadius: BorderRadius.circular(BafoRadii.sm),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 28,
              child: Text(
                '$rank',
                style: BafoTypography.tabular(style ?? const TextStyle()),
              ),
            ),
            Expanded(child: Text(name, style: style)),
            Ltr(
              child: Text(
                amount,
                style: BafoTypography.tabular(style ?? const TextStyle()),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// M26: sealed offers stay closed until the close.
class SealedLockPanel extends StatelessWidget {
  const SealedLockPanel({required this.hasOffer, super.key});

  final bool hasOffer;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return InfoNotice(
      tone: StatusTone.neutral,
      icon: Icons.lock_outline_rounded,
      title: l10n.liveSealedTitle,
      message: hasOffer ? l10n.liveSealedBody : l10n.liveSealedNoOffer,
    );
  }
}

/// M27: the BAFO banner (charcoal, with the mark) for the shortlisted.
class BafoBanner extends StatelessWidget {
  const BafoBanner({
    required this.bafo,
    required this.direction,
    this.currency = Money.sar,
    super.key,
  });

  final ParticipantBafo bafo;
  final Direction direction;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final colors = context.semanticColors.inverse;
    final cutoff = bafo.cutoffAt;
    final reference = bafo.referenceAmountMinor;
    final text = theme.textTheme.bodyMedium?.copyWith(color: colors.foreground);
    return Container(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: BorderRadius.circular(BafoRadii.card),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // The mark keeps its 24 dp minimum (05_brand.md §2.5).
          const BrandMark(size: 32, excludeFromSemantics: true),
          const SizedBox(width: BafoSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(
                  header: true,
                  child: Text(
                    l10n.competitionsStatusBafoRound,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: colors.foreground,
                    ),
                  ),
                ),
                const SizedBox(height: BafoSpacing.xs),
                Text(
                  bafo.submitted
                      ? l10n.bafoSubmitted
                      : l10n.bafoInvite(
                          cutoff == null
                              ? ''
                              : BafoDateFormat.deadline(
                                  cutoff,
                                  context.languageCode,
                                  l10n,
                                ),
                        ),
                  style: text,
                ),
                if (reference != null && !bafo.submitted) ...[
                  const SizedBox(height: BafoSpacing.sm),
                  Text(
                    l10n.bafoReferenceAmount(
                      ltrIsolate(
                        MoneyFormat.format(
                          Money(reference, currency: currency),
                          languageCode: context.languageCode,
                        ),
                      ),
                    ),
                    style: BafoTypography.tabular(text ?? const TextStyle()),
                  ),
                  const SizedBox(height: BafoSpacing.xs),
                  Text(l10n.bafoRule(direction.wire), style: text),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The anti-sniping (or manual) extension notice.
class ExtensionNoticeBanner extends StatelessWidget {
  const ExtensionNoticeBanner({required this.notice, super.key});

  final LiveExtensionNotice notice;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final by = notice.bySeconds;
    final manual =
        notice.reason == ExtensionReason.manual ||
        notice.reason == ExtensionReason.admin;
    final message = manual
        ? l10n.liveExtendedManual
        : by == null || by <= 0
        ? l10n.liveExtendedGeneric
        : by % 60 == 0
        ? l10n.liveExtended(by ~/ 60)
        : l10n.liveExtendedSeconds(by);
    return Semantics(
      container: true,
      liveRegion: true,
      child: InfoNotice(
        tone: StatusTone.info,
        icon: Icons.update_rounded,
        message: message,
      ),
    );
  }
}
