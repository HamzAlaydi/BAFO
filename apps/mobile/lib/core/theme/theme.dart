import 'package:bafo/core/theme/derived_colors.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/tokens.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

/// Material 3 themes built from the BAFO tokens.
///
/// Accessibility decision (05_brand.md §2.3, option B "strict AA"): filled
/// primary buttons use green-dark `#0B7A55` (5.34:1 with white). Brand green
/// `#0E9F6E` is kept for the mark and large brand moments
/// ([BafoSemanticColors.brandGreen]).
abstract final class BafoTheme {
  static const ColorScheme lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: BafoTokens.brandGreenDark,
    onPrimary: BafoTokens.lightTextOnBrand,
    primaryContainer: BafoTokens.brandGreenLight,
    onPrimaryContainer: BafoTokens.brandGreenDark,
    secondary: BafoTokens.brandCharcoal,
    onSecondary: BafoTokens.lightTextOnBrand,
    secondaryContainer: BafoTokens.gray100,
    onSecondaryContainer: BafoTokens.brandCharcoal,
    tertiary: BafoTokens.info,
    onTertiary: BafoTokens.lightTextOnBrand,
    tertiaryContainer: BafoTokens.infoBackground,
    onTertiaryContainer: BafoTokens.info,
    error: BafoTokens.error,
    onError: BafoTokens.lightTextOnBrand,
    errorContainer: BafoTokens.errorBackground,
    onErrorContainer: BafoTokens.brandRedDark,
    surface: BafoTokens.lightBgPage,
    onSurface: BafoTokens.lightTextPrimary,
    onSurfaceVariant: BafoTokens.lightTextSecondary,
    surfaceContainerLowest: BafoTokens.lightBgPage,
    surfaceContainerLow: BafoTokens.lightBgSurface,
    surfaceContainer: BafoTokens.lightBgSurfaceAlt,
    surfaceContainerHigh: BafoTokens.gray200,
    surfaceContainerHighest: BafoTokens.gray300,
    // Input borders need 3:1 against the page, so outline is gray-500.
    outline: BafoTokens.gray500,
    outlineVariant: BafoTokens.lightBorderDefault,
    inverseSurface: BafoTokens.brandCharcoal,
    onInverseSurface: BafoTokens.gray100,
    inversePrimary: BafoDerivedColors.darkGreen,
    shadow: BafoTokens.brandCharcoal,
    scrim: BafoTokens.brandCharcoal,
    surfaceTint: Colors.transparent,
  );

  static const ColorScheme darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: BafoDerivedColors.darkGreen,
    onPrimary: BafoTokens.darkBgPage,
    primaryContainer: BafoDerivedColors.darkGreenContainer,
    onPrimaryContainer: BafoDerivedColors.darkOnGreenContainer,
    secondary: BafoTokens.darkTextPrimary,
    onSecondary: BafoTokens.darkBgPage,
    secondaryContainer: BafoTokens.darkBgSurfaceAlt,
    onSecondaryContainer: BafoTokens.darkTextPrimary,
    tertiary: BafoDerivedColors.darkInfo,
    onTertiary: BafoTokens.darkBgPage,
    tertiaryContainer: BafoDerivedColors.darkInfoContainer,
    onTertiaryContainer: BafoTokens.infoBackground,
    error: BafoDerivedColors.darkRed,
    onError: BafoTokens.darkBgPage,
    errorContainer: BafoDerivedColors.darkRedContainer,
    onErrorContainer: BafoTokens.brandRedLight,
    surface: BafoTokens.darkBgPage,
    onSurface: BafoTokens.darkTextPrimary,
    onSurfaceVariant: BafoTokens.darkTextSecondary,
    surfaceContainerLowest: BafoTokens.darkBgPage,
    surfaceContainerLow: BafoTokens.darkBgSurface,
    surfaceContainer: BafoTokens.darkBgSurface,
    surfaceContainerHigh: BafoTokens.darkBgSurfaceAlt,
    surfaceContainerHighest: BafoTokens.darkBorderDefault,
    outline: BafoTokens.gray500,
    outlineVariant: BafoTokens.darkBorderDefault,
    inverseSurface: BafoTokens.darkTextPrimary,
    onInverseSurface: BafoTokens.brandCharcoal,
    inversePrimary: BafoTokens.brandGreenDark,
    shadow: Color(0xFF000000),
    scrim: Color(0xFF000000),
    surfaceTint: Colors.transparent,
  );

  /// Transparent (edge-to-edge) system bars with icons that contrast with a
  /// background of [brightness].
  static SystemUiOverlayStyle systemBarsOn(Brightness brightness) {
    final base = brightness == Brightness.light
        ? SystemUiOverlayStyle.dark
        : SystemUiOverlayStyle.light;
    return base.copyWith(
      statusBarColor: Colors.transparent,
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarContrastEnforced: false,
    );
  }

  static ThemeData light(String languageCode) =>
      _build(lightScheme, BafoSemanticColors.light, languageCode);

  static ThemeData dark(String languageCode) =>
      _build(darkScheme, BafoSemanticColors.dark, languageCode);

  static ThemeData _build(
    ColorScheme scheme,
    BafoSemanticColors semantic,
    String languageCode,
  ) {
    final text = BafoTypography.textTheme(languageCode, scheme.onSurface);
    final buttonShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(BafoRadii.button),
    );
    const buttonSize = Size(64, BafoSizes.buttonHeight);
    const buttonPadding = EdgeInsets.symmetric(horizontal: BafoSpacing.xl);
    final buttonLabel = text.labelLarge;
    OutlineInputBorder inputBorder(Color color, [double width = 1]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(BafoRadii.input),
          borderSide: BorderSide(color: color, width: width),
        );

    return ThemeData(
      useMaterial3: true,
      brightness: scheme.brightness,
      colorScheme: scheme,
      textTheme: text,
      fontFamily: text.bodyMedium?.fontFamily,
      scaffoldBackgroundColor: scheme.surface,
      canvasColor: scheme.surface,
      dividerColor: scheme.outlineVariant,
      splashFactory: InkSparkle.splashFactory,
      extensions: [semantic],
      appBarTheme: AppBarThemeData(
        systemOverlayStyle: systemBarsOn(scheme.brightness),
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 1,
        shadowColor: scheme.outlineVariant,
        centerTitle: false,
        titleSpacing: BafoSpacing.page,
        titleTextStyle: text.titleLarge,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: buttonSize,
          padding: buttonPadding,
          shape: buttonShape,
          textStyle: buttonLabel,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: buttonSize,
          padding: buttonPadding,
          shape: buttonShape,
          textStyle: buttonLabel,
          foregroundColor: scheme.onSurface,
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      // Brand FABs (team invite, create competition, Q&A, documents): the
      // green container with the brand label and radius, on a low, soft
      // elevation. The Material 3 default (level 3, 6 dp) drew a heavy
      // charcoal shadow; in widget renders, where flutter_test disables
      // shadows, it becomes a solid charcoal ring 2 × elevation wide (the
      // "thick black outline" of the team screenshot), not a focus style.
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: scheme.primaryContainer,
        foregroundColor: scheme.onPrimaryContainer,
        elevation: 2,
        focusElevation: 2,
        hoverElevation: 3,
        highlightElevation: 1,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BafoRadii.card),
        ),
        extendedTextStyle: buttonLabel,
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, BafoSizes.buttonHeight),
          padding: const EdgeInsets.symmetric(horizontal: BafoSpacing.md),
          shape: buttonShape,
          textStyle: buttonLabel,
          foregroundColor: semantic.link,
        ),
      ),
      inputDecorationTheme: InputDecorationThemeData(
        filled: true,
        fillColor: scheme.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: BafoSpacing.lg,
          vertical: 14,
        ),
        hintStyle: text.bodyLarge?.copyWith(color: scheme.onSurfaceVariant),
        errorStyle: text.bodySmall?.copyWith(color: scheme.error),
        errorMaxLines: 3,
        border: inputBorder(scheme.outline),
        enabledBorder: inputBorder(scheme.outline),
        focusedBorder: inputBorder(scheme.primary, 2),
        errorBorder: inputBorder(scheme.error),
        focusedErrorBorder: inputBorder(scheme.error, 2),
        disabledBorder: inputBorder(scheme.outlineVariant),
      ),
      cardTheme: CardThemeData(
        color: scheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BafoRadii.card),
          side: BorderSide(color: scheme.outlineVariant),
        ),
        clipBehavior: Clip.antiAlias,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        height: 68,
        indicatorColor: scheme.primaryContainer,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? scheme.onPrimaryContainer
                : scheme.onSurfaceVariant,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => text.labelSmall?.copyWith(
            color: states.contains(WidgetState.selected)
                ? scheme.onSurface
                : scheme.onSurfaceVariant,
          ),
        ),
      ),
      dividerTheme: DividerThemeData(
        color: scheme.outlineVariant,
        thickness: 1,
        space: 1,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BafoRadii.dialog),
        ),
        titleTextStyle: text.titleLarge,
        contentTextStyle: text.bodyMedium?.copyWith(
          color: scheme.onSurfaceVariant,
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: scheme.outline,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(BafoRadii.sheet),
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: scheme.inverseSurface,
        contentTextStyle: text.bodyMedium?.copyWith(
          color: scheme.onInverseSurface,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BafoRadii.button),
        ),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: scheme.primary),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: SegmentedButton.styleFrom(
          selectedBackgroundColor: scheme.primaryContainer,
          selectedForegroundColor: scheme.onPrimaryContainer,
          side: BorderSide(color: scheme.outline),
          textStyle: text.labelMedium,
        ),
      ),
    );
  }
}
