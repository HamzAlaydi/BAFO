import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:material_ui/material_ui.dart';

enum BafoButtonVariant {
  /// The one primary action of a view (green-dark fill).
  filled,

  /// Secondary emphasis (green-light fill).
  tonal,

  /// Neutral secondary action (bordered).
  outline,

  /// Low emphasis, inline.
  text,

  /// Delete, reject, cancel. Always behind a confirmation.
  destructive,
}

/// The BAFO button: 48 dp high, radius 10, bold 16 label.
///
/// While [loading] it shows a spinner, ignores taps and keeps its colours
/// (no flash to the disabled grey).
class BafoButton extends StatelessWidget {
  const BafoButton({
    required this.label,
    required this.onPressed,
    this.variant = BafoButtonVariant.filled,
    this.icon,
    this.loading = false,
    this.expand = false,
    super.key,
  });

  const BafoButton.tonal({
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = false,
    super.key,
  }) : variant = BafoButtonVariant.tonal;

  const BafoButton.outline({
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = false,
    super.key,
  }) : variant = BafoButtonVariant.outline;

  const BafoButton.text({
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = false,
    super.key,
  }) : variant = BafoButtonVariant.text;

  const BafoButton.destructive({
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = false,
    super.key,
  }) : variant = BafoButtonVariant.destructive;

  final String label;

  /// Null disables the button.
  final VoidCallback? onPressed;
  final BafoButtonVariant variant;
  final IconData? icon;
  final bool loading;

  /// Fill the available width.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final semantic = context.semanticColors;

    final (Color background, Color foreground) = switch (variant) {
      BafoButtonVariant.filled => (scheme.primary, scheme.onPrimary),
      BafoButtonVariant.tonal => (
        scheme.primaryContainer,
        scheme.onPrimaryContainer,
      ),
      BafoButtonVariant.outline => (Colors.transparent, scheme.onSurface),
      BafoButtonVariant.text => (Colors.transparent, semantic.link),
      BafoButtonVariant.destructive => (
        semantic.destructive,
        semantic.onDestructive,
      ),
    };

    final child = _ButtonContent(
      label: label,
      icon: icon,
      loading: loading,
      spinnerColor: foreground,
    );
    // While loading the button is disabled (taps and semantics) but keeps its
    // enabled colours; a plain disabled button greys out as usual.
    final VoidCallback? handler = loading ? null : onPressed;
    final ButtonStyle? loadingStyle = loading
        ? ButtonStyle(
            backgroundColor: WidgetStatePropertyAll(background),
            foregroundColor: WidgetStatePropertyAll(foreground),
          )
        : null;

    final Widget button = switch (variant) {
      BafoButtonVariant.filled => FilledButton(
        onPressed: handler,
        style: loadingStyle,
        child: child,
      ),
      BafoButtonVariant.tonal => FilledButton.tonal(
        onPressed: handler,
        style: loadingStyle,
        child: child,
      ),
      BafoButtonVariant.outline => OutlinedButton(
        onPressed: handler,
        style: loadingStyle,
        child: child,
      ),
      BafoButtonVariant.text => TextButton(
        onPressed: handler,
        style: loadingStyle,
        child: child,
      ),
      BafoButtonVariant.destructive => FilledButton(
        onPressed: handler,
        // `merge` keeps the receiver's values: loading colours win.
        style: (loadingStyle ?? const ButtonStyle()).merge(
          FilledButton.styleFrom(
            backgroundColor: background,
            foregroundColor: foreground,
          ),
        ),
        child: child,
      ),
    };

    if (!expand) return button;
    return SizedBox(width: double.infinity, child: button);
  }
}

class _ButtonContent extends StatelessWidget {
  const _ButtonContent({
    required this.label,
    required this.icon,
    required this.loading,
    required this.spinnerColor,
  });

  final String label;
  final IconData? icon;
  final bool loading;
  final Color spinnerColor;

  @override
  Widget build(BuildContext context) {
    final leading = loading
        ? SizedBox.square(
            dimension: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: spinnerColor,
            ),
          )
        : icon == null
        ? null
        : Icon(icon, size: 20);

    return Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (leading != null) ...[leading, const SizedBox(width: 8)],
        Flexible(
          child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ],
    );
  }
}
