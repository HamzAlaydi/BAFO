import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/config/app_config_repository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/single_child_widget.dart';

import 'fakes.dart';

/// The release-scope flags of RELEASE_SCOPE.md §1.3 as test fixtures. The
/// app itself never holds this table: it reads `features.flags` only.
abstract final class ScopeFlags {
  /// Scope `core`: the always-on flags only.
  static final FeatureFlags core = FeatureFlags({
    Feature.qaComments: true,
    Feature.attachments: true,
    Feature.cancelCompetition: true,
  });

  /// Scope `full`: everything built is on; the reserved flags stay off.
  static final FeatureFlags full = FeatureFlags({
    for (final feature in Feature.values)
      if (!reserved.contains(feature)) feature: true,
  });

  /// The `release_scope` that goes with [flags] when a test does not name
  /// one: `full` when the full-only flags are on (they all derive from the
  /// scope), else `core`. The mobile surfaces (RELEASE_SCOPE.md §4.1) follow
  /// it.
  static String scopeOf(FeatureFlags flags) =>
      flags.enabled(Feature.teamManagement) ? 'full' : 'core';

  /// Flags whose feature is not built yet (`false` in both scopes).
  static const Set<Feature> reserved = {
    Feature.deletionApproval,
    Feature.deletedCompetitions,
    Feature.offerReport,
    Feature.loginAs,
    Feature.googleSignin,
  };

  /// The `features` object of `GET /app-config` for [flags].
  static Map<String, dynamic> featuresJson(
    FeatureFlags flags, {
    required String scope,
  }) => {
    'release_scope': scope,
    'sponsorship': flags.enabled(Feature.sponsorship),
    'flags': {
      for (final entry in flags.asMap.entries) entry.key.wire: entry.value,
    },
  };

  /// The captured `app_config` fixture with its `features` replaced.
  static Map<String, dynamic> appConfigJson(
    FeatureFlags flags, {
    required String scope,
  }) => {
    ...fixtureData('app_config'),
    'features': featuresJson(flags, scope: scope),
  };
}

final class _FixedConfig implements AppConfigRepository {
  _FixedConfig(this.current);

  @override
  final AppConfig current;

  @override
  Future<AppConfig> fetch() async => current;
}

/// An [AppConfigCubit] whose config carries [flags] and [scope] (closed at
/// tear-down), so screens read `context.flags` and `context.surfaces` as
/// they do in the app. [scope] defaults to [ScopeFlags.scopeOf] the flags.
AppConfigCubit scopedAppConfig(FeatureFlags flags, {String? scope}) {
  final gate = AppGateCubit();
  final realtime = FakeRealtimeClient();
  final cubit = AppConfigCubit(
    repository: _FixedConfig(
      AppConfig.fromJson(
        ScopeFlags.appConfigJson(
          flags,
          scope: scope ?? ScopeFlags.scopeOf(flags),
        ),
      ),
    ),
    gate: gate,
    realtime: realtime,
    env: testEnv(),
    client: const ClientInfo(platform: 'android', appVersion: '1.2.3'),
  );
  addTearDown(() async {
    await cubit.close();
    await gate.close();
    await realtime.dispose();
  });
  return cubit;
}

/// The provider to add to a pumped feature so it runs in the given scope.
SingleChildWidget scopeProvider(FeatureFlags flags, {String? scope}) =>
    BlocProvider<AppConfigCubit>.value(
      value: scopedAppConfig(flags, scope: scope),
    );

/// [scopeProvider] for a release scope by name (`core` or `full`).
SingleChildWidget releaseScopeProvider(String scope) => scopeProvider(
  scope == 'full' ? ScopeFlags.full : ScopeFlags.core,
  scope: scope,
);
