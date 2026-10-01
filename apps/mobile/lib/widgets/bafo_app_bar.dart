import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/brand_mark.dart';
import 'package:material_ui/material_ui.dart';

/// The app bar: white bar, optional brand mark at the start, page title.
/// The mark is decorative when a title is shown (the title names the page).
class BafoAppBar extends StatelessWidget implements PreferredSizeWidget {
  const BafoAppBar({
    this.title,
    this.showLogo = false,
    this.actions,
    this.bottom,
    super.key,
  });

  final String? title;
  final bool showLogo;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;

  @override
  Size get preferredSize =>
      Size.fromHeight(kToolbarHeight + (bottom?.preferredSize.height ?? 0));

  @override
  Widget build(BuildContext context) {
    final text = title;
    return AppBar(
      title: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (showLogo) ...[
            BrandMark(
              size: BafoSizes.appBarMark,
              excludeFromSemantics: text != null,
            ),
            if (text != null) const SizedBox(width: BafoSpacing.md),
          ],
          if (text != null)
            Flexible(child: Text(text, overflow: TextOverflow.ellipsis)),
        ],
      ),
      actions: actions,
      bottom: bottom,
    );
  }
}
