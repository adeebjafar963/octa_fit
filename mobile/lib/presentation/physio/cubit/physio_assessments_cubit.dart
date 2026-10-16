import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';

sealed class PhysioAssessmentsState extends Equatable {
  const PhysioAssessmentsState();

  @override
  List<Object?> get props => [];
}

class PhysioAssessmentsInitial extends PhysioAssessmentsState {
  const PhysioAssessmentsInitial();
}

class PhysioAssessmentsLoading extends PhysioAssessmentsState {
  const PhysioAssessmentsLoading();
}

class PhysioAssessmentsLoaded extends PhysioAssessmentsState {
  const PhysioAssessmentsLoaded(this.assessments, {this.feedbackKey});

  final List<PhysioAssessment> assessments;
  final String? feedbackKey;

  @override
  List<Object?> get props => [assessments, feedbackKey];
}

class PhysioAssessmentsError extends PhysioAssessmentsState {
  const PhysioAssessmentsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class PhysioAssessmentsCubit extends Cubit<PhysioAssessmentsState> {
  PhysioAssessmentsCubit(this._repository)
    : super(const PhysioAssessmentsInitial());

  final PhysioRepository _repository;

  Future<void> load() async {
    emit(const PhysioAssessmentsLoading());
    try {
      final page = await _repository.getAssessments();
      emit(PhysioAssessmentsLoaded(page.items));
    } on ApiException catch (e) {
      emit(PhysioAssessmentsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const PhysioAssessmentsError('common_error'));
    }
  }

  Future<void> delete(int id) async {
    final current = state;
    try {
      await _repository.deleteAssessment(id);
      if (current is PhysioAssessmentsLoaded) {
        emit(
          PhysioAssessmentsLoaded(
            current.assessments.where((a) => a.id != id).toList(),
            feedbackKey: 'physio_assessment_deleted',
          ),
        );
      } else {
        await load();
      }
    } on ApiException catch (e) {
      if (current is PhysioAssessmentsLoaded) {
        emit(
          PhysioAssessmentsLoaded(
            current.assessments,
            feedbackKey: e.messageKey ?? 'common_error',
          ),
        );
      } else {
        emit(PhysioAssessmentsError(e.messageKey ?? 'common_error'));
      }
    }
  }
}
