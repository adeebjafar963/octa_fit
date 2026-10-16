import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';

void main() {
  group('mapDioException', () {
    test('maps 401 with bearer to session expired', () {
      final e = DioException(
        requestOptions: RequestOptions(
          path: '/me',
          headers: {'Authorization': 'Bearer x'},
        ),
        response: Response(
          requestOptions: RequestOptions(path: '/me'),
          statusCode: 401,
          data: {'message': 'Unauthenticated'},
        ),
        type: DioExceptionType.badResponse,
      );
      final mapped = mapDioException(e);
      expect(mapped.messageKey, 'error_session_expired');
    });

    test('maps login 401 to invalid credentials', () {
      final e = DioException(
        requestOptions: RequestOptions(path: '/login'),
        response: Response(
          requestOptions: RequestOptions(path: '/login'),
          statusCode: 401,
          data: {'message': 'Invalid'},
        ),
        type: DioExceptionType.badResponse,
      );
      final mapped = mapDioException(e);
      expect(mapped.messageKey, 'error_invalid_credentials');
    });

    test('maps 429 rate limit', () {
      final e = DioException(
        requestOptions: RequestOptions(path: '/coach/conversations/1/messages'),
        response: Response(
          requestOptions: RequestOptions(path: '/x'),
          statusCode: 429,
          data: {'message': 'Too Many'},
        ),
        type: DioExceptionType.badResponse,
      );
      expect(mapDioException(e).messageKey, 'error_rate_limited');
    });

    test('maps 403 and 404', () {
      DioException make(int code) => DioException(
            requestOptions: RequestOptions(path: '/x'),
            response: Response(
              requestOptions: RequestOptions(path: '/x'),
              statusCode: code,
              data: {'message': 'x'},
            ),
            type: DioExceptionType.badResponse,
          );
      expect(mapDioException(make(403)).messageKey, 'error_forbidden');
      expect(mapDioException(make(404)).messageKey, 'error_not_found');
      expect(mapDioException(make(409)).messageKey, 'error_conflict');
      expect(mapDioException(make(500)).messageKey, 'error_server');
      expect(mapDioException(make(503)).messageKey, 'error_service_unavailable');
    });

    test('maps coach 502/503 to coach_unavailable', () {
      DioException make(int code) => DioException(
            requestOptions: RequestOptions(path: '/coach/conversations/1/messages'),
            response: Response(
              requestOptions:
                  RequestOptions(path: '/coach/conversations/1/messages'),
              statusCode: code,
              data: {'message': 'Unable to reach the AI Coach service.'},
            ),
            type: DioExceptionType.badResponse,
          );
      expect(mapDioException(make(502)).messageKey, 'coach_unavailable');
      expect(mapDioException(make(503)).messageKey, 'coach_unavailable');
    });

    test('maps connection failure to server unreachable', () {
      final e = DioException(
        requestOptions: RequestOptions(path: '/login'),
        type: DioExceptionType.connectionError,
        message: 'Connection refused',
      );
      expect(mapDioException(e).messageKey, 'error_server_unreachable');
    });

    test('maps 422 validation errors through', () {
      final e = DioException(
        requestOptions: RequestOptions(path: '/register'),
        response: Response(
          requestOptions: RequestOptions(path: '/register'),
          statusCode: 422,
          data: {
            'message': 'Validation failed',
            'errors': {
              'email': ['The email has already been taken.'],
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
      final mapped = mapDioException(e);
      expect(mapped.messageKey, 'error_validation');
      expect(mapped.errors?['email']?.first, 'The email has already been taken.');
    });

    test('profile and password 401 do not count as a dead session', () {
      expect(
        AuthInterceptor.preservesSessionOn401(RequestOptions(path: '/profile')),
        isTrue,
      );
      expect(
        AuthInterceptor.preservesSessionOn401(RequestOptions(path: '/profile/password')),
        isTrue,
      );
      expect(
        AuthInterceptor.preservesSessionOn401(RequestOptions(path: '/login')),
        isTrue,
      );
      expect(
        AuthInterceptor.preservesSessionOn401(RequestOptions(path: '/me')),
        isFalse,
      );
    });
  });
}
