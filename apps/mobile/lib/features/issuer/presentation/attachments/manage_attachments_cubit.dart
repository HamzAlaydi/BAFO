import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Upload limits (API.md §1.4 attachments, ARCHITECTURE §4.6).
abstract final class AttachmentRules {
  static const int maxBytes = 100 * 1024 * 1024;

  static const List<String> extensions = [
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'png',
    'jpg',
    'jpeg',
    'zip',
  ];

  static bool allows(String fileName) {
    final dot = fileName.lastIndexOf('.');
    if (dot < 0 || dot == fileName.length - 1) return false;
    return extensions.contains(fileName.substring(dot + 1).toLowerCase());
  }
}

/// A file the user picked.
final class PickedDocument extends Equatable {
  const PickedDocument({required this.path, required this.name, this.size});

  final String path;
  final String name;
  final int? size;

  @override
  List<Object?> get props => [path, name, size];
}

/// One upload in progress or failed.
final class DocumentUpload extends Equatable {
  const DocumentUpload({
    required this.id,
    required this.name,
    this.progress,
    this.errorCode,
    this.errorMessage,
  });

  final int id;
  final String name;

  /// 0..1, or null while the size is unknown.
  final double? progress;

  /// `file_type_not_allowed`, `file_too_large`, `competition_not_editable`,
  /// or a transport code.
  final String? errorCode;
  final String? errorMessage;

  bool get failed => errorCode != null;

  @override
  List<Object?> get props => [id, name, progress, errorCode, errorMessage];
}

sealed class ManageAttachmentsState extends Equatable {
  const ManageAttachmentsState();

  @override
  List<Object?> get props => [];
}

final class ManageAttachmentsLoading extends ManageAttachmentsState {
  const ManageAttachmentsLoading();
}

final class ManageAttachmentsFailure extends ManageAttachmentsState {
  const ManageAttachmentsFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class ManageAttachmentsNotFound extends ManageAttachmentsState {
  const ManageAttachmentsNotFound();
}

/// Not the issuer: back to the shared detail (role guard).
final class ManageAttachmentsNotIssuer extends ManageAttachmentsState {
  const ManageAttachmentsNotIssuer(this.competition);

  final Competition competition;

  @override
  List<Object?> get props => [competition];
}

final class ManageAttachmentsLoaded extends ManageAttachmentsState {
  const ManageAttachmentsLoaded({
    required this.competition,
    required this.attachments,
    this.uploads = const [],
    this.deleting = const {},
    this.actionError,
  });

  final Competition competition;
  final List<Attachment> attachments;
  final List<DocumentUpload> uploads;
  final Set<String> deleting;
  final ApiException? actionError;

  bool get canUpload => competition.acceptsAttachments;

  bool get canDelete => competition.allowsAttachmentRemoval;

  ManageAttachmentsLoaded copyWith({
    List<Attachment>? attachments,
    List<DocumentUpload>? uploads,
    Set<String>? deleting,
    ApiException? Function()? actionError,
  }) => ManageAttachmentsLoaded(
    competition: competition,
    attachments: attachments ?? this.attachments,
    uploads: uploads ?? this.uploads,
    deleting: deleting ?? this.deleting,
    actionError: actionError == null ? this.actionError : actionError(),
  );

  @override
  List<Object?> get props => [
    competition,
    attachments,
    uploads,
    deleting,
    actionError,
  ];
}

/// M42: upload documents (kind `document` only; links and invitation
/// documents are web-only) with progress, and delete them while draft or
/// scheduled.
class ManageAttachmentsCubit extends Cubit<ManageAttachmentsState> {
  ManageAttachmentsCubit({
    required this._competitions,
    required this._attachments,
    required this.competitionId,
  }) : super(const ManageAttachmentsLoading());

  final CompetitionsRepository _competitions;
  final AttachmentsRepository _attachments;
  final String competitionId;
  int _nextUploadId = 0;

  Future<void> load() async {
    emit(const ManageAttachmentsLoading());
    try {
      final competition = await _competitions.show(competitionId);
      if (isClosed) return;
      if (competition.viewerRole != ViewerRole.issuer) {
        emit(ManageAttachmentsNotIssuer(competition));
        return;
      }
      final attachments = await _attachments.list(competitionId);
      if (!isClosed) {
        emit(
          ManageAttachmentsLoaded(
            competition: competition,
            attachments: attachments,
          ),
        );
      }
    } on ApiException catch (error) {
      if (isClosed) return;
      if (error.statusCode == 404 || error.code == 'not_found') {
        emit(const ManageAttachmentsNotFound());
      } else {
        emit(ManageAttachmentsFailure(error));
      }
    }
  }

  /// Checks the type and size first (the server re-checks), then uploads.
  Future<void> upload(PickedDocument document, {String? title}) async {
    final current = state;
    if (current is! ManageAttachmentsLoaded || !current.canUpload) return;
    final id = _nextUploadId++;
    final localError = !AttachmentRules.allows(document.name)
        ? 'file_type_not_allowed'
        : (document.size ?? 0) > AttachmentRules.maxBytes
        ? 'file_too_large'
        : null;
    if (localError != null) {
      emit(
        current.copyWith(
          uploads: [
            ...current.uploads,
            DocumentUpload(id: id, name: document.name, errorCode: localError),
          ],
        ),
      );
      return;
    }
    emit(
      current.copyWith(
        uploads: [
          ...current.uploads,
          DocumentUpload(id: id, name: document.name),
        ],
      ),
    );
    try {
      final trimmed = title?.trim();
      final attachment = await _attachments.uploadFile(
        competitionId,
        filePath: document.path,
        fileName: document.name,
        title: trimmed == null || trimmed.isEmpty ? null : trimmed,
        onSendProgress: (sent, total) =>
            _progress(id, total > 0 ? sent / total : null),
      );
      if (isClosed) return;
      final latest = state;
      if (latest is! ManageAttachmentsLoaded) return;
      emit(
        latest.copyWith(
          attachments: [...latest.attachments, attachment],
          uploads: [
            for (final upload in latest.uploads)
              if (upload.id != id) upload,
          ],
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      final latest = state;
      if (latest is! ManageAttachmentsLoaded) return;
      emit(
        latest.copyWith(
          uploads: [
            for (final upload in latest.uploads)
              upload.id == id
                  ? DocumentUpload(
                      id: id,
                      name: upload.name,
                      errorCode: error.code,
                      errorMessage: error.fieldError('file') ?? error.message,
                    )
                  : upload,
          ],
        ),
      );
    }
  }

  void _progress(int id, double? progress) {
    final current = state;
    if (isClosed || current is! ManageAttachmentsLoaded) return;
    emit(
      current.copyWith(
        uploads: [
          for (final upload in current.uploads)
            upload.id == id
                ? DocumentUpload(id: id, name: upload.name, progress: progress)
                : upload,
        ],
      ),
    );
  }

  void dismissUpload(int id) {
    final current = state;
    if (isClosed || current is! ManageAttachmentsLoaded) return;
    emit(
      current.copyWith(
        uploads: [
          for (final upload in current.uploads)
            if (upload.id != id) upload,
        ],
      ),
    );
  }

  /// `DELETE …/attachments/{id}` (draft or scheduled).
  Future<void> delete(Attachment attachment) async {
    final current = state;
    if (current is! ManageAttachmentsLoaded ||
        !current.canDelete ||
        current.deleting.contains(attachment.id)) {
      return;
    }
    emit(
      current.copyWith(
        deleting: {...current.deleting, attachment.id},
        actionError: () => null,
      ),
    );
    try {
      await _attachments.delete(competitionId, attachment.id);
      if (isClosed) return;
      final latest = state;
      if (latest is! ManageAttachmentsLoaded) return;
      emit(
        latest.copyWith(
          attachments: [
            for (final item in latest.attachments)
              if (item.id != attachment.id) item,
          ],
          deleting: {...latest.deleting}..remove(attachment.id),
        ),
      );
    } on ApiException catch (error) {
      if (isClosed) return;
      final latest = state;
      if (latest is! ManageAttachmentsLoaded) return;
      emit(
        latest.copyWith(
          deleting: {...latest.deleting}..remove(attachment.id),
          actionError: () => error,
        ),
      );
    }
  }
}
