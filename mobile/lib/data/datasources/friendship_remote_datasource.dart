import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/friendship.dart';

class FriendshipRemoteDataSource {
  FriendshipRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<CommunityUserPage> searchUsers({required String query, int page = 1}) async {
    final data = await _get('/friends/search', query: {
      'q': query,
      'page': page,
      'per_page': 20,
    });
    return _userPageFrom(data as Map<String, dynamic>);
  }

  Future<CommunityUserPage> fetchFriends({int page = 1}) async {
    final data = await _get('/friends', query: {'page': page, 'per_page': 20});
    return _userPageFrom(data as Map<String, dynamic>);
  }

  Future<FriendRequestPage> fetchIncoming({int page = 1}) async {
    final data = await _get('/friends/requests', query: {'page': page, 'per_page': 20});
    return _requestPageFrom(data as Map<String, dynamic>);
  }

  Future<CommunityUser> fetchPublicUser(int id) async {
    final data = await _get('/users/$id/public');
    return CommunityUser.fromJson(data as Map<String, dynamic>);
  }

  Future<void> sendRequest(int userId) async {
    await _post('/friends/requests', {'user_id': userId});
  }

  Future<CommunityUser?> acceptRequest(int requestId) async {
    final data = await _post('/friends/requests/$requestId/accept', {});
    if (data is Map<String, dynamic>) {
      return CommunityUser.fromJson(data);
    }
    return null;
  }

  Future<void> rejectRequest(int requestId) async {
    await _post('/friends/requests/$requestId/reject', {});
  }

  Future<void> removeFriend(int userId) async {
    await _delete('/friends/$userId');
  }

  CommunityUserPage _userPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CommunityUser.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return CommunityUserPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
      pendingIncomingCount: data['pending_incoming_count'] as int? ?? 0,
    );
  }

  FriendRequestPage _requestPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(FriendRequestItem.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return FriendRequestPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
      pendingIncomingCount: data['pending_incoming_count'] as int? ?? meta['total'] as int? ?? 0,
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

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(path, data: body);
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
