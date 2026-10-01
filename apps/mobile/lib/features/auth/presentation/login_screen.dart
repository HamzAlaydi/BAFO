import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/auth_routes.dart';
import 'package:bafo/features/auth/presentation/login_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M06: e-mail and password. Navigation after success is the router's job
/// (the session turns authenticated and the auth redirect moves on);
/// `email_not_verified` continues on the verification screen (M10).
class LoginScreen extends StatelessWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => LoginCubit(
        auth: context.read<AuthRepository>(),
        session: context.read<SessionCubit>(),
      ),
      child: const _LoginView(),
    );
  }
}

class _LoginView extends StatefulWidget {
  const _LoginView();

  @override
  State<_LoginView> createState() => _LoginViewState();
}

class _LoginViewState extends State<_LoginView> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void initState() {
    super.initState();
    // Tell the user why they are back here after the API rejected the token.
    final session = context.read<SessionCubit>().state;
    if (session is SessionUnauthenticated && session.expired) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          BafoToast.show(context, context.l10n.errorsUnauthenticated);
        }
      });
    }
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    context.read<LoginCubit>().submit(
      email: _email.text,
      password: _password.text,
    );
  }

  void _onState(BuildContext context, LoginState state) {
    switch (state) {
      case LoginNeedsVerification(:final email, :final otpExpiresAt):
        context.push(
          AppRoutes.verify,
          extra: VerifyEmailArgs(email: email, expiresAt: otpExpiresAt),
        );
      case LoginFailure(:final error) when error.fieldErrors.isEmpty:
        BafoToast.error(context, errorMessage(context.l10n, error));
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);

    return BlocConsumer<LoginCubit, LoginState>(
      listener: _onState,
      builder: (context, state) {
        return Scaffold(
          body: SafeArea(
            child: Column(
              children: [
                const Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: Padding(
                    padding: EdgeInsetsDirectional.all(BafoSpacing.sm),
                    child: LanguageSwitchButton(key: Key('login.language')),
                  ),
                ),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: BafoSpacing.xl,
                    ),
                    child: AutofillGroup(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const SizedBox(height: BafoSpacing.lg),
                            const Center(child: BrandMark(size: 72)),
                            const SizedBox(height: BafoSpacing.md),
                            Text(
                              l10n.appTagline,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.titleSmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                            const SizedBox(height: BafoSpacing.xxl),
                            Semantics(
                              header: true,
                              child: Text(
                                l10n.authLoginTitle,
                                style: theme.textTheme.headlineMedium,
                              ),
                            ),
                            const SizedBox(height: BafoSpacing.xs),
                            Text(
                              l10n.authLoginSubtitle,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                            const SizedBox(height: BafoSpacing.xl),
                            BafoTextField(
                              key: const Key('login.email'),
                              label: l10n.authFieldsEmailLabel,
                              hint: l10n.authFieldsEmailHint,
                              controller: _email,
                              errorText: state.fieldError('email'),
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [
                                AutofillHints.username,
                                AutofillHints.email,
                              ],
                              // E-mail addresses are Latin: keep them LTR.
                              textDirection: TextDirection.ltr,
                              onChanged: (_) =>
                                  context.read<LoginCubit>().edited(),
                              validator: (value) =>
                                  Validators.email(value, l10n),
                            ),
                            const SizedBox(height: BafoSpacing.lg),
                            BafoTextField(
                              key: const Key('login.password'),
                              label: l10n.authFieldsPasswordLabel,
                              controller: _password,
                              errorText: state.fieldError('password'),
                              obscure: true,
                              textInputAction: TextInputAction.done,
                              autofillHints: const [AutofillHints.password],
                              textDirection: TextDirection.ltr,
                              onChanged: (_) =>
                                  context.read<LoginCubit>().edited(),
                              onSubmitted: (_) => _submit(),
                              validator: (value) => (value ?? '').isEmpty
                                  ? l10n.validationRequired
                                  : null,
                            ),
                            Align(
                              alignment: AlignmentDirectional.centerEnd,
                              child: BafoButton.text(
                                key: const Key('login.forgot'),
                                label: l10n.authLoginForgot,
                                onPressed: () =>
                                    context.push(AppRoutes.forgotPassword),
                              ),
                            ),
                            const SizedBox(height: BafoSpacing.md),
                            CooldownButton(
                              key: const Key('login.submit'),
                              label: l10n.authLoginSubmit,
                              loading: state.isSubmitting,
                              availableAt: state is LoginFailure
                                  ? state.retryAt
                                  : null,
                              onPressed: _submit,
                            ),
                            const SizedBox(height: BafoSpacing.lg),
                            Wrap(
                              alignment: WrapAlignment.center,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                Text(
                                  l10n.authLoginNoAccount,
                                  style: theme.textTheme.bodyMedium,
                                ),
                                BafoButton.text(
                                  key: const Key('login.register'),
                                  label: l10n.authLoginCreateAccount,
                                  onPressed: () =>
                                      context.push(AppRoutes.register),
                                ),
                              ],
                            ),
                            const SizedBox(height: BafoSpacing.xl),
                            const LegalLinks(),
                            const SizedBox(height: BafoSpacing.lg),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Terms and privacy links (M13) at the foot of the sign-in flow.
class LegalLinks extends StatelessWidget {
  const LegalLinks({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Wrap(
      alignment: WrapAlignment.center,
      children: [
        BafoButton.text(
          label: l10n.legalLinksTerms,
          onPressed: () => context.push(AppRoutes.legal(LegalCode.terms.wire)),
        ),
        BafoButton.text(
          label: l10n.legalLinksPrivacy,
          onPressed: () =>
              context.push(AppRoutes.legal(LegalCode.privacy.wire)),
        ),
      ],
    );
  }
}
