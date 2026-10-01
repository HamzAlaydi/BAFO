import 'package:bafo/core/api/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final request = RequestOptions(path: '/competitions');

  DioException badResponse(int status, Object? body) => DioException(
    requestOptions: request,
    type: DioExceptionType.badResponse,
    response: Response<Object?>(
      requestOptions: request,
      statusCode: status,
      data: body,
    ),
  );

  group('ApiException.fromDioException', () {
    test('maps the validation envelope with field errors', () {
      final error = ApiException.fromDioException(
        badResponse(422, {
          'message': 'البيانات المدخلة غير صحيحة.',
          'code': 'validation_failed',
          'errors': {
            'email': ['البريد الإلكتروني مطلوب.'],
            'password': ['كلمة المرور قصيرة.', 'كلمة المرور ضعيفة.'],
          },
        }),
      );

      expect(error.code, 'validation_failed');
      expect(error.statusCode, 422);
      expect(error.message, 'البيانات المدخلة غير صحيحة.');
      expect(error.isValidation, isTrue);
      expect(error.fieldError('email'), 'البريد الإلكتروني مطلوب.');
      expect(error.fieldErrors['password'], hasLength(2));
      expect(error.fieldError('name'), isNull);
    });

    test('keeps domain codes from the server', () {
      final error = ApiException.fromDioException(
        badResponse(409, {
          'message': 'Offer must beat the leading offer.',
          'code': 'offer_not_better',
          'errors': <String, Object>{},
        }),
      );
      expect(error.code, 'offer_not_better');
      expect(error.fieldErrors, isEmpty);
      expect(error.isValidation, isFalse);
    });

    test('recognises an expired session', () {
      final error = ApiException.fromDioException(
        badResponse(401, {
          'message': 'Unauthenticated.',
          'code': 'unauthenticated',
        }),
      );
      expect(error.isUnauthenticated, isTrue);
    });

    test('maps a body that is not the envelope to bad_response', () {
      final error = ApiException.fromDioException(
        badResponse(502, '<html>Bad gateway</html>'),
      );
      expect(error.code, ApiErrorCode.badResponse);
      expect(error.statusCode, 502);
      expect(error.message, isEmpty);
    });

    test('accepts a single string as a field error', () {
      final error = ApiException.fromResponse(
        statusCode: 422,
        body: {
          'code': 'validation_failed',
          'errors': {'email': 'invalid', 'bogus': 42},
        },
      );
      expect(error.fieldErrors, {
        'email': ['invalid'],
      });
    });

    test('maps timeouts', () {
      for (final type in [
        DioExceptionType.connectionTimeout,
        DioExceptionType.sendTimeout,
        DioExceptionType.receiveTimeout,
      ]) {
        final error = ApiException.fromDioException(
          DioException(requestOptions: request, type: type),
        );
        expect(error.code, ApiErrorCode.timeout, reason: '$type');
        expect(error.isConnectivity, isTrue);
      }
    });

    test('maps connection failures to network_error', () {
      final error = ApiException.fromDioException(
        DioException.connectionError(
          requestOptions: request,
          reason: 'Connection refused',
        ),
      );
      expect(error.code, ApiErrorCode.network);
      expect(error.statusCode, isNull);
      expect(error.isConnectivity, isTrue);
    });

    test('maps cancellation', () {
      final error = ApiException.fromDioException(
        DioException(requestOptions: request, type: DioExceptionType.cancel),
      );
      expect(error.code, ApiErrorCode.cancelled);
    });

    test('passes through an ApiException raised inside Dio', () {
      const inner = ApiException(code: 'custom');
      final error = ApiException.fromDioException(
        DioException(requestOptions: request, error: inner),
      );
      expect(error, same(inner));
    });
  });
}
