import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:bafo/core/money/amount_input.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/features/issuer/domain/schedule_quick_picks.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// Halalas as input text: `25000000` → `250000`, `12550` → `125.50`.
String minorToInput(int? minor) {
  if (minor == null) return '';
  final major = minor ~/ Money.minorPerMajor;
  final fraction = minor % Money.minorPerMajor;
  return fraction == 0 ? '$major' : '$major.${'$fraction'.padLeft(2, '0')}';
}

/// [form] with the reading of an amount field's [text] recorded (FQ2, FQ8):
/// text that cannot be used marks [field] with why, so the step does not move
/// on with a null price; usable or empty text clears the mark.
CompetitionDraftForm withAmountText(
  CompetitionDraftForm form,
  DraftField field,
  String text, {
  required int granularityMinor,
}) {
  final problem = switch (parseAmountInput(
    text,
    granularityMinor: granularityMinor,
  ).error) {
    AmountInputError.invalid => DraftProblem.amountInvalid,
    AmountInputError.notPositive => DraftProblem.amountNotPositive,
    AmountInputError.granularity => DraftProblem.amountWholeRiyals,
    AmountInputError.empty || null => null,
  };
  if (form.unreadableAmounts[field] == problem) return form;
  return form.copyWith(
    unreadableAmounts: {
      for (final entry in form.unreadableAmounts.entries)
        if (entry.key != field) entry.key: entry.value,
      field: ?problem,
    },
  );
}

/// The message for a client [problem] of [field] (S7). The server's
/// message, when there is one, is shown instead. With [duration] (the
/// schedule's actual length) the R16 messages state the value and the bound
/// (RELEASE_SCOPE.md §2.3).
String draftProblemText(
  AppLocalizations l10n,
  DraftField field,
  DraftProblem problem,
  Direction? direction, {
  Duration? duration,
}) => switch (problem) {
  DraftProblem.required => switch (field) {
    DraftField.preset => l10n.issuerCreatePresetRequired,
    _ => l10n.validationRequired,
  },
  DraftProblem.tooLong => l10n.validationMaxLength(switch (field) {
    DraftField.title => DraftRules.titleMax,
    DraftField.categoryOtherText => DraftRules.otherTextMax,
    _ => DraftRules.descriptionMax,
  }),
  DraftProblem.auctionNotAllowed => l10n.issuerFieldCategoryNoAuction,
  DraftProblem.reserveVersusStart => l10n.issuerFieldReserveVersusStart(
    (direction ?? Direction.unknown).wire,
  ),
  DraftProblem.closeBeforeOpen => l10n.issuerFieldCloseBeforeOpen,
  DraftProblem.opensInPast => l10n.issuerFieldOpensInPast,
  DraftProblem.tooShort =>
    duration == null
        ? l10n.issuerFieldDurationTooShort(DraftRules.minDurationMinutes)
        : l10n.issuerFieldDurationTooShortDetail(
            duration.inMinutes,
            DraftRules.minDurationMinutes,
          ),
  DraftProblem.durationTooLong =>
    duration == null
        ? l10n.issuerFieldDurationTooLong(DraftRules.maxDurationDays)
        : l10n.issuerFieldDurationTooLongDetail(
            duration.inDays,
            DraftRules.maxDurationDays,
          ),
  DraftProblem.amountInvalid => l10n.validationAmount,
  DraftProblem.amountNotPositive => l10n.validationAmountPositive,
  DraftProblem.amountWholeRiyals => l10n.validationAmountWholeRiyals,
};

/// The visible label of [field], for the error summary (FQ8).
String draftFieldLabel(
  AppLocalizations l10n,
  DraftField field,
  Direction? direction,
) {
  final wire = (direction ?? Direction.unknown).wire;
  return switch (field) {
    DraftField.direction => l10n.issuerCreateDirectionTitle,
    DraftField.format => l10n.issuerCreateFormatTitle,
    DraftField.preset => l10n.issuerPresetTierTitle,
    DraftField.title => l10n.issuerFieldTitle,
    DraftField.description => l10n.issuerFieldDescription,
    DraftField.category => l10n.competitionsDetailCategory,
    DraftField.categoryOtherText => l10n.issuerFieldCategoryOther,
    DraftField.region => l10n.competitionsDetailRegion,
    DraftField.startPrice => l10n.issuerFieldStartPrice(wire),
    DraftField.reservePrice => l10n.issuerFieldReservePrice(wire),
    DraftField.opensAt => l10n.competitionsScheduleOpensAt,
    DraftField.closesAt => l10n.competitionsScheduleClosesAt,
  };
}

/// «بعد 3 أيام» / "in 3 days" for the relative close hint (FQ4).
String scheduleRelativePhrase(AppLocalizations l10n, Duration duration) {
  if (duration.inMinutes < 60) {
    return l10n.issuerScheduleInMinutes(duration.inMinutes);
  }
  if (duration.inHours < 24) {
    return l10n.issuerScheduleInHours(duration.inHours);
  }
  return l10n.issuerScheduleInDays((duration.inHours / 24).round());
}

/// The chip label of a quick pick (RELEASE_SCOPE.md §2.3).
String quickPickLabel(AppLocalizations l10n, ScheduleQuickPick pick) =>
    switch (pick) {
      ScheduleQuickPick.hour => l10n.issuerScheduleQuickHour,
      ScheduleQuickPick.hours3 => l10n.issuerScheduleQuickHours3,
      ScheduleQuickPick.day => l10n.issuerScheduleQuickDay,
      ScheduleQuickPick.days3 => l10n.issuerScheduleQuickDays3,
      ScheduleQuickPick.week => l10n.issuerScheduleQuickWeek,
      ScheduleQuickPick.custom => l10n.issuerScheduleQuickCustom,
    };

/// Which part of the draft form [DraftFormFields] shows.
enum DraftFormSection { basics, schedule }

/// The basics (M35) or prices-and-schedule (M36) fields of a draft, shared
/// by the creation wizard and the edit screen (M39). Controllers are made
/// once from [initial]; every change goes to [onChanged].
class DraftFormFields extends StatefulWidget {
  const DraftFormFields({
    required this.section,
    required this.initial,
    required this.form,
    required this.lookups,
    required this.onChanged,
    required this.granularityMinor,
    required this.publishAt,
    this.problems = const {},
    this.serverErrors = const {},
    this.isEnabled,
    this.rulesForPreview,
    this.showReserve = true,
    this.onTouched,
    this.fieldKeys,
    super.key,
  });

  final DraftFormSection section;

  /// Seeds the text controllers.
  final CompetitionDraftForm initial;
  final CompetitionDraftForm form;
  final Lookups lookups;
  final void Function(CompetitionDraftForm Function(CompetitionDraftForm))
  onChanged;

  /// The preset's `amount_granularity_minor` (1 or 100).
  final int granularityMinor;

  /// Server now, for the schedule preview, the quick picks and the pickers.
  final DateTime publishAt;
  final Map<DraftField, DraftProblem> problems;
  final Map<DraftField, String> serverErrors;

  /// Null: every field is editable.
  final bool Function(DraftField field)? isEnabled;

  /// The rules the schedule preview uses (the preset's or the draft's).
  final Rules? rulesForPreview;

  /// The target / reserve price is an advanced rule (RELEASE_SCOPE.md §4):
  /// shown with the `advanced_rules` flag or for an existing value.
  final bool showReserve;

  /// FQ2: called when the user leaves a field (blur) or picks a value.
  final void Function(DraftField field)? onTouched;

  /// Scroll targets for the error summary (FQ8), one per field.
  final Map<DraftField, GlobalKey>? fieldKeys;

  @override
  State<DraftFormFields> createState() => _DraftFormFieldsState();
}

class _DraftFormFieldsState extends State<DraftFormFields> {
  late final TextEditingController _title = TextEditingController(
    text: widget.initial.title,
  );
  late final TextEditingController _description = TextEditingController(
    text: widget.initial.description,
  );
  late final TextEditingController _otherText = TextEditingController(
    text: widget.initial.categoryOtherText,
  );
  late final TextEditingController _startPrice = TextEditingController(
    text: minorToInput(widget.initial.startPriceMinor),
  );
  late final TextEditingController _reservePrice = TextEditingController(
    text: minorToInput(widget.initial.reservePriceMinor),
  );

  @override
  void dispose() {
    for (final controller in [
      _title,
      _description,
      _otherText,
      _startPrice,
      _reservePrice,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  bool _enabled(DraftField field) => widget.isEnabled?.call(field) ?? true;

  String? _error(DraftField field) {
    final server = widget.serverErrors[field];
    if (server != null) return server;
    final problem = widget.problems[field];
    if (problem == null) return null;
    return draftProblemText(
      context.l10n,
      field,
      problem,
      widget.form.direction,
      duration: field == DraftField.closesAt
          ? widget.form.durationFrom(widget.publishAt)
          : null,
    );
  }

  /// A field's scroll target (FQ8) and blur detection (FQ2).
  Widget _field(DraftField field, Widget child) {
    final onTouched = widget.onTouched;
    return KeyedSubtree(
      key: widget.fieldKeys?[field],
      child: onTouched == null
          ? child
          : Focus(
              canRequestFocus: false,
              skipTraversal: true,
              onFocusChange: (focused) {
                if (!focused) onTouched(field);
              },
              child: child,
            ),
    );
  }

  void _touch(DraftField field) => widget.onTouched?.call(field);

  @override
  Widget build(BuildContext context) => switch (widget.section) {
    DraftFormSection.basics => _basics(context),
    DraftFormSection.schedule => _schedule(context),
  };

  Widget _basics(BuildContext context) {
    final l10n = context.l10n;
    final form = widget.form;
    final auction = form.direction == Direction.auction;
    final categories = [
      for (final category in widget.lookups.categories)
        if (!auction ||
            category.auctionAllowed ||
            category.id == form.categoryId)
          category,
    ];
    final selectedCategory = form.categoryId == null
        ? null
        : widget.lookups.categoryById(form.categoryId!);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _field(
          DraftField.title,
          BafoTextField(
            label: l10n.issuerFieldTitle,
            controller: _title,
            required: true,
            maxLength: DraftRules.titleMax,
            helperText: l10n.issuerFieldTitleHelper,
            enabled: _enabled(DraftField.title),
            errorText: _error(DraftField.title),
            textInputAction: TextInputAction.next,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (value) =>
                widget.onChanged((f) => f.copyWith(title: value)),
          ),
        ),
        const SizedBox(height: BafoSpacing.lg),
        _field(
          DraftField.description,
          BafoTextField(
            label: l10n.issuerFieldDescription,
            controller: _description,
            helperText: l10n.issuerFieldDescriptionExample,
            minLines: 3,
            maxLines: 8,
            enabled: _enabled(DraftField.description),
            errorText: _error(DraftField.description),
            keyboardType: TextInputType.multiline,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (value) =>
                widget.onChanged((f) => f.copyWith(description: value)),
          ),
        ),
        const SizedBox(height: BafoSpacing.lg),
        _field(
          DraftField.category,
          BafoDropdown<String>(
            // Required (S7): the same marker as BafoTextField.
            label: '${l10n.competitionsDetailCategory} *',
            value: form.categoryId,
            hint: l10n.commonActionsSelect,
            enabled: _enabled(DraftField.category),
            errorText: _error(DraftField.category),
            items: [
              for (final category in categories)
                BafoDropdownItem(value: category.id, label: category.name),
            ],
            onChanged: (id) {
              widget.onChanged((f) => f.copyWith(categoryId: () => id));
              _touch(DraftField.category);
            },
          ),
        ),
        if (selectedCategory?.isOther ?? false) ...[
          const SizedBox(height: BafoSpacing.lg),
          _field(
            DraftField.categoryOtherText,
            BafoTextField(
              label: l10n.issuerFieldCategoryOther,
              controller: _otherText,
              required: true,
              maxLength: DraftRules.otherTextMax,
              enabled: _enabled(DraftField.categoryOtherText),
              errorText: _error(DraftField.categoryOtherText),
              textCapitalization: TextCapitalization.sentences,
              onChanged: (value) =>
                  widget.onChanged((f) => f.copyWith(categoryOtherText: value)),
            ),
          ),
        ],
        const SizedBox(height: BafoSpacing.lg),
        _field(
          DraftField.region,
          BafoDropdown<String>(
            label: '${l10n.competitionsDetailRegion} *',
            value: form.regionId,
            hint: l10n.commonActionsSelect,
            enabled: _enabled(DraftField.region),
            errorText: _error(DraftField.region),
            items: [
              for (final region in widget.lookups.regions)
                BafoDropdownItem(value: region.id, label: region.name),
            ],
            onChanged: (id) {
              widget.onChanged((f) => f.copyWith(regionId: () => id));
              _touch(DraftField.region);
            },
          ),
        ),
      ],
    );
  }

  // ── Schedule (M36) ─────────────────────────────────────────────────────

  /// A quick pick sets the close from the opening (or the publish time).
  void _pickDuration(ScheduleQuickPick pick) {
    widget.onChanged(
      (f) => f.copyWith(
        scheduledCloseAt: () =>
            quickPickCloseAt(f.effectiveOpensAt, pick, widget.publishAt),
      ),
    );
    _touch(DraftField.closesAt);
  }

  /// «مخصص» hands the close to the date picker.
  void _pickCustom() {
    final active = activeQuickPick(
      widget.form.effectiveOpensAt,
      widget.form.scheduledCloseAt,
      widget.publishAt,
    );
    if (active == ScheduleQuickPick.custom) return;
    widget.onChanged((f) => f.copyWith(scheduledCloseAt: () => null));
  }

  /// Changing the opening while a pick is selected keeps the duration
  /// (RELEASE_SCOPE.md §2.3).
  void _setOpens({bool? opensOnPublish, DateTime? biddingOpensAt}) {
    final now = widget.publishAt;
    widget.onChanged((f) {
      final before = activeQuickPick(
        f.effectiveOpensAt,
        f.scheduledCloseAt,
        now,
      );
      var next = f.copyWith(
        opensOnPublish: opensOnPublish,
        biddingOpensAt: biddingOpensAt == null ? null : () => biddingOpensAt,
      );
      if (before != ScheduleQuickPick.custom) {
        next = next.copyWith(
          scheduledCloseAt: () =>
              quickPickCloseAt(next.effectiveOpensAt, before, now),
        );
      }
      return next;
    });
    _touch(DraftField.opensAt);
  }

  Widget _schedule(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    final form = widget.form;
    final direction = (form.direction ?? Direction.unknown).wire;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final pricesEnabled =
        _enabled(DraftField.startPrice) || _enabled(DraftField.reservePrice);
    final closesAt = form.scheduledCloseAt;
    final rules = widget.rulesForPreview;
    final preview = closesAt == null || rules == null
        ? null
        : SchedulePreview.of(
            opensAt: form.effectiveOpensAt,
            closesAt: closesAt,
            rules: rules,
            publishAt: widget.publishAt,
          );
    final duration = form.durationFrom(widget.publishAt);
    final activePick = activeQuickPick(
      form.effectiveOpensAt,
      closesAt,
      widget.publishAt,
    );
    final closeEnabled = _enabled(DraftField.closesAt);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pricesEnabled) ...[
          SectionHeading(title: l10n.issuerCreatePricesTitle),
          _field(
            DraftField.startPrice,
            MoneyInputField(
              label: l10n.issuerFieldStartPrice(direction),
              controller: _startPrice,
              granularityMinor: widget.granularityMinor,
              required: form.direction == Direction.auction,
              enabled: _enabled(DraftField.startPrice),
              errorText: _error(DraftField.startPrice),
              helperText: l10n.issuerFieldStartPriceHelper(direction),
              textInputAction: TextInputAction.next,
              onAmountChanged: (minor) => widget.onChanged(
                (f) => withAmountText(
                  f.copyWith(startPriceMinor: () => minor),
                  DraftField.startPrice,
                  _startPrice.text,
                  granularityMinor: widget.granularityMinor,
                ),
              ),
            ),
          ),
          if (widget.showReserve) ...[
            const SizedBox(height: BafoSpacing.lg),
            _field(
              DraftField.reservePrice,
              MoneyInputField(
                label: l10n.issuerFieldReservePrice(direction),
                controller: _reservePrice,
                granularityMinor: widget.granularityMinor,
                required: false,
                enabled: _enabled(DraftField.reservePrice),
                errorText: _error(DraftField.reservePrice),
                helperText: l10n.issuerFieldReserveHelper,
                textInputAction: TextInputAction.done,
                onAmountChanged: (minor) => widget.onChanged(
                  (f) => withAmountText(
                    f.copyWith(reservePriceMinor: () => minor),
                    DraftField.reservePrice,
                    _reservePrice.text,
                    granularityMinor: widget.granularityMinor,
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: BafoSpacing.sm),
          Text(l10n.commonPricesExcludeVat, style: muted),
          const SizedBox(height: BafoSpacing.xl),
        ],
        SectionHeading(title: l10n.issuerCreateScheduleTitle),
        _field(
          DraftField.opensAt,
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.competitionsScheduleOpensAt,
                style: theme.textTheme.labelMedium,
              ),
              RadioGroup<bool>(
                groupValue: form.opensOnPublish,
                onChanged: (value) {
                  if (value == null || !_enabled(DraftField.opensAt)) return;
                  _setOpens(opensOnPublish: value);
                },
                child: Column(
                  children: [
                    RadioListTile<bool>(
                      value: true,
                      enabled: _enabled(DraftField.opensAt),
                      contentPadding: EdgeInsetsDirectional.zero,
                      title: Text(l10n.issuerFieldOpensOnPublish),
                    ),
                    RadioListTile<bool>(
                      value: false,
                      enabled: _enabled(DraftField.opensAt),
                      contentPadding: EdgeInsetsDirectional.zero,
                      title: Text(l10n.issuerFieldOpensAtTime),
                    ),
                  ],
                ),
              ),
              if (!form.opensOnPublish) ...[
                DateTimeField(
                  label: l10n.competitionsScheduleOpensAt,
                  value: form.biddingOpensAt,
                  firstDate: widget.publishAt,
                  required: true,
                  enabled: _enabled(DraftField.opensAt),
                  errorText: _error(DraftField.opensAt),
                  helperText: l10n.issuerFieldRiyadhTime,
                  onChanged: (value) => _setOpens(biddingOpensAt: value),
                ),
                const SizedBox(height: BafoSpacing.lg),
              ] else if (_error(DraftField.opensAt) != null) ...[
                Text(
                  _error(DraftField.opensAt)!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.error,
                  ),
                ),
                const SizedBox(height: BafoSpacing.lg),
              ],
            ],
          ),
        ),
        _field(
          DraftField.closesAt,
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.issuerScheduleQuickTitle,
                style: theme.textTheme.labelMedium,
              ),
              const SizedBox(height: BafoSpacing.sm),
              Wrap(
                spacing: BafoSpacing.sm,
                runSpacing: BafoSpacing.sm,
                children: [
                  for (final pick in ScheduleQuickPick.fixed)
                    ChoiceChip(
                      key: ValueKey('quick-${pick.name}'),
                      label: Text(quickPickLabel(l10n, pick)),
                      selected: activePick == pick,
                      onSelected: closeEnabled
                          ? (_) => _pickDuration(pick)
                          : null,
                    ),
                  ChoiceChip(
                    key: const ValueKey('quick-custom'),
                    label: Text(quickPickLabel(l10n, ScheduleQuickPick.custom)),
                    selected: activePick == ScheduleQuickPick.custom,
                    onSelected: closeEnabled ? (_) => _pickCustom() : null,
                  ),
                ],
              ),
              if (form.opensOnPublish) ...[
                const SizedBox(height: BafoSpacing.xs),
                Text(l10n.issuerScheduleQuickFromPublish, style: muted),
              ],
              const SizedBox(height: BafoSpacing.lg),
              DateTimeField(
                label: l10n.competitionsScheduleClosesAt,
                value: closesAt,
                firstDate: form.effectiveOpensAt ?? widget.publishAt,
                required: true,
                enabled: closeEnabled,
                errorText: _error(DraftField.closesAt),
                helperText: l10n.issuerFieldRiyadhTime,
                onChanged: (value) {
                  widget.onChanged(
                    (f) => f.copyWith(scheduledCloseAt: () => value),
                  );
                  _touch(DraftField.closesAt);
                },
              ),
              if (closesAt != null &&
                  duration != null &&
                  duration > Duration.zero) ...[
                const SizedBox(height: BafoSpacing.xs),
                Text(
                  key: const Key('schedule.relativeClose'),
                  l10n.issuerScheduleRelativeClose(
                    scheduleRelativePhrase(l10n, duration),
                    BafoDateFormat.deadline(closesAt, language, l10n),
                  ),
                  style: muted,
                ),
              ],
            ],
          ),
        ),
        if (preview != null) ...[
          const SizedBox(height: BafoSpacing.lg),
          SchedulePreviewCard(preview: preview),
        ],
      ],
    );
  }
}

/// A form section title.
class SectionHeading extends StatelessWidget {
  const SectionHeading({required this.title, super.key});

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.md),
    child: Semantics(
      header: true,
      child: Text(title, style: Theme.of(context).textTheme.titleMedium),
    ),
  );
}

/// The estimated timeline of a draft (SCREENS.md G3): labelled
/// «تقديري، يُثبَّت عند النشر».
class SchedulePreviewCard extends StatelessWidget {
  const SchedulePreviewCard({required this.preview, super.key});

  final SchedulePreview preview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final language = context.languageCode;
    String date(DateTime value) => BafoDateFormat.dateTime(value, language);
    final opens = preview.opensAt;
    final finalWindow = preview.finalWindowStartsAt;
    final cutoff = preview.invitationCutoffAt;
    final hardStop = preview.hardStopAt;
    return BafoCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l10n.issuerSchedulePreviewTitle,
            style: theme.textTheme.titleSmall,
          ),
          const SizedBox(height: BafoSpacing.xxs),
          Text(
            l10n.issuerSchedulePreviewNote,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: BafoSpacing.md),
          KeyValueList(
            items: [
              KeyValue(
                l10n.competitionsScheduleOpensAt,
                opens == null
                    ? l10n.competitionsScheduleOpensOnPublish
                    : date(opens),
              ),
              if (finalWindow != null)
                KeyValue(
                  l10n.competitionsScheduleFinalWindow,
                  date(finalWindow),
                ),
              if (cutoff != null)
                KeyValue(l10n.competitionsScheduleJoinDeadline, date(cutoff)),
              KeyValue(
                l10n.competitionsScheduleClosesAt,
                date(preview.closesAt),
              ),
              if (hardStop != null)
                KeyValue(l10n.issuerTimelineHardStop, date(hardStop)),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.issuerFieldRiyadhTime,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}
