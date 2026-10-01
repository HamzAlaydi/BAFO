import 'package:material_ui/material_ui.dart';

/// 4-point spacing scale.
abstract final class BafoSpacing {
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
  static const double xxxl = 48;

  /// Horizontal page gutter.
  static const double page = 16;

  static const EdgeInsetsDirectional pagePadding = EdgeInsetsDirectional.all(
    page,
  );
}

/// Corner radii. Buttons use ~20% of their 48 dp height (brand guide p.8).
abstract final class BafoRadii {
  static const double sm = 6;
  static const double button = 10;
  static const double input = 10;
  static const double card = 12;
  static const double dialog = 16;
  static const double sheet = 20;
  static const double pill = 999;
}

/// Fixed component sizes.
abstract final class BafoSizes {
  static const double buttonHeight = 48;
  static const double minTouchTarget = 48;
  static const double appBarMark = 28;
}
