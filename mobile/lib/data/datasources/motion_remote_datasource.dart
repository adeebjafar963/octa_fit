import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/motion.dart';

class MotionRemoteDataSource {
  MotionRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<List<String>> fetchExercises() async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>(
        '/motion/exercises',
      );
      final data = _unwrap(response.data) as Map<String, dynamic>;
      final items = data['items'] as List<dynamic>? ?? [];
      return items.whereType<String>().toList();
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<MotionAnalysisResult> analyze({
    required String exercise,
    required String videoPath,
    String? fileName,
    String? language,
  }) async {
    try {
      final form = FormData.fromMap({
        'exercise': exercise,
        if (language != null) 'language': language,
        'video': await MultipartFile.fromFile(
          videoPath,
          filename: fileName ?? 'exercise.mp4',
        ),
      });

      final response = await _dioClient.client.post<Map<String, dynamic>>(
        '/motion/analyze',
        data: form,
        options: Options(
          receiveTimeout: const Duration(seconds: 120),
          sendTimeout: const Duration(seconds: 120),
        ),
      );
      final data = _unwrap(response.data) as Map<String, dynamic>;
      return MotionAnalysisResult.fromJson(data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  dynamic _unwrap(Map<String, dynamic>? envelope) {
    if (envelope == null) {
      throw ApiException('Empty response', messageKey: 'common_error');
    }
    return envelope['data'];
  }
}
