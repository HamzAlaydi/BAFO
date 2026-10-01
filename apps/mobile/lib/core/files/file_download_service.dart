import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:dio/dio.dart' show CancelToken, ProgressCallback;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

/// Outcome of opening a downloaded file.
enum FileOpenResult { opened, noAppToOpen, failed }

/// Private files (API.md §0.7, SCREENS.md M22): downloaded with the bearer
/// header into the app's temporary directory, then opened with the system
/// viewer. Tokens never go into a URL.
abstract interface class FileDownloadService {
  /// The local path of [file], downloading it unless an identical copy is
  /// already cached. Throws `ApiException` (`forbidden`, `not_found`, ...).
  Future<String> download(
    FileRef file, {
    ProgressCallback? onProgress,
    CancelToken? cancelToken,
  });

  /// Opens a downloaded file with the system viewer.
  Future<FileOpenResult> open(String path, {String? mimeType});
}

final class ApiFileDownloadService implements FileDownloadService {
  ApiFileDownloadService(
    this._api, {
    Future<Directory> Function()? cacheDirectory,
    Future<FileOpenResult> Function(String path, String? mimeType)? opener,
  }) : _cacheDirectory = cacheDirectory ?? getTemporaryDirectory,
       _opener = opener ?? _openWithSystem;

  final ApiClient _api;
  final Future<Directory> Function() _cacheDirectory;
  final Future<FileOpenResult> Function(String path, String? mimeType) _opener;

  @override
  Future<String> download(
    FileRef file, {
    ProgressCallback? onProgress,
    CancelToken? cancelToken,
  }) async {
    final root = await _cacheDirectory();
    final folder = Directory('${root.path}/bafo_files/${_safe(file.id)}');
    final target = File('${folder.path}/${safeFileName(file.name)}');
    if (target.existsSync() &&
        (file.sizeBytes == null || target.lengthSync() == file.sizeBytes)) {
      return target.path;
    }
    await folder.create(recursive: true);
    // Write to a temporary name first so a failed download never looks cached.
    final partial = File('${target.path}.part');
    await _api.download(
      file.downloadPath,
      partial.path,
      onReceiveProgress: onProgress,
      cancelToken: cancelToken,
    );
    await partial.rename(target.path);
    return target.path;
  }

  @override
  Future<FileOpenResult> open(String path, {String? mimeType}) =>
      _opener(path, mimeType);

  static Future<FileOpenResult> _openWithSystem(
    String path,
    String? mimeType,
  ) async {
    final result = await OpenFilex.open(path, type: mimeType);
    return switch (result.type) {
      ResultType.done => FileOpenResult.opened,
      ResultType.noAppToOpen => FileOpenResult.noAppToOpen,
      _ => FileOpenResult.failed,
    };
  }

  static String _safe(String value) =>
      value.replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_');

  /// A file name without path separators or control characters.
  static String safeFileName(String name) {
    final cleaned = name
        .replaceAll(RegExp(r'[/\\:*?"<>|\x00-\x1F]'), '_')
        .trim();
    return cleaned.isEmpty || cleaned == '.' || cleaned == '..'
        ? 'file'
        : cleaned;
  }
}
