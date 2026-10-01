import 'dart:math';

import 'package:bafo/core/storage/token_store.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:dio/dio.dart';

/// Adds `Authorization: Bearer <token>` and reports rejected tokens.
///
/// [onUnauthorized] fires when a request that carried a token gets a 401, so
/// the session can end (the token was revoked or expired). Requests without a
/// token (login) are left alone: their 401 is a normal validation outcome.
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._tokens, {required this._onUnauthorized});

  static const String _sentTokenKey = 'bafo.sentToken';

  final TokenStore _tokens;
  final void Function() _onUnauthorized;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokens.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
      options.extra[_sentTokenKey] = true;
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401 &&
        err.requestOptions.extra[_sentTokenKey] == true) {
      _onUnauthorized();
    }
    handler.next(err);
  }
}

/// Sends `Accept-Language` (ar|en) from the current app language so the API
/// localises messages and validation errors.
class LocaleInterceptor extends Interceptor {
  LocaleInterceptor(this._languageCode);

  final String Function() _languageCode;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept-Language'] = _languageCode();
    handler.next(options);
  }
}

/// Keeps [ServerClock] in sync from `server_time` in API responses.
///
/// Looks in `meta.server_time`, then `data.server_time`, then a top-level
/// `server_time` (competition payloads carry it; see BRIEF "Time").
class ServerTimeInterceptor extends Interceptor {
  ServerTimeInterceptor(this._clock, {DateTime Function()? deviceNow})
    : _deviceNow = deviceNow ?? DateTime.now;

  static const String _sentAtKey = 'bafo.sentAt';

  final ServerClock _clock;
  final DateTime Function() _deviceNow;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.extra[_sentAtKey] = _deviceNow();
    handler.next(options);
  }

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    final serverTime = extractServerTime(response.data);
    if (serverTime != null) {
      final sentAt = response.requestOptions.extra[_sentAtKey];
      _clock.syncFromIso(
        serverTime,
        requestSentAt: sentAt is DateTime ? sentAt : null,
        responseReceivedAt: _deviceNow(),
      );
    }
    handler.next(response);
  }

  /// The `server_time` string in [body], if present.
  static String? extractServerTime(Object? body) {
    if (body is! Map) return null;
    for (final holder in [body['meta'], body['data'], body]) {
      if (holder is Map) {
        final value = holder['server_time'];
        if (value is String) return value;
      }
    }
    return null;
  }
}

/// Tags every request with `X-Request-Id` (32 hex chars) so a failure the
/// user reports can be found in the server logs; the server echoes it back.
class RequestIdInterceptor extends Interceptor {
  RequestIdInterceptor([Random? random]) : _random = random ?? Random.secure();

  final Random _random;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers.putIfAbsent('X-Request-Id', _next);
    handler.next(options);
  }

  String _next() => List.generate(
    16,
    (_) => _random.nextInt(256).toRadixString(16).padLeft(2, '0'),
  ).join();
}
