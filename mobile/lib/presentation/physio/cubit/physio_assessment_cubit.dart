import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';

sealed class PhysioAssessmentState extends Equatable {
  const PhysioAssessmentState();

  @override
  List<Object?> get props => [];
}

class PhysioAssessmentInitial extends PhysioAssessmentState {
  const PhysioAssessmentInitial();
}

class PhysioAssessmentLoading extends PhysioAssessmentState {
  const PhysioAssessmentLoading();
}

class PhysioAssessmentFormState extends PhysioAssessmentState {
  const PhysioAssessmentFormState({this.submitting = false, this.errorKey});

  final bool submitting;
  final String? errorKey;

  PhysioAssessmentFormState copyWith({
    bool? submitting,
    String? errorKey,
    bool clearError = false,
  }) {
    return PhysioAssessmentFormState(
      submitting: submitting ?? this.submitting,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
    );
  }

  @override
  List<Object?> get props => [submitting, errorKey];
}

class PhysioAssessmentLoaded extends PhysioAssessmentState {
  const PhysioAssessmentLoaded({
    required this.assessment,
    required this.messages,
    this.sending = false,
    this.errorKey,
  });

  final PhysioAssessment assessment;
  final List<PhysioMessage> messages;
  final bool sending;
  final String? errorKey;

  PhysioAssessmentLoaded copyWith({
    PhysioAssessment? assessment,
    List<PhysioMessage>? messages,
    bool? sending,
    String? errorKey,
    bool clearError = false,
  }) {
    return PhysioAssessmentLoaded(
      assessment: assessment ?? this.assessment,
      messages: messages ?? this.messages,
      sending: sending ?? this.sending,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
    );
  }

  @override
  List<Object?> get props => [assessment, messages, sending, errorKey];
}

class PhysioAssessmentError extends PhysioAssessmentState {
  const PhysioAssessmentError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class PhysioAssessmentCubit extends Cubit<PhysioAssessmentState> {
  PhysioAssessmentCubit(this._repository)
    : super(const PhysioAssessmentFormState());

  final PhysioRepository _repository;
  int? _assessmentId;

  Future<void> load(int assessmentId) async {
    _assessmentId = assessmentId;
    emit(const PhysioAssessmentLoading());
    try {
      final assessment = await _repository.getAssessment(assessmentId);
      emit(
        PhysioAssessmentLoaded(
          assessment: assessment,
          messages: assessment.messages,
        ),
      );
    } on ApiException catch (e) {
      emit(PhysioAssessmentError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const PhysioAssessmentError('common_error'));
    }
  }

  Future<PhysioAssessment?> submit(PhysioCreateInput input) async {
    final current = state;
    if (current is PhysioAssessmentFormState && current.submitting) {
      return null;
    }

    emit(const PhysioAssessmentFormState(submitting: true));
    try {
      final assessment = await _repository.createAssessment(input);
      _assessmentId = assessment.id;
      emit(
        PhysioAssessmentLoaded(
          assessment: assessment,
          messages: assessment.messages,
        ),
      );
      return assessment;
    } on ApiException catch (e) {
      emit(
        PhysioAssessmentFormState(
          errorKey: e.messageKey ?? 'physio_unavailable',
        ),
      );
      return null;
    } catch (_) {
      emit(const PhysioAssessmentFormState(errorKey: 'physio_unavailable'));
      return null;
    }
  }

  Future<void> send(String text, {String? language}) async {
    final current = state;
    final assessmentId = _assessmentId;
    if (current is! PhysioAssessmentLoaded ||
        current.sending ||
        assessmentId == null) {
      return;
    }

    final trimmed = text.trim();
    if (trimmed.isEmpty) {
      emit(current.copyWith(errorKey: 'physio_message_required'));
      return;
    }

    final optimistic = PhysioMessage(
      id: -DateTime.now().millisecondsSinceEpoch,
      assessmentId: assessmentId,
      role: 'user',
      content: trimmed,
    );

    emit(
      current.copyWith(
        messages: [...current.messages, optimistic],
        sending: true,
        clearError: true,
      ),
    );

    try {
      final result = await _repository.sendMessage(
        assessmentId: assessmentId,
        message: trimmed,
        language: language,
      );
      final withoutOptimistic = current.messages
          .where((m) => m.id != optimistic.id)
          .toList();
      emit(
        PhysioAssessmentLoaded(
          assessment: result.assessment,
          messages: [
            ...withoutOptimistic,
            result.userMessage,
            result.assistantMessage,
          ],
        ),
      );
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          sending: false,
          errorKey: e.messageKey ?? 'physio_unavailable',
        ),
      );
    } catch (_) {
      emit(current.copyWith(sending: false, errorKey: 'physio_unavailable'));
    }
  }
}
