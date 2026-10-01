import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/team/team_member_form_cubit.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M57: invite a member (`/account/team/new`) or edit one
/// (`/account/team/:membershipId`, as W26). Pops `true` when the roster
/// changed. `seat_limit_reached` shows the seats with
/// «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.» and no upgrade.
class TeamMemberFormScreen extends StatelessWidget {
  const TeamMemberFormScreen({this.membershipId, this.member, super.key});

  /// Null to invite a new member.
  final String? membershipId;

  /// The member from the list, when the screen was opened from it.
  final TeamMember? member;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final canManage =
        context.read<SessionCubit>().state.me?.can(Permissions.teamManage) ??
        false;
    if (!canManage) {
      return Scaffold(
        appBar: BafoAppBar(title: l10n.accountTeamTitle),
        body: const ForbiddenState(),
      );
    }
    return BlocProvider(
      create: (context) => TeamMemberFormCubit(
        team: context.read<TeamRepository>(),
        membershipId: membershipId,
        member: member?.id == membershipId ? member : null,
      )..load(),
      child: const _TeamMemberFormView(),
    );
  }
}

class _TeamMemberFormView extends StatelessWidget {
  const _TeamMemberFormView();

  void _onState(BuildContext context, TeamMemberFormState state) {
    final l10n = context.l10n;
    final error = state.error;
    if (error != null) BafoToast.error(context, errorMessage(l10n, error));
    final kind = state.outcome?.kind;
    if (kind == null) return;
    switch (kind) {
      case TeamMemberResult.invited:
        BafoToast.success(
          context,
          l10n.accountTeamInviteSent(state.member?.user.email ?? ''),
        );
      case TeamMemberResult.saved:
        BafoToast.success(context, l10n.accountSaved);
      case TeamMemberResult.resent:
        BafoToast.success(context, l10n.accountTeamResent);
      case TeamMemberResult.deactivated:
        BafoToast.success(context, l10n.accountTeamDeactivated);
      case TeamMemberResult.reactivated:
        BafoToast.success(context, l10n.accountTeamReactivated);
      case TeamMemberResult.removed:
        BafoToast.success(context, l10n.accountTeamRemoved);
    }
    if (kind == TeamMemberResult.invited ||
        kind == TeamMemberResult.saved ||
        kind == TeamMemberResult.removed) {
      if (context.canPop()) context.pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<TeamMemberFormCubit>();
    return BlocConsumer<TeamMemberFormCubit, TeamMemberFormState>(
      listenWhen: (previous, current) =>
          current.outcome != previous.outcome ||
          (current.error != null && current.error != previous.error),
      listener: _onState,
      builder: (context, state) {
        final title = cubit.isNew
            ? l10n.accountTeamInvite
            : l10n.accountTeamMemberTitle;
        final Widget body;
        if (state.busy == TeamMemberBusy.loading) {
          body = const LoadingSkeletonList(itemCount: 2);
        } else if (state.loadError != null) {
          body = ErrorState(error: state.loadError, onRetry: cubit.load);
        } else if (state.notFound) {
          body = NotFoundState(message: l10n.accountTeamMemberNotFound);
        } else {
          body = _MemberForm(state: state, isNew: cubit.isNew);
        }
        return Scaffold(
          appBar: BafoAppBar(title: title),
          body: body,
        );
      },
    );
  }
}

class _MemberForm extends StatefulWidget {
  const _MemberForm({required this.state, required this.isNew});

  final TeamMemberFormState state;
  final bool isNew;

  @override
  State<_MemberForm> createState() => _MemberFormState();
}

class _MemberFormState extends State<_MemberForm> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  late MembershipRole _role = widget.state.member?.role == MembershipRole.admin
      ? MembershipRole.admin
      : MembershipRole.member;
  late bool _canAward =
      widget.state.member?.canAward ??
      (_role == MembershipRole.admin &&
          _mayGrant(Permissions.competitionsAward));
  late bool _canPurchase =
      widget.state.member?.canPurchase ??
      (_role == MembershipRole.admin && _mayGrant(Permissions.billingPurchase));

  /// The API lets an editor grant only the flags it holds itself (SECURITY_REVIEW S-02): a flag
  /// the signed-in user lacks is off by default and can only be switched off.
  bool _mayGrant(String permission) =>
      context.read<SessionCubit>().state.me?.can(permission) ?? false;

  bool _flagLocked(String permission, bool? stored) =>
      !_mayGrant(permission) && stored != true;

  TeamMember? get _member => widget.state.member;

  bool get _dirty {
    final member = _member;
    if (member == null) {
      return _name.text.trim().isNotEmpty ||
          _email.text.trim().isNotEmpty ||
          _phone.text.isNotEmpty;
    }
    return _role != member.role ||
        _canAward != member.canAward ||
        _canPurchase != member.canPurchase;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  /// ARCHITECTURE.md §8.1 defaults: admin → both flags on (the ones the editor
  /// holds); member → off.
  void _setRole(MembershipRole? role) {
    if (role == null || role == _role) return;
    setState(() {
      _role = role;
      _canAward =
          role == MembershipRole.admin &&
          _mayGrant(Permissions.competitionsAward);
      _canPurchase =
          role == MembershipRole.admin &&
          _mayGrant(Permissions.billingPurchase);
    });
  }

  void _submit() {
    final cubit = context.read<TeamMemberFormCubit>();
    if (widget.isNew) {
      if (!(_form.currentState?.validate() ?? false)) return;
      unawaited(
        cubit.invite(
          TeamMemberInvite(
            name: _name.text,
            email: _email.text,
            phone: _phone.text.isEmpty ? null : PhoneField.toE164(_phone.text),
            role: _role,
            canAward: _canAward,
            canPurchase: _canPurchase,
          ),
        ),
      );
    } else {
      unawaited(
        cubit.update(
          role: _role,
          canAward: _canAward,
          canPurchase: _canPurchase,
        ),
      );
    }
  }

  Future<void> _deactivate(TeamMember member) async {
    final l10n = context.l10n;
    final cubit = context.read<TeamMemberFormCubit>();
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.accountTeamDeactivate,
      message: l10n.accountTeamDeactivateConfirmMessage,
      confirmLabel: l10n.accountTeamDeactivate,
      destructive: true,
    );
    if (confirmed) await cubit.setActive(active: false);
  }

  Future<void> _remove(TeamMember member) async {
    final l10n = context.l10n;
    final cubit = context.read<TeamMemberFormCubit>();
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.accountTeamRemoveConfirmTitle(member.user.name),
      message: l10n.accountTeamRemoveConfirmMessage,
      confirmLabel: l10n.accountTeamRemove,
      destructive: true,
    );
    if (confirmed) await cubit.remove();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final state = widget.state;
    final cubit = context.read<TeamMemberFormCubit>();
    final member = _member;
    final busy = state.isBusy;
    final seatLimit = state.seatLimit;

    return UnsavedChangesGuard(
      dirty: _dirty && !busy,
      child: Form(
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
            if (seatLimit != null) ...[
              InfoNotice(
                key: const Key('member.seatLimit'),
                tone: StatusTone.warning,
                icon: Icons.event_seat_outlined,
                title: l10n.accountTeamSeatsFull(
                  seatLimit.used,
                  seatLimit.total,
                ),
                message: l10n.billingManagedOnWeb,
              ),
              const SizedBox(height: BafoSpacing.lg),
            ],
            if (member == null) ...[
              BafoTextField(
                key: const Key('member.name'),
                label: l10n.authFieldsNameLabel,
                controller: _name,
                required: true,
                errorText: state.fieldError('name'),
                textInputAction: TextInputAction.next,
                onChanged: (_) => cubit.fieldEdited('name'),
                validator: (value) =>
                    Validators.required(value, l10n) ??
                    Validators.maxLength(value, 150, l10n),
              ),
              const SizedBox(height: BafoSpacing.lg),
              BafoTextField(
                key: const Key('member.email'),
                label: l10n.authFieldsEmailLabel,
                hint: l10n.authFieldsEmailHint,
                controller: _email,
                required: true,
                keyboardType: TextInputType.emailAddress,
                textDirection: TextDirection.ltr,
                errorText: state.fieldError('email'),
                textInputAction: TextInputAction.next,
                onChanged: (_) => cubit.fieldEdited('email'),
                validator: (value) => Validators.email(value, l10n),
              ),
              const SizedBox(height: BafoSpacing.lg),
              PhoneField(
                key: const Key('member.phone'),
                controller: _phone,
                required: false,
                errorText: state.fieldError('phone'),
                onChanged: (_) => cubit.fieldEdited('phone'),
              ),
            ] else
              BafoCard(
                child: Row(
                  children: [
                    OrgAvatar(
                      name: member.user.name,
                      logoUrl: member.user.avatarUrl,
                      size: 48,
                      decorative: true,
                    ),
                    const SizedBox(width: BafoSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            member.user.name,
                            style: theme.textTheme.titleMedium,
                          ),
                          Ltr(
                            child: Text(
                              member.user.email,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                          const SizedBox(height: BafoSpacing.xs),
                          StatusPill(
                            label: membershipStatusLabel(l10n, member.status),
                            tone: membershipStatusTone(member.status),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            FormSectionTitle(l10n.accountTeamRole),
            RadioGroup<MembershipRole>(
              groupValue: _role,
              onChanged: busy ? (_) {} : _setRole,
              child: Column(
                children: [
                  RadioListTile<MembershipRole>(
                    key: const Key('member.role.admin'),
                    value: MembershipRole.admin,
                    enabled: !busy,
                    contentPadding: EdgeInsetsDirectional.zero,
                    title: Text(l10n.accountRoleAdmin),
                    subtitle: Text(l10n.accountTeamRoleAdminHint),
                  ),
                  RadioListTile<MembershipRole>(
                    key: const Key('member.role.member'),
                    value: MembershipRole.member,
                    enabled: !busy,
                    contentPadding: EdgeInsetsDirectional.zero,
                    title: Text(l10n.accountRoleMember),
                    subtitle: Text(l10n.accountTeamRoleMemberHint),
                  ),
                ],
              ),
            ),
            const Divider(height: BafoSpacing.xl),
            SwitchListTile(
              key: const Key('member.canAward'),
              contentPadding: EdgeInsetsDirectional.zero,
              title: Text(l10n.accountTeamCanAward),
              subtitle: Text(l10n.accountTeamCanAwardHint),
              value: _canAward,
              onChanged:
                  busy ||
                      _flagLocked(
                        Permissions.competitionsAward,
                        _member?.canAward,
                      )
                  ? null
                  : (value) => setState(() => _canAward = value),
            ),
            SwitchListTile(
              key: const Key('member.canPurchase'),
              contentPadding: EdgeInsetsDirectional.zero,
              title: Text(l10n.accountTeamCanPurchase),
              subtitle: Text(l10n.accountTeamCanPurchaseHint),
              value: _canPurchase,
              onChanged:
                  busy ||
                      _flagLocked(
                        Permissions.billingPurchase,
                        _member?.canPurchase,
                      )
                  ? null
                  : (value) => setState(() => _canPurchase = value),
            ),
            const SizedBox(height: BafoSpacing.xl),
            BafoButton(
              key: const Key('member.submit'),
              label: widget.isNew
                  ? l10n.accountTeamSendInvite
                  : l10n.commonActionsSave,
              icon: widget.isNew ? Icons.send_rounded : null,
              expand: true,
              loading: state.busy == TeamMemberBusy.saving,
              onPressed: busy || (!widget.isNew && !_dirty) ? null : _submit,
            ),
            if (member != null) ...[
              const SizedBox(height: BafoSpacing.xl),
              const Divider(),
              if (member.status == MembershipStatus.invited)
                BafoButton.outline(
                  key: const Key('member.resend'),
                  label: l10n.accountTeamResend,
                  icon: Icons.forward_to_inbox_outlined,
                  expand: true,
                  loading: state.busy == TeamMemberBusy.resending,
                  onPressed: busy ? null : () => unawaited(cubit.resend()),
                ),
              if (member.status == MembershipStatus.active)
                BafoButton.outline(
                  key: const Key('member.deactivate'),
                  label: l10n.accountTeamDeactivate,
                  icon: Icons.pause_circle_outline_rounded,
                  expand: true,
                  loading: state.busy == TeamMemberBusy.deactivating,
                  onPressed: busy ? null : () => unawaited(_deactivate(member)),
                ),
              if (member.status == MembershipStatus.inactive)
                BafoButton.outline(
                  key: const Key('member.reactivate'),
                  label: l10n.accountTeamReactivate,
                  icon: Icons.play_circle_outline_rounded,
                  expand: true,
                  loading: state.busy == TeamMemberBusy.reactivating,
                  onPressed: busy
                      ? null
                      : () => unawaited(cubit.setActive(active: true)),
                ),
              const SizedBox(height: BafoSpacing.sm),
              BafoButton.destructive(
                key: const Key('member.remove'),
                label: l10n.accountTeamRemove,
                icon: Icons.person_remove_outlined,
                expand: true,
                loading: state.busy == TeamMemberBusy.removing,
                onPressed: busy ? null : () => unawaited(_remove(member)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
