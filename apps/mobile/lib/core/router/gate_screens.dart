import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

/// M03: shown after a 426 `app_version_unsupported` or when app-config's
/// `min_version` is above this build. The button opens the store listing
/// (`store_links`, not a purchase) when one is configured.
class UpdateRequiredScreen extends StatelessWidget {
  const UpdateRequiredScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final config = context.watch<AppConfigCubit>().state.config;
    final platform = context.read<ClientInfo>().platform;
    final link = config?.storeLinks.of(platform) ?? '';
    final uri = Uri.tryParse(link);
    final canOpen = uri != null && uri.scheme == 'https';
    return Scaffold(
      body: SafeArea(
        child: EmptyState(
          icon: Icons.system_update_rounded,
          title: l10n.commonUpdateRequiredTitle,
          message: l10n.commonUpdateRequiredMessage,
          action: canOpen
              ? BafoButton(
                  label: l10n.commonUpdateAction,
                  icon: Icons.open_in_new_rounded,
                  onPressed: () =>
                      launchUrl(uri, mode: LaunchMode.externalApplication),
                )
              : null,
        ),
      ),
    );
  }
}

/// M04: shown after a 503 `maintenance` or when app-config says so, with the
/// server's message. Retries on tap and every 60 s (re-reading app-config);
/// the gate reopens when maintenance is over.
class MaintenanceScreen extends StatefulWidget {
  const MaintenanceScreen({super.key});

  static const Duration retryInterval = Duration(seconds: 60);

  @override
  State<MaintenanceScreen> createState() => _MaintenanceScreenState();
}

class _MaintenanceScreenState extends State<MaintenanceScreen> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(MaintenanceScreen.retryInterval, (_) => _retry());
  }

  void _retry() {
    if (mounted) unawaited(context.read<AppConfigCubit>().retryGate());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final gate = context.watch<AppGateCubit>().state;
    final loading = context.watch<AppConfigCubit>().state is AppConfigLoading;
    final serverMessage = gate is AppGateMaintenance ? gate.message : '';
    return Scaffold(
      body: SafeArea(
        child: EmptyState(
          icon: Icons.construction_rounded,
          title: l10n.commonMaintenanceTitle,
          message: serverMessage.isNotEmpty
              ? serverMessage
              : l10n.commonMaintenanceMessage,
          action: BafoButton.outline(
            label: l10n.commonActionsRetry,
            icon: Icons.refresh_rounded,
            loading: loading,
            onPressed: _retry,
          ),
        ),
      ),
    );
  }
}

/// S8 "Account gate": 403 `account_inactive` or `organization_suspended`.
/// Shows why, the support contacts from app-config, and Sign out.
class AccountBlockedScreen extends StatelessWidget {
  const AccountBlockedScreen({super.key});

  Future<void> _signOut(BuildContext context) async {
    final gate = context.read<AppGateCubit>();
    await context.read<SessionCubit>().signOut();
    gate.reopen();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final gate = context.watch<AppGateCubit>().state;
    final support =
        context.watch<AppConfigCubit>().state.config?.support ??
        const SupportContacts();
    final message = gate is AppGateAccountBlocked
        ? (errorText(l10n, gate.code) ?? gate.message)
        : l10n.errorsAccountInactive;
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsetsDirectional.all(BafoSpacing.xl),
          children: [
            const SizedBox(height: BafoSpacing.xxl),
            Icon(
              Icons.no_accounts_outlined,
              size: 48,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: BafoSpacing.lg),
            Semantics(
              header: true,
              child: Text(
                l10n.commonAccountBlockedTitle,
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge,
              ),
            ),
            const SizedBox(height: BafoSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (!support.isEmpty) ...[
              const SizedBox(height: BafoSpacing.xl),
              SupportContactsCard(support: support),
            ],
            const SizedBox(height: BafoSpacing.xl),
            BafoButton.outline(
              label: l10n.authLogoutAction,
              icon: Icons.logout_rounded,
              expand: true,
              onPressed: () => _signOut(context),
            ),
          ],
        ),
      ),
    );
  }
}

/// Support e-mail, phone and WhatsApp from app-config (tap to write or call).
class SupportContactsCard extends StatelessWidget {
  const SupportContactsCard({required this.support, super.key});

  final SupportContacts support;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    Widget row(IconData icon, String label, String value, Uri uri) => ListTile(
      leading: Icon(icon),
      title: Text(label),
      subtitle: Ltr(child: Text(value)),
      onTap: () => launchUrl(uri, mode: LaunchMode.externalApplication),
    );
    final whatsappDigits = support.whatsapp.replaceAll(RegExp(r'\D'), '');
    return BafoCard(
      padding: const EdgeInsetsDirectional.symmetric(vertical: BafoSpacing.xs),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(
              BafoSpacing.lg,
              BafoSpacing.sm,
              BafoSpacing.lg,
              0,
            ),
            child: Text(
              l10n.commonSupportTitle,
              style: Theme.of(context).textTheme.titleSmall,
            ),
          ),
          if (support.email.isNotEmpty)
            row(
              Icons.mail_outline_rounded,
              l10n.commonSupportEmail,
              support.email,
              Uri(scheme: 'mailto', path: support.email),
            ),
          if (support.phone.isNotEmpty)
            row(
              Icons.call_outlined,
              l10n.commonSupportPhone,
              support.phone,
              Uri(scheme: 'tel', path: support.phone),
            ),
          if (whatsappDigits.isNotEmpty)
            row(
              Icons.chat_outlined,
              l10n.commonSupportWhatsapp,
              support.whatsapp,
              Uri.https('wa.me', '/$whatsappDigits'),
            ),
        ],
      ),
    );
  }
}
