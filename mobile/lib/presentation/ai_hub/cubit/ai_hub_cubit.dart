import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/ai_hub.dart';
import 'package:octa_fit/domain/repositories/ai_hub_repository.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';

sealed class AiHubState extends Equatable {
  const AiHubState();
  @override
  List<Object?> get props => [];
}

class AiHubInitial extends AiHubState {
  const AiHubInitial();
}

class AiHubLoading extends AiHubState {
  const AiHubLoading();
}

class AiHubLoaded extends AiHubState {
  const AiHubLoaded(this.snapshot, {this.routing = false, this.errorKey, this.errorMessage});

  final AiHubSnapshot snapshot;
  final bool routing;
  final String? errorKey;
  final String? errorMessage;

  AiHubLoaded copyWith({
    AiHubSnapshot? snapshot,
    bool? routing,
    String? errorKey,
    String? errorMessage,
    bool clearError = false,
  }) {
    return AiHubLoaded(
      snapshot ?? this.snapshot,
      routing: routing ?? this.routing,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [snapshot, routing, errorKey, errorMessage];
}

class AiHubError extends AiHubState {
  const AiHubError({this.messageKey, this.message});
  final String? messageKey;
  final String? message;
  @override
  List<Object?> get props => [messageKey, message];
}

/// Side-effect navigation instruction after a successful route.
class AiHubNavigate {
  const AiHubNavigate({
    required this.path,
    this.pendingMessage,
    this.conversationId,
  });

  final String path;
  final String? pendingMessage;
  final int? conversationId;
}

class AiHubCubit extends Cubit<AiHubState> {
  AiHubCubit(this._aiHub, this._coach) : super(const AiHubInitial());

  final AiHubRepository _aiHub;
  final CoachRepository _coach;

  AiHubNavigate? lastNavigation;

  Future<void> load() async {
    emit(const AiHubLoading());
    try {
      final snap = await _aiHub.fetchHub();
      emit(AiHubLoaded(snap));
    } on ApiException catch (e) {
      emit(AiHubError(messageKey: e.messageKey, message: e.message));
    } catch (_) {
      emit(const AiHubError(messageKey: 'common_error'));
    }
  }

  Future<AiHubNavigate?> ask(String raw, {required String language}) async {
    final message = raw.trim();
    if (message.length < 2) return null;

    final current = state;
    if (current is! AiHubLoaded || current.routing) return null;

    emit(current.copyWith(routing: true, clearError: true));
    try {
      final decision = await _aiHub.routeMessage(
        message: message,
        language: language,
      );

      if (decision.createsConversation) {
        final conv = await _coach.createConversation(
          title: message.length > 48 ? '${message.substring(0, 48)}…' : message,
        );
        // Fire-and-forget first message; chat screen will show history including this send.
        try {
          await _coach.sendMessage(
            conversationId: conv.id,
            message: message,
            language: language,
          );
        } catch (_) {
          // Still open the chat — user can retry from there.
        }
        final nav = AiHubNavigate(
          path: '/coach/chat/${conv.id}',
          conversationId: conv.id,
          pendingMessage: message,
        );
        lastNavigation = nav;
        emit(current.copyWith(routing: false));
        return nav;
      }

      final path = switch (decision.action) {
        'open_physio_new' => '/physio/new',
        'open_motion' => '/motion',
        'open_coach' => '/coach',
        _ => decision.navigationRoute,
      };
      final nav = AiHubNavigate(path: path, pendingMessage: message);
      lastNavigation = nav;
      emit(current.copyWith(routing: false));
      return nav;
    } on ApiException catch (e) {
      emit(current.copyWith(
        routing: false,
        errorKey: e.messageKey,
        errorMessage: e.message,
      ));
      return null;
    } catch (_) {
      emit(current.copyWith(routing: false, errorKey: 'common_error'));
      return null;
    }
  }
}
