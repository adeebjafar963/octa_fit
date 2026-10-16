import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';

sealed class CoachChatState extends Equatable {
  const CoachChatState();

  @override
  List<Object?> get props => [];
}

class CoachChatInitial extends CoachChatState {
  const CoachChatInitial();
}

class CoachChatLoading extends CoachChatState {
  const CoachChatLoading();
}

class CoachChatLoaded extends CoachChatState {
  const CoachChatLoaded({
    required this.conversation,
    required this.messages,
    this.extrasByMessageId = const {},
    this.sending = false,
    this.errorKey,
    this.lastFailedText,
  });

  final CoachConversation conversation;
  final List<CoachMessage> messages;

  /// Structured Phase 11B–11F payloads keyed by assistant message id.
  final Map<int, CoachMessageExtras> extrasByMessageId;
  final bool sending;
  final String? errorKey;
  final String? lastFailedText;

  CoachMessageExtras? extrasFor(CoachMessage message) =>
      extrasByMessageId[message.id];

  CoachChatLoaded copyWith({
    CoachConversation? conversation,
    List<CoachMessage>? messages,
    Map<int, CoachMessageExtras>? extrasByMessageId,
    bool? sending,
    String? errorKey,
    String? lastFailedText,
    bool clearError = false,
    bool clearFailedText = false,
  }) {
    return CoachChatLoaded(
      conversation: conversation ?? this.conversation,
      messages: messages ?? this.messages,
      extrasByMessageId: extrasByMessageId ?? this.extrasByMessageId,
      sending: sending ?? this.sending,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
      lastFailedText:
          clearFailedText ? null : (lastFailedText ?? this.lastFailedText),
    );
  }

  @override
  List<Object?> get props => [
        conversation,
        messages,
        extrasByMessageId,
        sending,
        errorKey,
        lastFailedText,
      ];
}

class CoachChatError extends CoachChatState {
  const CoachChatError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CoachChatCubit extends Cubit<CoachChatState> {
  CoachChatCubit(this._repository) : super(const CoachChatInitial());

  final CoachRepository _repository;
  int? _conversationId;

  Future<void> load(int conversationId) async {
    _conversationId = conversationId;
    emit(const CoachChatLoading());
    try {
      final conversation = await _repository.getConversation(conversationId);
      emit(
        CoachChatLoaded(
          conversation: conversation,
          messages: conversation.messages,
        ),
      );
    } on ApiException catch (e) {
      emit(CoachChatError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CoachChatError('common_error'));
    }
  }

  Future<void> send(String text, {String? language}) async {
    final current = state;
    final conversationId = _conversationId;
    if (current is! CoachChatLoaded ||
        current.sending ||
        conversationId == null) {
      return;
    }

    final trimmed = text.trim();
    if (trimmed.isEmpty) {
      emit(current.copyWith(errorKey: 'coach_message_required'));
      return;
    }

    final optimistic = CoachMessage(
      id: -DateTime.now().millisecondsSinceEpoch,
      conversationId: conversationId,
      role: 'user',
      content: trimmed,
    );

    emit(
      current.copyWith(
        messages: [...current.messages, optimistic],
        sending: true,
        clearError: true,
        clearFailedText: true,
      ),
    );

    try {
      final result = await _repository.sendMessage(
        conversationId: conversationId,
        message: trimmed,
        language: language,
      );
      final withoutOptimistic = current.messages
          .where((m) => m.id != optimistic.id)
          .toList();
      final extras = Map<int, CoachMessageExtras>.from(current.extrasByMessageId);
      final payload = CoachMessageExtras.fromSendResult(result);
      if (payload.hasStructured) {
        extras[result.assistantMessage.id] = payload;
      }
      emit(
        CoachChatLoaded(
          conversation: result.conversation,
          messages: [
            ...withoutOptimistic,
            result.userMessage,
            result.assistantMessage,
          ],
          extrasByMessageId: extras,
        ),
      );
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          sending: false,
          errorKey: e.messageKey ?? 'coach_unavailable',
          lastFailedText: trimmed,
        ),
      );
    } catch (_) {
      emit(
        current.copyWith(
          sending: false,
          errorKey: 'coach_unavailable',
          lastFailedText: trimmed,
        ),
      );
    }
  }

  Future<void> retryLastFailed({String? language}) async {
    final current = state;
    if (current is! CoachChatLoaded || current.lastFailedText == null) {
      return;
    }
    await send(current.lastFailedText!, language: language);
  }

  /// Clears sticky send-error UI state after the snackbar has been shown.
  void clearPresentedError() {
    final current = state;
    if (current is! CoachChatLoaded || current.errorKey == null) {
      return;
    }
    emit(current.copyWith(clearError: true));
  }
}
