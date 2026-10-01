import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';

/// Picks one image from the device and returns its local path, or null
/// when cancelled. Injectable so tests never open the system picker.
typedef ImagePick = Future<String?> Function();

/// The system picker for images (photos or files). The camera needs
/// `image_picker`, which the app does not bundle yet (handoff M-9).
Future<String?> pickImageFromDevice() async {
  try {
    final file = await FilePicker.pickFile(type: FileType.image);
    return file?.path;
  } on Object catch (error) {
    debugPrint('Image picking failed: $error');
    return null;
  }
}

/// What the image sheet returned.
enum ImageSheetChoice { pick, remove }

/// M53, the themed image sheet: choose an image, or remove the current one.
Future<ImageSheetChoice?> showImageSheet(
  BuildContext context, {
  required String title,
  bool canRemove = false,
}) {
  final l10n = context.l10n;
  return showBafoBottomSheet<ImageSheetChoice>(
    context,
    title: title,
    builder: (sheet) {
      final error = Theme.of(sheet).colorScheme.error;
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            key: const Key('imageSheet.pick'),
            leading: const Icon(Icons.photo_library_outlined),
            title: Text(l10n.accountImagePick),
            onTap: () => Navigator.of(sheet).pop(ImageSheetChoice.pick),
          ),
          if (canRemove)
            ListTile(
              key: const Key('imageSheet.remove'),
              leading: Icon(Icons.delete_outline_rounded, color: error),
              title: Text(
                l10n.accountImageRemove,
                style: TextStyle(color: error),
              ),
              onTap: () => Navigator.of(sheet).pop(ImageSheetChoice.remove),
            ),
        ],
      );
    },
  );
}

/// Blocks leaving a form with unsaved changes (SCREENS.md S7) behind a
/// «تجاهل التغييرات؟» confirmation.
class UnsavedChangesGuard extends StatelessWidget {
  const UnsavedChangesGuard({
    required this.dirty,
    required this.child,
    super.key,
  });

  final bool dirty;
  final Widget child;

  @override
  Widget build(BuildContext context) => PopScope<Object?>(
    canPop: !dirty,
    onPopInvokedWithResult: (didPop, result) async {
      if (didPop) return;
      final l10n = context.l10n;
      final navigator = Navigator.of(context);
      final discard = await showConfirmDialog(
        context,
        title: l10n.accountDiscardTitle,
        message: l10n.accountDiscardMessage,
        confirmLabel: l10n.accountDiscardAction,
        destructive: true,
      );
      if (discard && navigator.mounted) navigator.pop(result);
    },
    child: child,
  );
}

/// The label above a group of rows or a card in the account hub and
/// settings (M51, M59), so every group on those screens reads the same.
class AccountSectionLabel extends StatelessWidget {
  const AccountSectionLabel(this.title, {super.key});

  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsetsDirectional.only(
        start: BafoSpacing.xs,
        top: BafoSpacing.xl,
        bottom: BafoSpacing.sm,
      ),
      child: Semantics(
        header: true,
        child: Text(
          title,
          style: theme.textTheme.titleSmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ),
    );
  }
}

/// A titled group of rows (M51, M59).
class AccountSection extends StatelessWidget {
  const AccountSection({
    required this.title,
    required this.children,
    super.key,
  });

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AccountSectionLabel(title),
        BafoCard(
          padding: const EdgeInsetsDirectional.symmetric(
            vertical: BafoSpacing.xs,
          ),
          child: Column(
            children: [
              for (final (index, child) in children.indexed) ...[
                if (index > 0) const Divider(height: 1, indent: 56),
                child,
              ],
            ],
          ),
        ),
      ],
    );
  }
}

/// A navigation row of an [AccountSection].
class AccountLinkTile extends StatelessWidget {
  const AccountLinkTile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.trailing,
    super.key,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return ListTile(
      leading: Icon(icon, color: muted),
      title: Text(title),
      subtitle: subtitle == null ? null : Text(subtitle!),
      trailing: trailing ?? Icon(Icons.chevron_right_rounded, color: muted),
      onTap: onTap,
    );
  }
}

/// Section title inside a form or detail page.
class FormSectionTitle extends StatelessWidget {
  const FormSectionTitle(this.title, {super.key});

  final String title;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(
      top: BafoSpacing.xl,
      bottom: BafoSpacing.md,
    ),
    child: Semantics(
      header: true,
      child: Text(title, style: Theme.of(context).textTheme.titleMedium),
    ),
  );
}

String roleLabel(AppLocalizations l10n, MembershipRole role) => switch (role) {
  MembershipRole.owner => l10n.accountRoleOwner,
  MembershipRole.admin => l10n.accountRoleAdmin,
  MembershipRole.member || MembershipRole.unknown => l10n.accountRoleMember,
};

String membershipStatusLabel(AppLocalizations l10n, MembershipStatus status) =>
    switch (status) {
      MembershipStatus.invited => l10n.accountTeamStatusInvited,
      MembershipStatus.inactive => l10n.accountTeamStatusInactive,
      MembershipStatus.active ||
      MembershipStatus.unknown => l10n.accountTeamStatusActive,
    };

StatusTone membershipStatusTone(MembershipStatus status) => switch (status) {
  MembershipStatus.invited => StatusTone.info,
  MembershipStatus.inactive => StatusTone.neutral,
  MembershipStatus.active || MembershipStatus.unknown => StatusTone.success,
};
