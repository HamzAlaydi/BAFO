import 'dart:convert';
import 'dart:io';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/api/interceptors.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/storage/token_store.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:dio/dio.dart';

/// A decoded success envelope: `{ "data": ..., "meta": {...} }`.
final class ApiResponse {
  const ApiResponse({
    required this.data,
    this.meta = const {},
    this.statusCode,
    this.headers = const {},
  });

  /// Wraps a raw body. Bodies that are not an envelope land in [data].
  factory ApiResponse.fromBody(
    Object? body, {
    int? statusCode,
    Map<String, List<String>> headers = const {},
  }) {
    if (body is Map<String, dynamic> && body.containsKey('data')) {
      final meta = body['meta'];
      return ApiResponse(
        data: body['data'],
        meta: meta is Map<String, dynamic> ? meta : const {},
        statusCode: statusCode,
        headers: headers,
      );
    }
    return ApiResponse(data: body, statusCode: statusCode, headers: headers);
  }

  final Object? data;
  final Map<String, dynamic> meta;
  final int? statusCode;

  /// Response headers, lower-case names.
  final Map<String, List<String>> headers;

  /// First value of the response header [name] (case-insensitive).
  String? header(String name) {
    final values = headers[name.toLowerCase()];
    return values == null || values.isEmpty ? null : values.first;
  }

  /// 304 Not Modified (a conditional GET whose cached copy is current).
  bool get notModified => statusCode == 304;

  /// [data] as a JSON object; throws [ApiException] if it is not one.
  Map<String, dynamic> get dataMap {
    final value = data;
    if (value is Map<String, dynamic>) return value;
    throw const ApiException(code: ApiErrorCode.badResponse);
  }

  /// [data] as a list of JSON objects; throws [ApiException] otherwise.
  List<Map<String, dynamic>> get dataList {
    final value = data;
    if (value is List) return value.whereType<Map<String, dynamic>>().toList();
    throw const ApiException(code: ApiErrorCode.badResponse);
  }
}

/// Identifies the app to the API (ARCHITECTURE.md §4.8): the server answers
/// 426 `app_version_unsupported` below its minimum version per platform.
final class ClientInfo {
  const ClientInfo({required this.platform, required this.appVersion});

  /// `android` or `ios`.
  final String platform;

  /// Semver, e.g. `1.0.0`.
  final String appVersion;

  Map<String, String> get headers => {
    'X-Platform': platform,
    'X-App-Version': appVersion,
  };
}

/// HTTP client for the first-party API (`/api/app/v1`).
///
/// Every call returns an [ApiResponse] or throws an [ApiException]; callers
/// never see [DioException].
class ApiClient {
  ApiClient(this.dio);

  /// Builds the production client (CONVENTIONS.md §5.1): base URL, JSON,
  /// `X-Request-Id`, Bearer token, `Accept-Language`, `X-Platform` /
  /// `X-App-Version`, server-clock sync, and the 426 / 503 gate.
  factory ApiClient.create({
    required Env env,
    required TokenStore tokens,
    required ServerClock clock,
    required String Function() languageCode,
    required void Function() onUnauthorized,
    AppGateCubit? gate,
    ClientInfo? client,
    HttpClientAdapter? httpClientAdapter,
    List<Interceptor> extraInterceptors = const [],
  }) {
    final dio = Dio(
      BaseOptions(
        baseUrl: _withTrailingSlash(env.apiBaseUrl.toString()),
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 30),
        sendTimeout: const Duration(seconds: 60),
        headers: {'Accept': 'application/json', ...?client?.headers},
        responseType: ResponseType.json,
      ),
    );
    if (httpClientAdapter != null) dio.httpClientAdapter = httpClientAdapter;
    dio.interceptors.addAll([
      RequestIdInterceptor(),
      AuthInterceptor(tokens, onUnauthorized: onUnauthorized),
      LocaleInterceptor(languageCode),
      ServerTimeInterceptor(clock),
      if (gate != null) AppGateInterceptor(gate),
      ...extraInterceptors,
    ]);
    return ApiClient(dio);
  }

  final Dio dio;

  /// The versioned API root, e.g. `http://10.0.2.2:8000/api/app/v1/`.
  Uri get baseUri => Uri.parse(dio.options.baseUrl);

  /// Resolves a server path such as a file's `download_path`
  /// (`/api/app/v1/files/…/download`) against the API origin.
  Uri resolve(String path) => baseUri.resolve(path);

  /// [allowNotModified] accepts a 304 answer to a conditional request
  /// (`If-None-Match` in [headers]); check [ApiResponse.notModified].
  Future<ApiResponse> get(
    String path, {
    Map<String, dynamic>? query,
    Map<String, String>? headers,
    bool allowNotModified = false,
    CancelToken? cancelToken,
  }) => _send(
    () => dio.get<Object?>(
      _relative(path),
      queryParameters: query,
      options: headers == null && !allowNotModified
          ? null
          : Options(
              headers: headers,
              validateStatus: allowNotModified ? _okOrNotModified : null,
            ),
      cancelToken: cancelToken,
    ),
  );

  /// [contentType] overrides JSON, e.g. `Headers.formUrlEncodedContentType`.
  /// An absolute [path] bypasses the base URL (used for `/broadcasting/auth`).
  ///
  /// [headers] adds request headers, e.g. `Idempotency-Key`.
  Future<ApiResponse> post(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    Map<String, String>? headers,
    String? contentType,
    CancelToken? cancelToken,
    ProgressCallback? onSendProgress,
  }) => _send(
    () => dio.post<Object?>(
      _relative(path),
      data: body,
      queryParameters: query,
      options: contentType == null && headers == null
          ? null
          : Options(contentType: contentType, headers: headers),
      cancelToken: cancelToken,
      onSendProgress: onSendProgress,
    ),
  );

  /// Multipart upload (API.md §0.7): the file goes in the `file` part, next
  /// to [fields].
  Future<ApiResponse> upload(
    String path, {
    required String filePath,
    String? fileName,
    Map<String, Object?> fields = const {},
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
  }) async {
    final form = FormData.fromMap({
      for (final entry in fields.entries)
        if (entry.value != null) entry.key: entry.value,
      'file': await MultipartFile.fromFile(filePath, filename: fileName),
    });
    return post(
      path,
      body: form,
      onSendProgress: onSendProgress,
      cancelToken: cancelToken,
    );
  }

  /// Streams a private file to [savePath] with the bearer header (API.md
  /// §0.7). [url] is absolute or a server path (`download_path`). Errors
  /// carry the server's code (`forbidden`, `not_found`, ...).
  Future<void> download(
    String url,
    String savePath, {
    ProgressCallback? onReceiveProgress,
    CancelToken? cancelToken,
  }) async {
    final target = url.startsWith('http') ? url : resolve(url).toString();
    try {
      await dio.download(
        target,
        savePath,
        onReceiveProgress: onReceiveProgress,
        cancelToken: cancelToken,
        options: Options(headers: {'Accept': '*/*'}),
      );
    } on DioException catch (error) {
      final response = error.response;
      if (error.type == DioExceptionType.badResponse && response != null) {
        throw ApiException.fromResponse(
          statusCode: response.statusCode,
          body: await _decodeErrorBody(response.data),
        );
      }
      throw ApiException.fromDioException(error);
    }
  }

  Future<ApiResponse> put(
    String path, {
    Object? body,
    CancelToken? cancelToken,
  }) => _send(
    () =>
        dio.put<Object?>(_relative(path), data: body, cancelToken: cancelToken),
  );

  Future<ApiResponse> patch(
    String path, {
    Object? body,
    CancelToken? cancelToken,
  }) => _send(
    () => dio.patch<Object?>(
      _relative(path),
      data: body,
      cancelToken: cancelToken,
    ),
  );

  Future<ApiResponse> delete(
    String path, {
    Object? body,
    CancelToken? cancelToken,
  }) => _send(
    () => dio.delete<Object?>(
      _relative(path),
      data: body,
      cancelToken: cancelToken,
    ),
  );

  Future<ApiResponse> _send(Future<Response<Object?>> Function() call) async {
    try {
      final response = await call();
      return ApiResponse.fromBody(
        response.data,
        statusCode: response.statusCode,
        headers: response.headers.map,
      );
    } on DioException catch (error) {
      throw ApiException.fromDioException(error);
    }
  }

  static bool _okOrNotModified(int? status) =>
      status != null && ((status >= 200 && status < 300) || status == 304);

  /// Download error bodies arrive as bytes or a stream: decode the JSON
  /// envelope when there is one.
  static Future<Object?> _decodeErrorBody(Object? data) async {
    try {
      final List<int> bytes;
      if (data is ResponseBody) {
        bytes = [for (final chunk in await data.stream.toList()) ...chunk];
      } else if (data is List<int>) {
        bytes = data;
      } else {
        return data;
      }
      return jsonDecode(utf8.decode(bytes));
    } on FormatException {
      return null;
    } on IOException {
      return null;
    }
  }

  // Paths are relative to the versioned base URL ('/auth/login' and
  // 'auth/login' both resolve under /api/app/v1); absolute URLs pass through.
  static String _relative(String path) =>
      path.startsWith('/') ? path.substring(1) : path;

  static String _withTrailingSlash(String url) =>
      url.endsWith('/') ? url : '$url/';
}
