import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/community.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';

sealed class PostDetailState extends Equatable {
  const PostDetailState();

  @override
  List<Object?> get props => [];
}

class PostDetailInitial extends PostDetailState {
  const PostDetailInitial();
}

class PostDetailLoading extends PostDetailState {
  const PostDetailLoading();
}

class PostDetailLoaded extends PostDetailState {
  const PostDetailLoaded({
    required this.post,
    this.busy = false,
    this.feedbackKey,
  });

  final CommunityPost post;
  final bool busy;
  final String? feedbackKey;

  PostDetailLoaded copyWith({
    CommunityPost? post,
    bool? busy,
    String? feedbackKey,
    bool clearFeedback = false,
  }) {
    return PostDetailLoaded(
      post: post ?? this.post,
      busy: busy ?? this.busy,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [post, busy, feedbackKey];
}

class PostDetailError extends PostDetailState {
  const PostDetailError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class PostDetailDeleted extends PostDetailState {
  const PostDetailDeleted();
}

class PostDetailCubit extends Cubit<PostDetailState> {
  PostDetailCubit(this._repository) : super(const PostDetailInitial());

  final CommunityRepository _repository;

  Future<void> load(int postId) async {
    emit(const PostDetailLoading());
    try {
      final post = await _repository.getPost(postId);
      emit(PostDetailLoaded(post: post));
    } on ApiException catch (e) {
      emit(PostDetailError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const PostDetailError('common_error'));
    }
  }

  Future<void> toggleLike() async {
    final current = state;
    if (current is! PostDetailLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      final result = current.post.isLiked
          ? await _repository.unlikePost(current.post.id)
          : await _repository.likePost(current.post.id);
      emit(current.copyWith(
        busy: false,
        post: current.post.copyWith(
          isLiked: result.liked,
          likeCount: result.likeCount,
        ),
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }

  Future<void> deletePost() async {
    final current = state;
    if (current is! PostDetailLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      await _repository.deletePost(current.post.id);
      emit(const PostDetailDeleted());
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }
}
