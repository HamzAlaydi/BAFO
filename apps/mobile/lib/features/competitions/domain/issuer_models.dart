import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:equatable/equatable.dart';

/// `POST /competitions` (and `PATCH` of a draft): mobile creates drafts from
/// a preset plus prices and schedule (SCREENS.md CD4).
final class CompetitionDraftInput extends Equatable {
  const CompetitionDraftInput({
    required this.title,
    required this.categoryId,
    required this.regionId,
    required this.direction,
    required this.format,
    this.description,
    this.categoryOtherText,
    this.presetCode,
    this.rules,
    this.biddingOpensAt,
    this.scheduledCloseAt,
  });

  final String title;
  final String? description;
  final String categoryId;

  /// Required when the category is "other".
  final String? categoryOtherText;
  final String regionId;
  final Direction direction;
  final CompetitionFormat format;
  final String? presetCode;

  /// The full rules object (SCREENS.md G4); prices ride here.
  final Rules? rules;

  /// Null opens on publish.
  final DateTime? biddingOpensAt;
  final DateTime? scheduledCloseAt;

  Json toJson() => {
    'title': title.trim(),
    'description': description,
    'category_id': categoryId,
    'category_other_text': categoryOtherText,
    'region_id': regionId,
    'direction': direction.wire,
    'format': format.wire,
    'preset_code': presetCode,
    if (rules != null) 'rules': rules!.toJson(),
    'bidding_opens_at': biddingOpensAt == null
        ? null
        : toApiTime(biddingOpensAt!),
    'scheduled_close_at': scheduledCloseAt == null
        ? null
        : toApiTime(scheduledCloseAt!),
  };

  @override
  List<Object?> get props => [
    title,
    description,
    categoryId,
    categoryOtherText,
    regionId,
    direction,
    format,
    presetCode,
    rules,
    biddingOpensAt,
    scheduledCloseAt,
  ];
}

/// Pass counts of a sponsorship.
final class SponsorshipCounts extends Equatable {
  const SponsorshipCounts({
    this.pending = 0,
    this.reserved = 0,
    this.joined = 0,
    this.released = 0,
    this.unused = 0,
    this.voided = 0,
    this.freeSlots = 0,
  });

  factory SponsorshipCounts.fromJson(Json json) => SponsorshipCounts(
    pending: json.intOrNull('pending') ?? 0,
    reserved: json.intOrNull('reserved') ?? 0,
    joined: json.intOrNull('joined') ?? 0,
    released: json.intOrNull('released') ?? 0,
    unused: json.intOrNull('unused') ?? 0,
    voided: json.intOrNull('void') ?? 0,
    freeSlots: json.intOrNull('free_slots') ?? 0,
  );

  final int pending;
  final int reserved;
  final int joined;
  final int released;
  final int unused;
  final int voided;
  final int freeSlots;

  @override
  List<Object?> get props => [
    pending,
    reserved,
    joined,
    released,
    unused,
    voided,
    freeSlots,
  ];
}

/// `Sponsorship` (API.md §2.10), read-only on mobile: counters only, never
/// prices (entitlement-only rule, SCREENS.md §3.2).
final class Sponsorship extends Equatable {
  const Sponsorship({
    required this.mode,
    this.maxPasses,
    this.status,
    this.fundedPasses = 0,
    this.enabled = false,
    this.counts = const SponsorshipCounts(),
    this.unusedCount,
    this.voucherCode,
  });

  factory Sponsorship.fromJson(Json json) => Sponsorship(
    mode: json.strOrNull('mode') ?? 'none',
    maxPasses: json.intOrNull('max_passes'),
    status: json.strOrNull('status'),
    fundedPasses: json.intOrNull('funded_passes') ?? 0,
    enabled: json.flag('enabled'),
    counts:
        json.parse('counts', SponsorshipCounts.fromJson) ??
        const SponsorshipCounts(),
    unusedCount: json.intOrNull('unused_count'),
    voucherCode: json.objOrNull('voucher')?.strOrNull('code'),
  );

  /// `none`, `all` or `selected`.
  final String mode;
  final int? maxPasses;

  /// `draft`, `active`, `settled` or null.
  final String? status;
  final int fundedPasses;

  /// The organisation flag and the global setting are both on.
  final bool enabled;
  final SponsorshipCounts counts;
  final int? unusedCount;
  final String? voucherCode;

  bool get isNone => mode == 'none';

  @override
  List<Object?> get props => [
    mode,
    maxPasses,
    status,
    fundedPasses,
    enabled,
    counts,
    unusedCount,
    voucherCode,
  ];
}

/// A row of `GET …/suggestions`: an organisation to invite.
final class Suggestion extends Equatable {
  const Suggestion({
    required this.organization,
    this.region,
    this.categories = const [],
    this.hasActivePlan = false,
    this.matchesCategory = false,
    this.matchesRegion = false,
  });

  factory Suggestion.fromJson(Json json) {
    final match = json.objOrNull('match') ?? const {};
    return Suggestion(
      organization: OrganizationSummary.fromJson(json.obj('organization')),
      region: json.parse('region', Region.fromJson),
      categories: json.list('categories', Category.fromJson),
      hasActivePlan: json.flag('has_active_plan'),
      matchesCategory: match.flag('category'),
      matchesRegion: match.flag('region'),
    );
  }

  final OrganizationSummary organization;
  final Region? region;
  final List<Category> categories;
  final bool hasActivePlan;
  final bool matchesCategory;
  final bool matchesRegion;

  @override
  List<Object?> get props => [
    organization,
    region,
    categories,
    hasActivePlan,
    matchesCategory,
    matchesRegion,
  ];
}

/// An ERP reference of a vendor (API.md §2.12).
final class ExternalRef extends Equatable {
  const ExternalRef({
    required this.system,
    required this.type,
    required this.id,
    this.number,
    this.url,
  });

  factory ExternalRef.fromJson(Json json) => ExternalRef(
    system: json.str('system'),
    type: json.str('type'),
    id: json.str('id'),
    number: json.strOrNull('number'),
    url: json.strOrNull('url'),
  );

  final String system;
  final String type;

  /// The ERP key.
  final String id;
  final String? number;
  final String? url;

  @override
  List<Object?> get props => [system, type, id, number, url];
}

/// A vendor of the issuer's directory (invite picker on mobile, M40).
final class Vendor extends Equatable {
  const Vendor({
    required this.id,
    required this.name,
    required this.email,
    this.nameEn,
    this.crNumber,
    this.vatNumber,
    this.contactName,
    this.phone,
    this.region,
    this.city,
    this.categories = const [],
    this.status = 'active',
    this.linkedOrganization,
    this.source,
    this.externalRefs = const [],
  });

  factory Vendor.fromJson(Json json) => Vendor(
    id: json.str('id'),
    name: json.str('name'),
    nameEn: json.strOrNull('name_en'),
    crNumber: json.strOrNull('cr_number'),
    vatNumber: json.strOrNull('vat_number'),
    email: json.str('email'),
    contactName: json.strOrNull('contact_name'),
    phone: json.strOrNull('phone'),
    region: json.parse('region', Region.fromJson),
    city: json.strOrNull('city'),
    categories: json.list('categories', Category.fromJson),
    status: json.strOrNull('status') ?? 'active',
    linkedOrganization: json.parse(
      'linked_organization',
      OrganizationSummary.fromJson,
    ),
    source: json.strOrNull('source'),
    externalRefs: json.list('external_refs', ExternalRef.fromJson),
  );

  final String id;
  final String name;
  final String? nameEn;
  final String? crNumber;
  final String? vatNumber;
  final String email;
  final String? contactName;
  final String? phone;
  final Region? region;
  final String? city;
  final List<Category> categories;

  /// `active`, `blocked` or `archived`.
  final String status;

  /// Set when the vendor has a BAFO account («مسجّلة في بافو»).
  final OrganizationSummary? linkedOrganization;
  final String? source;
  final List<ExternalRef> externalRefs;

  @override
  List<Object?> get props => [
    id,
    name,
    nameEn,
    crNumber,
    vatNumber,
    email,
    contactName,
    phone,
    region,
    city,
    categories,
    status,
    linkedOrganization,
    source,
    externalRefs,
  ];
}

/// `Report` (issuer result PDF; not in the MVP app, kept for completeness).
final class CompetitionReport extends Equatable {
  const CompetitionReport({
    required this.status,
    this.locale,
    this.generatedAt,
    this.file,
  });

  factory CompetitionReport.fromJson(Json json) => CompetitionReport(
    status: json.str('status'),
    locale: json.strOrNull('locale'),
    generatedAt: json.dateOrNull('generated_at'),
    file: json.parse('file', FileRef.fromJson),
  );

  /// `pending`, `ready` or `failed`.
  final String status;
  final String? locale;
  final DateTime? generatedAt;
  final FileRef? file;

  @override
  List<Object?> get props => [status, locale, generatedAt, file];
}
