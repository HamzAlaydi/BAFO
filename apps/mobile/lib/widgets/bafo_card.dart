import 'package:bafo/core/theme/spacing.dart';
import 'package:material_ui/material_ui.dart';

/// A bordered surface for grouped content. Tappable when [onTap] is set.
class BafoCard extends StatelessWidget {
  const BafoCard({
    required this.child,
    this.onTap,
    this.padding = const EdgeInsetsDirectional.all(BafoSpacing.lg),
    this.color,
    super.key,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;

  /// Overrides the theme's card colour (e.g. a tinted highlight).
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final content = Padding(padding: padding, child: child);
    return Card(
      color: color,
      child: onTap == null ? content : InkWell(onTap: onTap, child: content),
    );
  }
}
