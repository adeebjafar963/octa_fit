import 'package:octa_fit/data/datasources/community_remote_datasource.dart';
import 'package:octa_fit/domain/entities/community.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';

class CommunityRepositoryImpl implements CommunityRepository {
  CommunityRepositoryImpl(this._remote);

  final CommunityRemoteDataSource _remote;

  @override
  Future<CommunityPostPage> getFeed({int page = 1, int perPage = 15}) =>
      _remote.fetchFeed(page: page, perPage: perPage);

  @override
  Future<CommunityPost> getPost(int id) => _remote.fetchPost(id);

  @override
  Future<CommunityPost> createPost({required String content, String? imageUrl}) =>
      _remote.createPost(content: content, imageUrl: imageUrl);

  @override
  Future<CommunityPost> updatePost({
    required int id,
    required String content,
    String? imageUrl,
  }) =>
      _remote.updatePost(id: id, content: content, imageUrl: imageUrl);

  @override
  Future<void> deletePost(int id) => _remote.deletePost(id);

  @override
  Future<LikeResult> likePost(int id) => _remote.likePost(id);

  @override
  Future<LikeResult> unlikePost(int id) => _remote.unlikePost(id);

  @override
  Future<CommunityCommentPage> getComments({required int postId, int page = 1}) =>
      _remote.fetchComments(postId: postId, page: page);

  @override
  Future<CommunityComment> createComment({
    required int postId,
    required String content,
  }) =>
      _remote.createComment(postId: postId, content: content);

  @override
  Future<void> deleteComment(int id) => _remote.deleteComment(id);

  @override
  Future<List<CommunityStory>> getActiveStories() => _remote.fetchActiveStories();

  @override
  Future<CommunityStory> createStory({required String mediaUrl, String? caption}) =>
      _remote.createStory(mediaUrl: mediaUrl, caption: caption);

  @override
  Future<void> deleteStory(int id) => _remote.deleteStory(id);

  @override
  Future<String> uploadMedia({required String filePath, String? fileName}) =>
      _remote.uploadMedia(filePath: filePath, fileName: fileName);
}
