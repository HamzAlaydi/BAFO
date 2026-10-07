import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:equatable/equatable.dart';

/// The four mobile creation steps (SCREENS.md M34–M37). Advanced rules are
/// web-only (CD4): mobile picks a preset and sets prices and schedule.
enum CreateStep {
  /// M34: direction, format and preset.
  type,

  /// M35: title, description, category, region.
  basics,

  /// M36: prices and schedule.
  schedule,

  /// M37: review and save the draft.
  review;

  int get number => index + 1;

  static const int count = 4;
}

/// A field of the creation form (and of the draft edit form).
enum DraftField {
  direction,
  format,
  preset,
  title,
  description,
  category,
  categoryOtherText,
  region,
  startPrice,
  reservePrice,
  opensAt,
  closesAt;

  /// The step that shows this field.
  CreateStep get step => switch (this) {
    direction || format || preset => CreateStep.type,
    title ||
    description ||
    category ||
    categoryOtherText ||
    region => CreateStep.basics,
    startPrice || reservePrice || opensAt || closesAt => CreateStep.schedule,
  };

  /// The field a server validation path (API.md §0.4 `errors`) belongs to,
  /// or null when the path has no control on mobile (it then goes into the
  /// form-level alert, SCREENS.md S7).
  static DraftField? fromServerPath(String path) => switch (path) {
    'direction' => direction,
    'format' => format,
    'preset_code' => preset,
    'title' => title,
    'description' => description,
    'category_id' => category,
    'category_other_text' => categoryOtherText,
    'region_id' => region,
    'rules.start_price_minor' || 'start_price_minor' => startPrice,
    'rules.reserve_price_minor' => reservePrice,
    'bidding_opens_at' => opensAt,
    'scheduled_close_at' => closesAt,
    _ => null,
  };
}

/// Splits a `validation_failed` answer (S7): messages bound to the form's
/// fields, and the rest (paths without a control on mobile, such as
/// `rules.min_step_bps`) as an error for the form-level alert, or null.
({Map<DraftField, String> fields, ApiException? rest}) splitServerErrors(
  ApiException error,
) {
  final fields = <DraftField, String>{};
  final rest = <String, List<String>>{};
  for (final entry in error.fieldErrors.entries) {
    final field = DraftField.fromServerPath(entry.key);
    if (field != null && entry.value.isNotEmpty) {
      fields.putIfAbsent(field, () => entry.value.first);
    } else if (field == null) {
      rest[entry.key] = entry.value;
    }
  }
  if (fields.isEmpty) return (fields: fields, rest: error);
  return (
    fields: fields,
    rest: rest.isEmpty
        ? null
        : ApiException(
            code: error.code,
            message: error.message,
            fieldErrors: rest,
            details: error.details,
            statusCode: error.statusCode,
          ),
  );
}

/// Why a field is not acceptable yet. Client checks mirror the API rules
/// for feedback only; the server decides (SCREENS.md S7).
enum DraftProblem {
  required,
  tooLong,

  /// The category does not allow auctions (R14).
  auctionNotAllowed,

  /// R6: tender reserve ≤ start, auction reserve ≥ start.
  reserveVersusStart,

  /// R16: the close must come after the opening.
  closeBeforeOpen,

  /// R16 at publish: the opening cannot be in the past.
  opensInPast,

  /// R16: at least [DraftRules.minDurationMinutes].
  tooShort,

  /// R16: at most [DraftRules.maxDurationDays].
  durationTooLong,

  /// The typed amount cannot be read (FQ2, FQ8): «1.2.3», more than two
  /// decimals. Its value is null meanwhile, so the step must not move on.
  amountInvalid,

  /// The typed amount is zero.
  amountNotPositive,

  /// The typed amount has halalas where only whole riyals are allowed.
  amountWholeRiyals,
}

/// Mirrors of the server's default settings, used for hints only.
abstract final class DraftRules {
  static const int titleMax = 200;
  static const int descriptionMax = 20000;
  static const int otherTextMax = 150;

  /// Setting `competitions.min_duration_minutes`.
  static const int minDurationMinutes = 10;

  /// Setting `competitions.max_duration_days`.
  static const int maxDurationDays = 90;

  /// Setting `competitions.invite_cutoff_minutes`.
  static const int inviteCutoffMinutes = 60;
}

/// The mobile creation form (M34–M37). Immutable; the cubit replaces it.
final class CompetitionDraftForm extends Equatable {
  const CompetitionDraftForm({
    this.direction,
    this.format,
    this.presetCode,
    this.title = '',
    this.description = '',
    this.categoryId,
    this.categoryOtherText = '',
    this.regionId,
    this.startPriceMinor,
    this.reservePriceMinor,
    this.opensOnPublish = true,
    this.biddingOpensAt,
    this.scheduledCloseAt,
    this.unreadableAmounts = const {},
  });

  /// The local autosave shape (RELEASE_SCOPE.md FQ6): what the user typed,
  /// kept in `PreferencesStore` until `POST /competitions` succeeds.
  factory CompetitionDraftForm.fromJson(Json json) => CompetitionDraftForm(
    direction: json['direction'] == null
        ? null
        : Direction.parse(json['direction']),
    format: json['format'] == null
        ? null
        : CompetitionFormat.parse(json['format']),
    presetCode: json.strOrNull('preset_code'),
    title: json.strOrNull('title') ?? '',
    description: json.strOrNull('description') ?? '',
    categoryId: json.strOrNull('category_id'),
    categoryOtherText: json.strOrNull('category_other_text') ?? '',
    regionId: json.strOrNull('region_id'),
    startPriceMinor: json.intOrNull('start_price_minor'),
    reservePriceMinor: json.intOrNull('reserve_price_minor'),
    opensOnPublish: json.flag('opens_on_publish', fallback: true),
    biddingOpensAt: json.dateOrNull('bidding_opens_at'),
    scheduledCloseAt: json.dateOrNull('scheduled_close_at'),
  );

  final Direction? direction;
  final CompetitionFormat? format;
  final String? presetCode;
  final String title;
  final String description;
  final String? categoryId;
  final String categoryOtherText;
  final String? regionId;

  /// Ceiling price (tender) or opening price (auction), in halalas.
  final int? startPriceMinor;

  /// Target price (tender) or reserve price (auction): issuer only.
  final int? reservePriceMinor;

  /// True: offers open when the competition is published.
  final bool opensOnPublish;
  final DateTime? biddingOpensAt;
  final DateTime? scheduledCloseAt;

  /// Amount fields whose typed text cannot be used (FQ2, FQ8), with why. Their
  /// halalas are null meanwhile; without this the step would move on and save
  /// no price. Not autosaved: a restored form shows the saved amounts.
  final Map<DraftField, DraftProblem> unreadableAmounts;

  Json toJson() => {
    'direction': direction?.wire,
    'format': format?.wire,
    'preset_code': presetCode,
    'title': title,
    'description': description,
    'category_id': categoryId,
    'category_other_text': categoryOtherText,
    'region_id': regionId,
    'start_price_minor': startPriceMinor,
    'reserve_price_minor': reservePriceMinor,
    'opens_on_publish': opensOnPublish,
    'bidding_opens_at': biddingOpensAt == null
        ? null
        : toApiTime(biddingOpensAt!),
    'scheduled_close_at': scheduledCloseAt == null
        ? null
        : toApiTime(scheduledCloseAt!),
  };

  /// The opening that is sent: null when it opens on publish.
  DateTime? get effectiveOpensAt => opensOnPublish ? null : biddingOpensAt;

  /// The duration offers stay open, counted from the opening or from [now]
  /// (publish) when they open on publish; null without a closing time.
  Duration? durationFrom(DateTime now) {
    final close = scheduledCloseAt;
    if (close == null) return null;
    return close.difference(effectiveOpensAt ?? now);
  }

  CompetitionDraftForm copyWith({
    Direction? direction,
    CompetitionFormat? format,
    String? Function()? presetCode,
    String? title,
    String? description,
    String? Function()? categoryId,
    String? categoryOtherText,
    String? Function()? regionId,
    int? Function()? startPriceMinor,
    int? Function()? reservePriceMinor,
    bool? opensOnPublish,
    DateTime? Function()? biddingOpensAt,
    DateTime? Function()? scheduledCloseAt,
    Map<DraftField, DraftProblem>? unreadableAmounts,
  }) => CompetitionDraftForm(
    direction: direction ?? this.direction,
    format: format ?? this.format,
    presetCode: presetCode == null ? this.presetCode : presetCode(),
    title: title ?? this.title,
    description: description ?? this.description,
    categoryId: categoryId == null ? this.categoryId : categoryId(),
    categoryOtherText: categoryOtherText ?? this.categoryOtherText,
    regionId: regionId == null ? this.regionId : regionId(),
    startPriceMinor: startPriceMinor == null
        ? this.startPriceMinor
        : startPriceMinor(),
    reservePriceMinor: reservePriceMinor == null
        ? this.reservePriceMinor
        : reservePriceMinor(),
    opensOnPublish: opensOnPublish ?? this.opensOnPublish,
    biddingOpensAt: biddingOpensAt == null
        ? this.biddingOpensAt
        : biddingOpensAt(),
    scheduledCloseAt: scheduledCloseAt == null
        ? this.scheduledCloseAt
        : scheduledCloseAt(),
    unreadableAmounts: unreadableAmounts ?? this.unreadableAmounts,
  );

  /// Presets that match the chosen direction and format.
  List<Preset> presetsFor(List<Preset> presets) => [
    for (final preset in presets)
      if (preset.direction == direction && preset.format == format) preset,
  ];

  /// The tier cards of the chosen direction and format (RELEASE_SCOPE.md
  /// §2.2), ordered simple → standard → protected.
  List<Preset> tieredPresetsFor(List<Preset> presets) => [
    for (final preset in presetsFor(presets))
      if (preset.isTiered) preset,
  ]..sort((a, b) => a.tier!.index.compareTo(b.tier!.index));

  /// The untiered («قوالب أخرى») presets of the chosen direction and format.
  List<Preset> untieredPresetsFor(List<Preset> presets) => [
    for (final preset in presetsFor(presets))
      if (!preset.isTiered) preset,
  ];

  Preset? presetIn(List<Preset> presets) =>
      presets.where((preset) => preset.code == presetCode).firstOrNull;

  /// The problems of [step] (empty when the step is complete). [now] is
  /// server time.
  Map<DraftField, DraftProblem> validate(
    CreateStep step, {
    required Lookups lookups,
    required DateTime now,
  }) {
    final problems = <DraftField, DraftProblem>{};
    switch (step) {
      case CreateStep.type:
        if (direction == null || direction == Direction.unknown) {
          problems[DraftField.direction] = DraftProblem.required;
        }
        if (format == null || format == CompetitionFormat.unknown) {
          problems[DraftField.format] = DraftProblem.required;
        }
        // CONTRACT-GAP: the API accepts `preset_code: null` (column
        // defaults), which can break R1 for sealed competitions; mobile has no
        // rules editor (CD4), so it requires a matching preset. Asked only
        // once the direction and format exist (the cards are not shown
        // before, and the summary would name a choice the user cannot see).
        if (!problems.containsKey(DraftField.direction) &&
            !problems.containsKey(DraftField.format) &&
            presetIn(presetsFor(lookups.presets)) == null) {
          problems[DraftField.preset] = DraftProblem.required;
        }
      case CreateStep.basics:
        final trimmed = title.trim();
        if (trimmed.isEmpty) {
          problems[DraftField.title] = DraftProblem.required;
        } else if (trimmed.length > DraftRules.titleMax) {
          problems[DraftField.title] = DraftProblem.tooLong;
        }
        if (description.length > DraftRules.descriptionMax) {
          problems[DraftField.description] = DraftProblem.tooLong;
        }
        final category = categoryId == null
            ? null
            : lookups.categoryById(categoryId!);
        if (category == null) {
          problems[DraftField.category] = DraftProblem.required;
        } else {
          if (direction == Direction.auction && !category.auctionAllowed) {
            problems[DraftField.category] = DraftProblem.auctionNotAllowed;
          }
          if (category.isOther) {
            final other = categoryOtherText.trim();
            if (other.isEmpty) {
              problems[DraftField.categoryOtherText] = DraftProblem.required;
            } else if (other.length > DraftRules.otherTextMax) {
              problems[DraftField.categoryOtherText] = DraftProblem.tooLong;
            }
          }
        }
        if (regionId == null || lookups.regionById(regionId!) == null) {
          problems[DraftField.region] = DraftProblem.required;
        }
      case CreateStep.schedule:
        final start = startPriceMinor;
        final reserve = reservePriceMinor;
        if (direction == Direction.auction && start == null) {
          problems[DraftField.startPrice] = DraftProblem.required;
        }
        if (start != null &&
            reserve != null &&
            direction != null &&
            !direction!.satisfiesBound(reserve, start)) {
          problems[DraftField.reservePrice] = DraftProblem.reserveVersusStart;
        }
        final opens = effectiveOpensAt;
        if (!opensOnPublish) {
          if (opens == null) {
            problems[DraftField.opensAt] = DraftProblem.required;
          } else if (opens.isBefore(now)) {
            problems[DraftField.opensAt] = DraftProblem.opensInPast;
          }
        }
        final close = scheduledCloseAt;
        final from = opens ?? now;
        if (close == null) {
          problems[DraftField.closesAt] = DraftProblem.required;
        } else if (!close.isAfter(from)) {
          problems[DraftField.closesAt] = DraftProblem.closeBeforeOpen;
        } else if (close.difference(from) <
            const Duration(minutes: DraftRules.minDurationMinutes)) {
          problems[DraftField.closesAt] = DraftProblem.tooShort;
        } else if (close.difference(from) >
            const Duration(days: DraftRules.maxDurationDays)) {
          problems[DraftField.closesAt] = DraftProblem.durationTooLong;
        }
        // The text wins over checks made on the last usable value.
        problems.addAll(unreadableAmounts);
      case CreateStep.review:
        for (final earlier in [
          CreateStep.type,
          CreateStep.basics,
          CreateStep.schedule,
        ]) {
          problems.addAll(validate(earlier, lookups: lookups, now: now));
        }
    }
    return problems;
  }

  /// `POST /competitions`: the preset's rules plus the prices (the client
  /// always sends the full rules object with `preset_code`, SCREENS.md G4).
  CompetitionDraftInput toInput(Preset preset, Lookups lookups) {
    final category = categoryId == null
        ? null
        : lookups.categoryById(categoryId!);
    final description = this.description.trim();
    return CompetitionDraftInput(
      title: title.trim(),
      description: description.isEmpty ? null : description,
      categoryId: categoryId!,
      categoryOtherText: (category?.isOther ?? false)
          ? categoryOtherText.trim()
          : null,
      regionId: regionId!,
      direction: direction!,
      format: format!,
      presetCode: preset.code,
      rules: preset.rules.copyWith(
        startPriceMinor: () => startPriceMinor,
        reservePriceMinor: () => reservePriceMinor,
      ),
      biddingOpensAt: effectiveOpensAt,
      scheduledCloseAt: scheduledCloseAt,
    );
  }

  @override
  List<Object?> get props => [
    direction,
    format,
    presetCode,
    title,
    description,
    categoryId,
    categoryOtherText,
    regionId,
    startPriceMinor,
    reservePriceMinor,
    opensOnPublish,
    biddingOpensAt,
    scheduledCloseAt,
    unreadableAmounts,
  ];
}
