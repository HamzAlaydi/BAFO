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
    this.currency = 'SAR',
    this.vatRateBp = 1500,
    this.supportedLocales = const ['ar', 'en'],
    this.serverTime,
  });

  factory AppConfig.fromJson(Json json) {
    final maintenance = json.objOrNull('maintenance') ?? const {};
    final legal = json.objOrNull('legal') ?? const {};
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
      sponsorshipEnabled: (json.objOrNull('features') ?? const {}).flag(
        'sponsorship',
      ),
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

  /// `features.sponsorship` (the global R4 switch).
  final bool sponsorshipEnabled;
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
