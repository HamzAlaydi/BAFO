import 'package:dio/dio.dart';

/// Error codes raised on the client side (no usable server response).
/// Server codes (`validation_failed`, `unauthenticated`, ...) pass through
/// unchanged from the API error envelope.
abstract final class ApiErrorCode {
  static const String network = 'network_error';
  static const String timeout = 'timeout';
  static const String cancelled = 'cancelled';
  static const String badResponse = 'bad_response';
  static const String unknown = 'unknown_error';

  // Server codes the app reacts to (CONVENTIONS.md §8).
  static const String unauthenticated = 'unauthenticated';
  static const String validationFailed = 'validation_failed';
  static const String appVersionUnsupported = 'app_version_unsupported';
  static const String maintenance = 'maintenance';
}

/// Any failed API call. Mirrors the API error envelope (API.md §0.4)
/// `{ "message": "...", "code": "snake_case", "errors": { field: [...] },
/// "details": {...} }`.
///
/// [message] is already localised by the server (Accept-Language). It is
/// empty for client-side failures; show a localised text for [code] instead
/// (see `errorMessage` in core/l10n).
final class ApiException implements Exception {
  const ApiException({
    required this.code,
    this.message = '',
    this.fieldErrors = const {},
    this.details = const {},
    this.statusCode,
  });

  /// Maps a Dio failure to an [ApiException].
  factory ApiException.fromDioException(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.transformTimeout:
        return const ApiException(code: ApiErrorCode.timeout);
      case DioExceptionType.cancel:
        return const ApiException(code: ApiErrorCode.cancelled);
      case DioExceptionType.connectionError:
        return const ApiException(code: ApiErrorCode.network);
      case DioExceptionType.badCertificate:
        return const ApiException(code: ApiErrorCode.network);
      case DioExceptionType.badResponse:
        final response = error.response;
        return ApiException.fromResponse(
          statusCode: response?.statusCode,
          body: response?.data,
        );
      case DioExceptionType.unknown:
        final inner = error.error;
        if (inner is ApiException) return inner;
        // SocketException and friends surface here on some platforms.
        return ApiException(
          code: error.response == null
              ? ApiErrorCode.network
              : ApiErrorCode.unknown,
        );
    }
  }

  /// Maps an error response body (the envelope, or anything else).
  factory ApiException.fromResponse({int? statusCode, Object? body}) {
    if (body is Map) {
      final code = body['code'];
      final message = body['message'];
      final details = body['details'];
      return ApiException(
        code: code is String && code.isNotEmpty
            ? code
            : ApiErrorCode.badResponse,
        message: message is String ? message : '',
        fieldErrors: _parseFieldErrors(body['errors']),
        details: details is Map<String, dynamic>
            ? Map.unmodifiable(details)
            : const {},
        statusCode: statusCode,
      );
    }
    // Not our envelope: a proxy page, an HTML error, an empty body.
    return ApiException(code: ApiErrorCode.badResponse, statusCode: statusCode);
  }

  /// Machine-readable, snake_case.
  final String code;

  /// Human-readable, localised by the server. May be empty.
  final String message;

  /// Validation messages per field path (the envelope's `errors`, e.g.
  /// `email`, `invitations.2.email`).
  final Map<String, List<String>> fieldErrors;

  /// Machine-readable extras documented per endpoint, e.g.
  /// `required_amount_minor` on `offer_step_not_met`.
  final Map<String, dynamic> details;

  /// HTTP status when the server answered.
  final int? statusCode;

  bool get isUnauthenticated =>
      statusCode == 401 || code == ApiErrorCode.unauthenticated;

  bool get isValidation =>
      statusCode == 422 || code == ApiErrorCode.validationFailed;

  /// Transport failure: retrying may help.
  bool get isConnectivity =>
      code == ApiErrorCode.network || code == ApiErrorCode.timeout;

  /// First validation message for [field], if any.
  String? fieldError(String field) {
    final messages = fieldErrors[field];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  static Map<String, List<String>> _parseFieldErrors(Object? raw) {
    if (raw is! Map) return const {};
    final result = <String, List<String>>{};
    raw.forEach((key, value) {
      final messages = switch (value) {
        final List<Object?> list => list.whereType<String>().toList(),
        final String single => [single],
        _ => const <String>[],
      };
      if (messages.isNotEmpty) result['$key'] = messages;
    });
    return Map.unmodifiable(result);
  }

  @override
  String toString() =>
      'ApiException($code, status: $statusCode, message: $message)';
}
