import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:equatable/equatable.dart';

/// Organisation permissions (ARCHITECTURE.md §8.1). The UI renders from
/// `me.permissions`; it never derives them from the role.
abstract final class Permissions {
  static const String organizationUpdate = 'organization.update';
  static const String teamManage = 'team.manage';
  static const String billingView = 'billing.view';
  static const String billingPurchase = 'billing.purchase';
  static const String competitionsCreate = 'competitions.create';
  static const String competitionsManageAll = 'competitions.manage_all';
  static const String competitionsAward = 'competitions.award';
  static const String submitOffers = 'participation.submit_offers';
  static const String integrationsManage = 'integrations.manage';
  static const String deleteOrganization = 'account.delete_organization';
}

enum UserStatus implements WireEnum {
  active('active'),
  pendingVerification('pending_verification'),
  inactive('inactive'),
  unknown('unknown');

  const UserStatus(this.wire);

  @override
  final String wire;

  static UserStatus parse(Object? raw) => parseWire(values, raw, unknown);
}

/// User (API.md §2.1).
final class User extends Equatable {
  const User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.locale = 'ar',
    this.avatarUrl,
    this.status = UserStatus.active,
    this.emailVerifiedAt,
    this.createdAt,
  });

  factory User.fromJson(Json json) => User(
    id: json.str('id'),
    name: json.str('name'),
    email: json.str('email'),
    phone: json.strOrNull('phone'),
    locale: json.strOrNull('locale') ?? 'ar',
    avatarUrl: json.strOrNull('avatar_url'),
    status: UserStatus.parse(json['status']),
    emailVerifiedAt: json.dateOrNull('email_verified_at'),
    createdAt: json.dateOrNull('created_at'),
  );

  final String id;
  final String name;
  final String email;

  /// E.164, `+9665XXXXXXXX`.
  final String? phone;
  final String locale;
  final String? avatarUrl;
  final UserStatus status;
  final DateTime? emailVerifiedAt;
  final DateTime? createdAt;

  @override
  List<Object?> get props => [
    id,
    name,
    email,
    phone,
    locale,
    avatarUrl,
    status,
    emailVerifiedAt,
    createdAt,
  ];
}

/// Saudi national address. Every part is optional.
final class NationalAddress extends Equatable {
  const NationalAddress({
    this.buildingNumber,
    this.street,
    this.district,
    this.postalCode,
    this.additionalNumber,
    this.shortAddress,
  });

  factory NationalAddress.fromJson(Json json) => NationalAddress(
    buildingNumber: json.strOrNull('building_number'),
    street: json.strOrNull('street'),
    district: json.strOrNull('district'),
    postalCode: json.strOrNull('postal_code'),
    additionalNumber: json.strOrNull('additional_number'),
    shortAddress: json.strOrNull('short_address'),
  );

  /// 4 digits.
  final String? buildingNumber;
  final String? street;
  final String? district;

  /// 5 digits.
  final String? postalCode;

  /// 4 digits.
  final String? additionalNumber;

  /// `^[A-Z]{4}\d{4}$`.
  final String? shortAddress;

  bool get isEmpty => [
    buildingNumber,
    street,
    district,
    postalCode,
    additionalNumber,
    shortAddress,
  ].every((part) => part == null || part.isEmpty);

  Json toJson() => {
    'building_number': buildingNumber,
    'street': street,
    'district': district,
    'postal_code': postalCode,
    'additional_number': additionalNumber,
    'short_address': shortAddress,
  };

  @override
  List<Object?> get props => [
    buildingNumber,
    street,
    district,
    postalCode,
    additionalNumber,
    shortAddress,
  ];
}

/// Organisation feature flags set by the platform admin.
final class OrganizationFeatures extends Equatable {
  const OrganizationFeatures({
    this.apiEnabled = false,
    this.auctionEnabled = false,
    this.sponsorshipEnabled = false,
  });

  factory OrganizationFeatures.fromJson(Json json) => OrganizationFeatures(
    apiEnabled: json.flag('api_enabled'),
    auctionEnabled: json.flag('auction_enabled'),
    sponsorshipEnabled: json.flag('sponsorship_enabled'),
  );

  final bool apiEnabled;
  final bool auctionEnabled;
  final bool sponsorshipEnabled;

  @override
  List<Object?> get props => [apiEnabled, auctionEnabled, sponsorshipEnabled];
}

/// The caller's own organisation (API.md §2.2, "own view").
final class Organization extends Equatable {
  const Organization({
    required this.id,
    required this.name,
    required this.crNumber,
    this.legalNameAr,
    this.legalNameEn,
    this.vatRegistered = false,
    this.vatNumber,
    this.region,
    this.city,
    this.nationalAddress = const NationalAddress(),
    this.website,
    this.email,
    this.phone,
    this.logoUrl,
    this.profileDocument,
    this.categories = const [],
    this.visibleInSuggestions = true,
    this.status = 'active',
    this.verified = false,
    this.features = const OrganizationFeatures(),
    this.billingProfileComplete = false,
    this.billingProfileMissing = const [],
    this.trialAvailable = false,
    this.createdAt,
  });

  factory Organization.fromJson(Json json) => Organization(
    id: json.str('id'),
    name: json.str('name'),
    legalNameAr: json.strOrNull('legal_name_ar'),
    legalNameEn: json.strOrNull('legal_name_en'),
    crNumber: json.str('cr_number'),
    vatRegistered: json.flag('vat_registered'),
    vatNumber: json.strOrNull('vat_number'),
    region: json.parse('region', Region.fromJson),
    city: json.strOrNull('city'),
    nationalAddress:
        json.parse('national_address', NationalAddress.fromJson) ??
        const NationalAddress(),
    website: json.strOrNull('website'),
    email: json.strOrNull('email'),
    phone: json.strOrNull('phone'),
    logoUrl: json.strOrNull('logo_url'),
    profileDocument: json.parse('profile_document', FileRef.fromJson),
    categories: json.list('categories', Category.fromJson),
    visibleInSuggestions: json.flag('visible_in_suggestions', fallback: true),
    status: json.strOrNull('status') ?? 'active',
    verified: json.flag('verified'),
    features:
        json.parse('features', OrganizationFeatures.fromJson) ??
        const OrganizationFeatures(),
    billingProfileComplete: json.flag('billing_profile_complete'),
    billingProfileMissing: json.strings('billing_profile_missing'),
    trialAvailable: json.flag('trial_available'),
    createdAt: json.dateOrNull('created_at'),
  );

  final String id;
  final String name;
  final String? legalNameAr;
  final String? legalNameEn;

  /// Commercial registration, 10 digits. Immutable after registration.
  final String crNumber;
  final bool vatRegistered;

  /// 15 digits starting and ending with 3.
  final String? vatNumber;
  final Region? region;
  final String? city;
  final NationalAddress nationalAddress;
  final String? website;
  final String? email;
  final String? phone;
  final String? logoUrl;
  final FileRef? profileDocument;
  final List<Category> categories;
  final bool visibleInSuggestions;
  final String status;
  final bool verified;
  final OrganizationFeatures features;
  final bool billingProfileComplete;

  /// Field paths still needed for invoicing, e.g. `legal_name_ar`.
  final List<String> billingProfileMissing;
  final bool trialAvailable;
  final DateTime? createdAt;

  OrganizationSummary get summary => OrganizationSummary(
    id: id,
    name: name,
    logoUrl: logoUrl,
    verified: verified,
  );

  @override
  List<Object?> get props => [
    id,
    name,
    legalNameAr,
    legalNameEn,
    crNumber,
    vatRegistered,
    vatNumber,
    region,
    city,
    nationalAddress,
    website,
    email,
    phone,
    logoUrl,
    profileDocument,
    categories,
    visibleInSuggestions,
    status,
    verified,
    features,
    billingProfileComplete,
    billingProfileMissing,
    trialAvailable,
    createdAt,
  ];
}

enum MembershipRole implements WireEnum {
  owner('owner'),
  admin('admin'),
  member('member'),
  unknown('unknown');

  const MembershipRole(this.wire);

  @override
  final String wire;

  static MembershipRole parse(Object? raw) => parseWire(values, raw, unknown);
}

enum MembershipStatus implements WireEnum {
  invited('invited'),
  active('active'),
  inactive('inactive'),
  unknown('unknown');

  const MembershipStatus(this.wire);

  @override
  final String wire;

  static MembershipStatus parse(Object? raw) =>
      parseWire(values, raw, unknown);
}

/// The caller's membership in `Me` (without the user).
final class MembershipSummary extends Equatable {
  const MembershipSummary({
    required this.id,
    required this.role,
    this.canAward = false,
    this.canPurchase = false,
    this.status = MembershipStatus.active,
  });

  factory MembershipSummary.fromJson(Json json) => MembershipSummary(
    id: json.str('id'),
    role: MembershipRole.parse(json['role']),
    canAward: json.flag('can_award'),
    canPurchase: json.flag('can_purchase'),
    status: MembershipStatus.parse(json['status']),
  );

  final String id;
  final MembershipRole role;
  final bool canAward;
  final bool canPurchase;
  final MembershipStatus status;

  @override
  List<Object?> get props => [id, role, canAward, canPurchase, status];
}

/// `{id, code, name}` of a plan.
final class PlanRef extends Equatable {
  const PlanRef({required this.id, required this.code, required this.name});

  factory PlanRef.fromJson(Json json) => PlanRef(
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

enum SubscriptionSource implements WireEnum {
  paid('paid'),
  trial('trial'),
  grant('grant'),
  unknown('unknown');

  const SubscriptionSource(this.wire);

  @override
  final String wire;

  static SubscriptionSource parse(Object? raw) =>
      parseWire(values, raw, unknown);
}

enum SubscriptionStatus implements WireEnum {
  pendingPayment('pending_payment'),
  active('active'),
  superseded('superseded'),
  expired('expired'),
  cancelled('cancelled'),
  unknown('unknown');

  const SubscriptionStatus(this.wire);

  @override
  final String wire;

  static SubscriptionStatus parse(Object? raw) =>
      parseWire(values, raw, unknown);
}

/// The current subscription as `Me` and `Home` show it.
final class SubscriptionSummary extends Equatable {
  const SubscriptionSummary({
    required this.plan,
    required this.status,
    this.source,
    this.endsAt,
    this.daysLeft,
    this.totalDays,
  });

  factory SubscriptionSummary.fromJson(Json json) => SubscriptionSummary(
    plan: PlanRef.fromJson(json.obj('plan')),
    source: json['source'] == null
        ? null
        : SubscriptionSource.parse(json['source']),
    status: SubscriptionStatus.parse(json['status']),
    endsAt: json.dateOrNull('ends_at'),
    daysLeft: json.intOrNull('days_left'),
    totalDays: json.intOrNull('total_days'),
  );

  final PlanRef plan;

  /// Absent in `Home.subscription`.
  final SubscriptionSource? source;
  final SubscriptionStatus status;
  final DateTime? endsAt;
  final int? daysLeft;
  final int? totalDays;

  @override
  List<Object?> get props => [plan, source, status, endsAt, daysLeft, totalDays];
}

/// What the organisation may do under its plan (ARCHITECTURE.md §8.4).
final class Entitlements extends Equatable {
  const Entitlements({
    this.canIssue = false,
    this.seatsUsed = 0,
    this.seatsTotal = 1,
  });

  factory Entitlements.fromJson(Json json) => Entitlements(
    canIssue: json.flag('can_issue'),
    seatsUsed: json.intOrNull('seats_used') ?? 0,
    seatsTotal: json.intOrNull('seats_total') ?? 1,
  );

  final bool canIssue;
  final int seatsUsed;
  final int seatsTotal;

  @override
  List<Object?> get props => [canIssue, seatsUsed, seatsTotal];
}

/// `GET /me` (API.md §2.3): who is signed in and what they may do.
final class Me extends Equatable {
  const Me({
    required this.user,
    required this.organization,
    required this.membership,
    this.permissions = const {},
    this.subscription,
    this.entitlements = const Entitlements(),
    this.unreadNotificationsCount = 0,
  });

  factory Me.fromJson(Json json) => Me(
    user: User.fromJson(json.obj('user')),
    organization: Organization.fromJson(json.obj('organization')),
    membership: MembershipSummary.fromJson(json.obj('membership')),
    permissions: Set.unmodifiable(json.strings('permissions')),
    subscription: json.parse('subscription', SubscriptionSummary.fromJson),
    entitlements:
        json.parse('entitlements', Entitlements.fromJson) ??
        const Entitlements(),
    unreadNotificationsCount: json.intOrNull('unread_notifications_count') ?? 0,
  );

  final User user;
  final Organization organization;
  final MembershipSummary membership;
  final Set<String> permissions;

  /// Null when there is no current subscription.
  final SubscriptionSummary? subscription;
  final Entitlements entitlements;
  final int unreadNotificationsCount;

  bool can(String permission) => permissions.contains(permission);

  /// S10: create competition needs the permission **and** `can_issue`.
  bool get canCreateCompetition =>
      can(Permissions.competitionsCreate) && entitlements.canIssue;

  @override
  List<Object?> get props => [
    user,
    organization,
    membership,
    permissions,
    subscription,
    entitlements,
    unreadNotificationsCount,
  ];
}

/// `AuthTokenPayload` = [Me] plus the Sanctum token (API.md §2.3).
final class AuthTokenPayload extends Equatable {
  const AuthTokenPayload({required this.token, required this.me});

  factory AuthTokenPayload.fromJson(Json json) {
    final token = json.str('token');
    if (token.isEmpty) throw const FormatException('Empty token');
    return AuthTokenPayload(token: token, me: Me.fromJson(json));
  }

  /// Plain-text bearer token. Never log it.
  final String token;
  final Me me;

  @override
  List<Object?> get props => [token, me];

  @override
  String toString() => 'AuthTokenPayload(user: ${me.user.id})';
}
