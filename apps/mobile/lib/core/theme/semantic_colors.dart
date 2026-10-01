import 'package:bafo/core/theme/derived_colors.dart';
import 'package:bafo/core/theme/tokens.dart';
import 'package:material_ui/material_ui.dart';

/// A foreground/background pair for chips, pills, banners and toasts.
@immutable
class ToneColors {
  const ToneColors({required this.foreground, required this.background});

  final Color foreground;
  final Color background;

  static ToneColors lerp(ToneColors a, ToneColors b, double t) => ToneColors(
    foreground: Color.lerp(a.foreground, b.foreground, t)!,
    background: Color.lerp(a.background, b.background, t)!,
  );
}

/// Semantic roles that [ColorScheme] has no slot for.
///
/// * [leading]: "your offer is currently the best" (green).
/// * [outbid]: "you are behind" (amber, deliberately not red: red is for
///   destructive actions only, and price direction must never be coded
///   red/green because auctions reward rising prices).
/// * [destructive]: delete, reject, cancel.
///
/// Never rely on colour alone: pair every tone with an icon and a label.
@immutable
class BafoSemanticColors extends ThemeExtension<BafoSemanticColors> {
  const BafoSemanticColors({
    required this.brandGreen,
    required this.brandRed,
    required this.link,
    required this.neutral,
    required this.success,
    required this.warning,
    required this.info,
    required this.leading,
    required this.outbid,
    required this.sponsored,
    required this.inverse,
    required this.primarySolid,
    required this.destructive,
    required this.onDestructive,
    required this.destructiveContainer,
  });

  static const BafoSemanticColors light = BafoSemanticColors(
    brandGreen: BafoTokens.brandGreen,
    brandRed: BafoTokens.brandRed,
    link: BafoTokens.brandGreenDark,
    neutral: ToneColors(
      foreground: BafoTokens.gray700,
      background: BafoTokens.gray100,
    ),
    success: ToneColors(
      foreground: BafoTokens.brandGreenDark,
      background: BafoTokens.brandGreenLight,
    ),
    warning: ToneColors(
      foreground: BafoDerivedColors.warningText,
      background: BafoTokens.warningBackground,
    ),
    info: ToneColors(
      foreground: BafoTokens.info,
      background: BafoTokens.infoBackground,
    ),
    leading: ToneColors(
      foreground: BafoTokens.brandGreenDark,
      background: BafoTokens.brandGreenLight,
    ),
    outbid: ToneColors(
      foreground: BafoDerivedColors.warningText,
      background: BafoTokens.warningBackground,
    ),
    sponsored: ToneColors(
      foreground: BafoTokens.info,
      background: BafoTokens.infoBackground,
    ),
    inverse: ToneColors(
      foreground: BafoTokens.lightTextOnBrand,
      background: BafoTokens.brandCharcoal,
    ),
    primarySolid: ToneColors(
      foreground: BafoTokens.lightTextOnBrand,
      background: BafoTokens.brandGreenDark,
    ),
    destructive: BafoTokens.brandRed,
    onDestructive: BafoTokens.lightTextOnBrand,
    destructiveContainer: ToneColors(
      foreground: BafoTokens.brandRedDark,
      background: BafoTokens.brandRedLight,
    ),
  );

  static const BafoSemanticColors dark = BafoSemanticColors(
    brandGreen: BafoTokens.brandGreen,
    brandRed: BafoTokens.brandRed,
    link: BafoDerivedColors.darkGreen,
    neutral: ToneColors(
      foreground: BafoTokens.gray300,
      background: BafoTokens.darkBgSurfaceAlt,
    ),
    success: ToneColors(
      foreground: BafoDerivedColors.darkOnGreenContainer,
      background: BafoDerivedColors.darkGreenContainer,
    ),
    warning: ToneColors(
      foreground: BafoDerivedColors.darkWarning,
      background: BafoDerivedColors.darkWarningContainer,
    ),
    info: ToneColors(
      foreground: BafoDerivedColors.darkInfo,
      background: BafoDerivedColors.darkInfoContainer,
    ),
    leading: ToneColors(
      foreground: BafoDerivedColors.darkOnGreenContainer,
      background: BafoDerivedColors.darkGreenContainer,
    ),
    outbid: ToneColors(
      foreground: BafoDerivedColors.darkWarning,
      background: BafoDerivedColors.darkWarningContainer,
    ),
    sponsored: ToneColors(
      foreground: BafoDerivedColors.darkInfo,
      background: BafoDerivedColors.darkInfoContainer,
    ),
    inverse: ToneColors(
      foreground: BafoTokens.brandCharcoal,
      background: BafoTokens.darkTextPrimary,
    ),
    primarySolid: ToneColors(
      foreground: BafoTokens.darkBgPage,
      background: BafoDerivedColors.darkGreen,
    ),
    destructive: BafoDerivedColors.darkRed,
    onDestructive: BafoTokens.darkBgPage,
    destructiveContainer: ToneColors(
      foreground: BafoTokens.brandRedLight,
      background: BafoDerivedColors.darkRedContainer,
    ),
  );

  /// Brand green for the mark, illustrations and large brand moments only
  /// (fails AA as text on white).
  final Color brandGreen;

  /// Brand red for the mark only.
  final Color brandRed;

  /// Inline links and small green text.
  final Color link;

  final ToneColors neutral;
  final ToneColors success;
  final ToneColors warning;
  final ToneColors info;
  final ToneColors leading;
  final ToneColors outbid;

  /// "Fees covered" (sponsored participation) badges.
  final ToneColors sponsored;

  /// Charcoal with white text: the BAFO round chip and banner.
  final ToneColors inverse;

  /// Solid primary: the "awarded" status and the "won" result.
  final ToneColors primarySolid;

  final Color destructive;
  final Color onDestructive;
  final ToneColors destructiveContainer;

  @override
  BafoSemanticColors copyWith({
    Color? brandGreen,
    Color? brandRed,
    Color? link,
    ToneColors? neutral,
    ToneColors? success,
    ToneColors? warning,
    ToneColors? info,
    ToneColors? leading,
    ToneColors? outbid,
    ToneColors? sponsored,
    ToneColors? inverse,
    ToneColors? primarySolid,
    Color? destructive,
    Color? onDestructive,
    ToneColors? destructiveContainer,
  }) {
    return BafoSemanticColors(
      brandGreen: brandGreen ?? this.brandGreen,
      brandRed: brandRed ?? this.brandRed,
      link: link ?? this.link,
      neutral: neutral ?? this.neutral,
      success: success ?? this.success,
      warning: warning ?? this.warning,
      info: info ?? this.info,
      leading: leading ?? this.leading,
      outbid: outbid ?? this.outbid,
      sponsored: sponsored ?? this.sponsored,
      inverse: inverse ?? this.inverse,
      primarySolid: primarySolid ?? this.primarySolid,
      destructive: destructive ?? this.destructive,
      onDestructive: onDestructive ?? this.onDestructive,
      destructiveContainer: destructiveContainer ?? this.destructiveContainer,
    );
  }

  @override
  BafoSemanticColors lerp(BafoSemanticColors? other, double t) {
    if (other == null) return this;
    return BafoSemanticColors(
      brandGreen: Color.lerp(brandGreen, other.brandGreen, t)!,
      brandRed: Color.lerp(brandRed, other.brandRed, t)!,
      link: Color.lerp(link, other.link, t)!,
      neutral: ToneColors.lerp(neutral, other.neutral, t),
      success: ToneColors.lerp(success, other.success, t),
      warning: ToneColors.lerp(warning, other.warning, t),
      info: ToneColors.lerp(info, other.info, t),
      leading: ToneColors.lerp(leading, other.leading, t),
      outbid: ToneColors.lerp(outbid, other.outbid, t),
      sponsored: ToneColors.lerp(sponsored, other.sponsored, t),
      inverse: ToneColors.lerp(inverse, other.inverse, t),
      primarySolid: ToneColors.lerp(primarySolid, other.primarySolid, t),
      destructive: Color.lerp(destructive, other.destructive, t)!,
      onDestructive: Color.lerp(onDestructive, other.onDestructive, t)!,
      destructiveContainer: ToneColors.lerp(
        destructiveContainer,
        other.destructiveContainer,
        t,
      ),
    );
  }
}

extension BafoThemeX on BuildContext {
  /// The BAFO semantic colours of the ambient theme.
  BafoSemanticColors get semanticColors =>
      Theme.of(this).extension<BafoSemanticColors>() ??
      BafoSemanticColors.light;
}
