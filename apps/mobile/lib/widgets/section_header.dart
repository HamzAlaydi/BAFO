import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:material_ui/material_ui.dart';

/// A section title with an optional trailing action ("See all").
///
/// It adds no horizontal padding: place it inside page-padded content
/// (`BafoSpacing.pagePadding`), so the title lines up with the cards and
/// text of its section on the start edge.
class SectionHeader extends StatelessWidget {
  const SectionHeader({
    required this.title,
    this.actionLabel,
    this.onAction,
    super.key,
  });

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(
        top: BafoSpacing.lg,
        bottom: BafoSpacing.xs,
      ),
      child: Row(
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ),
          if (actionLabel != null)
            BafoButton.text(label: actionLabel!, onPressed: onAction),
        ],
      ),
    );
  }
}
