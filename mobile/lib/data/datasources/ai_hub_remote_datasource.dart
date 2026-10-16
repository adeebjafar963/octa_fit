import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/ai_hub.dart';

class AiHubRemoteDataSource {
  AiHubRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<AiHubSnapshot> fetchHub() async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>('/ai/hub');
      final data = _unwrap(response.data);
      if (data is! Map<String, dynamic>) {
        throw ApiException('Invalid AI hub response');
      }
      return AiHubSnapshot.fromJson(data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<AiRouteDecision> routeMessage({
    required String message,
    String? language,
  }) async {
    try {
      final body = <String, dynamic>{'message': message};
      if (language != null) body['language'] = language;
      final response = await _dioClient.client.post<Map<String, dynamic>>(
        '/ai/route',
        data: body,
      );
      final data = _unwrap(response.data);
      if (data is! Map<String, dynamic>) {
        throw ApiException('Invalid AI route response');
      }
      return AiRouteDecision.fromJson(data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  dynamic _unwrap(Map<String, dynamic>? body) {
    if (body == null) {
      throw ApiException('Empty response', messageKey: 'common_error');
    }
    return body['data'];
  }
}
