import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/data/models/health_response_dto.dart';

class HealthRemoteDataSource {
  HealthRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<HealthResponseDto> fetchHealth() async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>('/health');
      final data = response.data;
      if (data == null) {
        throw ApiException('Empty health response');
      }
      return HealthResponseDto.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        e.message ?? 'Network error',
        statusCode: e.response?.statusCode,
      );
    }
  }
}
