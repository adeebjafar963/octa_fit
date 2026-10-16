import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';

sealed class CoachConversationsState extends Equatable {
  const CoachConversationsState();

  @override
  List<Object?> get props => [];
}

class CoachConversationsInitial extends CoachConversationsState {
  const CoachConversationsInitial();
}

class CoachConversationsLoading extends CoachConversationsState {
  const CoachConversationsLoading();
}

class CoachConversationsLoaded extends CoachConversationsState {
  const CoachConversationsLoaded(this.conversations, {this.feedbackKey});

  final List<CoachConversation> conversations;
  final String? feedbackKey;

  @override
  List<Object?> get props => [conversations, feedbackKey];
}

class CoachConversationsError extends CoachConversationsState {
  const CoachConversationsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CoachConversationsCubit extends Cubit<CoachConversationsState> {
  CoachConversationsCubit(this._repository)
    : super(const CoachConversationsInitial());

  final CoachRepository _repository;

  Future<void> load() async {
    emit(const CoachConversationsLoading());
    try {
      final page = await _repository.getConversations();
      emit(CoachConversationsLoaded(page.items));
    } on ApiException catch (e) {
      emit(CoachConversationsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CoachConversationsError('common_error'));
    }
  }

  Future<CoachConversation?> create({String? title}) async {
    try {
      final conversation = await _repository.createConversation(title: title);
      await load();
      return conversation;
    } on ApiException {
      rethrow;
    }
  }

  Future<void> delete(int id) async {
    final current = state;
    try {
      await _repository.deleteConversation(id);
      if (current is CoachConversationsLoaded) {
        emit(
          CoachConversationsLoaded(
            current.conversations.where((c) => c.id != id).toList(),
            feedbackKey: 'coach_conversation_deleted',
          ),
        );
      } else {
        await load();
      }
    } on ApiException catch (e) {
      if (current is CoachConversationsLoaded) {
        emit(
          CoachConversationsLoaded(
            current.conversations,
            feedbackKey: e.messageKey ?? 'common_error',
          ),
        );
      } else {
        emit(CoachConversationsError(e.messageKey ?? 'common_error'));
      }
    }
  }
}
