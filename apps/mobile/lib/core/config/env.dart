import 'package:bafo/core/config/app_config.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';

/// Build-time configuration, passed with `--dart-define` or
/// `--dart-define-from-file=env/dev.json` (see env/dev.example.json).
///
/// | Key              | Default                                          |
/// |------------------|--------------------------------------------------|
/// | API_BASE_URL     | http://10.0.2.2:8000/api/app/v1 (iOS: localhost) |
/// | REVERB_AUTH_URL  | {API origin}/broadcasting/auth                   |
/// | REVERB_APP_KEY   | empty: realtime off until app-config provides it |
/// | REVERB_HOST      | 10.0.2.2 (iOS simulator: localhost)              |
/// | REVERB_PORT      | 8085                                             |
/// | REVERB_SCHEME    | http (http or https)                             |
///
/// 10.0.2.2 is the host machine as seen from the Android emulator.
///
/// Realtime settings normally come from `GET /app-config` (ARCHITECTURE.md
/// §9.1); see [resolveRealtime]. The REVERB_* defines above are the fallback
/// before app-config loads, and explicit defines override the server values.
@immutable
final class Env {
  const Env({
    required this.apiBaseUrl,
    required this.broadcastingAuthUrl,
    required this.realtime,
    this.realtimeOverrides = const RealtimeOverrides(),
  });

  /// Reads the compile-time defines. [platform] picks the local dev host.
  factory Env.fromEnvironment({TargetPlatform? platform}) {
    final devHost = (platform ?? defaultTargetPlatform) == TargetPlatform.iOS
        ? 'localhost'
        : '10.0.2.2';
    final apiBase = Uri.parse(
      _nonEmpty(_apiBaseUrl) ?? 'http://$devHost:8000/api/app/v1',
    );
    final scheme = (_nonEmpty(_reverbScheme) ?? 'http').toLowerCase();

    return Env(
      apiBaseUrl: apiBase,
      broadcastingAuthUrl: Uri.parse(
        _nonEmpty(_reverbAuthUrl) ??
            apiBase.replace(path: '/broadcasting/auth').toString(),
      ),
      realtime: RealtimeConfig(
        appKey: _reverbAppKey,
        host: _nonEmpty(_reverbHost) ?? devHost,
        port: int.tryParse(_reverbPort) ?? 8085,
        useTls: scheme == 'https' || scheme == 'wss',
      ),
      realtimeOverrides: RealtimeOverrides(
        appKey: _nonEmpty(_reverbAppKey),
        host: _nonEmpty(_reverbHost),
        port: int.tryParse(_reverbPort),
        scheme: _nonEmpty(_reverbScheme)?.toLowerCase(),
      ),
    );
  }

  static const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static const String _reverbAuthUrl = String.fromEnvironment(
    'REVERB_AUTH_URL',
  );
  static const String _reverbAppKey = String.fromEnvironment('REVERB_APP_KEY');
  static const String _reverbHost = String.fromEnvironment('REVERB_HOST');
  static const String _reverbPort = String.fromEnvironment('REVERB_PORT');
  static const String _reverbScheme = String.fromEnvironment('REVERB_SCHEME');

  /// First-party API root, e.g. `http://10.0.2.2:8000/api/app/v1`.
  final Uri apiBaseUrl;

  /// Laravel channel-auth endpoint for private Reverb channels
  /// (ARCHITECTURE.md §9.1: `POST /broadcasting/auth`).
  final Uri broadcastingAuthUrl;

  /// Realtime settings from the build: used until app-config arrives.
  final RealtimeConfig realtime;

  /// REVERB_* values that were set explicitly at build time.
  final RealtimeOverrides realtimeOverrides;

  /// The Reverb settings to use, from the server's app-config:
  /// * explicit build defines win (a phone on the LAN, a tunnel);
  /// * a loopback host (`localhost`, `127.0.0.1`) from the server is replaced
  ///   by the API host, because the device reaches the dev machine the same
  ///   way for both (the Android emulator's `10.0.2.2`, ARCHITECTURE.md §9.1);
  /// * an empty server key keeps the build key (realtime off when both are
  ///   empty).
  RealtimeConfig resolveRealtime(ServerRealtime server) {
    final overrides = realtimeOverrides;
    final serverHost = server.host.isEmpty ? apiBaseUrl.host : server.host;
    final scheme = overrides.scheme ?? server.scheme.toLowerCase();
    return RealtimeConfig(
      appKey: overrides.appKey ?? (server.key.isEmpty ? realtime.appKey : server.key),
      host: overrides.host ??
          (_isLoopback(serverHost) ? apiBaseUrl.host : serverHost),
      port: overrides.port ?? server.port,
      useTls: scheme == 'https' || scheme == 'wss',
    );
  }

  static bool _isLoopback(String host) =>
      host == 'localhost' || host == '127.0.0.1' || host == '::1';

  static String? _nonEmpty(String value) => value.isEmpty ? null : value;
}

/// Build-time REVERB_* defines that override the server's realtime settings.
@immutable
final class RealtimeOverrides {
  const RealtimeOverrides({this.appKey, this.host, this.port, this.scheme});

  final String? appKey;
  final String? host;
  final int? port;
  final String? scheme;
}

/// Where the Reverb WebSocket lives.
@immutable
final class RealtimeConfig extends Equatable {
  const RealtimeConfig({
    required this.appKey,
    required this.host,
    required this.port,
    required this.useTls,
  });

  /// Public Pusher/Reverb app key (`REVERB_APP_KEY`). Not a secret.
  final String appKey;
  final String host;
  final int port;
  final bool useTls;

  /// Realtime stays off until an app key is known.
  bool get enabled => appKey.isNotEmpty;

  @override
  List<Object?> get props => [appKey, host, port, useTls];
}
