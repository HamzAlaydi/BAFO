import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_bloc.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// «المتنافس 7 · شركة الريادة»: how the issuer sees a participant (the
/// issuer projection carries both the alias and the organisation).
String participantLabel(AppLocalizations l10n, int aliasNo, String? name) =>
    name == null || name.trim().isEmpty
    ? l10n.issuerParticipantAlias(aliasNo)
    : l10n.issuerParticipantLabel(aliasNo, bidiIsolate(name));

/// Basis points as a signed percentage (SCREENS.md S6): `6080` → `+60.8%`,
/// `-50` → `−0.5%`. Server-signed values (positive is better for the
/// issuer) are never recomputed.
String formatSignedBps(int bps) {
  final sign = bps > 0
      ? '+'
      : bps < 0
      ? '−'
      : '';
  final value = (bps.abs() / 100).toStringAsFixed(2);
  final trimmed = value.contains('.')
      ? value.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '')
      : value;
  return '$sign$trimmed%';
}

/// An amount as `MoneyText` inside an LTR island, placed at the start edge
/// of the surrounding (usually RTL) layout.
class AmountText extends StatelessWidget {
  const AmountText(
    this.amountMinor, {
    this.currency = Money.sar,
    this.style,
    super.key,
  });

  final int amountMinor;
  final String currency;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) => Align(
    alignment: AlignmentDirectional.centerStart,
    widthFactor: 1,
    child: Ltr(
      child: MoneyText(Money(amountMinor, currency: currency), style: style),
    ),
  );
}

/// A rank (`#1`) in an LTR island at the start edge.
class RankText extends StatelessWidget {
  const RankText({required this.rank, super.key});

  final int? rank;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final value = rank;
    return SizedBox(
      width: 44,
      child: Align(
        alignment: AlignmentDirectional.topStart,
        child: Ltr(
          child: Text(
            value == null ? l10n.issuerNoValue : '#$value',
            style: Theme.of(context).textTheme.titleMedium,
            semanticsLabel: value == null ? null : l10n.issuerLiveRank(value),
          ),
        ),
      ),
    );
  }
}

/// «متاح في لوحة التحكم على الويب.» (SCREENS.md §3.7): what mobile shows
/// for issuer actions that are web-only (CD5). No link or button.
class WebOnlyNotice extends StatelessWidget {
  const WebOnlyNotice({this.message, this.title, super.key});

  final String? message;
  final String? title;

  @override
  Widget build(BuildContext context) => InfoNotice(
    title: title,
    message: message ?? context.l10n.issuerWebOnly,
    tone: StatusTone.neutral,
    icon: Icons.desktop_windows_outlined,
  );
}

/// A business error shown above a form or in a sheet (S7): the app's
/// `errors.<code>` text, else the server message. Entitlement errors get
/// the web notice instead of a purchase action (SCREENS.md §3.2).
class IssuerErrorNotice extends StatelessWidget {
  const IssuerErrorNotice({required this.error, super.key});

  final ApiException error;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return switch (error.code) {
      'issuer_plan_required' => InfoNotice(
        title: l10n.issuerPlanRequired,
        message: l10n.billingManagedOnWeb,
        tone: StatusTone.warning,
        icon: Icons.workspace_premium_outlined,
      ),
      'sponsorship_payment_required' => InfoNotice(
        message: l10n.sponsorshipManagedOnWeb,
        icon: Icons.confirmation_number_outlined,
      ),
      // Field errors without a control here: the server's messages (S7).
      'validation_failed' when error.fieldErrors.isNotEmpty => Semantics(
        liveRegion: true,
        child: InfoNotice(
          message: [
            for (final messages in error.fieldErrors.values)
              if (messages.isNotEmpty) messages.first,
          ].join('\n'),
          tone: StatusTone.danger,
          icon: Icons.error_outline_rounded,
        ),
      ),
      _ => Semantics(
        liveRegion: true,
        child: InfoNotice(
          message: errorMessage(l10n, error),
          tone: StatusTone.danger,
          icon: Icons.error_outline_rounded,
        ),
      ),
    };
  }
}

/// A polite live region whose announced text changes at most once per
/// [interval] (SCREENS.md S9); the visual [child] updates at once.
class ThrottledLiveRegion extends StatefulWidget {
  const ThrottledLiveRegion({
    required this.message,
    required this.child,
    this.interval = const Duration(seconds: 10),
    super.key,
  });

  /// Null: nothing to announce yet.
  final String? message;
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

  String? _label() {
    final message = widget.message;
    if (message == null) return _announced;
    if (_announced == null || (_announced != message && !_cooling)) {
      _announced = message;
      _cooling = true;
      _cooldown?.cancel();
      _cooldown = Timer(widget.interval, () {
        _cooling = false;
        if (mounted) setState(() {});
      });
    }
    return _announced;
  }

  @override
  Widget build(BuildContext context) {
    final label = _label();
    return Stack(
      children: [
        widget.child,
        if (label != null)
          Positioned.fill(
            child: Semantics(
              liveRegion: true,
              label: label,
              child: const SizedBox.shrink(),
            ),
          ),
      ],
    );
  }
}

/// `invitations.status.*` (S2 "Other chips").
class InvitationStatusChip extends StatelessWidget {
  const InvitationStatusChip({required this.status, super.key});

  final InvitationStatus status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final (tone, icon) = switch (status) {
      InvitationStatus.draft => (StatusTone.neutral, Icons.edit_outlined),
      InvitationStatus.sent => (StatusTone.info, Icons.send_outlined),
      InvitationStatus.viewed => (StatusTone.info, Icons.visibility_outlined),
      InvitationStatus.joined => (
        StatusTone.success,
        Icons.check_circle_outline_rounded,
      ),
      InvitationStatus.declined => (
        StatusTone.neutral,
        Icons.remove_circle_outline_rounded,
      ),
      InvitationStatus.revoked => (StatusTone.neutral, Icons.block_rounded),
      InvitationStatus.expired => (
        StatusTone.neutral,
        Icons.timer_off_outlined,
      ),
      InvitationStatus.unknown => (
        StatusTone.neutral,
        Icons.help_outline_rounded,
      ),
    };
    return StatusPill(
      label: l10n.issuerInvitationStatus(status.wire),
      tone: tone,
      icon: icon,
    );
  }
}

/// Who pays for a participation, as the issuer sees it
/// (`sponsorship.coverage.*`).
class CoverageChip extends StatelessWidget {
  const CoverageChip({required this.coverage, super.key});

  final Coverage coverage;

  @override
  Widget build(BuildContext context) {
    final covered = coverage == Coverage.sponsored;
    return StatusPill(
      label: context.l10n.issuerCoverage(coverage.wire),
      tone: covered ? StatusTone.sponsored : StatusTone.neutral,
      icon: covered
          ? Icons.confirmation_number_outlined
          : Icons.workspace_premium_outlined,
    );
  }
}

/// The read-only sponsorship counters (M38, M41). Mobile never shows the
/// pass price or a payment action (SCREENS.md §3.2).
class SponsorshipCard extends StatelessWidget {
  const SponsorshipCard({required this.sponsorship, super.key});

  final Sponsorship sponsorship;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final counts = sponsorship.counts;
    final cap = sponsorship.maxPasses;
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.confirmation_number_outlined,
                color: theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    l10n.issuerSponsorshipTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.issuerSponsorshipMode(sponsorship.mode),
            style: theme.textTheme.bodyMedium,
          ),
          const SizedBox(height: BafoSpacing.md),
          KeyValueList(
            items: [
              if (cap != null)
                KeyValue(l10n.issuerSponsorshipCap, '$cap', ltr: true),
              KeyValue(
                l10n.issuerSponsorshipFunded,
                '${sponsorship.fundedPasses}',
                ltr: true,
              ),
              KeyValue(
                l10n.issuerSponsorshipJoined,
                '${counts.joined}',
                ltr: true,
              ),
              KeyValue(
                l10n.issuerSponsorshipReserved,
                '${counts.reserved}',
                ltr: true,
              ),
              KeyValue(
                l10n.issuerSponsorshipFreeSlots,
                '${counts.freeSlots}',
                ltr: true,
              ),
              if (counts.pending > 0)
                KeyValue(
                  l10n.issuerSponsorshipPending,
                  '${counts.pending}',
                  ltr: true,
                ),
              if ((sponsorship.unusedCount ?? 0) > 0)
                KeyValue(
                  l10n.issuerSponsorshipUnused,
                  '${sponsorship.unusedCount}',
                  ltr: true,
                ),
            ],
          ),
          const SizedBox(height: BafoSpacing.md),
          InfoNotice(message: l10n.issuerSponsorshipOnWeb),
        ],
      ),
    );
  }
}

/// Title, reference and chips of an issuer competition screen.
class IssuerCompetitionHeader extends StatelessWidget {
  const IssuerCompetitionHeader({
    required this.competition,
    this.effectiveCloseAt,
    this.extensionCount,
    super.key,
  });

  final Competition competition;

  /// The live snapshot's close and extension count when newer.
  final DateTime? effectiveCloseAt;
  final int? extensionCount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final reference = competition.referenceNo;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          header: true,
          child: Text(competition.title, style: theme.textTheme.titleLarge),
        ),
        if (reference != null) ...[
          const SizedBox(height: BafoSpacing.xxs),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: Ltr(
              child: Text(
                reference,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ),
        ],
        const SizedBox(height: BafoSpacing.md),
        Wrap(
          spacing: BafoSpacing.xs,
          runSpacing: BafoSpacing.xs,
          children: [
            CompetitionStatusChip(
              status: competition.status,
              phase: competition.phase,
              effectiveCloseAt:
                  effectiveCloseAt ?? competition.schedule.effectiveCloseAt,
              extensionCount: extensionCount ?? competition.extensionCount,
            ),
            DirectionChip(direction: competition.direction),
            FormatChip(format: competition.format),
          ],
        ),
      ],
    );
  }
}

/// S4 connection states of the live monitor. `connected` shows a small
/// «مباشر» indicator; the degraded states a banner.
class LiveConnectionBanner extends StatelessWidget {
  const LiveConnectionBanner({
    required this.connection,
    required this.pollSeconds,
    super.key,
  });

  final LiveConnection connection;
  final int pollSeconds;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return switch (connection) {
      LiveConnection.connected => Semantics(
        label: l10n.issuerLiveConnected,
        excludeSemantics: true,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.circle,
              size: 10,
              color: context.semanticColors.leading.foreground,
            ),
            const SizedBox(width: BafoSpacing.xs),
            Text(l10n.issuerLiveConnected, style: theme.textTheme.labelMedium),
          ],
        ),
      ),
      LiveConnection.connecting => Semantics(
        label: l10n.commonLoading,
        excludeSemantics: true,
        child: const SizedBox.square(
          dimension: 16,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
      LiveConnection.reconnecting => InfoNotice(
        message: l10n.issuerLiveReconnecting,
        tone: StatusTone.warning,
        icon: Icons.sync_problem_rounded,
      ),
      LiveConnection.polling => InfoNotice(
        message: l10n.issuerLivePolling(pollSeconds),
        tone: StatusTone.warning,
        icon: Icons.sync_problem_rounded,
      ),
    };
  }
}

/// A navigation row of the issuer detail (live monitor, offers log, …).
class IssuerNavTile extends StatelessWidget {
  const IssuerNavTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.trailingText,
    super.key,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final String? trailingText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListTile(
      leading: Icon(icon, color: theme.colorScheme.onSurfaceVariant),
      title: Text(label),
      minTileHeight: BafoSizes.minTouchTarget,
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (trailingText != null)
            Text(
              trailingText!,
              style: BafoTypography.tabular(
                theme.textTheme.bodyMedium ?? const TextStyle(),
              ).copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          const SizedBox(width: BafoSpacing.xs),
          // Chevrons point to the end edge: mirrored in RTL (S1).
          const Icon(Icons.chevron_right_rounded),
        ],
      ),
      onTap: onTap,
    );
  }
}

/// A small label/value tile (live metrics).
class MetricTile extends StatelessWidget {
  const MetricTile({required this.label, required this.value, super.key});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return MergeSemantics(
      child: BafoCard(
        padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Ltr(
              child: Text(
                value,
                style: BafoTypography.tabular(
                  theme.textTheme.titleLarge ?? const TextStyle(),
                ),
              ),
            ),
            const SizedBox(height: BafoSpacing.xxs),
            Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Two metric tiles per row.
class MetricGrid extends StatelessWidget {
  const MetricGrid({required this.tiles, super.key});

  final List<MetricTile> tiles;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      const gap = BafoSpacing.sm;
      final width = (constraints.maxWidth - gap) / 2;
      return Wrap(
        spacing: gap,
        runSpacing: gap,
        children: [
          for (final tile in tiles) SizedBox(width: width, child: tile),
        ],
      );
    },
  );
}

/// Whether mutations are disabled because the app is offline (SCREENS.md
/// S8, M05). Rebuilds the caller on a change; false without a
/// [NetworkStatusCubit].
bool issuerOffline(BuildContext context) {
  try {
    return context.select<NetworkStatusCubit, bool>(
      (cubit) => cubit.state.isOffline,
    );
  } on ProviderNotFoundException {
    return false;
  }
}

/// Role guard of the issuer child screens (SCREENS.md §3.1): back to the
/// competition's detail, which renders the viewer's own projection.
void leaveToDetail(BuildContext context, String competitionId) {
  if (context.canPop()) {
    context.pop();
  } else {
    context.go(IssuerPaths.competition(competitionId));
  }
}
