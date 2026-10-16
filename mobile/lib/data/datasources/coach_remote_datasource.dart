import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';

class CoachRemoteDataSource {
  CoachRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<CoachConversationPage> fetchConversations({int page = 1}) async {
    final data = await _get('/coach/conversations', query: {'page': page});
    return _pageFrom(data as Map<String, dynamic>);
  }

  Future<CoachConversation> createConversation({String? title}) async {
    final body = <String, dynamic>{};
    if (title != null && title.trim().isNotEmpty) {
      body['title'] = title.trim();
    }
    final data = await _post('/coach/conversations', body);
    return CoachConversation.fromJson(data as Map<String, dynamic>);
  }

  Future<CoachConversation> fetchConversation(int id) async {
    final data = await _get('/coach/conversations/$id');
    return CoachConversation.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deleteConversation(int id) async {
    await _delete('/coach/conversations/$id');
  }

  Future<CoachSendResult> sendMessage({
    required int conversationId,
    required String message,
    String? language,
  }) async {
    final body = <String, dynamic>{'message': message};
    if (language != null) body['language'] = language;
    final data = await _post(
      '/coach/conversations/$conversationId/messages',
      body,
      receiveTimeout: const Duration(seconds: 60),
    );
    return CoachSendResult.fromJson(data as Map<String, dynamic>);
  }

  Future<CoachMemoryPage> fetchMemories() async {
    final data = await _get('/coach/memories');
    return CoachMemoryPage.fromJson(data as Map<String, dynamic>);
  }

  Future<CoachMemoryItem> deactivateMemory(int id) async {
    final data = await _post('/coach/memories/$id/deactivate', {});
    return CoachMemoryItem.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deleteMemory(int id) async {
    await _delete('/coach/memories/$id');
  }

  Future<void> clearMemories() async {
    await _delete('/coach/memories');
  }

  CoachConversationPage _pageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CoachConversation.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return CoachConversationPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
    );
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

  Future<dynamic> _post(
    String path,
    Map<String, dynamic> body, {
    Duration? receiveTimeout,
  }) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(
        path,
        data: body,
        options: receiveTimeout == null
            ? null
            : Options(receiveTimeout: receiveTimeout, sendTimeout: receiveTimeout),
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _delete(String path) async {
    try {
      final response = await _dioClient.client.delete<Map<String, dynamic>>(path);
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
