import 'dart:io';

import 'package:integration_test/integration_test_driver_extended.dart';

/// `flutter drive --driver=test_driver/integration_test.dart
/// --target=integration_test/release_scope_screenshots_test.dart`: saves every
/// screenshot the test takes under `docs/screenshots/core/<name>.png`.
Future<void> main() => integrationDriver(
  onScreenshot: (name, bytes, [args]) async {
    final file = File('docs/screenshots/core/$name.png');
    await file.parent.create(recursive: true);
    await file.writeAsBytes(bytes);
    return true;
  },
);
