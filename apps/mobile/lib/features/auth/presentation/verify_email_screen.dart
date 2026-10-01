import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/presentation/auth_routes.dart';
import 'package:bafo/features/auth/presentation/otp_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M10: the 6-digit code sent to [VerifyEmailArgs.email]. Success signs in
/// (token → `TokenStore` → session authenticated), and the router moves on.
class VerifyEmailScreen extends StatelessWidget {
  const VerifyEmailScreen({required this.args, super.key});

  final VerifyEmailArgs args;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => OtpCubit(
      auth: context.read<AuthRepository>(),
      session: context.read<SessionCubit>(),
      email: args.email,
      expiresAt: args.expiresAt,
    ),
    child: const _VerifyView(),
  );
}

class _VerifyView extends StatefulWidget {
  const _VerifyView();

  @override
  State<_VerifyView> createState() => _VerifyViewState();
}

class _VerifyViewState extends State<_VerifyView> {
  final _code = TextEditingController();
  String? _localError;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _verify() {
    final l10n = context.l10n;
    final error = Validators.otp(_code.text, l10n);
    setState(() => _localError = error);
    if (error == null) context.read<OtpCubit>().verify(_code.text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<OtpCubit, OtpState>(
      listener: (context, state) {
        if (state.resent) BafoToast.success(context, l10n.authOtpResent);
        final error = state.error;
        // Resend failures have no field: show them as a toast.
        if (error != null && error.code != 'otp_invalid' && error.code != 'otp_expired') {
          BafoToast.error(context, errorMessage(l10n, error));
        }
      },
      builder: (context, state) {
        final cubit = context.read<OtpCubit>();
        final expiresAt = state.expiresAt;
        final error = state.error;
        final codeError = _localError ??
            (error != null && (error.code == 'otp_invalid' || error.code == 'otp_expired')
                ? errorMessage(l10n, error)
                : null);
        return Scaffold(
          appBar: BafoAppBar(title: l10n.authVerifyTitle),
          body: SafeArea(
            child: ListView(
              padding: const EdgeInsetsDirectional.all(BafoSpacing.xl),
              children: [
                const Center(
                  child: Icon(Icons.mark_email_read_outlined, size: 56),
                ),
                const SizedBox(height: BafoSpacing.lg),
                Text(
                  l10n.authVerifyMessage(ltrIsolate(cubit.email)),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyLarge,
                ),
                const SizedBox(height: BafoSpacing.xl),
                OtpField(
                  controller: _code,
                  errorText: codeError,
                  enabled: state.status != OtpStatus.verifying,
                  onChanged: (_) {
                    if (_localError != null) setState(() => _localError = null);
                    cubit.edited();
                  },
                  onCompleted: (_) => _verify(),
                ),
                const SizedBox(height: BafoSpacing.md),
                if (expiresAt != null)
                  Center(
                    child: _ExpiryLine(expiresAt: expiresAt),
                  ),
                const SizedBox(height: BafoSpacing.xl),
                BafoButton(
                  key: const Key('verify.submit'),
                  label: l10n.authVerifySubmit,
                  expand: true,
                  loading: state.status == OtpStatus.verifying,
                  onPressed: _verify,
                ),
                const SizedBox(height: BafoSpacing.md),
                CooldownButton(
                  key: const Key('verify.resend'),
                  label: l10n.authOtpResend,
                  variant: error?.code == 'otp_expired' ||
                          error?.code == 'otp_too_many_attempts'
                      ? BafoButtonVariant.tonal
                      : BafoButtonVariant.text,
                  availableAt: state.resendAvailableAt,
                  cooldownLabel: l10n.authOtpResendIn,
                  loading: state.status == OtpStatus.resending,
                  onPressed: cubit.resend,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// «تنتهي صلاحية الرمز خلال 09:12» on server time; «انتهت صلاحية الرمز» at
/// zero.
class _ExpiryLine extends StatelessWidget {
  const _ExpiryLine({required this.expiresAt});

  final DateTime expiresAt;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.timer_outlined, size: 18, color: theme.colorScheme.onSurfaceVariant),
        const SizedBox(width: BafoSpacing.xs),
        Flexible(
          child: CountdownText(
            deadline: expiresAt,
            elapsedLabel: l10n.authOtpExpired,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
            template: l10n.authOtpExpiresIn,
          ),
        ),
      ],
    );
  }
}
