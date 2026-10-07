import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/account/presentation/account_paths.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/home/presentation/home_sections.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M51 Account hub: who is signed in, the organisation and its plan, links
/// to the account screens (by permission, S10, and by release-scope flag,
/// RELEASE_SCOPE.md §4), and sign-out.
///
/// It renders the session's `Me`; pull to refresh re-reads `GET /me`.
class AccountHubScreen extends StatefulWidget {
  const AccountHubScreen({super.key});

  @override
  State<AccountHubScreen> createState() => _AccountHubScreenState();
}

class _AccountHubScreenState extends State<AccountHubScreen> {
  bool _signingOut = false;

  Future<void> _signOut() async {
    final l10n = context.l10n;
    final session = context.read<SessionCubit>();
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.authLogoutConfirmTitle,
      message: l10n.authLogoutConfirmMessage,
      confirmLabel: l10n.authLogoutAction,
      destructive: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _signingOut = true);
    // Device removal, token revocation and the local sign-out (SessionCubit;
    // handoff M-1). The router then leaves the signed-in shell.
    await session.signOut();
    if (mounted) setState(() => _signingOut = false);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final me = context.select<SessionCubit, Me?>((cubit) => cubit.state.me);
    // Release scope (RELEASE_SCOPE.md §4): Team and Invoices are built only
    // when their flags are on, never greyed out.
    final flags = context.flags;
    final version = context.read<ClientInfo>().appVersion;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: BafoAppBar(title: l10n.navAccount),
      body: RefreshIndicator(
        onRefresh: context.read<SessionCubit>().refreshMe,
        child: ListView(
          key: const Key('account.list'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsetsDirectional.fromSTEB(
            BafoSpacing.page,
            BafoSpacing.sm,
            BafoSpacing.page,
            BafoSpacing.xxl,
          ),
          children: [
            if (me == null)
              InfoNotice(
                tone: StatusTone.warning,
                icon: Icons.sync_problem_rounded,
                message: l10n.accountMeUnavailable,
              )
            else ...[
              _UserCard(me: me),
              const SizedBox(height: BafoSpacing.sm),
              _OrganizationCard(me: me),
              const SizedBox(height: BafoSpacing.sm),
              HomeSubscriptionCard(
                subscription: me.subscription,
                teamMembers: me.entitlements.seatsUsed,
                seatsTotal: me.entitlements.seatsTotal,
                onOpen: () => context.push(AppRoutes.billing),
              ),
              AccountSection(
                title: l10n.accountSectionAccount,
                children: [
                  AccountLinkTile(
                    key: const Key('account.profile'),
                    icon: Icons.person_outline_rounded,
                    title: l10n.accountProfileTitle,
                    onTap: () => context.go(AccountPaths.profile),
                  ),
                  AccountLinkTile(
                    key: const Key('account.password'),
                    icon: Icons.lock_outline_rounded,
                    title: l10n.accountPasswordTitle,
                    onTap: () => context.go(AccountPaths.password),
                  ),
                ],
              ),
              AccountSection(
                title: l10n.accountSectionOrganization,
                children: [
                  AccountLinkTile(
                    key: const Key('account.organization'),
                    icon: Icons.apartment_rounded,
                    title: l10n.accountOrganizationTitle,
                    onTap: () => context.go(AccountPaths.organization),
                  ),
                  if (me.can(Permissions.teamManage) &&
                      flags.enabled(Feature.teamManagement))
                    AccountLinkTile(
                      key: const Key('account.team'),
                      icon: Icons.groups_outlined,
                      title: l10n.accountTeamTitle,
                      onTap: () => context.go(AccountPaths.team),
                    ),
                  AccountLinkTile(
                    key: const Key('account.plan'),
                    icon: Icons.workspace_premium_outlined,
                    title: l10n.billingStatusTitle,
                    onTap: () => context.push(AppRoutes.billing),
                  ),
                  if (me.can(Permissions.billingView) &&
                      flags.enabled(Feature.billingInvoices))
                    AccountLinkTile(
                      key: const Key('account.invoices'),
                      icon: Icons.receipt_long_outlined,
                      title: l10n.accountInvoicesTitle,
                      onTap: () => context.go(AccountPaths.invoices),
                    ),
                ],
              ),
            ],
            AccountSection(
              title: l10n.accountSectionApp,
              children: [
                AccountLinkTile(
                  key: const Key('account.settings'),
                  icon: Icons.settings_outlined,
                  title: l10n.accountSettingsTitle,
                  onTap: () => context.go(AccountPaths.settings),
                ),
                AccountLinkTile(
                  key: const Key('account.help'),
                  icon: Icons.support_agent_outlined,
                  title: l10n.accountHelpTitle,
                  onTap: () => context.go(AccountPaths.help),
                ),
                if (me != null)
                  AccountLinkTile(
                    key: const Key('account.delete'),
                    icon: Icons.person_remove_outlined,
                    title: l10n.accountDeleteTitle,
                    onTap: () => context.go(AccountPaths.deleteAccount),
                  ),
              ],
            ),
            const SizedBox(height: BafoSpacing.xl),
            BafoButton.outline(
              key: const Key('account.signOut'),
              label: l10n.authLogoutAction,
              icon: Icons.logout_rounded,
              expand: true,
              loading: _signingOut,
              onPressed: _signingOut ? null : () => unawaited(_signOut()),
            ),
            const SizedBox(height: BafoSpacing.lg),
            Center(
              child: Text(
                l10n.accountVersion(version),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _UserCard extends StatelessWidget {
  const _UserCard({required this.me});

  final Me me;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final user = me.user;
    return BafoCard(
      onTap: () => context.go(AccountPaths.profile),
      child: Row(
        children: [
          OrgAvatar(
            name: user.name,
            logoUrl: user.avatarUrl,
            size: 52,
            decorative: true,
          ),
          const SizedBox(width: BafoSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user.name, style: theme.textTheme.titleMedium),
                const SizedBox(height: BafoSpacing.xxs),
                Ltr(
                  child: Text(
                    user.email,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Icon(
            Icons.chevron_right_rounded,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ],
      ),
    );
  }
}

class _OrganizationCard extends StatelessWidget {
  const _OrganizationCard({required this.me});

  final Me me;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final organization = me.organization;
    return BafoCard(
      onTap: () => context.go(AccountPaths.organization),
      child: Row(
        children: [
          OrgAvatar(
            name: organization.name,
            logoUrl: organization.logoUrl,
            size: 44,
            decorative: true,
          ),
          const SizedBox(width: BafoSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(organization.name, style: theme.textTheme.titleSmall),
                const SizedBox(height: BafoSpacing.xs),
                Wrap(
                  spacing: BafoSpacing.xs,
                  runSpacing: BafoSpacing.xs,
                  children: [
                    StatusPill(
                      label: roleLabel(l10n, me.membership.role),
                      icon: Icons.badge_outlined,
                    ),
                    if (organization.verified)
                      StatusPill(
                        label: l10n.accountVerified,
                        tone: StatusTone.success,
                        icon: Icons.verified_outlined,
                      ),
                  ],
                ),
              ],
            ),
          ),
          Icon(
            Icons.chevron_right_rounded,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ],
      ),
    );
  }
}
