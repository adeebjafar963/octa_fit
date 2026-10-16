import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/community.dart';
import 'package:octa_fit/domain/entities/friendship.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';

sealed class CommunityFeedState extends Equatable {
  const CommunityFeedState();

  @override
  List<Object?> get props => [];
}

class CommunityFeedInitial extends CommunityFeedState {
  const CommunityFeedInitial();
}

class CommunityFeedLoading extends CommunityFeedState {
  const CommunityFeedLoading();
}

class CommunityFeedLoaded extends CommunityFeedState {
  const CommunityFeedLoaded({
    required this.posts,
    required this.stories,
    required this.currentPage,
    required this.hasMore,
    this.pendingFriendRequests = 0,
    this.friendCount = 0,
    this.loadingMore = false,
    this.feedbackKey,
  });

  final List<CommunityPost> posts;
  final List<CommunityStory> stories;
  final int currentPage;
  final bool hasMore;
  final int pendingFriendRequests;
  final int friendCount;
  final bool loadingMore;
  final String? feedbackKey;

  CommunityFeedLoaded copyWith({
    List<CommunityPost>? posts,
    List<CommunityStory>? stories,
    int? currentPage,
    bool? hasMore,
    int? pendingFriendRequests,
    int? friendCount,
    bool? loadingMore,
    String? feedbackKey,
    bool clearFeedback = false,
  }) {
    return CommunityFeedLoaded(
      posts: posts ?? this.posts,
      stories: stories ?? this.stories,
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
      pendingFriendRequests: pendingFriendRequests ?? this.pendingFriendRequests,
      friendCount: friendCount ?? this.friendCount,
      loadingMore: loadingMore ?? this.loadingMore,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [
        posts,
        stories,
        currentPage,
        hasMore,
        pendingFriendRequests,
        friendCount,
        loadingMore,
        feedbackKey,
      ];
}

class CommunityFeedError extends CommunityFeedState {
  const CommunityFeedError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CommunityFeedCubit extends Cubit<CommunityFeedState> {
  CommunityFeedCubit(this._repository, this._friendshipRepository)
      : super(const CommunityFeedInitial());

  final CommunityRepository _repository;
  final FriendshipRepository _friendshipRepository;

  Future<void> load() async {
    emit(const CommunityFeedLoading());
    try {
      final results = await Future.wait([
        _repository.getFeed(perPage: 15),
        _repository.getActiveStories(),
        _friendshipRepository.getFriends(page: 1),
      ]);
      final page = results[0] as CommunityPostPage;
      final stories = results[1] as List<CommunityStory>;
      final friends = results[2] as CommunityUserPage;
      emit(CommunityFeedLoaded(
        posts: page.items,
        stories: stories,
        currentPage: page.currentPage,
        hasMore: page.hasMore,
        pendingFriendRequests: friends.pendingIncomingCount,
        friendCount: friends.total,
      ));
    } on ApiException catch (e) {
      emit(CommunityFeedError(e.messageKey ?? 'community_load_error'));
    } catch (_) {
      emit(const CommunityFeedError('community_load_error'));
    }
  }

  Future<void> refresh() => load();

  Future<void> loadMore() async {
    final current = state;
    if (current is! CommunityFeedLoaded || current.loadingMore || !current.hasMore) {
      return;
    }
    emit(current.copyWith(loadingMore: true, clearFeedback: true));
    try {
      final page = await _repository.getFeed(page: current.currentPage + 1);
      emit(CommunityFeedLoaded(
        posts: [...current.posts, ...page.items],
        stories: current.stories,
        currentPage: page.currentPage,
        hasMore: page.hasMore,
        pendingFriendRequests: current.pendingFriendRequests,
        friendCount: current.friendCount,
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(
        loadingMore: false,
        feedbackKey: e.messageKey ?? 'common_error',
      ));
    } catch (_) {
      emit(current.copyWith(loadingMore: false, feedbackKey: 'common_error'));
    }
  }

  Future<void> toggleLike(int postId) async {
    final current = state;
    if (current is! CommunityFeedLoaded) return;
    final index = current.posts.indexWhere((p) => p.id == postId);
    if (index < 0) return;
    final post = current.posts[index];

    try {
      final result = post.isLiked
          ? await _repository.unlikePost(postId)
          : await _repository.likePost(postId);
      final updated = List<CommunityPost>.from(current.posts);
      updated[index] = post.copyWith(
        isLiked: result.liked,
        likeCount: result.likeCount,
      );
      emit(current.copyWith(posts: updated, clearFeedback: true));
    } on ApiException catch (e) {
      emit(current.copyWith(feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(feedbackKey: 'common_error'));
    }
  }

  Future<void> deletePost(int postId) async {
    final current = state;
    if (current is! CommunityFeedLoaded) return;
    try {
      await _repository.deletePost(postId);
      emit(current.copyWith(
        posts: current.posts.where((p) => p.id != postId).toList(),
        feedbackKey: 'community_post_deleted',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(feedbackKey: 'common_error'));
    }
  }
}
