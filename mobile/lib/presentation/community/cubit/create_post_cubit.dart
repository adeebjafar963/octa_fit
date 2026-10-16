import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/community.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';

sealed class CreatePostState extends Equatable {
  const CreatePostState();

  @override
  List<Object?> get props => [];
}

class CreatePostInitial extends CreatePostState {
  const CreatePostInitial();
}

class CreatePostSubmitting extends CreatePostState {
  const CreatePostSubmitting();
}

class CreatePostSuccess extends CreatePostState {
  const CreatePostSuccess(this.post);

  final CommunityPost post;

  @override
  List<Object?> get props => [post];
}

class CreatePostError extends CreatePostState {
  const CreatePostError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CreatePostCubit extends Cubit<CreatePostState> {
  CreatePostCubit(this._repository) : super(const CreatePostInitial());

  final CommunityRepository _repository;

  Future<void> submit(String content, {String? imageUrl}) async {
    final trimmed = content.trim();
    if (trimmed.isEmpty && (imageUrl == null || imageUrl.isEmpty)) {
      emit(const CreatePostError('community_content_required'));
      return;
    }
    emit(const CreatePostSubmitting());
    try {
      final post = await _repository.createPost(
        content: trimmed,
        imageUrl: imageUrl,
      );
      emit(CreatePostSuccess(post));
    } on ApiException catch (e) {
      emit(CreatePostError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CreatePostError('common_error'));
    }
  }
}

sealed class EditPostState extends Equatable {
  const EditPostState();

  @override
  List<Object?> get props => [];
}

class EditPostInitial extends EditPostState {
  const EditPostInitial();
}

class EditPostLoading extends EditPostState {
  const EditPostLoading();
}

class EditPostReady extends EditPostState {
  const EditPostReady(this.post, {this.busy = false, this.errorKey});

  final CommunityPost post;
  final bool busy;
  final String? errorKey;

  EditPostReady copyWith({CommunityPost? post, bool? busy, String? errorKey, bool clearError = false}) {
    return EditPostReady(
      post ?? this.post,
      busy: busy ?? this.busy,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
    );
  }

  @override
  List<Object?> get props => [post, busy, errorKey];
}

class EditPostSuccess extends EditPostState {
  const EditPostSuccess(this.post);

  final CommunityPost post;

  @override
  List<Object?> get props => [post];
}

class EditPostError extends EditPostState {
  const EditPostError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class EditPostCubit extends Cubit<EditPostState> {
  EditPostCubit(this._repository) : super(const EditPostInitial());

  final CommunityRepository _repository;

  Future<void> load(int id) async {
    emit(const EditPostLoading());
    try {
      final post = await _repository.getPost(id);
      emit(EditPostReady(post));
    } on ApiException catch (e) {
      emit(EditPostError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const EditPostError('common_error'));
    }
  }

  Future<void> submit(String content) async {
    final current = state;
    if (current is! EditPostReady || current.busy) return;
    final trimmed = content.trim();
    if (trimmed.isEmpty) {
      emit(current.copyWith(errorKey: 'community_content_required'));
      return;
    }
    emit(current.copyWith(busy: true, clearError: true));
    try {
      final post = await _repository.updatePost(id: current.post.id, content: trimmed);
      emit(EditPostSuccess(post));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, errorKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }
}
