import 'dart:async';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/feature_gate.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/profile/profile_cubit.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M52 Profile: photo, name, mobile number and the app language. The
/// e-mail signs the user in and cannot be changed. The photo upload is a
/// mobile surface (RELEASE_SCOPE.md §4.1): core shows the avatar only.
class EditProfileScreen extends StatelessWidget {
  const EditProfileScreen({this.pickImage = pickImageFromDevice, super.key});

  final ImagePick pickImage;

  @override
  Widget build(BuildContext context) {
    final me = context.select<SessionCubit, Me?>((cubit) => cubit.state.me);
    final l10n = context.l10n;
    if (me == null) {
      return Scaffold(
        appBar: BafoAppBar(title: l10n.accountProfileTitle),
        body: ErrorState(
          error: null,
          onRetry: context.read<SessionCubit>().refreshMe,
        ),
      );
    }
    return BlocProvider(
      create: (context) => ProfileCubit(
        account: context.read<AccountRepository>(),
        session: context.read<SessionCubit>(),
      ),
      child: _ProfileForm(user: me.user, pickImage: pickImage),
    );
  }
}

class _ProfileForm extends StatefulWidget {
  const _ProfileForm({required this.user, required this.pickImage});

  final User user;
  final ImagePick pickImage;

  @override
  State<_ProfileForm> createState() => _ProfileFormState();
}

class _ProfileFormState extends State<_ProfileForm> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: widget.user.name,
  );
  late final TextEditingController _phone = TextEditingController(
    text: PhoneField.fromE164(widget.user.phone),
  );
  late final TextEditingController _email = TextEditingController(
    text: widget.user.email,
  );
  late String _savedName = widget.user.name;
  late String _savedPhone = PhoneField.fromE164(widget.user.phone);

  bool get _dirty =>
      _name.text.trim() != _savedName.trim() || _phone.text != _savedPhone;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  void _save() {
    if (!(_form.currentState?.validate() ?? false)) return;
    unawaited(
      context.read<ProfileCubit>().save(
        name: _name.text,
        phone: PhoneField.toE164(_phone.text),
      ),
    );
  }

  Future<void> _changePhoto(User user) async {
    final l10n = context.l10n;
    final cubit = context.read<ProfileCubit>();
    final choice = await showImageSheet(
      context,
      title: l10n.accountProfileAvatarChange,
      canRemove: (user.avatarUrl ?? '').isNotEmpty,
    );
    switch (choice) {
      case ImageSheetChoice.pick:
        final path = await widget.pickImage();
        if (path != null) await cubit.uploadAvatar(path);
      case ImageSheetChoice.remove:
        await cubit.removeAvatar();
      case null:
        break;
    }
  }

  void _onState(BuildContext context, ProfileState state) {
    final l10n = context.l10n;
    final error = state.error;
    if (error != null) BafoToast.error(context, errorMessage(l10n, error));
    switch (state.outcome?.kind) {
      case ProfileResult.saved:
        setState(() {
          _savedName = _name.text;
          _savedPhone = _phone.text;
        });
        BafoToast.success(context, l10n.accountSaved);
      case ProfileResult.avatarUpdated:
        BafoToast.success(context, l10n.accountProfileAvatarUpdated);
      case ProfileResult.avatarRemoved:
        BafoToast.success(context, l10n.accountProfileAvatarRemoved);
      case null:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final user =
        context.select<SessionCubit, User?>((cubit) => cubit.state.me?.user) ??
        widget.user;
    final photoUpload = context.surfaces.enabled(MobileSurface.profilePhoto);

    return BlocConsumer<ProfileCubit, ProfileState>(
      listenWhen: (previous, current) =>
          current.outcome != previous.outcome ||
          (current.error != null && current.error != previous.error),
      listener: _onState,
      builder: (context, state) {
        final cubit = context.read<ProfileCubit>();
        final photoBusy =
            state.busy == ProfileBusy.uploadingAvatar ||
            state.busy == ProfileBusy.removingAvatar;
        return UnsavedChangesGuard(
          dirty: _dirty && state.busy != ProfileBusy.saving,
          child: Scaffold(
            appBar: BafoAppBar(title: l10n.accountProfileTitle),
            body: Form(
              key: _form,
              onChanged: () => setState(() {}),
              child: ListView(
                padding: const EdgeInsetsDirectional.fromSTEB(
                  BafoSpacing.page,
                  BafoSpacing.lg,
                  BafoSpacing.page,
                  BafoSpacing.xxl,
                ),
                children: [
                  Center(
                    child: Stack(
                      alignment: AlignmentDirectional.center,
                      children: [
                        OrgAvatar(
                          name: user.name,
                          logoUrl: user.avatarUrl,
                          size: 88,
                        ),
                        if (photoBusy)
                          const SizedBox.square(
                            dimension: 88,
                            child: CircularProgressIndicator(strokeWidth: 3),
                          ),
                      ],
                    ),
                  ),
                  // Core (RELEASE_SCOPE.md §4.1): no photo upload on
                  // mobile; the avatar still shows.
                  if (photoUpload) ...[
                    const SizedBox(height: BafoSpacing.sm),
                    Center(
                      child: BafoButton.text(
                        key: const Key('profile.photo'),
                        label: l10n.accountProfileAvatarChange,
                        icon: Icons.photo_camera_outlined,
                        onPressed: state.isBusy
                            ? null
                            : () => unawaited(_changePhoto(user)),
                      ),
                    ),
                    Text(
                      l10n.accountProfileAvatarHint,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                  const SizedBox(height: BafoSpacing.xl),
                  BafoTextField(
                    key: const Key('profile.name'),
                    label: l10n.authFieldsNameLabel,
                    controller: _name,
                    required: true,
                    errorText: state.fieldError('name'),
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.name],
                    onChanged: (_) => cubit.fieldEdited('name'),
                    validator: (value) =>
                        Validators.required(value, l10n) ??
                        Validators.maxLength(value, 150, l10n),
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  BafoTextField(
                    key: const Key('profile.email'),
                    label: l10n.authFieldsEmailLabel,
                    controller: _email,
                    readOnly: true,
                    enabled: false,
                    textDirection: TextDirection.ltr,
                    helperText: l10n.accountProfileEmailHelper,
                  ),
                  const SizedBox(height: BafoSpacing.lg),
                  PhoneField(
                    key: const Key('profile.phone'),
                    controller: _phone,
                    errorText: state.fieldError('phone'),
                    onChanged: (_) => cubit.fieldEdited('phone'),
                  ),
                  const SizedBox(height: BafoSpacing.xl),
                  Text(
                    l10n.profileLanguageTitle,
                    style: theme.textTheme.titleSmall,
                  ),
                  const SizedBox(height: BafoSpacing.sm),
                  const LanguageSegmented(),
                  const SizedBox(height: BafoSpacing.xxl),
                  BafoButton(
                    key: const Key('profile.save'),
                    label: l10n.commonActionsSave,
                    expand: true,
                    loading: state.busy == ProfileBusy.saving,
                    onPressed: state.isBusy || !_dirty ? null : _save,
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
