import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:dio/dio.dart' show ProgressCallback;

/// Competition documents and links (API.md §1.4). Downloads go through
/// `FileDownloadService` (bearer header, temporary directory).
abstract interface class AttachmentsRepository {
  /// `GET …/attachments`: what the viewer may see (invitees get invitation
  /// documents only).
  Future<List<Attachment>> list(String competitionId);

  /// `POST …/attachments` (multipart). Mobile uploads `document` only
  /// (≤ 100 MB; pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip).
  Future<Attachment> uploadFile(
    String competitionId, {
    required String filePath,
    String? fileName,
    String? title,
    AttachmentKind kind = AttachmentKind.document,
    ProgressCallback? onSendProgress,
  });

  /// `PATCH …/attachments/{id}`.
  Future<Attachment> update(
    String competitionId,
    String attachmentId, {
    String? title,
    int? sortOrder,
  });

  /// `DELETE …/attachments/{id}` (draft or scheduled only).
  Future<void> delete(String competitionId, String attachmentId);
}

final class ApiAttachmentsRepository implements AttachmentsRepository {
  ApiAttachmentsRepository(this._api);

  final ApiClient _api;

  @override
  Future<List<Attachment>> list(String competitionId) async =>
      (await _api.get('competitions/$competitionId/attachments')).dataList
          .map(Attachment.fromJson)
          .toList();

  @override
  Future<Attachment> uploadFile(
    String competitionId, {
    required String filePath,
    String? fileName,
    String? title,
    AttachmentKind kind = AttachmentKind.document,
    ProgressCallback? onSendProgress,
  }) async {
    final response = await _api.upload(
      'competitions/$competitionId/attachments',
      filePath: filePath,
      fileName: fileName,
      fields: {'kind': kind.wire, 'title': title},
      onSendProgress: onSendProgress,
    );
    return Attachment.fromJson(response.dataMap);
  }

  @override
  Future<Attachment> update(
    String competitionId,
    String attachmentId, {
    String? title,
    int? sortOrder,
  }) async => Attachment.fromJson(
    (await _api.patch(
      'competitions/$competitionId/attachments/$attachmentId',
      body: {'title': ?title, 'sort_order': ?sortOrder},
    )).dataMap,
  );

  @override
  Future<void> delete(String competitionId, String attachmentId) =>
      _api.delete('competitions/$competitionId/attachments/$attachmentId');
}

/// Q&A (API.md §1.4). Posting needs `scheduled` or `live`, otherwise 409
/// `comments_closed`.
abstract interface class CommentsRepository {
  /// `GET …/comments`: top-level comments newest first, paginated.
  Future<Paged<Comment>> list(
    String competitionId, {
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  });

  /// `POST …/comments` (1–2000 characters).
  Future<Comment> post(String competitionId, {required String body, String? parentId});
}

final class ApiCommentsRepository implements CommentsRepository {
  ApiCommentsRepository(this._api);

  final ApiClient _api;

  @override
  Future<Paged<Comment>> list(
    String competitionId, {
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async => Paged.fromResponse(
    await _api.get(
      'competitions/$competitionId/comments',
      query: pageQuery(page, perPage),
    ),
    Comment.fromJson,
  );

  @override
  Future<Comment> post(
    String competitionId, {
    required String body,
    String? parentId,
  }) async => Comment.fromJson(
    (await _api.post(
      'competitions/$competitionId/comments',
      body: {'body': body, 'parent_id': parentId},
    )).dataMap,
  );
}

/// The issuer's vendor directory (invite picker, API.md §1.9).
abstract interface class VendorsRepository {
  /// `GET /vendors?q=` (needs `competitions.create`).
  Future<Paged<Vendor>> search({
    String? query,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  });
}

final class ApiVendorsRepository implements VendorsRepository {
  ApiVendorsRepository(this._api);

  final ApiClient _api;

  @override
  Future<Paged<Vendor>> search({
    String? query,
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async {
    final search = query?.trim();
    return Paged.fromResponse(
      await _api.get(
        'vendors',
        query: {
          if (search != null && search.isNotEmpty) 'q': search,
          'status': 'active',
          ...pageQuery(page, perPage),
        },
      ),
      Vendor.fromJson,
    );
  }
}
