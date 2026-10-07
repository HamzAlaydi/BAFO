import 'package:bafo/core/api/json.dart';
import 'package:equatable/equatable.dart';

/// A value per mobile platform (`ios`, `android`).
final class PlatformValue extends Equatable {
  const PlatformValue({required this.ios, required this.android});

  factory PlatformValue.fromJson(Json json) => PlatformValue(
    ios: json.strOrNull('ios') ?? '',
    android: json.strOrNull('android') ?? '',
  );

  final String ios;
  final String android;

  /// The value for `X-Platform` [platform] (`ios` or `android`).
  String of(String platform) => platform == 'ios' ? ios : android;

  @override
  List<Object?> get props => [ios, android];
}

/// Where the Reverb server is, as the API publishes it.
final class ServerRealtime extends Equatable {
  const ServerRealtime({
    required this.key,
    required this.host,
    required this.port,
    required this.scheme,
  });

  factory ServerRealtime.fromJson(Json json) => ServerRealtime(
    key: json.strOrNull('key') ?? '',
    host: json.strOrNull('host') ?? '',
    port: json.intOrNull('port') ?? 443,
    scheme: json.strOrNull('scheme') ?? 'https',
  );

  final String key;
  final String host;
  final int port;
  final String scheme;

  @override
  List<Object?> get props => [key, host, port, scheme];
}

/// Support contacts shown on the help screen and account gates. Empty
/// strings mean "not configured".
final class SupportContacts extends Equatable {
  const SupportContacts({this.email = '', this.phone = '', this.whatsapp = ''});

  factory SupportContacts.fromJson(Json json) => SupportContacts(
    email: json.strOrNull('email') ?? '',
    phone: json.strOrNull('phone') ?? '',
    whatsapp: json.strOrNull('whatsapp') ?? '',
  );

  final String email;
  final String phone;
  final String whatsapp;

  bool get isEmpty => email.isEmpty && phone.isEmpty && whatsapp.isEmpty;

  @override
  List<Object?> get props => [email, phone, whatsapp];
}

/// A release-scope feature flag (RELEASE_SCOPE.md §1.3). The server derives
/// each value from `platform.release_scope`; the app only reads
/// `features.flags.<name>` and never branches on the scope itself.
enum Feature implements WireEnum {
  teamManagement('team_management'),
  vendorDirectory('vendor_directory'),
  integrationsApi('integrations_api'),
  csvImportExport('csv_import_export'),
  sponsorship('sponsorship'),
  bafoRound('bafo_round'),
  sealedFormat('sealed_format'),
  advancedRules('advanced_rules'),
  finalPricingWindow('final_pricing_window'),
  deletionApproval('deletion_approval'),
  deletedCompetitions('deleted_competitions'),
  offerReport('offer_report'),
  loginAs('login_as'),
  googleSignin('google_signin'),
  darkMode('dark_mode'),
  billingInvoices('billing_invoices'),
  customPlanQuote('custom_plan_quote'),
  coupons('coupons'),
  qaComments('qa_comments'),
  attachments('attachments'),
  extendCompetition('extend_competition'),
  cancelCompetition('cancel_competition');

  const Feature(this.wire);

  @override
  final String wire;

  static Feature? parse(Object? raw) {
    if (raw is! String) return null;
    for (final value in values) {
      if (value.wire == raw) return value;
    }
    return null;
  }
}

/// `features.flags` of `GET /app-config` (RELEASE_SCOPE.md §1.4): one boolean
/// per [Feature]. Missing keys read as false; keys a newer server adds are
/// ignored. [none] (no config yet) hides every gated surface.
final class FeatureFlags extends Equatable {
  const FeatureFlags._(this._values);

  /// Every [Feature] from [values]; absent ones are false.
  factory FeatureFlags(Map<Feature, bool> values) => FeatureFlags._(
    Map.unmodifiable({
      for (final feature in Feature.values) feature: values[feature] ?? false,
    }),
  );

  /// Reads the `flags` object (null or absent → every flag off).
  factory FeatureFlags.fromJson(Json? json) => FeatureFlags({
    if (json != null)
      for (final entry in json.entries)
        if (Feature.parse(entry.key) case final feature?)
          if (entry.value is bool) feature: entry.value as bool,
  });

  /// No flag on: the state before the first `GET /app-config`.
  static const FeatureFlags none = FeatureFlags._({});

  final Map<Feature, bool> _values;

  bool enabled(Feature feature) => _values[feature] ?? false;

  /// Every [Feature] with its value, in catalogue order.
  Map<Feature, bool> get asMap => {
    for (final feature in Feature.values) feature: enabled(feature),
  };

  @override
  List<Object?> get props => [asMap];
}

/// A mobile-only detail that the minimal `core` app hides
/// (RELEASE_SCOPE.md §4.1). No server flag covers these screen details, so
/// [MobileSurfaces] derives them from `features.release_scope`, here and
/// nowhere else; screens read `context.surfaces` (or a `SurfaceGate`) and
/// never the scope. Nothing behind a surface is deleted: its route and
/// screen stay, and scope `full` shows it exactly as before.
enum MobileSurface {
  /// M55 organisation screen: the hub row, the tappable organisation card
  /// and the home billing-profile alert link. Core shows the organisation
  /// name read-only; the details are managed on the web.
  organizationManagement,

  /// M59 settings (push card, legal list, licences): the hub row and the
  /// route. Core keeps the language on the profile and puts the legal
  /// documents in the hub.
  appSettings,

  /// M50, the push explainer after the first join or publish.
  pushPrompts,

  /// Search and direction filters on M16 and M33, the all / unread filter on
  /// M49. Core keeps the status segments only.
  listFilters,

  /// Delete and delete-all on M49. Core keeps the list and mark read.
  notificationCleanup,

  /// Home extras: the plan card (it is in the Account tab), recent activity,
  /// the 30-day offer tile, the billing-profile alert, and the issuer tiles
  /// of an organisation that cannot issue and has issued nothing.
  homeExtras,

  /// M47 offers log and its entry points (M38 row, M46 ranking link). Core
  /// keeps the leading offer and the live ranking.
  issuerOffersLog,

  /// M42 document upload and removal and its entry points. Documents stay
  /// readable and downloadable everywhere.
  documentUpload,

  /// The photo upload on M52 (the avatar still shows).
  profilePhoto,
}

/// Which [MobileSurface]s show: all of them in scope `full`, none in `core`
/// or before the first `GET /app-config` (RELEASE_SCOPE.md §4.1).
final class MobileSurfaces extends Equatable {
  const MobileSurfaces._(this._full);

  /// The surfaces of `features.release_scope` = [releaseScope].
  factory MobileSurfaces.forScope(String releaseScope) =>
      releaseScope == 'full' ? all : none;

  /// Scope `core` (and no config yet): the minimal app.
  static const MobileSurfaces none = MobileSurfaces._(false);

  /// Scope `full`: every surface, as before the minimal release.
  static const MobileSurfaces all = MobileSurfaces._(true);

  final bool _full;

  /// The derivation table: one arm per surface, like the server's
  /// `FeatureFlags::enabled()`.
  bool enabled(MobileSurface surface) => switch (surface) {
    MobileSurface.organizationManagement ||
    MobileSurface.appSettings ||
    MobileSurface.pushPrompts ||
    MobileSurface.listFilters ||
    MobileSurface.notificationCleanup ||
    MobileSurface.homeExtras ||
    MobileSurface.issuerOffersLog ||
    MobileSurface.documentUpload ||
    MobileSurface.profilePhoto => _full,
  };

  @override
  List<Object?> get props => [_full];
}

/// `GET /app-config` (API.md §2.13).
final class AppConfig extends Equatable {
  const AppConfig({
    required this.minVersion,
    required this.latestVersion,
    required this.storeLinks,
    required this.realtime,
    this.maintenanceEnabled = false,
    this.maintenanceMessage = '',
    this.support = const SupportContacts(),
    this.legalVersions = const {},
    this.sponsorshipEnabled = false,
    this.releaseScope = 'core',
    this.flags = FeatureFlags.none,
    this.currency = 'SAR',
    this.vatRateBp = 1500,
    this.supportedLocales = const ['ar', 'en'],
    this.serverTime,
  });

  factory AppConfig.fromJson(Json json) {
    final maintenance = json.objOrNull('maintenance') ?? const {};
    final legal = json.objOrNull('legal') ?? const {};
    final features = json.objOrNull('features') ?? const {};
    return AppConfig(
      minVersion: PlatformValue.fromJson(json.obj('min_version')),
      latestVersion: PlatformValue.fromJson(json.obj('latest_version')),
      storeLinks: PlatformValue.fromJson(json.obj('store_links')),
      maintenanceEnabled: maintenance.flag('enabled'),
      maintenanceMessage: maintenance.strOrNull('message') ?? '',
      support:
          json.parse('support', SupportContacts.fromJson) ??
          const SupportContacts(),
      realtime: ServerRealtime.fromJson(json.obj('realtime')),
      legalVersions: {
        for (final entry in legal.entries)
          if (entry.value is Json && (entry.value as Json)['version'] is String)
            entry.key: (entry.value as Json)['version'] as String,
      },
      sponsorshipEnabled: features.flag('sponsorship'),
      releaseScope: features.strOrNull('release_scope') ?? 'core',
      flags: FeatureFlags.fromJson(features.objOrNull('flags')),
      currency: json.strOrNull('currency') ?? 'SAR',
      vatRateBp: json.intOrNull('vat_rate_bp') ?? 1500,
      supportedLocales: json.strings('supported_locales'),
      serverTime: json.dateOrNull('server_time'),
    );
  }

  final PlatformValue minVersion;
  final PlatformValue latestVersion;

  /// Store listings for the update screen (not a purchase link).
  final PlatformValue storeLinks;
  final bool maintenanceEnabled;

  /// Localised by the server.
  final String maintenanceMessage;
  final SupportContacts support;
  final ServerRealtime realtime;

  /// Current published version per legal code (`terms`, `privacy`, ...).
  final Map<String, String> legalVersions;

  /// `features.sponsorship` (the global R4 switch). Kept for compatibility;
  /// it equals `flags.sponsorship` on a server that sends the flags.
  final bool sponsorshipEnabled;

  /// `features.release_scope` (`core` or `full`). Screens branch on [flags]
  /// and [surfaces], never on this value.
  final String releaseScope;

  /// `features.flags` (RELEASE_SCOPE.md §1.4).
  final FeatureFlags flags;

  /// The mobile-only surfaces of [releaseScope] (RELEASE_SCOPE.md §4.1).
  MobileSurfaces get surfaces => MobileSurfaces.forScope(releaseScope);
  final String currency;
  final int vatRateBp;
  final List<String> supportedLocales;
  final DateTime? serverTime;

  /// Whether [appVersion] (semver) is below the minimum for [platform].
  bool requiresUpdate(String platform, String appVersion) {
    final minimum = minVersion.of(platform);
    if (minimum.isEmpty) return false;
    return compareSemver(appVersion, minimum) < 0;
  }

  @override
  List<Object?> get props => [
    minVersion,
    latestVersion,
    storeLinks,
    maintenanceEnabled,
    maintenanceMessage,
    support,
    realtime,
    legalVersions,
    sponsorshipEnabled,
    releaseScope,
    flags,
    currency,
    vatRateBp,
    supportedLocales,
    serverTime,
  ];
}

/// Compares `major.minor.patch` versions; pre-release and build suffixes
/// are ignored, missing parts count as 0.
int compareSemver(String a, String b) {
  List<int> parts(String version) => version
      .split(RegExp('[+-]'))
      .first
      .split('.')
      .map((part) => int.tryParse(part.trim()) ?? 0)
      .toList();
  final left = parts(a);
  final right = parts(b);
  for (var i = 0; i < 3; i++) {
    final x = i < left.length ? left[i] : 0;
    final y = i < right.length ? right[i] : 0;
    if (x != y) return x.compareTo(y);
  }
  return 0;
}
