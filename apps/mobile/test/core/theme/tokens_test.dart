import 'dart:convert';
import 'dart:io';
import 'dart:ui' show Color;

import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/theme/tokens.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../tool/generate_tokens.dart';

void main() {
  test('tokens.dart matches the brand package JSON', () {
    final source = File(tokensSourcePath);
    if (!source.existsSync()) {
      markTestSkipped('Brand package not found (run from apps/mobile).');
      return;
    }
    final json = jsonDecode(source.readAsStringSync()) as Map<String, dynamic>;
    expect(
      File(tokensOutputPath).readAsStringSync(),
      renderTokens(json),
      reason: 'Run: dart run tool/generate_tokens.dart',
    );
  });

  test('filled primary buttons meet WCAG AA with white text', () {
    // Decision B in 05_brand.md §2.3: green-dark, not brand green (3.39:1).
    final primary = BafoTheme.lightScheme.primary;
    expect(primary, BafoTokens.brandGreenDark);
    expect(
      _contrast(primary, BafoTheme.lightScheme.onPrimary),
      greaterThan(4.5),
    );
  });

  test('dark-mode error colour is readable on the dark page', () {
    const scheme = BafoTheme.darkScheme;
    expect(_contrast(scheme.error, scheme.surface), greaterThan(4.5));
  });
}

double _contrast(Color a, Color b) {
  final la = a.computeLuminance();
  final lb = b.computeLuminance();
  final (hi, lo) = la > lb ? (la, lb) : (lb, la);
  return (hi + 0.05) / (lo + 0.05);
}
