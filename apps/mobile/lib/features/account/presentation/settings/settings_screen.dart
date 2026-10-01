import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/notifications/presentation/push_explainer_sheet.dart';
import 'package:bafo/features/notifications/presentation/push_permission_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M59 Settings: the app language (saved on the account with `PATCH /me`
/// by the locale listener), push notifications on this device, the app
/// version, the legal documents (M13) and the open-source licences.
///
/// Mobile is light-only in v1 (SCREENS.md CD11), so there is no theme
/// choice (handoff M-8).
class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const BlocProvider(create: pushPermissionCubitOf, child: _SettingsView());
}

class _SettingsView extends StatelessWidget {
  const _SettingsView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final version = context.read<ClientInfo>().appVersion;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.accountSettingsTitle),
      body: ListView(
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.page,
          0,
          BafoSpacing.page,
          BafoSpacing.xxl,
        ),
        children: [
          AccountSectionLabel(l10n.profileLanguageTitle),
          const LanguageSegmented(),
          AccountSectionLabel(l10n.notificationsPushSettingTitle),
          const _PushCard(),
          AccountSection(
            title: l10n.accountSettingsLegal,
            children: [
              for (final (code, label) in [
                (LegalCode.terms, l10n.legalLinksTerms),
                (LegalCode.privacy, l10n.legalLinksPrivacy),
                (LegalCode.competitionRules, l10n.legalLinksCompetitionRules),
              ])
                AccountLinkTile(
                  key: Key('settings.legal.${code.wire}'),
                  icon: Icons.description_outlined,
                  title: label,
                  onTap: () => context.push(AppRoutes.legal(code.wire)),
                ),
            ],
          ),
          AccountSection(
            title: l10n.accountSettingsAbout,
            children: [
              ListTile(
                key: const Key('settings.version'),
                leading: Icon(
                  Icons.info_outline_rounded,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                title: Text(l10n.accountSettingsVersion),
                trailing: Ltr(
                  child: Text(version, style: theme.textTheme.bodyMedium),
                ),
              ),
              AccountLinkTile(
                key: const Key('settings.licenses'),
                icon: Icons.gavel_outlined,
                title: l10n.accountSettingsLicenses,
                onTap: () => showLicensePage(
                  context: context,
                  applicationName: l10n.appName,
                  applicationVersion: version,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Push on this device: its state, and a button that asks the OS and
/// registers the device (M50 without the explainer, which is for the
/// in-context moments).
class _PushCard extends StatelessWidget {
  const _PushCard();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocBuilder<PushPermissionCubit, PushPermissionState>(
      builder: (context, state) {
        final status = state.status;
        final enabled = status == PushSetupStatus.enabled;
        final message = pushStatusMessage(l10n, state);
        final canRequest =
            status == PushSetupStatus.off || status == PushSetupStatus.failed;
        return BafoCard(
          key: const Key('settings.push'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    enabled
                        ? Icons.notifications_active_outlined
                        : Icons.notifications_off_outlined,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                  const SizedBox(width: BafoSpacing.md),
                  Expanded(
                    child: Semantics(
                      liveRegion: true,
                      child: Text(
                        message ?? l10n.notificationsPushOff,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ),
                ],
              ),
              // Under the text, so 200% text never squeezes it (S9).
              if (canRequest || status == PushSetupStatus.requesting) ...[
                const SizedBox(height: BafoSpacing.md),
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: BafoButton.tonal(
                    key: const Key('settings.pushTurnOn'),
                    label: l10n.notificationsPushTurnOn,
                    loading: status == PushSetupStatus.requesting,
                    onPressed: canRequest
                        ? () => unawaited(
                            context.read<PushPermissionCubit>().request(),
                          )
                        : null,
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}
