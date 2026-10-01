import 'package:material_ui/material_ui.dart';

/// BAFO type system (docs/01_findings/05_brand.md §2.4).
///
/// * Arabic UI: IBM Plex Sans Arabic, Inter as Latin fallback, taller lines.
/// * English UI: Inter, IBM Plex Sans Arabic as fallback.
/// * Letter spacing is always 0: tracking breaks Arabic letter joining.
/// * Prices, offers and countdowns use Western digits with tabular figures
///   (see [BafoTypography.tabular]) so live updates do not jitter.
abstract final class BafoTypography {
  static const String arabicFamily = 'IBMPlexSansArabic';
  static const String latinFamily = 'Inter';

  /// A text theme for [languageCode] with every colour set to [color].
  static TextTheme textTheme(String languageCode, Color color) {
    final arabic = languageCode == 'ar';
    final family = arabic ? arabicFamily : latinFamily;
    final fallback = [arabic ? latinFamily : arabicFamily];
    // Arabic needs more line height than Latin for its ascenders and dots.
    final body = arabic ? 1.6 : 1.4;
    final title = arabic ? 1.45 : 1.3;

    TextStyle style(double size, FontWeight weight, double height) => TextStyle(
      fontFamily: family,
      fontFamilyFallback: fallback,
      fontSize: size,
      fontWeight: weight,
      height: height,
      letterSpacing: 0,
      color: color,
    );

    return TextTheme(
      displayLarge: style(40, FontWeight.w700, title),
      displayMedium: style(36, FontWeight.w700, title),
      displaySmall: style(32, FontWeight.w700, title),
      headlineLarge: style(28, FontWeight.w700, title),
      headlineMedium: style(24, FontWeight.w700, title),
      headlineSmall: style(20, FontWeight.w700, title),
      titleLarge: style(18, FontWeight.w600, title),
      titleMedium: style(16, FontWeight.w600, title),
      titleSmall: style(14, FontWeight.w600, title),
      bodyLarge: style(16, FontWeight.w400, body),
      bodyMedium: style(14, FontWeight.w400, body),
      bodySmall: style(12, FontWeight.w400, body),
      labelLarge: style(16, FontWeight.w700, title),
      labelMedium: style(14, FontWeight.w600, title),
      labelSmall: style(12, FontWeight.w600, title),
    );
  }

  /// [style] with tabular (fixed-width) figures for numbers that change live.
  static TextStyle tabular(TextStyle style) =>
      style.copyWith(fontFeatures: const [FontFeature.tabularFigures()]);
}
