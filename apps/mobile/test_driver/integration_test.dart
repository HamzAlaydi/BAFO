import 'dart:io';

import 'package:integration_test/integration_test_driver_extended.dart';

/// `flutter drive --driver=test_driver/integration_test.dart
/// --target=integration_test/{test}.dart`: saves every screenshot the test
/// takes under `docs/screenshots/core/{name}.png`, or under
/// `docs/screenshots/{dir}/{file}.png` when the name is `{dir}/{file}`
/// (e.g. `minimal/01_home_issuer_ar`).
Future<void> main() => integrationDriver(
  onScreenshot: (name, bytes, [args]) async {
    final file = File(
      name.contains('/')
          ? 'docs/screenshots/$name.png'
          : 'docs/screenshots/core/$name.png',
    );
    await file.parent.create(recursive: true);
    await file.writeAsBytes(bytes);
    return true;
  },
);
