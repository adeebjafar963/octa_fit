import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class ExerciseDetailState extends Equatable {
  const ExerciseDetailState();

  @override
  List<Object?> get props => [];
}

class ExerciseDetailInitial extends ExerciseDetailState {
  const ExerciseDetailInitial();
}

class ExerciseDetailLoading extends ExerciseDetailState {
  const ExerciseDetailLoading();
}

class ExerciseDetailLoaded extends ExerciseDetailState {
  const ExerciseDetailLoaded(this.exercise);

  final Exercise exercise;

  @override
  List<Object?> get props => [exercise];
}

class ExerciseDetailError extends ExerciseDetailState {
  const ExerciseDetailError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class ExerciseDetailCubit extends Cubit<ExerciseDetailState> {
  ExerciseDetailCubit(this._repository) : super(const ExerciseDetailInitial());

  final TrainingRepository _repository;

  Future<void> load(int id) async {
    emit(const ExerciseDetailLoading());
    try {
      final exercise = await _repository.getExercise(id);
      emit(ExerciseDetailLoaded(exercise));
    } on ApiException catch (e) {
      emit(ExerciseDetailError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const ExerciseDetailError('common_error'));
    }
  }
}
