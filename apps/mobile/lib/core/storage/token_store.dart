import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the Sanctum personal access token of the signed-in user.
abstract interface class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

/// [TokenStore] in the Android Keystore / iOS Keychain.
///
/// The Keychain item is `first_unlock_this_device`: it is readable by
/// background work after the first unlock, and never syncs to iCloud or
/// migrates to another device. Android backups are disabled in the manifest.
final class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
    : _storage =
          storage ??
          const FlutterSecureStorage(
            iOptions: IOSOptions(
              accessibility: KeychainAccessibility.first_unlock_this_device,
            ),
          );

  static const String _key = 'bafo.auth.token';

  final FlutterSecureStorage _storage;

  // Read once, then served from memory: every API call needs the token.
  String? _cached;
  bool _loaded = false;

  @override
  Future<String?> read() async {
    if (!_loaded) {
      _cached = await _storage.read(key: _key);
      _loaded = true;
    }
    return _cached;
  }

  @override
  Future<void> write(String token) async {
    await _storage.write(key: _key, value: token);
    _cached = token;
    _loaded = true;
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _key);
    _cached = null;
    _loaded = true;
  }
}
