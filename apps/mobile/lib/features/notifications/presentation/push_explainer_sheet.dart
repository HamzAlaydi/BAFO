import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/push/push_service.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/notifications/presentation/push_permission_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// A [PushPermissionCubit] from the app services in [context].
PushPermissionCubit pushPermissionCubitOf(BuildContext context) =>
    PushPermissionCubit(
      push: context.read<PushService>(),
      devices: context.read<DevicesRepository>(),
      preferences: context.read<PreferencesStore>(),
      client: context.read<ClientInfo>(),
    );

/// M50: explains push notifications once, then asks the OS. Call it after
/// the first successful join (M18) or publish (M43), never at cold start
/// (CONVENTIONS.md §5.1). Returns without showing anything when the
/// explainer was already shown on this device or push is already on.
Future<void> showPushExplainerIfNeeded(BuildContext context) async {
  final cubit = pushPermissionCubitOf(context);
  try {
    if (!cubit.shouldExplain) return;
    await cubit.markExplained();
    if (!context.mounted) return;
    final allow = await showBafoBottomSheet<bool>(
      context,
      title: context.l10n.notificationsPushTitle,
      builder: (sheet) => const PushExplainerContent(),
    );
    if (allow != true || !context.mounted) return;
    await cubit.request();
    if (!context.mounted) return;
    final message = pushStatusMessage(context.l10n, cubit.state);
    if (message != null) {
      BafoToast.show(
        context,
        message,
        tone: cubit.state.status == PushSetupStatus.enabled
            ? StatusTone.success
            : StatusTone.neutral,
      );
    }
  } finally {
    await cubit.close();
  }
}

/// The user-facing result of a push request, or null while it runs.
String? pushStatusMessage(AppLocalizations l10n, PushPermissionState state) =>
    switch (state.status) {
      PushSetupStatus.enabled => l10n.notificationsPushEnabled,
      PushSetupStatus.denied => l10n.notificationsPushDenied,
      PushSetupStatus.unavailable => l10n.notificationsPushUnavailable,
      PushSetupStatus.failed => errorMessage(l10n, state.error),
      PushSetupStatus.off => l10n.notificationsPushOff,
      PushSetupStatus.requesting => null,
    };

/// The explainer body: what BAFO sends, then Allow / Not now. Pops `true`
/// for Allow.
class PushExplainerContent extends StatelessWidget {
  const PushExplainerContent({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Center(
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: theme.colorScheme.primaryContainer,
              shape: BoxShape.circle,
            ),
            child: Padding(
              padding: const EdgeInsetsDirectional.all(BafoSpacing.lg),
              child: Icon(
                Icons.notifications_active_outlined,
                size: 32,
                color: theme.colorScheme.onPrimaryContainer,
              ),
            ),
          ),
        ),
        const SizedBox(height: BafoSpacing.lg),
        Text(l10n.notificationsPushBody, style: theme.textTheme.bodyMedium),
        const SizedBox(height: BafoSpacing.xl),
        BafoButton(
          key: const Key('push.allow'),
          label: l10n.notificationsPushAllow,
          icon: Icons.notifications_active_outlined,
          expand: true,
          onPressed: () => Navigator.of(context).pop(true),
        ),
        const SizedBox(height: BafoSpacing.sm),
        BafoButton.text(
          key: const Key('push.notNow'),
          label: l10n.notificationsPushNotNow,
          expand: true,
          onPressed: () => Navigator.of(context).pop(false),
        ),
      ],
    );
  }
}
