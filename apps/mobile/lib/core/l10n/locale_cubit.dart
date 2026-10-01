import 'dart:ui' show Locale, TextDirection;

import 'package:bafo/core/storage/preferences_store.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Languages the app ships. Arabic first: it is the default.
abstract final class AppLocales {
  static const Locale arabic = Locale('ar');
  static const Locale english = Locale('en');
  static const List<Locale> supported = [arabic, english];
  static const Locale fallback = arabic;

  static Locale? tryParse(String? code) {
    for (final locale in supported) {
      if (locale.languageCode == code) return locale;
    }
    return null;
  }

  static TextDirection directionOf(Locale locale) =>
      locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr;
}

/// The app language. Arabic unless the user picked another one; the choice is
/// kept across launches and can be changed before login.
class LocaleCubit extends Cubit<Locale> {
  LocaleCubit(this._preferences)
    : super(
        AppLocales.tryParse(_preferences.getString(PreferenceKeys.locale)) ??
            AppLocales.fallback,
      );

  final PreferencesStore _preferences;

  Future<void> select(Locale locale) async {
    final supported = AppLocales.tryParse(locale.languageCode);
    if (supported == null || supported == state) return;
    emit(supported);
    await _preferences.setString(PreferenceKeys.locale, supported.languageCode);
  }
}
