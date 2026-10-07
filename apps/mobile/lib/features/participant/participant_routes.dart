import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/features/live/presentation/live_room_screen.dart';
import 'package:bafo/features/live/presentation/my_offers_screen.dart';
import 'package:bafo/features/notifications/presentation/push_explainer_sheet.dart';
import 'package:bafo/features/participant/participant_paths.dart';
import 'package:bafo/features/participant/presentation/participant_competition_screen.dart';
import 'package:bafo/features/qa/presentation/qa_screen.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

export 'package:bafo/features/live/presentation/live_room_screen.dart'
    show LiveViewBuilder;
export 'package:bafo/features/participant/presentation/participant_competition_screen.dart'
    show CompetitionViewBuilder;
export 'package:bafo/features/participant/presentation/participating_list_screen.dart'
    show ParticipatingListScreen;

/// The participant screens on the root navigator (SCREENS.md §3.1):
///
/// * `/competitions/:id` → M17 (invitee) / M21 (participant); an issuer gets
///   [issuerDetail];
/// * `…/live` → M23; an issuer gets [issuerLive] (M46), others without a
///   participation go back to the overview;
/// * `…/qa` → M31 (participants and the issuer);
/// * `…/my-offers` → M28.
///
/// List these **before** the shell route: go_router takes the first full
/// match, and a path none of these children match (`…/offers`,
/// `…/participants`, …) falls through to the next route.
List<RouteBase> participantRoutes(
  GlobalKey<NavigatorState> rootKey, {
  required CompetitionViewBuilder issuerDetail,
  LiveViewBuilder? issuerLive,
}) => [
  GoRoute(
    path: '${ParticipantPaths.list}/:id',
    parentNavigatorKey: rootKey,
    builder: (_, state) => ParticipantCompetitionScreen(
      key: ValueKey('competition-${state.pathParameters['id']}'),
      competitionId: state.pathParameters['id']!,
      openJoin:
          state.uri.queryParameters[ParticipantPaths.intentParameter] ==
          ParticipantPaths.intentJoin,
      issuerView: issuerDetail,
      // M50, once, after the first successful join (never at cold start;
      // scope `full` only, RELEASE_SCOPE.md §4.1).
      afterJoin: showPushExplainerInScope,
    ),
    routes: [
      GoRoute(
        path: 'live',
        parentNavigatorKey: rootKey,
        builder: (_, state) => LiveRoomScreen(
          competitionId: state.pathParameters['id']!,
          issuerView: issuerLive,
        ),
      ),
      // Q&A behind its flag (`qa_comments`, on in both scopes today).
      GoRoute(
        path: 'qa',
        parentNavigatorKey: rootKey,
        builder: (_, state) => FeatureGate(
          feature: Feature.qaComments,
          fallback: const FeatureUnavailableScreen(),
          child: QaScreen(competitionId: state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: 'my-offers',
        parentNavigatorKey: rootKey,
        builder: (_, state) =>
            MyOffersScreen(competitionId: state.pathParameters['id']!),
      ),
    ],
  ),
];
