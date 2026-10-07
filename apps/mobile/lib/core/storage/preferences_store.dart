import 'package:shared_preferences/shared_preferences.dart';

/// Non-secret, per-device preferences (language, UI choices).
/// Never store tokens or personal data here: use [TokenStore].
abstract interface class PreferencesStore {
  String? getString(String key);
  Future<void> setString(String key, String value);
  Future<void> remove(String key);
}

/// Keys used with [PreferencesStore]. Only these keys are cached/allowed.
abstract final class PreferenceKeys {
  static const String locale = 'bafo.locale';

  /// The last `GET /app-config` body (no personal data).
  static const String appConfig = 'bafo.app_config';

  /// The welcome slides (M02) were shown once.
  static const String onboardingSeen = 'bafo.onboarding_seen';

  /// Public id of the device registered with `POST /devices` (deleted on
  /// sign-out). Not a secret: the push token itself is never stored here.
  static const String pushDeviceId = 'bafo.push.device_id';

  /// The push permission explainer (M50) was shown once.
  static const String pushExplainerShown = 'bafo.push.explainer_shown';

  /// The unsaved creation form (M34–M37) until `POST /competitions` succeeds
  /// (RELEASE_SCOPE.md FQ6). Titles and prices of a draft, never credentials.
  static const String issuerCreateDraft = 'bafo.issuer.create_draft';

  static const Set<String> all = {
    locale,
    appConfig,
    onboardingSeen,
    pushDeviceId,
    pushExplainerShown,
    issuerCreateDraft,
  };
}

/// [PreferencesStore] over `SharedPreferencesWithCache` (synchronous reads).
final class SharedPreferencesStore implements PreferencesStore {
  SharedPreferencesStore._(this._prefs);

  static Future<SharedPreferencesStore> create() async {
    final prefs = await SharedPreferencesWithCache.create(
      cacheOptions: const SharedPreferencesWithCacheOptions(
        allowList: PreferenceKeys.all,
      ),
    );
    return SharedPreferencesStore._(prefs);
  }

  final SharedPreferencesWithCache _prefs;

  @override
  String? getString(String key) => _prefs.getString(key);

  @override
  Future<void> setString(String key, String value) =>
      _prefs.setString(key, value);

  @override
  Future<void> remove(String key) => _prefs.remove(key);
}
