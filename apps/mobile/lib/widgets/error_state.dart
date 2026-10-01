import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:bafo/widgets/empty_state.dart';
import 'package:material_ui/material_ui.dart';

/// A failed load with a retry action. The message comes from [error]
/// (server-localised when available, see `errorMessage`).
class ErrorState extends StatelessWidget {
  const ErrorState({required this.error, this.onRetry, super.key});

  final Object? error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final current = error;
    final connectivity = current is ApiException && current.isConnectivity;
    return EmptyState(
      icon: connectivity ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
      title: l10n.commonErrorTitle,
      message: errorMessage(l10n, current),
      action: onRetry == null
          ? null
          : BafoButton.outline(
              label: l10n.commonActionsRetry,
              icon: Icons.refresh_rounded,
              onPressed: onRetry,
            ),
    );
  }
}
