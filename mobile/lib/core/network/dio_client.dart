import 'package:dio/dio.dart';
import 'package:octa_fit/config/app_config.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/storage/token_storage.dart';

class DioClient {
  DioClient({
    required TokenStorage tokenStorage,
    required void Function() onUnauthorized,
  }) {
    _dio = Dio(
      BaseOptions(
        baseUrl: '${AppConfig.apiBaseUrl}${AppConfig.apiPrefix}',
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(
      AuthInterceptor(
        tokenStorage: tokenStorage,
        onUnauthorized: onUnauthorized,
      ),
    );
  }

  late final Dio _dio;

  Dio get client => _dio;
}
