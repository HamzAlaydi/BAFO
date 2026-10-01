import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/amount_input.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/domain/offer_rules.dart';
import 'package:bafo/features/live/presentation/live_room_bloc.dart';
import 'package:bafo/features/live/presentation/offer_submit_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// An amount as an LTR island for a translated sentence.
String _money(BuildContext context, int minor, String currency) => ltrIsolate(
  MoneyFormat.format(
    Money(minor, currency: currency),
    languageCode: context.languageCode,
  ),
);

/// The pre-check message of CD13 (direction-specific wording, server
/// amounts only).
String? boundMessage(
  BuildContext context,
  OfferBoundViolation? violation, {
  required Direction direction,
  required String currency,
}) {
  if (violation == null) return null;
  final l10n = context.l10n;
  final bound = violation.boundMinor;
  return switch (violation.issue) {
    OfferBoundIssue.granularity => l10n.validationAmountWholeRiyals,
    OfferBoundIssue.startPrice => l10n.offersErrorStartPrice(
      direction.wire,
      _money(context, bound ?? 0, currency),
    ),
    OfferBoundIssue.stepNotMet => l10n.offersErrorStepNotMet(
      direction.wire,
      _money(context, bound ?? 0, currency),
    ),
    OfferBoundIssue.bafoReference => l10n.offersErrorBafoReference(
      direction.wire,
      _money(context, bound ?? 0, currency),
    ),
  };
}

/// The text of a server answer (S5 "Responses").
String issueMessage(
  BuildContext context,
  OfferIssue issue, {
  required Direction direction,
  required String currency,
}) {
  final l10n = context.l10n;
  final amount = issue.amountMinor;
  String money() => _money(context, amount ?? 0, currency);
  return switch (issue.kind) {
    OfferIssueKind.stepNotMet when amount != null => l10n.offersErrorStepNotMet(
      direction.wire,
      money(),
    ),
    OfferIssueKind.startPrice when amount != null => l10n.offersErrorStartPrice(
      direction.wire,
      money(),
    ),
    OfferIssueKind.granularity when amount != null =>
      l10n.offersErrorGranularity(money()),
    OfferIssueKind.amountTooLarge when amount != null =>
      l10n.offersErrorAmountTooLarge(money()),
    OfferIssueKind.bafoReference when amount != null =>
      l10n.offersErrorBafoReference(direction.wire, money()),
    OfferIssueKind.notAccepting when issue.at != null =>
      l10n.liveComposerOpensAt(
        BafoDateFormat.deadline(issue.at!, context.languageCode, l10n),
      ),
    OfferIssueKind.rateLimited => l10n.offersRateLimited,
    OfferIssueKind.keyReused => l10n.offersKeyReused,
    _ => errorMessage(l10n, issue.error),
  };
}

/// W19 `OfferComposer` (S5): the amount, the server bounds as hints, the
/// "Use {amount}" chip, inline errors, and the submit button that opens the
/// confirm step. Disabled from the room's server-derived `canSubmit`.
class OfferComposer extends StatefulWidget {
  const OfferComposer({required this.room, super.key});

  final LiveRoomLoaded room;

  @override
  State<OfferComposer> createState() => _OfferComposerState();
}

class _OfferComposerState extends State<OfferComposer> {
  final TextEditingController _amount = TextEditingController();
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  OfferBounds get _bounds => OfferBounds.of(
    widget.room.snapshot,
    format: widget.room.competition.format,
  );

  String get _currency => widget.room.competition.currency;

  void _fill(int minor) {
    final full = MoneyFormat.amountOnly(Money(minor, currency: _currency));
    final text = _bounds.wholeRiyals && full.endsWith('.00')
        ? full.substring(0, full.length - 3)
        : full;
    _amount.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
    context.read<OfferSubmitCubit>().clearIssue();
    _form.currentState?.validate();
  }

  Future<void> _submit() async {
    final form = _form.currentState;
    if (form == null || !form.validate()) return;
    final parsed = parseAmountInput(
      _amount.text,
      granularityMinor: _bounds.granularityMinor,
    );
    final amount = parsed.amountMinor;
    if (parsed.error != null || amount == null) return;
    // Close the keyboard for the confirm sheet, and keep it closed when the
    // sheet pops (the route would otherwise refocus the amount field and
    // cover the room and the receipt).
    FocusManager.instance.primaryFocus?.unfocus();
    final cubit = context.read<OfferSubmitCubit>()..openConfirm(amount);
    await showOfferConfirmSheet(
      context,
      cubit: cubit,
      direction: widget.room.competition.direction,
      mode: _bounds.mode,
      currency: _currency,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final room = widget.room;
    final bounds = _bounds;
    final direction = room.competition.direction;
    final snapshot = room.snapshot;

    return BlocConsumer<OfferSubmitCubit, OfferSubmitState>(
      listenWhen: (previous, current) => current is OfferAccepted,
      listener: (_, _) => _amount.clear(),
      builder: (context, state) {
        final issue = state is OfferComposing ? state.issue : null;
        final retryAt = state is OfferComposing ? state.retryAt : null;
        final inline = issue != null && issue.isInline
            ? issueMessage(
                context,
                issue,
                direction: direction,
                currency: _currency,
              )
            : null;
        final banner = issue != null && !issue.isInline
            ? issueMessage(
                context,
                issue,
                direction: direction,
                currency: _currency,
              )
            : null;
        final enabled = room.canSubmit;
        final hints = <String>[
          if (bounds.mode == OfferMode.bafo) l10n.bafoRule(direction.wire),
          if (bounds.requiredNextMinor case final next?)
            l10n.offersHintRequiredNext(
              direction.wire,
              _money(context, next, _currency),
            )
          else if (snapshot.myOffer == null && bounds.startPriceMinor != null)
            l10n.offersHintStartPrice(
              direction.wire,
              _money(context, bounds.startPriceMinor!, _currency),
            ),
        ];
        final submitLabel = switch (bounds.mode) {
          OfferMode.bafo => l10n.liveComposerSubmitBafo,
          OfferMode.sealed when snapshot.myOffer != null =>
            l10n.liveComposerSubmitRevise,
          _ when snapshot.myOffer != null => l10n.liveComposerSubmitImprove,
          _ => l10n.liveComposerSubmitFirst,
        };
        final pausedReason = room.deadlinePassed
            ? l10n.competitionsCountdownClosing
            : (!room.connectionAllowsSubmit
                  ? l10n.liveConnectionSubmitPaused
                  : null);

        return Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              MoneyInputField(
                key: const Key('offer-amount'),
                label: l10n.liveComposerLabel,
                controller: _amount,
                currency: _currency,
                granularityMinor: bounds.granularityMinor,
                enabled: enabled,
                errorText: inline,
                helperText:
                    '${bounds.wholeRiyals ? l10n.liveComposerWholeRiyals : l10n.liveComposerDecimalsAllowed} · ${l10n.commonPricesExcludeVat}',
                textInputAction: TextInputAction.done,
                onAmountChanged: (_) =>
                    context.read<OfferSubmitCubit>().clearIssue(),
                onSubmitted: (_) => unawaited(_submit()),
                validator: (amount) => boundMessage(
                  context,
                  bounds.check(amount),
                  direction: direction,
                  currency: _currency,
                ),
              ),
              for (final hint in hints)
                Padding(
                  // Aligned with the field's helper text: the theme's
                  // content padding (lg) plus the outlined border's input
                  // gap (4).
                  padding: const EdgeInsetsDirectional.only(
                    top: BafoSpacing.xs,
                    start: BafoSpacing.lg + BafoSpacing.xs,
                  ),
                  child: Text(
                    hint,
                    style: BafoTypography.tabular(
                      theme.textTheme.bodySmall ?? const TextStyle(),
                    ),
                  ),
                ),
              if (bounds.requiredNextMinor case final next? when enabled)
                Padding(
                  padding: const EdgeInsetsDirectional.only(
                    top: BafoSpacing.xs,
                  ),
                  child: Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: ActionChip(
                      key: const Key('use-required-amount'),
                      avatar: const Icon(Icons.input_rounded, size: 16),
                      label: Text(
                        l10n.liveComposerUseAmount(
                          _money(context, next, _currency),
                        ),
                      ),
                      onPressed: () => _fill(next),
                    ),
                  ),
                ),
              if (issue?.kind == OfferIssueKind.stepNotMet &&
                  issue?.amountMinor != null &&
                  enabled)
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: BafoButton.text(
                    key: const Key('use-this-amount'),
                    label: l10n.offersUseThisAmount,
                    onPressed: () => _fill(issue!.amountMinor!),
                  ),
                ),
              if (banner != null) ...[
                const SizedBox(height: BafoSpacing.sm),
                InfoNotice(
                  tone: StatusTone.warning,
                  icon: Icons.info_outline_rounded,
                  message: banner,
                ),
              ],
              if (pausedReason != null && snapshot.acceptingOffers) ...[
                const SizedBox(height: BafoSpacing.sm),
                Text(
                  pausedReason,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
              const SizedBox(height: BafoSpacing.sm),
              CooldownButton(
                key: const Key('offer-submit'),
                label: submitLabel,
                availableAt: retryAt,
                loading: state is OfferSubmitting,
                onPressed: enabled ? () => unawaited(_submit()) : null,
              ),
            ],
          ),
        );
      },
    );
  }
}

/// M24: the amount large (LTR), "excl. VAT", the direction reminder, the
/// sealed or BAFO note. The key was made when this step opened
/// ([OfferSubmitCubit.openConfirm]); a retry reuses it.
Future<void> showOfferConfirmSheet(
  BuildContext context, {
  required OfferSubmitCubit cubit,
  required Direction direction,
  required OfferMode mode,
  required String currency,
}) async {
  final l10n = context.l10n;
  await showBafoBottomSheet<void>(
    context,
    title: mode == OfferMode.bafo
        ? l10n.offersConfirmBafoTitle
        : l10n.offersConfirmTitle,
    builder: (_) => BlocProvider.value(
      value: cubit,
      child: _ConfirmSheet(
        direction: direction,
        mode: mode,
        currency: currency,
      ),
    ),
  );
  final state = cubit.state;
  if (state is OfferConfirming || state is OfferUnconfirmed) cubit.cancel();
}

class _ConfirmSheet extends StatefulWidget {
  const _ConfirmSheet({
    required this.direction,
    required this.mode,
    required this.currency,
  });

  final Direction direction;
  final OfferMode mode;
  final String currency;

  @override
  State<_ConfirmSheet> createState() => _ConfirmSheetState();
}

class _ConfirmSheetState extends State<_ConfirmSheet> {
  /// The sheet closes once: later states (the success is acknowledged right
  /// after) must not pop the page below it.
  bool _closed = false;

  void _close() {
    if (_closed) return;
    _closed = true;
    Navigator.of(context).pop();
  }

  Future<void> _outlier(BuildContext context, OfferOutlierConfirm state) async {
    final cubit = context.read<OfferSubmitCubit>();
    final send = await showDialog<bool>(
      context: context,
      builder: (_) => OutlierConfirmDialog(state: state),
    );
    if (send ?? false) {
      await cubit.confirmOutlier();
    } else {
      cubit.acknowledge();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<OfferSubmitCubit, OfferSubmitState>(
      listener: (context, state) {
        switch (state) {
          case OfferAccepted() || OfferComposing():
            _close();
          case OfferOutlierConfirm():
            unawaited(_outlier(context, state));
          default:
            break;
        }
      },
      builder: (context, state) {
        final amount = switch (state) {
          OfferConfirming(:final amountMinor) ||
          OfferSubmitting(:final amountMinor) ||
          OfferUnconfirmed(:final amountMinor) ||
          OfferOutlierConfirm(:final amountMinor) => amountMinor,
          _ => null,
        };
        final submitting = state is OfferSubmitting;
        final formatted = amount == null
            ? ''
            : MoneyFormat.format(
                Money(amount, currency: widget.currency),
                languageCode: context.languageCode,
              );
        return PopScope(
          canPop: !submitting,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(
                label: formatted,
                excludeSemantics: true,
                child: Center(
                  child: Ltr(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        formatted,
                        style: BafoTypography.tabular(
                          theme.textTheme.headlineMedium ?? const TextStyle(),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: BafoSpacing.xxs),
              Text(
                l10n.offersConfirmExclVat,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: BafoSpacing.md),
              Center(child: DirectionChip(direction: widget.direction)),
              if (widget.mode == OfferMode.sealed) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  tone: StatusTone.neutral,
                  icon: Icons.lock_outline_rounded,
                  message: l10n.offersConfirmSealed,
                ),
              ],
              if (widget.mode == OfferMode.bafo) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  tone: StatusTone.inverse,
                  icon: Icons.workspace_premium_outlined,
                  message: l10n.offersConfirmBafo,
                ),
              ],
              if (state is OfferConfirming && state.issue != null) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  tone: StatusTone.warning,
                  message: l10n.offersKeyReused,
                ),
              ],
              if (state is OfferUnconfirmed) ...[
                const SizedBox(height: BafoSpacing.md),
                InfoNotice(
                  tone: StatusTone.warning,
                  icon: Icons.sync_problem_rounded,
                  message: l10n.offersUnconfirmed,
                ),
              ],
              const SizedBox(height: BafoSpacing.lg),
              BafoButton(
                key: const Key('offer-confirm'),
                label: state is OfferUnconfirmed
                    ? l10n.commonActionsRetry
                    : l10n.offersConfirmAction,
                loading: submitting || state is OfferOutlierConfirm,
                expand: true,
                onPressed: () {
                  final cubit = context.read<OfferSubmitCubit>();
                  if (state is OfferUnconfirmed) {
                    unawaited(cubit.retry());
                  } else {
                    unawaited(cubit.submit());
                  }
                },
              ),
              const SizedBox(height: BafoSpacing.sm),
              BafoButton.text(
                label: l10n.commonActionsCancel,
                expand: true,
                onPressed: submitting
                    ? null
                    : () {
                        context.read<OfferSubmitCubit>().cancel();
                      },
              ),
            ],
          ),
        );
      },
    );
  }
}

/// M25: «هذا العرض أقل/أعلى من عرضك الحالي بنسبة {pct}.». Confirming
/// re-sends with a new key; editing goes back to the amount.
class OutlierConfirmDialog extends StatelessWidget {
  const OutlierConfirmDialog({required this.state, super.key});

  final OfferOutlierConfirm state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final pct = ltrIsolate(formatBps(state.changeBps));
    return AlertDialog(
      title: Text(l10n.offersConfirmOutlierTitle),
      content: Text(
        state.isLower
            ? l10n.offersConfirmOutlierLower(pct)
            : l10n.offersConfirmOutlierHigher(pct),
      ),
      actions: [
        BafoButton.text(
          label: l10n.offersConfirmOutlierEdit,
          onPressed: () => Navigator.of(context).pop(false),
        ),
        BafoButton(
          key: const Key('outlier-send'),
          label: l10n.offersConfirmOutlierSend,
          onPressed: () => Navigator.of(context).pop(true),
        ),
      ],
    );
  }
}

/// M26: the sealed receipt (seq, time with milliseconds, amount).
Future<void> showSealedReceipt(
  BuildContext context,
  MyOffer offer, {
  String currency = Money.sar,
}) {
  final l10n = context.l10n;
  return showBafoBottomSheet<void>(
    context,
    title: l10n.offersSealedReceived,
    builder: (sheetContext) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        BafoCard(
          child: KeyValueList(
            items: [
              KeyValue(l10n.offersReceiptSeq, '#${offer.seq}', ltr: true),
              KeyValue(
                l10n.offersReceiptTime,
                BafoDateFormat.machine(offer.acceptedAt),
                ltr: true,
              ),
              KeyValue(
                l10n.offersReceiptAmount,
                MoneyFormat.format(
                  Money(offer.amountMinor, currency: currency),
                  languageCode: sheetContext.languageCode,
                ),
                ltr: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: BafoSpacing.md),
        InfoNotice(
          tone: StatusTone.neutral,
          icon: Icons.lock_outline_rounded,
          message: l10n.liveSealedBody,
        ),
        const SizedBox(height: BafoSpacing.lg),
        BafoButton(
          label: l10n.commonActionsDone,
          expand: true,
          onPressed: () => Navigator.of(sheetContext).pop(),
        ),
      ],
    ),
  );
}
