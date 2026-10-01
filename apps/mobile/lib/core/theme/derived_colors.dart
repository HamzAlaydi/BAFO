import 'dart:ui' show Color;

/// Colour roles the brand package does not define. They are PROPOSED in
/// docs/01_findings/05_brand.md (§2.1–§2.3) and still need brand sign-off.
///
/// Rules behind them:
/// * White on brand green `#0E9F6E` is 3.39:1 (fails AA), so filled primary
///   buttons and green text use green-dark `#0B7A55` (5.34:1).
/// * Brand red fails on dark surfaces, so dark mode uses a lighter red.
/// * Warning `#B7791F` fails as text on white, so text uses `#8C5802`.
abstract final class BafoDerivedColors {
  // Light mode, AA text variants.
  static const Color warningText = Color(0xFF8C5802);

  // Dark mode brand roles. `darkGreen` is the brighter green drawn on
  // charcoal on the guide cover (OBSERVED, not in the token files).
  static const Color darkGreen = Color(0xFF16C27E);
  static const Color darkGreenContainer = Color(0xFF0B3D2C);
  static const Color darkOnGreenContainer = Color(0xFFA3E3C8);
  static const Color darkRed = Color(0xFFEB6B5D);
  static const Color darkRedContainer = Color(0xFF3A1512);
  static const Color darkWarning = Color(0xFFEAA957);
  static const Color darkWarningContainer = Color(0xFF3A2806);
  static const Color darkInfo = Color(0xFF74A2FE);
  static const Color darkInfoContainer = Color(0xFF14264A);
}
