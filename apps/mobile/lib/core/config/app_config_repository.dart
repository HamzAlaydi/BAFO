import 'dart:async';
import 'dart:convert';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:flutter/foundation.dart';

/// `GET /app-config` (API.md §1.1), cached.
///
/// The last good answer is kept in memory and in [PreferencesStore] (it holds
/// no personal data), so an offline start still knows the store links,
/// support contacts and realtime settings.
abstract interface class AppConfigRepository {
  /// The latest known config (fetched this run or cached), or null.
  AppConfig? get current;

  /// Fetches a fresh config. Throws `ApiException`; [current] is unchanged
  /// on failure.
  Future<AppConfig> fetch();
}

final class ApiAppConfigRepository implements AppConfigRepository {
  ApiAppConfigRepository(this._api, this._preferences) {
    _current = _readCache();
  }

  final ApiClient _api;
  final PreferencesStore _preferences;
  AppConfig? _current;
  Future<AppConfig>? _inFlight;

  @override
  AppConfig? get current => _current;

  @override
  Future<AppConfig> fetch() => _inFlight ??= _fetch().whenComplete(() {
    _inFlight = null;
  });

  Future<AppConfig> _fetch() async {
    final response = await _api.get('app-config');
    final config = AppConfig.fromJson(response.dataMap);
    _current = config;
    unawaited(
      _preferences.setString(
        PreferenceKeys.appConfig,
        jsonEncode(response.dataMap),
      ),
    );
    return config;
  }

  AppConfig? _readCache() {
    final raw = _preferences.getString(PreferenceKeys.appConfig);
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw);
      return decoded is Map<String, dynamic> ? AppConfig.fromJson(decoded) : null;
    } on FormatException catch (error) {
      debugPrint('Ignoring a broken cached app-config: $error');
      return null;
    }
  }
}
