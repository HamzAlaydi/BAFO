import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// CONVENTIONS.md §6: identical key sets in ar and en, no exclamation marks
/// (brand voice), no old brand names.
void main() {
  Map<String, dynamic> load(String locale) =>
      jsonDecode(File('lib/l10n/app_$locale.arb').readAsStringSync())
          as Map<String, dynamic>;

  Map<String, String> messages(Map<String, dynamic> arb) => {
    for (final entry in arb.entries)
      if (!entry.key.startsWith('@')) entry.key: entry.value as String,
  };

  final ar = messages(load('ar'));
  final en = messages(load('en'));

  test('Arabic and English have the same keys', () {
    expect(en.keys.toSet(), ar.keys.toSet());
  });

  test('copy follows the brand voice and glossary', () {
    for (final text in [...ar.values, ...en.values]) {
      expect(text, isNot(contains('!')), reason: text);
      expect(text.toLowerCase(), isNot(contains('munaqes')), reason: text);
      expect(text.toLowerCase(), isNot(contains('monaqus')), reason: text);
    }
  });
}
