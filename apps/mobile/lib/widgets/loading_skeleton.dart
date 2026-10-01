import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:material_ui/material_ui.dart';

/// A pulsing placeholder block shown while content loads. The pulse stops
/// when the platform asks for reduced motion.
class LoadingSkeleton extends StatefulWidget {
  const LoadingSkeleton({
    this.width,
    this.height = 16,
    this.radius = BafoRadii.sm,
    super.key,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<LoadingSkeleton> createState() => _LoadingSkeletonState();
}

class _LoadingSkeletonState extends State<LoadingSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
    lowerBound: 0.45,
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller
        ..stop()
        ..value = 1;
    } else if (!_controller.isAnimating) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.surfaceContainerHigh;
    return FadeTransition(
      opacity: _controller,
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

/// A list of card-shaped skeletons, announced once as "loading".
class LoadingSkeletonList extends StatelessWidget {
  const LoadingSkeletonList({this.itemCount = 4, super.key});

  final int itemCount;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.commonLoading,
      excludeSemantics: true,
      child: ListView.separated(
        padding: BafoSpacing.pagePadding,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: itemCount,
        separatorBuilder: (_, _) => const SizedBox(height: BafoSpacing.md),
        itemBuilder: (_, _) => const _SkeletonCard(),
      ),
    );
  }
}

class _SkeletonCard extends StatelessWidget {
  const _SkeletonCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(BafoSpacing.lg),
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        borderRadius: BorderRadius.circular(BafoRadii.card),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LoadingSkeleton(width: 180, height: 18),
          SizedBox(height: BafoSpacing.md),
          LoadingSkeleton(height: 12),
          SizedBox(height: BafoSpacing.sm),
          LoadingSkeleton(width: 120, height: 12),
        ],
      ),
    );
  }
}
