import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/empty_state.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// S8 **F**: the user lacks the permission (403 `forbidden` or a missing
/// `me.permissions` entry). Rendered instead of redirecting (CD8).
class ForbiddenState extends StatelessWidget {
  const ForbiddenState({this.message, super.key});

  final String? message;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return EmptyState(
      icon: Icons.lock_outline_rounded,
      title: l10n.commonForbiddenTitle,
      message: message ?? l10n.errorsForbidden,
    );
  }
}

/// S8 **N**: not found, never revealing whether the thing exists.
class NotFoundState extends StatelessWidget {
  const NotFoundState({this.message, super.key});

  final String? message;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return EmptyState(
      icon: Icons.search_off_rounded,
      title: l10n.commonNotFoundTitle,
      message: message ?? l10n.errorsNotFound,
    );
  }
}

/// The offline strip (M05): shown while [NetworkStatusCubit] reports
/// offline, with a retry that probes the API. Mutations are disabled
/// meanwhile (screens read `NetworkStatus.isOffline`).
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final offline = context.select<NetworkStatusCubit, bool>(
      (cubit) => cubit.state.isOffline,
    );
    final colors = context.semanticColors.warning;
    final l10n = context.l10n;
    return AnimatedSize(
      duration: MediaQuery.disableAnimationsOf(context)
          ? Duration.zero
          : const Duration(milliseconds: 200),
      child: !offline
          ? const SizedBox(width: double.infinity)
          : Semantics(
              container: true,
              liveRegion: true,
              child: Material(
                color: colors.background,
                child: Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                    horizontal: BafoSpacing.page,
                    vertical: BafoSpacing.xs,
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.wifi_off_rounded,
                        size: 18,
                        color: colors.foreground,
                      ),
                      const SizedBox(width: BafoSpacing.sm),
                      Expanded(
                        child: Text(
                          l10n.commonOfflineBanner,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: colors.foreground),
                        ),
                      ),
                      TextButton(
                        onPressed: context.read<NetworkStatusCubit>().check,
                        style: TextButton.styleFrom(
                          foregroundColor: colors.foreground,
                          minimumSize: const Size(48, 40),
                        ),
                        child: Text(l10n.commonActionsRetry),
                      ),
                    ],
                  ),
                ),
              ),
            ),
    );
  }
}
