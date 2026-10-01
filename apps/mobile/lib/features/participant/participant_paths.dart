/// Canonical client paths of the participant screens (CONVENTIONS.md §4.3,
/// SCREENS.md §3.1). Push deep links use the same strings.
abstract final class ParticipantPaths {
  static const String list = '/competitions';

  /// M17 (invitee) / M21 (participant).
  static String competition(String id) => '/competitions/$id';

  /// M17 with the join sheet opened once the invitation has loaded (the
  /// "Join" action of a list card).
  static String join(String id) => '/competitions/$id?intent=join';

  /// M23 live room.
  static String live(String id) => '/competitions/$id/live';

  /// M31 Q&A.
  static String qa(String id) => '/competitions/$id/qa';

  /// M28 my offers.
  static String myOffers(String id) => '/competitions/$id/my-offers';

  /// M13 competition rules (linked from the join sheet).
  static const String competitionRules = '/legal/competition_rules';

  /// Query parameter of [join].
  static const String intentParameter = 'intent';
  static const String intentJoin = 'join';
}
