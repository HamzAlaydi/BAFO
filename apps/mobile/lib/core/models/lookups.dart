import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:equatable/equatable.dart';

/// A region (API.md §2.4). `name` is in the request language.
final class Region extends Equatable {
  const Region({required this.id, required this.code, required this.name});

  factory Region.fromJson(Json json) => Region(
    id: json.str('id'),
    code: json.str('code'),
    name: json.str('name'),
  );

  final String id;
  final String code;
  final String name;

  @override
  List<Object?> get props => [id, code, name];
}

/// A category. [isOther] needs `category_other_text`; auctions are refused
/// in categories without [auctionAllowed].
final class Category extends Equatable {
  const Category({
    required this.id,
    required this.code,
    required this.name,
    this.isOther = false,
    this.auctionAllowed = true,
  });

  factory Category.fromJson(Json json) => Category(
    id: json.str('id'),
    code: json.str('code'),
    name: json.str('name'),
    isOther: json.flag('is_other'),
    auctionAllowed: json.flag('auction_allowed', fallback: true),
  );

  final String id;
  final String code;
  final String name;
  final bool isOther;
  final bool auctionAllowed;

  @override
  List<Object?> get props => [id, code, name, isOther, auctionAllowed];
}

enum CloseReasonKind implements WireEnum {
  cancel('cancel'),
  notAwarded('not_awarded'),
  awardJustification('award_justification'),
  voidOffer('void_offer'),
  unknown('unknown');

  const CloseReasonKind(this.wire);

  @override
  final String wire;

  static CloseReasonKind parse(Object? raw) => parseWire(values, raw, unknown);
}

/// A reason for cancelling, closing without award, justifying an award or
/// voiding an offer. [requiresNote] makes the note field required.
final class CloseReason extends Equatable {
  const CloseReason({
    required this.id,
    required this.code,
    required this.kind,
    required this.name,
    this.requiresNote = false,
  });

  factory CloseReason.fromJson(Json json) => CloseReason(
    id: json.str('id'),
    code: json.str('code'),
    kind: CloseReasonKind.parse(json['kind']),
    name: json.str('name'),
    requiresNote: json.flag('requires_note'),
  );

  final String id;
  final String code;
  final CloseReasonKind kind;
  final String name;
  final bool requiresNote;

  @override
  List<Object?> get props => [id, code, kind, name, requiresNote];
}

/// The level of a tiered preset (RELEASE_SCOPE.md §2.2): the three cards of
/// the rules step, from the fewest to the most protective rules.
enum PresetTier implements WireEnum {
  simple('simple'),
  standard('standard'),
  protected('protected'),
  unknown('unknown');

  const PresetTier(this.wire);

  @override
  final String wire;

  /// Null when the preset has no tier (the legacy templates).
  static PresetTier? parse(Object? raw) =>
      parseWireOrNull(values, raw, unknown);
}

/// A rules preset (no prices). Mobile creates drafts from presets only
/// (SCREENS.md CD4). Tiered presets ([tier] set) are the cards of the
/// creation step; untiered ones are the «قوالب أخرى» of the web rules step.
final class Preset extends Equatable {
  const Preset({
    required this.id,
    required this.code,
    required this.name,
    required this.direction,
    required this.format,
    required this.rules,
    this.description,
    this.tier,
  });

  factory Preset.fromJson(Json json) => Preset(
    id: json.str('id'),
    code: json.str('code'),
    name: json.str('name'),
    description: json.strOrNull('description'),
    direction: Direction.parse(json['direction']),
    format: CompetitionFormat.parse(json['format']),
    rules: Rules.fromJson(json.obj('rules')),
    tier: PresetTier.parse(json['tier']),
  );

  final String id;
  final String code;
  final String name;
  final String? description;
  final Direction direction;
  final CompetitionFormat format;
  final Rules rules;

  /// `tier` (RELEASE_SCOPE.md §2.2); null for untiered presets.
  final PresetTier? tier;

  /// Whether this preset is one of the three tier cards.
  bool get isTiered => tier != null && tier != PresetTier.unknown;

  @override
  List<Object?> get props => [
    id,
    code,
    name,
    description,
    direction,
    format,
    rules,
    tier,
  ];
}

/// `GET /lookups`: active rows ordered by `sort_order`.
final class Lookups extends Equatable {
  const Lookups({
    this.regions = const [],
    this.categories = const [],
    this.closeReasons = const [],
    this.presets = const [],
  });

  factory Lookups.fromJson(Json json) => Lookups(
    regions: json.list('regions', Region.fromJson),
    categories: json.list('categories', Category.fromJson),
    closeReasons: json.list('close_reasons', CloseReason.fromJson),
    presets: json.list('presets', Preset.fromJson),
  );

  final List<Region> regions;
  final List<Category> categories;
  final List<CloseReason> closeReasons;
  final List<Preset> presets;

  List<CloseReason> closeReasonsOf(CloseReasonKind kind) =>
      closeReasons.where((reason) => reason.kind == kind).toList();

  Region? regionById(String id) =>
      regions.where((region) => region.id == id).firstOrNull;

  Category? categoryById(String id) =>
      categories.where((category) => category.id == id).firstOrNull;

  @override
  List<Object?> get props => [regions, categories, closeReasons, presets];
}
