import 'dart:async';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_cubit.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/router/gate_screens.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/data/contact_repository.dart';
import 'package:bafo/features/account/domain/contact_message.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/help/contact_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M60 Help and contact: the support e-mail, phone and WhatsApp from
/// app-config (tap to write or call), and the contact form
/// (`POST /contact`), pre-filled from the account.
class HelpScreen extends StatelessWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => ContactCubit(context.read<ContactRepository>()),
    child: const _HelpView(),
  );
}

class _HelpView extends StatelessWidget {
  const _HelpView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final support = context.select<AppConfigCubit, SupportContacts>(
      (cubit) => cubit.state.config?.support ?? const SupportContacts(),
    );
    final state = context.watch<ContactCubit>().state;
    return Scaffold(
      appBar: BafoAppBar(title: l10n.accountHelpTitle),
      body: ListView(
        padding: const EdgeInsetsDirectional.fromSTEB(
          BafoSpacing.page,
          BafoSpacing.lg,
          BafoSpacing.page,
          BafoSpacing.xxl,
        ),
        children: [
          Text(l10n.accountHelpIntro, style: theme.textTheme.bodyMedium),
          const SizedBox(height: BafoSpacing.lg),
          if (support.isEmpty)
            InfoNotice(
              key: const Key('help.noContacts'),
              tone: StatusTone.neutral,
              message: l10n.accountHelpNoContacts,
            )
          else
            SupportContactsCard(support: support),
          FormSectionTitle(l10n.accountHelpFormTitle),
          if (state is ContactSent)
            BafoCard(
              key: const Key('help.sent'),
              child: EmptyState(
                icon: Icons.mark_email_read_outlined,
                title: l10n.accountHelpSentTitle,
                message: l10n.accountHelpSentMessage,
                action: BafoButton.outline(
                  label: l10n.accountHelpSendAnother,
                  onPressed: context.read<ContactCubit>().reset,
                ),
              ),
            )
          else
            _ContactForm(state: state),
        ],
      ),
    );
  }
}

class _ContactForm extends StatefulWidget {
  const _ContactForm({required this.state});

  final ContactState state;

  @override
  State<_ContactForm> createState() => _ContactFormState();
}

class _ContactFormState extends State<_ContactForm> {
  final _form = GlobalKey<FormState>();
  late final Me? _me = context.read<SessionCubit>().state.me;
  late final _name = TextEditingController(text: _me?.user.name);
  late final _email = TextEditingController(text: _me?.user.email);
  late final _phone = TextEditingController(
    text: PhoneField.fromE164(_me?.user.phone),
  );
  late final _company = TextEditingController(text: _me?.organization.name);
  final _subject = TextEditingController();
  final _message = TextEditingController();

  @override
  void dispose() {
    for (final controller in [
      _name,
      _email,
      _phone,
      _company,
      _subject,
      _message,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _send() {
    if (!(_form.currentState?.validate() ?? false)) return;
    unawaited(
      context.read<ContactCubit>().send(
        ContactMessage(
          name: _name.text,
          email: _email.text,
          phone: _phone.text.isEmpty ? null : PhoneField.toE164(_phone.text),
          company: _company.text,
          subject: _subject.text,
          message: _message.text,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = widget.state;
    final failure = state is ContactFailure ? state : null;
    final sending = state is ContactSending;
    String? error(String path) => failure?.fieldError(path);

    return Form(
      key: _form,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (failure != null && failure.isFormLevel) ...[
            InfoNotice(
              tone: StatusTone.danger,
              icon: Icons.error_outline_rounded,
              message: errorMessage(l10n, failure.error),
            ),
            const SizedBox(height: BafoSpacing.lg),
          ],
          BafoTextField(
            key: const Key('help.name'),
            label: l10n.authFieldsNameLabel,
            controller: _name,
            required: true,
            errorText: error('name'),
            textInputAction: TextInputAction.next,
            validator: (value) =>
                Validators.required(value, l10n) ??
                Validators.maxLength(value, 150, l10n),
          ),
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            key: const Key('help.email'),
            label: l10n.authFieldsEmailLabel,
            controller: _email,
            required: true,
            keyboardType: TextInputType.emailAddress,
            textDirection: TextDirection.ltr,
            errorText: error('email'),
            textInputAction: TextInputAction.next,
            validator: (value) => Validators.email(value, l10n),
          ),
          const SizedBox(height: BafoSpacing.lg),
          PhoneField(
            key: const Key('help.phone'),
            controller: _phone,
            required: false,
            errorText: error('phone'),
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            key: const Key('help.company'),
            label: l10n.organizationFieldsNameLabel,
            controller: _company,
            optional: true,
            errorText: error('company'),
            textInputAction: TextInputAction.next,
            validator: (value) => Validators.maxLength(value, 150, l10n),
          ),
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            key: const Key('help.subject'),
            label: l10n.accountHelpSubject,
            controller: _subject,
            required: true,
            errorText: error('subject'),
            textInputAction: TextInputAction.next,
            validator: (value) =>
                Validators.required(value, l10n) ??
                Validators.maxLength(value, 150, l10n),
          ),
          const SizedBox(height: BafoSpacing.lg),
          BafoTextField(
            key: const Key('help.message'),
            label: l10n.accountHelpMessage,
            controller: _message,
            required: true,
            minLines: 4,
            maxLines: 8,
            maxLength: 5000,
            keyboardType: TextInputType.multiline,
            errorText: error('message'),
            validator: (value) => Validators.required(value, l10n),
          ),
          const SizedBox(height: BafoSpacing.xl),
          CooldownButton(
            key: const Key('help.send'),
            label: l10n.accountHelpSend,
            icon: Icons.send_rounded,
            expand: true,
            loading: sending,
            availableAt: failure?.retryAt,
            onPressed: sending ? null : _send,
          ),
        ],
      ),
    );
  }
}
