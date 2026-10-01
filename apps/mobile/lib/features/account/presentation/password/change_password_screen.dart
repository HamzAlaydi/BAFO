import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/presentation/password/change_password_cubit.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M54 Change password: the current password, the new one with the rule
/// checklist, and its confirmation. The other devices are signed out.
class ChangePasswordScreen extends StatelessWidget {
  const ChangePasswordScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => ChangePasswordCubit(context.read<AccountRepository>()),
    child: const _ChangePasswordForm(),
  );
}

class _ChangePasswordForm extends StatefulWidget {
  const _ChangePasswordForm();

  @override
  State<_ChangePasswordForm> createState() => _ChangePasswordFormState();
}

class _ChangePasswordFormState extends State<_ChangePasswordForm> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_form.currentState?.validate() ?? false)) return;
    unawaited(
      context.read<ChangePasswordCubit>().submit(
        currentPassword: _current.text,
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<ChangePasswordCubit, ChangePasswordState>(
      listener: (context, state) {
        if (state is ChangePasswordDone) {
          BafoToast.success(context, l10n.accountPasswordChanged);
          if (context.canPop()) context.pop();
        }
      },
      builder: (context, state) {
        final failure = state is ChangePasswordFailure ? state : null;
        final busy = state is ChangePasswordSubmitting;
        final cubit = context.read<ChangePasswordCubit>();
        return Scaffold(
          appBar: BafoAppBar(title: l10n.accountPasswordTitle),
          body: Form(
            key: _form,
            child: AutofillGroup(
              child: ListView(
                padding: const EdgeInsetsDirectional.fromSTEB(
                  BafoSpacing.page,
                  BafoSpacing.lg,
                  BafoSpacing.page,
                  BafoSpacing.xxl,
                ),
                children: [
                  InfoNotice(
                    icon: Icons.devices_other_outlined,
                    message: l10n.accountPasswordOtherDevices,
                  ),
                  if (failure != null && failure.isFormLevel) ...[
                    const SizedBox(height: BafoSpacing.md),
                    InfoNotice(
                      tone: StatusTone.danger,
                      icon: Icons.error_outline_rounded,
                      message: errorMessage(l10n, failure.error),
                    ),
                  ],
                  const SizedBox(height: BafoSpacing.xl),
                  PasswordField(
                    key: const Key('password.current'),
                    controller: _current,
                    label: l10n.accountPasswordCurrent,
                    autofillHints: const [AutofillHints.password],
                    textInputAction: TextInputAction.next,
                    errorText: failure == null
                        ? null
                        : failure.currentPasswordWrong
                        ? l10n.errorsPasswordIncorrect
                        : failure.fieldError('current_password'),
                    onChanged: (_) => cubit.edited(),
                    validator: (value) => Validators.required(value, l10n),
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  PasswordField(
                    key: const Key('password.new'),
                    controller: _password,
                    label: l10n.accountPasswordNew,
                    showRules: true,
                    autofillHints: const [AutofillHints.newPassword],
                    textInputAction: TextInputAction.next,
                    errorText: failure?.fieldError('password'),
                    onChanged: (_) => cubit.edited(),
                    validator: (value) => Validators.password(value, l10n),
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  PasswordField(
                    key: const Key('password.confirm'),
                    controller: _confirmation,
                    label: l10n.accountPasswordConfirm,
                    autofillHints: const [AutofillHints.newPassword],
                    textInputAction: TextInputAction.done,
                    errorText: failure?.fieldError('password_confirmation'),
                    onChanged: (_) => cubit.edited(),
                    onSubmitted: (_) => _submit(),
                    validator: (value) => Validators.passwordConfirmation(
                      value,
                      _password.text,
                      l10n,
                    ),
                  ),
                  const SizedBox(height: BafoSpacing.xxl),
                  BafoButton(
                    key: const Key('password.submit'),
                    label: l10n.accountPasswordTitle,
                    expand: true,
                    loading: busy,
                    onPressed: busy ? null : _submit,
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
