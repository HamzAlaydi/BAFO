/// Canonical client paths of the issuer screens (CONVENTIONS.md §4.3,
/// SCREENS.md §3.1). Push deep links use the same strings.
abstract final class IssuerPaths {
  /// M33, the "My competitions" tab.
  static const String list = '/my-competitions';

  /// M34–M37, the creation wizard (root navigator).
  static const String create = '/my-competitions/new';

  /// M38 (issuer view of the shared detail, CD1).
  static String competition(String id) => '/competitions/$id';

  /// M46 (issuer) — the same path as the participant live room.
  static String live(String id) => '/competitions/$id/live';

  /// M31, the shared Q&A (issuer replies and announcements).
  static String qa(String id) => '/competitions/$id/qa';

  /// M47.
  static String offers(String id) => '/competitions/$id/offers';

  /// M41.
  static String participants(String id) => '/competitions/$id/participants';

  /// M40.
  static String invite(String id) => '/competitions/$id/invite';

  /// M42.
  static String attachments(String id) => '/competitions/$id/attachments';

  /// M39.
  static String edit(String id) => '/competitions/$id/edit';

  /// M48.
  static String award(String id) => '/competitions/$id/award';
}
