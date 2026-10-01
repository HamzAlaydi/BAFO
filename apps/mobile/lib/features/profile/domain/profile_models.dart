import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:equatable/equatable.dart';

/// The editable organisation fields for `PATCH /organization` (every
/// register field except `cr_number`, which is immutable). The form sends
/// all of them on save.
final class OrganizationUpdate extends Equatable {
  const OrganizationUpdate({
    required this.name,
    required this.regionId,
    required this.city,
    this.vatRegistered = false,
    this.vatNumber,
    this.legalNameAr,
    this.legalNameEn,
    this.website,
    this.nationalAddress = const NationalAddress(),
    this.categoryIds = const [],
    this.visibleInSuggestions = true,
  });

  /// Pre-fills the form from the current organisation.
  factory OrganizationUpdate.from(Organization organization) =>
      OrganizationUpdate(
        name: organization.name,
        regionId: organization.region?.id ?? '',
        city: organization.city ?? '',
        vatRegistered: organization.vatRegistered,
        vatNumber: organization.vatNumber,
        legalNameAr: organization.legalNameAr,
        legalNameEn: organization.legalNameEn,
        website: organization.website,
        nationalAddress: organization.nationalAddress,
        categoryIds: [for (final category in organization.categories) category.id],
        visibleInSuggestions: organization.visibleInSuggestions,
      );

  final String name;
  final String regionId;
  final String city;
  final bool vatRegistered;
  final String? vatNumber;
  final String? legalNameAr;
  final String? legalNameEn;
  final String? website;
  final NationalAddress nationalAddress;
  final List<String> categoryIds;
  final bool visibleInSuggestions;

  static String? _blankToNull(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  Json toJson() => {
    'name': name.trim(),
    'region_id': regionId,
    'city': city.trim(),
    'vat_registered': vatRegistered,
    'vat_number': vatRegistered ? _blankToNull(vatNumber) : null,
    'legal_name_ar': _blankToNull(legalNameAr),
    'legal_name_en': _blankToNull(legalNameEn),
    'website': _blankToNull(website),
    'national_address': {
      for (final entry in nationalAddress.toJson().entries)
        entry.key: _blankToNull(entry.value as String?),
    },
    'category_ids': categoryIds,
    'visible_in_suggestions': visibleInSuggestions,
  };

  @override
  List<Object?> get props => [
    name,
    regionId,
    city,
    vatRegistered,
    vatNumber,
    legalNameAr,
    legalNameEn,
    website,
    nationalAddress,
    categoryIds,
    visibleInSuggestions,
  ];
}

enum DeletionScope implements WireEnum {
  user('user'),
  organization('organization'),
  unknown('unknown');

  const DeletionScope(this.wire);

  @override
  final String wire;

  static DeletionScope parse(Object? raw) => parseWire(values, raw, unknown);
}

/// `AccountDeletionRequest` (API.md §1.3, store requirement).
final class AccountDeletionRequest extends Equatable {
  const AccountDeletionRequest({
    required this.id,
    required this.scope,
    required this.status,
    this.scheduledFor,
    this.createdAt,
  });

  factory AccountDeletionRequest.fromJson(Json json) => AccountDeletionRequest(
    id: json.str('id'),
    scope: DeletionScope.parse(json['scope']),
    status: json.str('status'),
    scheduledFor: json.dateOrNull('scheduled_for'),
    createdAt: json.dateOrNull('created_at'),
  );

  final String id;
  final DeletionScope scope;

  /// `pending`, `cancelled` or `completed`.
  final String status;
  final DateTime? scheduledFor;
  final DateTime? createdAt;

  @override
  List<Object?> get props => [id, scope, status, scheduledFor, createdAt];
}

/// One entry of `details.blockers` on `account_deletion_blocked` (409).
final class DeletionBlocker extends Equatable {
  const DeletionBlocker({
    required this.type,
    required this.competitionId,
    required this.title,
  });

  factory DeletionBlocker.fromJson(Json json) => DeletionBlocker(
    type: json.strOrNull('type') ?? '',
    competitionId: json.strOrNull('competition_id') ?? '',
    title: json.strOrNull('title') ?? '',
  );

  /// Parses `details.blockers` of an `account_deletion_blocked` error.
  static List<DeletionBlocker> fromDetails(Map<String, dynamic> details) {
    final raw = details['blockers'];
    if (raw is! List) return const [];
    return raw.whereType<Json>().map(DeletionBlocker.fromJson).toList();
  }

  /// `issued_competition` or `participation`.
  final String type;
  final String competitionId;
  final String title;

  @override
  List<Object?> get props => [type, competitionId, title];
}
