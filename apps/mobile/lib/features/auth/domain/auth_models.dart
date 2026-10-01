import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:equatable/equatable.dart';

/// Why a one-time code is sent (ARCHITECTURE.md §13.9).
enum OtpPurpose implements WireEnum {
  emailVerification('email_verification'),
  passwordReset('password_reset');

  const OtpPurpose(this.wire);

  @override
  final String wire;
}

/// `POST /auth/otp/send` → 202 `{otp_expires_at}`, the same answer for unknown
/// e-mails (null only from older servers).
final class OtpSent extends Equatable {
  const OtpSent({this.expiresAt});

  factory OtpSent.fromJson(Json json) =>
      OtpSent(expiresAt: json.dateOrNull('otp_expires_at'));

  final DateTime? expiresAt;

  @override
  List<Object?> get props => [expiresAt];
}

/// The company part of `POST /auth/register` (`organization.*`).
final class OrganizationRegistration extends Equatable {
  const OrganizationRegistration({
    required this.name,
    required this.crNumber,
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

  final String name;

  /// 10 digits.
  final String crNumber;
  final String regionId;
  final String city;
  final bool vatRegistered;

  /// Required when [vatRegistered]: 15 digits starting and ending with 3.
  final String? vatNumber;
  final String? legalNameAr;
  final String? legalNameEn;

  /// https only.
  final String? website;
  final NationalAddress nationalAddress;

  /// At most 20.
  final List<String> categoryIds;
  final bool visibleInSuggestions;

  static String? _blankToNull(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  Json toJson() => {
    'name': name.trim(),
    'cr_number': crNumber,
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
    crNumber,
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

/// `POST /auth/register` (API.md §1.3).
final class RegistrationRequest extends Equatable {
  const RegistrationRequest({
    required this.name,
    required this.email,
    required this.phone,
    required this.password,
    required this.passwordConfirmation,
    required this.organization,
    required this.acceptTerms,
    required this.acceptPrivacy,
    this.locale,
    this.invitationToken,
  });

  final String name;
  final String email;

  /// E.164, `+9665XXXXXXXX`.
  final String phone;
  final String password;
  final String passwordConfirmation;
  final String? locale;
  final OrganizationRegistration organization;
  final bool acceptTerms;
  final bool acceptPrivacy;
  final String? invitationToken;

  Json toJson() => {
    'name': name.trim(),
    'email': email.trim(),
    'phone': phone,
    'password': password,
    'password_confirmation': passwordConfirmation,
    'locale': locale,
    'organization': organization.toJson(),
    'accept_terms': acceptTerms,
    'accept_privacy': acceptPrivacy,
    'invitation_token': invitationToken,
    // Honeypot (API.md §0.8): always sent empty.
    'website_url': '',
  };

  @override
  List<Object?> get props => [
    name,
    email,
    phone,
    password,
    passwordConfirmation,
    locale,
    organization,
    acceptTerms,
    acceptPrivacy,
    invitationToken,
  ];

  @override
  String toString() => 'RegistrationRequest($email)';
}

/// 201 of `POST /auth/register`: no token yet, the e-mail must be verified.
final class RegistrationResult extends Equatable {
  const RegistrationResult({
    required this.email,
    this.verificationRequired = true,
    this.otpExpiresAt,
  });

  factory RegistrationResult.fromJson(Json json) => RegistrationResult(
    email: json.str('email'),
    verificationRequired: json.flag('verification_required', fallback: true),
    otpExpiresAt: json.dateOrNull('otp_expires_at'),
  );

  final String email;
  final bool verificationRequired;
  final DateTime? otpExpiresAt;

  @override
  List<Object?> get props => [email, verificationRequired, otpExpiresAt];
}

/// `POST /auth/team-invitations/lookup` (accepted on the web; kept for
/// completeness of the Identity module).
final class TeamInvitationLookup extends Equatable {
  const TeamInvitationLookup({
    required this.email,
    required this.name,
    required this.organizationName,
    required this.role,
    this.organizationLogoUrl,
    this.expiresAt,
  });

  factory TeamInvitationLookup.fromJson(Json json) {
    final organization = json.objOrNull('organization') ?? const {};
    return TeamInvitationLookup(
      email: json.str('email'),
      name: json.strOrNull('name') ?? '',
      organizationName: organization.strOrNull('name') ?? '',
      organizationLogoUrl: organization.strOrNull('logo_url'),
      role: MembershipRole.parse(json['role']),
      expiresAt: json.dateOrNull('expires_at'),
    );
  }

  final String email;
  final String name;
  final String organizationName;
  final String? organizationLogoUrl;
  final MembershipRole role;
  final DateTime? expiresAt;

  @override
  List<Object?> get props => [
    email,
    name,
    organizationName,
    organizationLogoUrl,
    role,
    expiresAt,
  ];
}

/// Legal document codes (`GET /legal/{code}`).
enum LegalCode implements WireEnum {
  terms('terms'),
  privacy('privacy'),
  refund('refund'),
  competitionRules('competition_rules'),
  apiTerms('api_terms');

  const LegalCode(this.wire);

  @override
  final String wire;

  static LegalCode? tryParse(String? raw) {
    for (final code in values) {
      if (code.wire == raw) return code;
    }
    return null;
  }
}

/// The latest published version of a legal document in the request language.
final class LegalDocument extends Equatable {
  const LegalDocument({
    required this.code,
    required this.locale,
    required this.version,
    required this.title,
    required this.bodyMarkdown,
    this.publishedAt,
  });

  factory LegalDocument.fromJson(Json json) => LegalDocument(
    code: json.str('code'),
    locale: json.str('locale'),
    version: json.str('version'),
    title: json.str('title'),
    bodyMarkdown: json.str('body_markdown'),
    publishedAt: json.dateOrNull('published_at'),
  );

  final String code;
  final String locale;

  /// e.g. `2026-10-01`.
  final String version;
  final String title;
  final String bodyMarkdown;
  final DateTime? publishedAt;

  @override
  List<Object?> get props => [code, locale, version, title, bodyMarkdown, publishedAt];
}
