import 'package:octa_fit/domain/entities/community.dart';

abstract class CommunityRepository {
  Future<CommunityPostPage> getFeed({int page = 1, int perPage = 15});

  Future<CommunityPost> getPost(int id);

  Future<CommunityPost> createPost({required String content, String? imageUrl});

  Future<CommunityPost> updatePost({
    required int id,
    required String content,
    String? imageUrl,
  });

  Future<void> deletePost(int id);

  Future<LikeResult> likePost(int id);

  Future<LikeResult> unlikePost(int id);

  Future<CommunityCommentPage> getComments({required int postId, int page = 1});

  Future<CommunityComment> createComment({
    required int postId,
    required String content,
  });

  Future<void> deleteComment(int id);

  Future<List<CommunityStory>> getActiveStories();

  Future<CommunityStory> createStory({required String mediaUrl, String? caption});

  Future<void> deleteStory(int id);

  Future<String> uploadMedia({required String filePath, String? fileName});
}
