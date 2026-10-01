import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/features/account/data/contact_repository.dart';
import 'package:bafo/features/account/presentation/account_hub_screen.dart';
import 'package:bafo/features/account/presentation/deletion/delete_account_screen.dart';
import 'package:bafo/features/account/presentation/help/help_screen.dart';
import 'package:bafo/features/account/presentation/invoices/invoices_screen.dart';
import 'package:bafo/features/account/presentation/organization/organization_screen.dart';
import 'package:bafo/features/account/presentation/password/change_password_screen.dart';
import 'package:bafo/features/account/presentation/profile/edit_profile_screen.dart';
import 'package:bafo/features/account/presentation/settings/settings_screen.dart';
import 'package:bafo/features/account/presentation/team/team_member_form_screen.dart';
import 'package:bafo/features/account/presentation/team/team_screen.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

/// The Account tab (SCREENS.md §3.1): `/account` (M51) and its children,
/// inside the tab's navigator so the bottom bar stays visible. The plan
/// status (M58) is the root-level `/billing`; legal documents are
/// `/legal/:code` (M13).
///
/// `/account/organization?edit=1` opens the organisation form directly (the
/// home billing-profile alert).
GoRoute accountRoute() => GoRoute(
  path: AppRoutes.account,
  builder: (_, _) => const AccountHubScreen(),
  routes: [
    GoRoute(path: 'profile', builder: (_, _) => const EditProfileScreen()),
    GoRoute(path: 'password', builder: (_, _) => const ChangePasswordScreen()),
    GoRoute(
      path: 'organization',
      builder: (_, state) => OrganizationScreen(
        startEditing: state.uri.queryParameters['edit'] == '1',
      ),
    ),
    GoRoute(
      path: 'team',
      builder: (_, _) => const TeamScreen(),
      routes: [
        GoRoute(path: 'new', builder: (_, _) => const TeamMemberFormScreen()),
        GoRoute(
          path: ':membershipId',
          builder: (_, state) => TeamMemberFormScreen(
            membershipId: state.pathParameters['membershipId'],
            member: state.extra is TeamMember
                ? state.extra! as TeamMember
                : null,
          ),
        ),
      ],
    ),
    GoRoute(path: 'invoices', builder: (_, _) => const InvoicesScreen()),
    GoRoute(path: 'settings', builder: (_, _) => const SettingsScreen()),
    GoRoute(
      path: 'help',
      builder: (_, _) => RepositoryProvider<ContactRepository>(
        create: (context) => ApiContactRepository(context.read<ApiClient>()),
        child: const HelpScreen(),
      ),
    ),
    GoRoute(
      path: 'delete-account',
      builder: (_, _) => const DeleteAccountScreen(),
    ),
  ],
);
