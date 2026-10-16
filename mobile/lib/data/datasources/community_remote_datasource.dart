import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/community.dart';

class CommunityRemoteDataSource {
  CommunityRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<CommunityPostPage> fetchFeed({int page = 1, int perPage = 15}) async {
    final data = await _get('/community/posts', query: {
      'page': page,
      'per_page': perPage,
    });
    return _postPageFrom(data as Map<String, dynamic>);
  }

  Future<CommunityPost> fetchPost(int id) async {
    final data = await _get('/community/posts/$id');
    return CommunityPost.fromJson(data as Map<String, dynamic>);
  }

  Future<CommunityPost> createPost({required String content, String? imageUrl}) async {
    final body = <String, dynamic>{'content': content};
    if (imageUrl != null) body['image_url'] = imageUrl;
    final data = await _post('/community/posts', body);
    return CommunityPost.fromJson(data as Map<String, dynamic>);
  }

  Future<CommunityPost> updatePost({
    required int id,
    required String content,
    String? imageUrl,
  }) async {
    final body = <String, dynamic>{'content': content};
    if (imageUrl != null) body['image_url'] = imageUrl;
    final data = await _put('/community/posts/$id', body);
    return CommunityPost.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deletePost(int id) async {
    await _delete('/community/posts/$id');
  }

  Future<LikeResult> likePost(int id) async {
    final data = await _post('/community/posts/$id/like', {});
    return LikeResult.fromJson(data as Map<String, dynamic>);
  }

  Future<LikeResult> unlikePost(int id) async {
    final data = await _delete('/community/posts/$id/like');
    return LikeResult.fromJson(data as Map<String, dynamic>);
  }

  Future<CommunityCommentPage> fetchComments({required int postId, int page = 1}) async {
    final data = await _get('/community/posts/$postId/comments', query: {'page': page});
    return _commentPageFrom(data as Map<String, dynamic>);
  }

  Future<CommunityComment> createComment({
    required int postId,
    required String content,
  }) async {
    final data = await _post('/community/posts/$postId/comments', {'content': content});
    return CommunityComment.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deleteComment(int id) async {
    await _delete('/community/comments/$id');
  }

  Future<List<CommunityStory>> fetchActiveStories() async {
    final data = await _get('/community/stories');
    final map = data as Map<String, dynamic>;
    final items = (map['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CommunityStory.fromJson)
        .toList();
    return items;
  }

  Future<CommunityStory> createStory({required String mediaUrl, String? caption}) async {
    final body = <String, dynamic>{'media_url': mediaUrl};
    if (caption != null && caption.trim().isNotEmpty) {
      body['caption'] = caption.trim();
    }
    final data = await _post('/community/stories', body);
    return CommunityStory.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deleteStory(int id) async {
    await _delete('/community/stories/$id');
  }

  Future<String> uploadMedia({required String filePath, String? fileName}) async {
    try {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(
          filePath,
          filename: fileName ?? 'community.jpg',
        ),
      });
      final response = await _dioClient.client.post<Map<String, dynamic>>(
        '/community/media',
        data: form,
        options: Options(
          sendTimeout: const Duration(seconds: 60),
          receiveTimeout: const Duration(seconds: 60),
        ),
      );
      final data = _unwrap(response.data) as Map<String, dynamic>;
      final url = data['url'] as String?;
      if (url == null || url.isEmpty) {
        throw ApiException('Upload failed', messageKey: 'common_error');
      }
      return url;
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  CommunityPostPage _postPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CommunityPost.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return CommunityPostPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
    );
  }

  CommunityCommentPage _commentPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CommunityComment.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return CommunityCommentPage(
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

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _put(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.put<Map<String, dynamic>>(path, data: body);
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
