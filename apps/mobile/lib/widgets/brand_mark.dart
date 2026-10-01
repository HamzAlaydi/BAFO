import 'package:bafo/core/l10n/l10n.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:material_ui/material_ui.dart';

/// The BAFO symbol (green V over red Λ) from the vector master.
///
/// The wordmark is never re-typeset as live text (05_brand.md §2.4); until
/// vector lockups exist, screens show the symbol only. Minimum size: 24 dp.
class BrandMark extends StatelessWidget {
  const BrandMark({
    this.size = 48,
    this.excludeFromSemantics = false,
    super.key,
  });

  static const String asset = 'assets/brand/mark.svg';

  final double size;

  /// Set when an adjacent text already names the app.
  final bool excludeFromSemantics;

  @override
  Widget build(BuildContext context) {
    assert(size >= 24, 'The mark must be at least 24 dp high.');
    return SvgPicture.asset(
      asset,
      width: size,
      height: size,
      excludeFromSemantics: excludeFromSemantics,
      semanticsLabel: excludeFromSemantics ? null : context.l10n.appName,
    );
  }
}
