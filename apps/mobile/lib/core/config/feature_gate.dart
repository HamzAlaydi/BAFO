import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/bafo_app_bar.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:bafo/widgets/empty_state.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Release-scope flags for widgets (RELEASE_SCOPE.md §4): read from the
/// cached `GET /app-config` through [AppConfigCubit]. Without a config (first
/// offline start) or without the cubit (isolated widget tests) every gated
/// surface stays hidden, which is the `core` behaviour.
extension FeatureFlagsContext on BuildContext {
  /// The current flags; rebuilds the caller when they change (build only).
  FeatureFlags get flags {
    try {
      return select<AppConfigCubit, FeatureFlags>(
        (cubit) => cubit.state.config?.flags ?? FeatureFlags.none,
      );
    } on ProviderNotFoundException {
      return FeatureFlags.none;
    }
  }

  /// The current flags without subscribing (callbacks, `create:`).
  FeatureFlags get flagsNow {
    try {
      return read<AppConfigCubit>().state.config?.flags ?? FeatureFlags.none;
    } on ProviderNotFoundException {
      return FeatureFlags.none;
    }
  }

  bool hasFeature(Feature feature) => flags.enabled(feature);

  /// The mobile-only surfaces of the release scope (RELEASE_SCOPE.md §4.1);
  /// rebuilds the caller when they change (build only). Without a config or
  /// the cubit: [MobileSurfaces.none], the minimal `core` app.
  MobileSurfaces get surfaces {
    try {
      return select<AppConfigCubit, MobileSurfaces>(
        (cubit) => cubit.state.config?.surfaces ?? MobileSurfaces.none,
      );
    } on ProviderNotFoundException {
      return MobileSurfaces.none;
    }
  }

  /// The surfaces without subscribing (callbacks, `create:`).
  MobileSurfaces get surfacesNow {
    try {
      return read<AppConfigCubit>().state.config?.surfaces ??
          MobileSurfaces.none;
    } on ProviderNotFoundException {
      return MobileSurfaces.none;
    }
  }

  bool showsSurface(MobileSurface surface) => surfaces.enabled(surface);
}

/// Shows [child] only while [feature] is on; otherwise [fallback] (nothing by
/// default). Rows, segments and routes are built conditionally, never greyed
/// out (RELEASE_SCOPE.md §4).
class FeatureGate extends StatelessWidget {
  const FeatureGate({
    required this.feature,
    required this.child,
    this.fallback,
    super.key,
  });

  final Feature feature;
  final Widget child;
  final Widget? fallback;

  @override
  Widget build(BuildContext context) => context.flags.enabled(feature)
      ? child
      : (fallback ?? const SizedBox.shrink());
}

/// Shows [child] only while the mobile [surface] is on (scope `full`);
/// otherwise [fallback] (nothing by default). The [FeatureGate] of the
/// mobile-only surfaces of RELEASE_SCOPE.md §4.1.
class SurfaceGate extends StatelessWidget {
  const SurfaceGate({
    required this.surface,
    required this.child,
    this.fallback,
    super.key,
  });

  final MobileSurface surface;
  final Widget child;
  final Widget? fallback;

  @override
  Widget build(BuildContext context) => context.surfaces.enabled(surface)
      ? child
      : (fallback ?? const SizedBox.shrink());
}

/// «غير متاح في هذا الإصدار»: the friendly screen behind a route whose
/// feature is off in this release. The route and its screen stay in the app;
/// a deep link or a stale shortcut lands here instead of on a blank page.
class FeatureUnavailableScreen extends StatelessWidget {
  const FeatureUnavailableScreen({this.message, super.key});

  /// Replaces the generic «هذه الميزة غير متاحة في هذا الإصدار.».
  final String? message;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: BafoAppBar(title: l10n.commonFeatureUnavailableTitle),
      body: SafeArea(
        // Scrolls at 200 % text instead of overflowing.
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: _body(context, l10n, theme),
            ),
          ),
        ),
      ),
    );
  }

  Widget _body(BuildContext context, AppLocalizations l10n, ThemeData theme) =>
      EmptyState(
        key: const Key('feature.unavailable'),
        icon: Icons.upcoming_outlined,
        title: l10n.commonFeatureUnavailableTitle,
        message: message ?? l10n.errorsFeatureDisabled,
        action: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              l10n.commonFeatureUnavailableHint,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: BafoSpacing.lg),
            BafoButton.outline(
              key: const Key('feature.unavailable.back'),
              label: l10n.commonActionsBack,
              icon: Icons.arrow_back_rounded,
              onPressed: () =>
                  context.canPop() ? context.pop() : context.go(AppRoutes.home),
            ),
          ],
        ),
      );
}
