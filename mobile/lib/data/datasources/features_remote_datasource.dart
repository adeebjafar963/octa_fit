import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';

class FeaturesRemoteDataSource {
  FeaturesRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<FeatureFlags> fetchFlags() async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>('/features');
      final body = response.data;
      final data = body?['data'];
      if (data is! Map) {
        return FeatureFlags.allEnabled;
      }
      return FeatureFlags(
        coachEnabled: data['coach_enabled'] != false,
        physioEnabled: data['physio_enabled'] != false,
        motionEnabled: data['motion_enabled'] != false,
      );
    } on DioException catch (e) {
      throw mapDioException(e);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Failed to load feature flags');
    }
  }
}
