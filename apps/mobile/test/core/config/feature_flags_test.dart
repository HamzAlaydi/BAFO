import 'package:bafo/core/config/app_config.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scope.dart';

/// RELEASE_SCOPE.md §1.4: `features.release_scope` and the 22 `features.flags`
/// as the app reads them.
void main() {
  test('the catalogue has the 22 flags of §1.3, in order', () {
    expect(Feature.values, hasLength(22));
    expect(Feature.values.first.wire, 'team_management');
    expect(Feature.values.last.wire, 'cancel_competition');
    expect(Feature.parse('sealed_format'), Feature.sealedFormat);
    expect(Feature.parse('not_a_flag'), isNull);
    expect(Feature.parse(42), isNull);
  });

  test('AppConfig parses release_scope and flags; missing keys are false', () {
    final config = AppConfig.fromJson({
      ...fixtureData('app_config'),
      'features': {
        'release_scope': 'full',
        'sponsorship': true,
        'flags': {
          'team_management': true,
          'vendor_directory': false,
          'qa_comments': true,
          // A newer server: ignored.
          'teleport': true,
          // Mistyped: ignored (reads as false).
          'coupons': 'yes',
        },
      },
    });
    expect(config.releaseScope, 'full');
    expect(config.sponsorshipEnabled, isTrue);
    expect(config.flags.enabled(Feature.teamManagement), isTrue);
    expect(config.flags.enabled(Feature.vendorDirectory), isFalse);
    expect(config.flags.enabled(Feature.qaComments), isTrue);
    expect(config.flags.enabled(Feature.billingInvoices), isFalse);
    expect(config.flags.enabled(Feature.coupons), isFalse);
    expect(config.flags.asMap, hasLength(22));
  });

  test('a server without flags (today\'s fixture) reads as nothing on', () {
    final config = AppConfig.fromJson(fixtureData('app_config'));
    expect(config.releaseScope, 'core');
    // The legacy key keeps working.
    expect(config.sponsorshipEnabled, isTrue);
    expect(config.flags, FeatureFlags.none);
    for (final feature in Feature.values) {
      expect(config.flags.enabled(feature), isFalse, reason: feature.wire);
    }
  });

  test('the core and full fixtures follow §1.3', () {
    final core = AppConfig.fromJson(
      ScopeFlags.appConfigJson(ScopeFlags.core, scope: 'core'),
    );
    expect(core.releaseScope, 'core');
    expect(core.sponsorshipEnabled, isFalse);
    expect(core.flags.enabled(Feature.qaComments), isTrue);
    expect(core.flags.enabled(Feature.attachments), isTrue);
    expect(core.flags.enabled(Feature.cancelCompetition), isTrue);
    expect(core.flags.enabled(Feature.teamManagement), isFalse);
    expect(core.flags.enabled(Feature.sealedFormat), isFalse);

    final full = AppConfig.fromJson(
      ScopeFlags.appConfigJson(ScopeFlags.full, scope: 'full'),
    );
    expect(full.sponsorshipEnabled, isTrue);
    expect(full.flags.enabled(Feature.teamManagement), isTrue);
    expect(full.flags.enabled(Feature.billingInvoices), isTrue);
    for (final reserved in ScopeFlags.reserved) {
      expect(full.flags.enabled(reserved), isFalse, reason: reserved.wire);
    }
  });

  test('FeatureFlags are values', () {
    expect(
      FeatureFlags({Feature.darkMode: true}),
      FeatureFlags.fromJson({'dark_mode': true}),
    );
    expect(FeatureFlags.fromJson(null), FeatureFlags.none);
    expect(FeatureFlags.fromJson(const {}), FeatureFlags.none);
    expect(FeatureFlags({Feature.darkMode: true}), isNot(FeatureFlags.none));
  });
}
