/// The issuer feature (SCREENS.md M33–M48): what other features and the
/// router use.
///
/// * [issuerTabRoute]: the "My competitions" tab with its creation wizard.
/// * [issuerCompetitionChildRoutes] / [issuerCompetitionRoutes]: the
///   issuer-only children of `/competitions/:id`.
/// * [issuerDetailView] (M38) and [issuerLiveView] (M46): the issuer's side
///   of the shared `/competitions/:id` and `/competitions/:id/live`, for the
///   `viewer_role` dispatch (`participantRoutes(issuerDetail:, issuerLive:)`).
library;

export 'package:bafo/features/issuer/issuer_paths.dart';
export 'package:bafo/features/issuer/presentation/detail/issuer_competition_screen.dart'
    show IssuerCompetitionScreen, IssuerCompetitionView;
export 'package:bafo/features/issuer/presentation/issuer_routes.dart';
export 'package:bafo/features/issuer/presentation/live/issuer_live_monitor_screen.dart'
    show IssuerLiveMonitorScreen;
