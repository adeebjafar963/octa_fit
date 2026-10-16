import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/storage/token_storage.dart';

class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required this.tokenStorage,
    required this.onUnauthorized,
  });

  final TokenStorage tokenStorage;
  final void Function() onUnauthorized;

  /// Login/register failures, and profile or password errors, must not
  /// wipe a session. Only a 401 on a protected request means the token
  /// itself is invalid.
  static bool preservesSessionOn401(RequestOptions options) {
    final path = options.path;
    return path.endsWith('/login') ||
        path.endsWith('/register') ||
        path.contains('/profile');
  }

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await tokenStorage.readToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    // Do not clear session on failed login/register credentials.
    if (err.response?.statusCode == 401 &&
        !preservesSessionOn401(err.requestOptions)) {
      onUnauthorized();
    }
    handler.next(err);
  }
}

ApiException mapDioException(DioException e) {
  final status = e.response?.statusCode;
  final data = e.response?.data;

  if (e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      e.type == DioExceptionType.receiveTimeout) {
    return ApiException(
      'Request timed out',
      statusCode: status,
      messageKey: 'error_timeout',
    );
  }

  if (e.type == DioExceptionType.connectionError) {
    return ApiException(
      'Unable to connect to the server',
      statusCode: 0,
      messageKey: 'error_server_unreachable',
    );
  }

  var message = 'Something went wrong';
  String? messageKey = 'common_error';
  Map<String, List<String>>? errors;

  if (data is Map<String, dynamic>) {
    message = (data['message'] as String?) ?? message;
    final rawErrors = data['errors'];
    if (rawErrors is Map) {
      errors = rawErrors.map((key, value) {
        if (value is List) {
          return MapEntry(key.toString(), value.map((e) => e.toString()).toList());
        }
        return MapEntry(key.toString(), [value.toString()]);
      });
    }
  }

  switch (status) {
    case 400:
      messageKey = 'error_validation';
    case 401:
      final hadBearer = e.requestOptions.headers['Authorization'] != null;
      final path = e.requestOptions.path;
      final isCredentialPath = path.endsWith('/login') || path.endsWith('/register');
      if (isCredentialPath || !hadBearer) {
        messageKey = 'error_invalid_credentials';
      } else {
        messageKey = 'error_session_expired';
      }
    case 403:
      messageKey = 'error_forbidden';
    case 404:
      messageKey = 'error_not_found';
    case 409:
      messageKey = 'error_conflict';
    case 422:
      messageKey = 'error_validation';
    case 429:
      messageKey = 'error_rate_limited';
    case 502:
    case 503:
      // Prefer Coach-specific copy on Coach routes; keep generic key elsewhere.
      final path = e.requestOptions.path;
      messageKey = path.contains('/coach/')
          ? 'coach_unavailable'
          : 'error_service_unavailable';
    default:
      if (status != null && status >= 500) {
        messageKey = 'error_server';
      }
  }

  return ApiException(
    message,
    statusCode: status,
    errors: errors,
    messageKey: messageKey,
  );
}
