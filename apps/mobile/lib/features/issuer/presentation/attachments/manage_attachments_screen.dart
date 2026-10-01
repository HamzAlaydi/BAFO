import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/issuer/presentation/attachments/manage_attachments_cubit.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// Picks one document from the device (the system file picker).
typedef DocumentPicker = Future<PickedDocument?> Function();

Future<PickedDocument?> _pickWithSystem() async {
  final file = await FilePicker.pickFile(
    type: FileType.custom,
    allowedExtensions: AttachmentRules.extensions,
  );
  final path = file?.path;
  if (file == null || path == null) return null;
  return PickedDocument(path: path, name: file.name, size: file.lengthSync());
}

/// M42 (`/competitions/:id/attachments`): upload documents (kind
/// `document`) with progress, download them, and delete them while draft
/// or scheduled. Links and invitation documents are web-only.
class ManageAttachmentsScreen extends StatelessWidget {
  const ManageAttachmentsScreen({
    required this.competitionId,
    this.picker,
    super.key,
  });

  final String competitionId;

  /// Replaces the system file picker (tests).
  final DocumentPicker? picker;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => ManageAttachmentsCubit(
      competitions: context.read<CompetitionsRepository>(),
      attachments: context.read<AttachmentsRepository>(),
      competitionId: competitionId,
    )..load(),
    child: _AttachmentsView(picker: picker ?? _pickWithSystem),
  );
}

class _AttachmentsView extends StatelessWidget {
  const _AttachmentsView({required this.picker});

  final DocumentPicker picker;

  Future<void> _upload(BuildContext context) async {
    final cubit = context.read<ManageAttachmentsCubit>();
    final document = await picker();
    if (document != null) await cubit.upload(document);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<ManageAttachmentsCubit, ManageAttachmentsState>(
      listenWhen: (previous, current) =>
          current is ManageAttachmentsNotIssuer ||
          (current is ManageAttachmentsLoaded &&
              current.actionError != null &&
              (previous is! ManageAttachmentsLoaded ||
                  previous.actionError != current.actionError)),
      listener: (context, state) {
        if (state is ManageAttachmentsNotIssuer) {
          leaveToDetail(context, state.competition.id);
        } else if (state is ManageAttachmentsLoaded) {
          BafoToast.error(context, errorMessage(l10n, state.actionError));
        }
      },
      builder: (context, state) {
        final cubit = context.read<ManageAttachmentsCubit>();
        final loaded = state is ManageAttachmentsLoaded ? state : null;
        return Scaffold(
          appBar: BafoAppBar(title: l10n.issuerDocumentsTitle),
          floatingActionButton:
              loaded != null && loaded.canUpload && !issuerOffline(context)
              ? FloatingActionButton.extended(
                  onPressed: () => _upload(context),
                  icon: const Icon(Icons.upload_file_rounded),
                  label: Text(l10n.issuerDocumentsUpload),
                )
              : null,
          body: switch (state) {
            ManageAttachmentsLoading() ||
            ManageAttachmentsNotIssuer() => const LoadingSkeletonList(),
            ManageAttachmentsNotFound() => NotFoundState(
              message: l10n.competitionsDetailNotFound,
            ),
            ManageAttachmentsFailure(:final error) => ErrorState(
              error: error,
              onRetry: cubit.load,
            ),
            ManageAttachmentsLoaded() => _Loaded(state: state),
          },
        );
      },
    );
  }
}

class _Loaded extends StatelessWidget {
  const _Loaded({required this.state});

  final ManageAttachmentsLoaded state;

  Future<void> _delete(BuildContext context, Attachment attachment) async {
    final l10n = context.l10n;
    final confirmed = await showConfirmDialog(
      context,
      title: l10n.issuerDocumentDeleteTitle,
      message: l10n.issuerDocumentDeleteMessage(attachment.displayName),
      confirmLabel: l10n.issuerDocumentDelete,
      destructive: true,
    );
    if (confirmed && context.mounted) {
      await context.read<ManageAttachmentsCubit>().delete(attachment);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final cubit = context.read<ManageAttachmentsCubit>();
    final published = state.competition.status != CompetitionStatus.draft;
    return ListView(
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.md,
        BafoSpacing.page,
        BafoSpacing.xxxl * 2,
      ),
      children: [
        Text(state.competition.title, style: theme.textTheme.titleMedium),
        const SizedBox(height: BafoSpacing.md),
        if (state.canUpload) ...[
          Text(
            l10n.issuerDocumentsLimits,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          if (published) ...[
            const SizedBox(height: BafoSpacing.sm),
            InfoNotice(message: l10n.issuerDocumentsAddendumNote),
          ],
          const SizedBox(height: BafoSpacing.sm),
        ],
        if (!state.canDelete && state.attachments.isNotEmpty) ...[
          InfoNotice(
            message: l10n.issuerDocumentsDeleteClosed,
            tone: StatusTone.neutral,
          ),
          const SizedBox(height: BafoSpacing.sm),
        ],
        WebOnlyNotice(message: l10n.issuerDocumentsWebOnly),
        const SizedBox(height: BafoSpacing.lg),
        for (final upload in state.uploads)
          Padding(
            padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.sm),
            child: _UploadRow(
              upload: upload,
              onDismiss: () => cubit.dismissUpload(upload.id),
            ),
          ),
        if (state.attachments.isEmpty && state.uploads.isEmpty)
          EmptyState(
            icon: Icons.folder_open_outlined,
            title: l10n.issuerDocumentsEmptyTitle,
            message: l10n.issuerDocumentsEmptyMessage,
          )
        else if (state.attachments.isNotEmpty)
          BafoCard(
            padding: const EdgeInsetsDirectional.symmetric(
              horizontal: BafoSpacing.sm,
            ),
            child: Column(
              children: [
                for (final (index, attachment)
                    in state.attachments.indexed) ...[
                  if (index > 0) const Divider(),
                  AttachmentTile(
                    attachment: attachment,
                    onDelete:
                        state.canDelete &&
                            !issuerOffline(context) &&
                            !state.deleting.contains(attachment.id)
                        ? () => _delete(context, attachment)
                        : null,
                  ),
                ],
              ],
            ),
          ),
      ],
    );
  }
}

class _UploadRow extends StatelessWidget {
  const _UploadRow({required this.upload, required this.onDismiss});

  final DocumentUpload upload;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final message = switch (upload.errorCode) {
      null => null,
      'file_type_not_allowed' => l10n.errorsFileTypeNotAllowed,
      'file_too_large' => l10n.errorsFileTooLarge,
      final code =>
        (upload.errorMessage ?? '').isNotEmpty
            ? upload.errorMessage!
            : errorText(l10n, code) ?? l10n.issuerDocumentsUploadFailed,
    };
    return BafoCard(
      padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                upload.failed
                    ? Icons.error_outline_rounded
                    : Icons.upload_file_rounded,
                color: upload.failed
                    ? theme.colorScheme.error
                    : theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: BafoSpacing.sm),
              Expanded(
                child: Text(
                  upload.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (upload.failed)
                IconButton(
                  tooltip: l10n.commonActionsClose,
                  icon: const Icon(Icons.close_rounded),
                  onPressed: onDismiss,
                ),
            ],
          ),
          const SizedBox(height: BafoSpacing.sm),
          if (message != null)
            Semantics(
              liveRegion: true,
              child: Text(
                message,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
            )
          else
            LinearProgressIndicator(
              value: upload.progress,
              semanticsLabel: l10n.issuerDocumentsUploading(upload.name),
              semanticsValue: upload.progress == null
                  ? null
                  : '${(upload.progress! * 100).round()}%',
            ),
        ],
      ),
    );
  }
}
