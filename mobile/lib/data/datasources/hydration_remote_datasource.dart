import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/hydration.dart';

class HydrationRemoteDataSource {
  HydrationRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<HydrationSummary> fetchToday({String? timezone}) async {
    final data = await _get('/hydration/today', query: {
      if (timezone != null) 'timezone': timezone,
    });
    return HydrationSummary.fromJson(data as Map<String, dynamic>);
  }

  Future<HydrationHistory> fetchHistory({int days = 14, String? timezone}) async {
    final data = await _get('/hydration/history', query: {
      'days': days,
      if (timezone != null) 'timezone': timezone,
    });
    return HydrationHistory.fromJson(data as Map<String, dynamic>);
  }

  Future<HydrationSummary> addIntake({
    required int amountMl,
    String? timezone,
  }) async {
    final data = await _post('/hydration/intakes', {
      'amount_ml': amountMl,
      if (timezone != null) 'timezone': timezone,
    });
    final map = data as Map<String, dynamic>;
    final today = map['today'];
    if (today is Map<String, dynamic>) {
      return HydrationSummary.fromJson(today);
    }
    return fetchToday(timezone: timezone);
  }

  Future<HydrationSummary> deleteIntake({
    required int intakeId,
    String? timezone,
  }) async {
    final data = await _delete(
      '/hydration/intakes/$intakeId',
      query: {if (timezone != null) 'timezone': timezone},
    );
    final map = data as Map<String, dynamic>?;
    final today = map?['today'];
    if (today is Map<String, dynamic>) {
      return HydrationSummary.fromJson(today);
    }
    return fetchToday(timezone: timezone);
  }

  Future<HydrationSummary> updateGoal({
    required int goalMl,
    String? timezone,
    bool resetToRecommended = false,
  }) async {
    final data = await _put('/hydration/goal', {
      'goal_ml': goalMl,
      'reset_to_recommended': resetToRecommended,
      if (timezone != null) 'timezone': timezone,
    });
    final map = data as Map<String, dynamic>;
    final today = map['today'];
    if (today is Map<String, dynamic>) {
      return HydrationSummary.fromJson(today);
    }
    return fetchToday(timezone: timezone);
  }

  Future<dynamic> _get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      final response =
          await _dioClient.client.post<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _put(String path, Map<String, dynamic> body) async {
    try {
      final response =
          await _dioClient.client.put<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _delete(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dioClient.client.delete<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      return _unwrap(response.data);
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
