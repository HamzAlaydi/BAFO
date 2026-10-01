import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:material_ui/material_ui.dart';

/// One segment of a [SegmentedFilter].
class FilterSegment<T> {
  const FilterSegment({required this.value, required this.label, this.count});

  final T value;
  final String label;

  /// Optional count shown after the label (e.g. invitation statuses).
  final int? count;
}

/// A single-choice segmented control (Active / Ended / All …), full width.
class SegmentedFilter<T> extends StatelessWidget {
  const SegmentedFilter({
    required this.segments,
    required this.selected,
    required this.onChanged,
    super.key,
  });

  final List<FilterSegment<T>> segments;
  final T selected;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: SegmentedButton<T>(
        showSelectedIcon: false,
        segments: [
          for (final segment in segments)
            ButtonSegment<T>(
              value: segment.value,
              label: Text(
                segment.count == null
                    ? segment.label
                    : '${segment.label} (${segment.count})',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        selected: {selected},
        onSelectionChanged: (values) => onChanged(values.first),
      ),
    );
  }
}

/// "Step 2 of 3" with the step title and a progress bar that fills from the
/// start edge (mirrored in RTL).
class StepperHeader extends StatelessWidget {
  const StepperHeader({
    required this.current,
    required this.total,
    required this.title,
    super.key,
  });

  /// 1-based.
  final int current;
  final int total;
  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l10n = context.l10n;
    return Semantics(
      container: true,
      header: true,
      label: '${l10n.commonStepOf(current, total)}: $title',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l10n.commonStepOf(current, total),
            style: theme.textTheme.labelMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: BafoSpacing.xxs),
          Text(title, style: theme.textTheme.titleLarge),
          const SizedBox(height: BafoSpacing.sm),
          Row(
            children: [
              for (var i = 1; i <= total; i++) ...[
                if (i > 1) const SizedBox(width: BafoSpacing.xs),
                Expanded(
                  child: Container(
                    height: 4,
                    decoration: BoxDecoration(
                      color: i <= current
                          ? theme.colorScheme.primary
                          : theme.colorScheme.surfaceContainerHigh,
                      borderRadius: BorderRadius.circular(BafoRadii.pill),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

/// A button that stays disabled until [availableAt] (device time) and shows
/// the seconds left, for 429 `Retry-After` and OTP resend cooldowns (S7).
class CooldownButton extends StatefulWidget {
  const CooldownButton({
    required this.label,
    required this.onPressed,
    this.availableAt,
    this.cooldownLabel,
    this.variant = BafoButtonVariant.filled,
    this.loading = false,
    this.expand = true,
    this.icon,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;

  /// Null or past: enabled.
  final DateTime? availableAt;

  /// The disabled label for the seconds left; defaults to «أعد المحاولة بعد».
  final String Function(int seconds)? cooldownLabel;
  final BafoButtonVariant variant;
  final bool loading;
  final bool expand;
  final IconData? icon;

  @override
  State<CooldownButton> createState() => _CooldownButtonState();
}

class _CooldownButtonState extends State<CooldownButton> {
  Timer? _ticker;
  int _secondsLeft = 0;

  @override
  void initState() {
    super.initState();
    _sync();
  }

  @override
  void didUpdateWidget(CooldownButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.availableAt != widget.availableAt) _sync();
  }

  /// Reads the wait once, then counts it down with a timer.
  void _sync() {
    _ticker?.cancel();
    final until = widget.availableAt;
    final left = until == null ? Duration.zero : until.difference(DateTime.now());
    _secondsLeft = left.isNegative ? 0 : (left.inMilliseconds / 1000).ceil();
    if (_secondsLeft > 0) {
      _ticker = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) return;
        setState(() => _secondsLeft--);
        if (_secondsLeft <= 0) timer.cancel();
      });
    }
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final seconds = _secondsLeft;
    final waiting = seconds > 0;
    final label = waiting
        ? (widget.cooldownLabel ?? context.l10n.commonRetryIn)(seconds)
        : widget.label;
    return BafoButton(
      label: label,
      variant: widget.variant,
      icon: waiting ? Icons.hourglass_top_rounded : widget.icon,
      loading: widget.loading,
      expand: widget.expand,
      onPressed: waiting ? null : widget.onPressed,
    );
  }
}
