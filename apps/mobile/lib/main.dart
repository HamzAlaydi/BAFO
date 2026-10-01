import 'package:bafo/app/app.dart';
import 'package:bafo/app/dependencies.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Draw behind transparent system bars (the default from Android 15).
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  _registerFontLicenses();
  // SCREENS.md R-M3: dates never switch to Arabic-Indic digits.
  BafoDateFormat.useWesternDigits();

  final dependencies = await AppDependencies.create(Env.fromEnvironment());
  await dependencies.push.initialize();

  runApp(BafoApp(dependencies: dependencies));
}

/// Bundled fonts are SIL OFL 1.1; the licence must ship with them.
void _registerFontLicenses() {
  LicenseRegistry.addLicense(() async* {
    for (final (family, file) in const [
      ('IBM Plex Sans Arabic', 'assets/fonts/OFL-IBMPlexSansArabic.txt'),
      ('Inter', 'assets/fonts/OFL-Inter.txt'),
    ]) {
      yield LicenseEntryWithLineBreaks([
        family,
      ], await rootBundle.loadString(file));
    }
  });
}
