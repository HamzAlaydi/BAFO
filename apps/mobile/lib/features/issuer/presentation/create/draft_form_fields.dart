import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// Halalas as input text: `25000000` → `250000`, `12550` → `125.50`.
String minorToInput(int? minor) {
  if (minor == null) return '';
  final major = minor ~/ Money.minorPerMajor;
  final fraction = minor % Money.minorPerMajor;
  return fraction == 0 ? '$major' : '$major.${'$fraction'.padLeft(2, '0')}';
}

/// The message for a client [problem] of [field] (S7). The server's
/// message, when there is one, is shown instead.
String draftProblemText(
  AppLocalizations l10n,
  DraftField field,
  DraftProblem problem,
  Direction? direction,
) => switch (problem) {
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
  DraftProblem.tooShort => l10n.issuerFieldDurationTooShort(
    DraftRules.minDurationMinutes,
  ),
  DraftProblem.durationTooLong => l10n.issuerFieldDurationTooLong(
    DraftRules.maxDurationDays,
  ),
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

  /// Server now, for the schedule preview and date pickers.
  final DateTime publishAt;
  final Map<DraftField, DraftProblem> problems;
  final Map<DraftField, String> serverErrors;

  /// Null: every field is editable.
  final bool Function(DraftField field)? isEnabled;

  /// The rules the schedule preview uses (the preset's or the draft's).
  final Rules? rulesForPreview;

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
    );
  }

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
        BafoTextField(
          label: l10n.issuerFieldTitle,
          controller: _title,
          required: true,
          maxLength: DraftRules.titleMax,
          enabled: _enabled(DraftField.title),
          errorText: _error(DraftField.title),
          textInputAction: TextInputAction.next,
          onChanged: (value) =>
              widget.onChanged((f) => f.copyWith(title: value)),
        ),
        const SizedBox(height: BafoSpacing.lg),
        BafoTextField(
          label: l10n.issuerFieldDescription,
          controller: _description,
          helperText: l10n.issuerFieldDescriptionHelper,
          minLines: 3,
          maxLines: 8,
          enabled: _enabled(DraftField.description),
          errorText: _error(DraftField.description),
          keyboardType: TextInputType.multiline,
          onChanged: (value) =>
              widget.onChanged((f) => f.copyWith(description: value)),
        ),
        const SizedBox(height: BafoSpacing.lg),
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
          onChanged: (id) =>
              widget.onChanged((f) => f.copyWith(categoryId: () => id)),
        ),
        if (selectedCategory?.isOther ?? false) ...[
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            label: l10n.issuerFieldCategoryOther,
            controller: _otherText,
            required: true,
            maxLength: DraftRules.otherTextMax,
            enabled: _enabled(DraftField.categoryOtherText),
            errorText: _error(DraftField.categoryOtherText),
            onChanged: (value) =>
                widget.onChanged((f) => f.copyWith(categoryOtherText: value)),
          ),
        ],
        const SizedBox(height: BafoSpacing.lg),
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
          onChanged: (id) =>
              widget.onChanged((f) => f.copyWith(regionId: () => id)),
        ),
      ],
    );
  }

  Widget _schedule(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final form = widget.form;
    final direction = (form.direction ?? Direction.unknown).wire;
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pricesEnabled) ...[
          SectionHeading(title: l10n.issuerCreatePricesTitle),
          MoneyInputField(
            label: l10n.issuerFieldStartPrice(direction),
            controller: _startPrice,
            granularityMinor: widget.granularityMinor,
            required: form.direction == Direction.auction,
            enabled: _enabled(DraftField.startPrice),
            errorText: _error(DraftField.startPrice),
            helperText: l10n.issuerFieldStartPriceHelper(direction),
            onAmountChanged: (minor) => widget.onChanged(
              (f) => f.copyWith(startPriceMinor: () => minor),
            ),
          ),
          const SizedBox(height: BafoSpacing.lg),
          MoneyInputField(
            label: l10n.issuerFieldReservePrice(direction),
            controller: _reservePrice,
            granularityMinor: widget.granularityMinor,
            required: false,
            enabled: _enabled(DraftField.reservePrice),
            errorText: _error(DraftField.reservePrice),
            helperText: l10n.issuerFieldReserveHelper,
            onAmountChanged: (minor) => widget.onChanged(
              (f) => f.copyWith(reservePriceMinor: () => minor),
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            l10n.commonPricesExcludeVat,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: BafoSpacing.xl),
        ],
        SectionHeading(title: l10n.issuerCreateScheduleTitle),
        Text(
          l10n.competitionsScheduleOpensAt,
          style: theme.textTheme.labelMedium,
        ),
        RadioGroup<bool>(
          groupValue: form.opensOnPublish,
          onChanged: (value) {
            if (value == null || !_enabled(DraftField.opensAt)) return;
            widget.onChanged((f) => f.copyWith(opensOnPublish: value));
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
            onChanged: (value) => widget.onChanged(
              (f) => f.copyWith(biddingOpensAt: () => value),
            ),
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
        DateTimeField(
          label: l10n.competitionsScheduleClosesAt,
          value: form.scheduledCloseAt,
          firstDate: form.effectiveOpensAt ?? widget.publishAt,
          required: true,
          enabled: _enabled(DraftField.closesAt),
          errorText: _error(DraftField.closesAt),
          helperText: l10n.issuerFieldRiyadhTime,
          onChanged: (value) => widget.onChanged(
            (f) => f.copyWith(scheduledCloseAt: () => value),
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
