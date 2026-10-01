import 'package:bafo/features/issuer/issuer_paths.dart';
import 'package:bafo/features/issuer/presentation/attachments/manage_attachments_screen.dart';
import 'package:bafo/features/issuer/presentation/award/award_screen.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_screen.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_competition_screen.dart';
import 'package:bafo/features/issuer/presentation/edit/edit_competition_screen.dart';
import 'package:bafo/features/issuer/presentation/invite/invite_participants_screen.dart';
import 'package:bafo/features/issuer/presentation/list/my_competitions_screen.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_monitor_screen.dart';
import 'package:bafo/features/issuer/presentation/offers_log/offers_log_screen.dart';
import 'package:bafo/features/issuer/presentation/participants/participants_screen.dart';
import 'package:bafo/features/notifications/presentation/push_explainer_sheet.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The "My competitions" tab (M33) and its creation wizard (M34–M37), which
/// opens on the root navigator ([rootKey]) above the shell.
GoRoute issuerTabRoute(GlobalKey<NavigatorState> rootKey) => GoRoute(
  path: IssuerPaths.list,
  builder: (_, _) => const MyCompetitionsScreen(),
  routes: [
    GoRoute(
      path: 'new',
      parentNavigatorKey: rootKey,
      builder: (_, _) => const CreateCompetitionScreen(),
    ),
  ],
);

/// The issuer-only children of `/competitions/:id` (SCREENS.md §3.1), as
/// relative routes for the `:id` route: `offers` (M47), `participants`
/// (M41), `invite` (M40), `attachments` (M42), `edit` (M39) and `award`
/// (M48). Each screen checks the loaded `viewer_role` and falls back to
/// `/competitions/:id` (role guard).
///
/// `/competitions/:id` itself (M38: `IssuerCompetitionScreen`) and `live`
/// (M46: `IssuerLiveMonitorScreen`) are shared with the participant
/// screens and dispatched by `viewer_role` where that route is defined.
List<GoRoute> issuerCompetitionChildRoutes(GlobalKey<NavigatorState> rootKey) =>
    [
      for (final (path, builder) in _children)
        GoRoute(
          path: path,
          parentNavigatorKey: rootKey,
          builder: (_, state) => builder(state.pathParameters['id']!),
        ),
    ];

/// The same screens as top-level routes (`/competitions/:id/offers`, …),
/// for a router whose `:id` route does not include
/// [issuerCompetitionChildRoutes]. go_router matches them after the shell,
/// so they never shadow the participant routes.
List<GoRoute> issuerCompetitionRoutes(GlobalKey<NavigatorState> rootKey) => [
  for (final (path, builder) in _children)
    GoRoute(
      path: '/competitions/:id/$path',
      parentNavigatorKey: rootKey,
      builder: (_, state) => builder(state.pathParameters['id']!),
    ),
];

final List<(String, Widget Function(String id))> _children = [
  ('offers', (id) => OffersLogScreen(competitionId: id)),
  ('participants', (id) => ParticipantsScreen(competitionId: id)),
  ('invite', (id) => InviteParticipantsScreen(competitionId: id)),
  ('attachments', (id) => ManageAttachmentsScreen(competitionId: id)),
  ('edit', (id) => EditCompetitionScreen(competitionId: id)),
  ('award', (id) => AwardScreen(competitionId: id)),
];

/// M38 for the `viewer_role` dispatch of the shared `/competitions/:id`
/// (the participant feature's `CompetitionViewBuilder`).
/// A successful publish offers the push explainer (M50) once.
Widget issuerDetailView(BuildContext context, String competitionId) =>
    IssuerCompetitionScreen(
      competitionId: competitionId,
      afterPublish: showPushExplainerIfNeeded,
    );

/// M46 for the `viewer_role` dispatch of the shared
/// `/competitions/:id/live` (the live feature's `LiveViewBuilder`).
Widget issuerLiveView(BuildContext context, String competitionId) =>
    IssuerLiveMonitorScreen(competitionId: competitionId);
