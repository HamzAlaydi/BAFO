import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/presentation/password_reset_cubits.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M11: the registered e-mail → a reset code (always the same neutral
/// answer) → M12.
class ForgotPasswordScreen extends StatelessWidget {
  const ForgotPasswordScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => ForgotPasswordCubit(context.read<AuthRepository>()),
    child: const _ForgotView(),
  );
}

class _ForgotView extends StatefulWidget {
  const _ForgotView();

  @override
  State<_ForgotView> createState() => _ForgotViewState();
}

class _ForgotViewState extends State<_ForgotView> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    if (_formKey.currentState?.validate() ?? false) {
      context.read<ForgotPasswordCubit>().submit(_email.text);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<ForgotPasswordCubit, ForgotPasswordState>(
      listener: (context, state) {
        switch (state) {
          case ForgotPasswordSent(:final email):
            context.pushReplacement(AppRoutes.resetPassword, extra: email);
          case ForgotPasswordFailure(:final error):
            BafoToast.error(context, errorMessage(l10n, error));
          default:
            break;
        }
      },
      builder: (context, state) => Scaffold(
        appBar: BafoAppBar(title: l10n.authForgotTitle),
        body: SafeArea(
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsetsDirectional.all(BafoSpacing.xl),
              children: [
                Text(l10n.authForgotMessage, style: theme.textTheme.bodyLarge),
                const SizedBox(height: BafoSpacing.xl),
                BafoTextField(
                  key: const Key('forgot.email'),
                  label: l10n.authFieldsEmailLabel,
                  hint: l10n.authFieldsEmailHint,
                  controller: _email,
                  required: true,
                  keyboardType: TextInputType.emailAddress,
                  textDirection: TextDirection.ltr,
                  autofillHints: const [AutofillHints.email],
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _submit(),
                  validator: (value) => Validators.email(value, l10n),
                ),
                const SizedBox(height: BafoSpacing.xl),
                CooldownButton(
                  key: const Key('forgot.submit'),
                  label: l10n.authForgotSubmit,
                  loading: state is ForgotPasswordSubmitting,
                  availableAt: state is ForgotPasswordFailure
                      ? state.retryAt
                      : null,
                  onPressed: _submit,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// M12: the code (checked early), a new password with the rule checklist,
/// and a resend. Success → sign in (M06) with a toast. Every token of the
/// user is revoked by the server.
class ResetPasswordScreen extends StatelessWidget {
  const ResetPasswordScreen({required this.email, super.key});

  final String email;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => ResetPasswordCubit(
      auth: context.read<AuthRepository>(),
      email: email,
    ),
    child: const _ResetView(),
  );
}

class _ResetView extends StatefulWidget {
  const _ResetView();

  @override
  State<_ResetView> createState() => _ResetViewState();
}

class _ResetViewState extends State<_ResetView> {
  final _formKey = GlobalKey<FormState>();
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  String? _codeLocalError;

  @override
  void dispose() {
    _code.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    final l10n = context.l10n;
    final codeError = Validators.otp(_code.text, l10n);
    setState(() => _codeLocalError = codeError);
    final valid = _formKey.currentState?.validate() ?? false;
    if (codeError != null || !valid) return;
    context.read<ResetPasswordCubit>().submit(
      code: _code.text,
      password: _password.text,
      passwordConfirmation: _confirmation.text,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocConsumer<ResetPasswordCubit, ResetPasswordState>(
      listener: (context, state) {
        if (state.status == ResetStatus.done) {
          BafoToast.success(context, l10n.authResetDone);
          context.go(AppRoutes.login);
          return;
        }
        if (state.resent) BafoToast.success(context, l10n.authOtpResent);
        final error = state.error;
        if (error != null) BafoToast.error(context, errorMessage(l10n, error));
      },
      builder: (context, state) {
        final cubit = context.read<ResetPasswordCubit>();
        final codeError =
            _codeLocalError ??
            (state.codeError == null
                ? null
                : errorMessage(l10n, state.codeError));
        return Scaffold(
          appBar: BafoAppBar(title: l10n.authResetTitle),
          body: SafeArea(
            child: Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsetsDirectional.all(BafoSpacing.xl),
                children: [
                  InfoNotice(message: l10n.authForgotSent),
                  const SizedBox(height: BafoSpacing.lg),
                  Text(
                    l10n.authResetMessage(ltrIsolate(cubit.email)),
                    style: theme.textTheme.bodyLarge,
                  ),
                  const SizedBox(height: BafoSpacing.xl),
                  OtpField(
                    controller: _code,
                    errorText: codeError,
                    onChanged: (_) {
                      if (_codeLocalError != null) {
                        setState(() => _codeLocalError = null);
                      }
                      cubit.codeEdited();
                    },
                    onCompleted: cubit.checkCode,
                  ),
                  if (state.codeValid)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(
                        top: BafoSpacing.sm,
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.check_circle_rounded,
                            size: 18,
                            color: context.semanticColors.success.foreground,
                          ),
                          const SizedBox(width: BafoSpacing.xs),
                          Text(
                            l10n.authResetCodeValid,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: context.semanticColors.success.foreground,
                            ),
                          ),
                        ],
                      ),
                    ),
                  CooldownButton(
                    key: const Key('reset.resend'),
                    label: l10n.authOtpResend,
                    variant: BafoButtonVariant.text,
                    availableAt: state.resendAvailableAt,
                    cooldownLabel: l10n.authOtpResendIn,
                    onPressed: cubit.resend,
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  PasswordField(
                    key: const Key('reset.password'),
                    controller: _password,
                    label: l10n.authResetNewPasswordLabel,
                    showRules: true,
                    errorText: state.fieldError('password'),
                    autofillHints: const [AutofillHints.newPassword],
                    validator: (value) => Validators.password(value, l10n),
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  PasswordField(
                    key: const Key('reset.confirmation'),
                    controller: _confirmation,
                    label: l10n.authFieldsPasswordConfirmLabel,
                    errorText: state.fieldError('password_confirmation'),
                    autofillHints: const [AutofillHints.newPassword],
                    validator: (value) => Validators.passwordConfirmation(
                      value,
                      _password.text,
                      l10n,
                    ),
                  ),
                  const SizedBox(height: BafoSpacing.xl),
                  BafoButton(
                    key: const Key('reset.submit'),
                    label: l10n.authResetSubmit,
                    expand: true,
                    loading: state.status == ResetStatus.submitting,
                    onPressed: _submit,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
