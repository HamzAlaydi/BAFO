import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/widgets/bafo_toast.dart';
import 'package:bafo/widgets/status_pill.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

/// "482 KB" / «482 كيلوبايت», Western digits.
String formatFileSize(int bytes, AppLocalizations l10n) {
  if (bytes < 1024) return l10n.commonFileSizeBytes('$bytes');
  if (bytes < 1024 * 1024) {
    return l10n.commonFileSizeKb((bytes / 1024).toStringAsFixed(0));
  }
  return l10n.commonFileSizeMb((bytes / (1024 * 1024)).toStringAsFixed(1));
}

/// A competition document or link (SCREENS.md M22).
///
/// * Files download with the bearer header ([FileDownloadService], from the
///   nearest provider) into the temporary directory, with progress, then
///   open in the system viewer.
/// * Links (https only) open in the browser.
/// * [onDelete] adds a remove action (issuer, draft or scheduled).
class AttachmentTile extends StatefulWidget {
  const AttachmentTile({
    required this.attachment,
    this.onDelete,
    this.downloads,
    super.key,
  });

  final Attachment attachment;
  final VoidCallback? onDelete;

  /// Defaults to `context.read<FileDownloadService>()`.
  final FileDownloadService? downloads;

  @override
  State<AttachmentTile> createState() => _AttachmentTileState();
}

class _AttachmentTileState extends State<AttachmentTile> {
  /// Null when idle; 0..1 while downloading (or -1 when the size is unknown).
  double? _progress;

  Future<void> _activate() async {
    final attachment = widget.attachment;
    final l10n = context.l10n;
    final file = attachment.file;
    if (attachment.isLink || file == null) {
      final uri = Uri.tryParse(attachment.url ?? '');
      final opened =
          uri != null &&
          uri.scheme == 'https' &&
          await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted) BafoToast.error(context, l10n.commonLinkOpenFailed);
      return;
    }
    if (_progress != null) return;
    final downloads = widget.downloads ?? context.read<FileDownloadService>();
    setState(() => _progress = -1);
    try {
      final path = await downloads.download(
        file,
        onProgress: (received, total) {
          if (!mounted) return;
          setState(() => _progress = total > 0 ? received / total : -1);
        },
      );
      if (!mounted) return;
      setState(() => _progress = null);
      final result = await downloads.open(path, mimeType: file.mimeType);
      if (!mounted) return;
      if (result == FileOpenResult.noAppToOpen) {
        BafoToast.show(context, l10n.commonNoAppToOpen);
      } else if (result == FileOpenResult.failed) {
        BafoToast.error(context, l10n.commonDownloadFailed);
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _progress = null);
      BafoToast.error(
        context,
        error.isConnectivity ? errorMessage(l10n, error) : l10n.commonDownloadFailed,
      );
    } on Exception {
      if (!mounted) return;
      setState(() => _progress = null);
      BafoToast.error(context, l10n.commonDownloadFailed);
    }
  }

  static IconData _iconFor(Attachment attachment) {
    if (attachment.isLink) return Icons.link_rounded;
    return switch ((attachment.file?.extension ?? '').toLowerCase()) {
      'pdf' => Icons.picture_as_pdf_outlined,
      'xls' || 'xlsx' => Icons.table_chart_outlined,
      'doc' || 'docx' => Icons.description_outlined,
      'png' || 'jpg' || 'jpeg' => Icons.image_outlined,
      'zip' => Icons.folder_zip_outlined,
      _ => Icons.insert_drive_file_outlined,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final attachment = widget.attachment;
    final size = attachment.file?.sizeBytes;
    final progress = _progress;
    final details = [
      if (attachment.isLink) l10n.commonExternalLink,
      if (size != null) formatFileSize(size, l10n),
    ].join(' · ');

    final Widget trailing = progress != null
        ? SizedBox.square(
            dimension: 24,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              value: progress < 0 ? null : progress,
              semanticsLabel: l10n.commonDownloading,
            ),
          )
        : Icon(
            attachment.isLink
                ? Icons.open_in_new_rounded
                : Icons.download_rounded,
            color: theme.colorScheme.primary,
          );

    final main = Semantics(
      button: true,
      label: [
        attachment.displayName,
        if (details.isNotEmpty) details,
        if (attachment.isAddendum) l10n.commonAddendum,
        attachment.isLink ? l10n.commonActionsOpen : l10n.commonActionsDownload,
      ].join(', '),
      excludeSemantics: true,
      child: InkWell(
        onTap: _activate,
        borderRadius: BorderRadius.circular(BafoRadii.card),
        child: Padding(
          padding: const EdgeInsetsDirectional.symmetric(
            horizontal: BafoSpacing.sm,
            vertical: BafoSpacing.md,
          ),
          child: Row(
            children: [
              Icon(_iconFor(attachment), color: theme.colorScheme.onSurfaceVariant),
              const SizedBox(width: BafoSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      attachment.displayName,
                      style: theme.textTheme.bodyLarge,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (details.isNotEmpty || attachment.isAddendum)
                      Padding(
                        padding: const EdgeInsetsDirectional.only(
                          top: BafoSpacing.xxs,
                        ),
                        child: Wrap(
                          spacing: BafoSpacing.sm,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            if (details.isNotEmpty)
                              Text(
                                details,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                              ),
                            if (attachment.isAddendum)
                              StatusPill(
                                label: l10n.commonAddendum,
                                tone: StatusTone.info,
                                icon: Icons.add_circle_outline_rounded,
                              ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: BafoSpacing.sm),
              trailing,
            ],
          ),
        ),
      ),
    );
    final onDelete = widget.onDelete;
    if (onDelete == null) return main;
    return Row(
      children: [
        Expanded(child: main),
        IconButton(
          onPressed: onDelete,
          tooltip: l10n.commonActionsRemove,
          icon: const Icon(Icons.delete_outline_rounded),
        ),
      ],
    );
  }
}
