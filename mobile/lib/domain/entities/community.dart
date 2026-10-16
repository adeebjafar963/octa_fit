import 'package:equatable/equatable.dart';

class CommunityAuthor extends Equatable {
  const CommunityAuthor({
    required this.id,
    required this.name,
    this.avatarUrl,
  });

  factory CommunityAuthor.fromJson(Map<String, dynamic> json) {
    return CommunityAuthor(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      avatarUrl: json['avatar_url'] as String?,
    );
  }

  final int id;
  final String name;
  final String? avatarUrl;

  @override
  List<Object?> get props => [id, name, avatarUrl];
}

class CommunityPost extends Equatable {
  const CommunityPost({
    required this.id,
    required this.content,
    required this.likeCount,
    required this.commentCount,
    required this.isLiked,
    required this.isOwner,
    this.imageUrl,
    this.author,
    this.createdAt,
    this.updatedAt,
  });

  factory CommunityPost.fromJson(Map<String, dynamic> json) {
    return CommunityPost(
      id: json['id'] as int,
      content: json['content'] as String? ?? '',
      imageUrl: json['image_url'] as String?,
      author: json['author'] is Map<String, dynamic>
          ? CommunityAuthor.fromJson(json['author'] as Map<String, dynamic>)
          : null,
      likeCount: json['like_count'] as int? ?? 0,
      commentCount: json['comment_count'] as int? ?? 0,
      isLiked: json['is_liked'] as bool? ?? false,
      isOwner: json['is_owner'] as bool? ?? false,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  final int id;
  final String content;
  final String? imageUrl;
  final CommunityAuthor? author;
  final int likeCount;
  final int commentCount;
  final bool isLiked;
  final bool isOwner;
  final String? createdAt;
  final String? updatedAt;

  CommunityPost copyWith({
    String? content,
    int? likeCount,
    int? commentCount,
    bool? isLiked,
    bool? isOwner,
    String? imageUrl,
    CommunityAuthor? author,
    String? createdAt,
    String? updatedAt,
  }) {
    return CommunityPost(
      id: id,
      content: content ?? this.content,
      imageUrl: imageUrl ?? this.imageUrl,
      author: author ?? this.author,
      likeCount: likeCount ?? this.likeCount,
      commentCount: commentCount ?? this.commentCount,
      isLiked: isLiked ?? this.isLiked,
      isOwner: isOwner ?? this.isOwner,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props =>
      [id, content, likeCount, commentCount, isLiked, isOwner, updatedAt];
}

class CommunityComment extends Equatable {
  const CommunityComment({
    required this.id,
    required this.postId,
    required this.content,
    required this.isOwner,
    this.author,
    this.createdAt,
  });

  factory CommunityComment.fromJson(Map<String, dynamic> json) {
    return CommunityComment(
      id: json['id'] as int,
      postId: json['post_id'] as int? ?? 0,
      content: json['content'] as String? ?? '',
      author: json['author'] is Map<String, dynamic>
          ? CommunityAuthor.fromJson(json['author'] as Map<String, dynamic>)
          : null,
      isOwner: json['is_owner'] as bool? ?? false,
      createdAt: json['created_at'] as String?,
    );
  }

  final int id;
  final int postId;
  final String content;
  final CommunityAuthor? author;
  final bool isOwner;
  final String? createdAt;

  @override
  List<Object?> get props => [id, postId, content, isOwner];
}

class CommunityPostPage extends Equatable {
  const CommunityPostPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<CommunityPost> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class CommunityCommentPage extends Equatable {
  const CommunityCommentPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<CommunityComment> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class LikeResult extends Equatable {
  const LikeResult({required this.liked, required this.likeCount});

  factory LikeResult.fromJson(Map<String, dynamic> json) {
    return LikeResult(
      liked: json['liked'] as bool? ?? false,
      likeCount: json['like_count'] as int? ?? 0,
    );
  }

  final bool liked;
  final int likeCount;

  @override
  List<Object?> get props => [liked, likeCount];
}

class CommunityStory extends Equatable {
  const CommunityStory({
    required this.id,
    required this.mediaUrl,
    required this.isOwner,
    this.caption,
    this.author,
    this.createdAt,
    this.expiresAt,
    this.isActive = true,
  });

  factory CommunityStory.fromJson(Map<String, dynamic> json) {
    return CommunityStory(
      id: json['id'] as int,
      mediaUrl: json['media_url'] as String? ?? '',
      caption: json['caption'] as String?,
      author: json['author'] is Map<String, dynamic>
          ? CommunityAuthor.fromJson(json['author'] as Map<String, dynamic>)
          : null,
      isOwner: json['is_owner'] as bool? ?? false,
      createdAt: json['created_at'] as String?,
      expiresAt: json['expires_at'] as String?,
      isActive: json['is_active'] as bool? ?? true,
    );
  }

  final int id;
  final String mediaUrl;
  final String? caption;
  final CommunityAuthor? author;
  final bool isOwner;
  final String? createdAt;
  final String? expiresAt;
  final bool isActive;

  @override
  List<Object?> get props => [id, mediaUrl, isOwner, expiresAt, isActive];
}
