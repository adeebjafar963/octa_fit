import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class TrainingListState extends Equatable {
  const TrainingListState();

  @override
  List<Object?> get props => [];
}

class TrainingListInitial extends TrainingListState {
  const TrainingListInitial();
}

class TrainingListLoading extends TrainingListState {
  const TrainingListLoading();
}

class TrainingListLoaded extends TrainingListState {
  const TrainingListLoaded(this.page, {this.search = ''});

  final ExercisePage page;
  final String search;

  @override
  List<Object?> get props => [page, search];
}

class TrainingListError extends TrainingListState {
  const TrainingListError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

enum TrainingSport { bodybuilding, mma, homeCalisthenics }

extension TrainingSportX on TrainingSport {
  String get apiType => switch (this) {
        TrainingSport.bodybuilding => 'bodybuilding',
        TrainingSport.mma => 'mma',
        TrainingSport.homeCalisthenics => 'home_calisthenics',
      };

  static TrainingSport fromType(String type) {
    return switch (type) {
      'mma' => TrainingSport.mma,
      'home_calisthenics' => TrainingSport.homeCalisthenics,
      _ => TrainingSport.bodybuilding,
    };
  }
}

class TrainingListCubit extends Cubit<TrainingListState> {
  TrainingListCubit(
    this._repository, {
    required this.type,
    this.level,
    this.categoryId,
  }) : super(const TrainingListInitial());

  final TrainingRepository _repository;
  final String type;
  final String? level;
  final int? categoryId;

  Future<void> load({String search = '', String? equipment}) async {
    emit(const TrainingListLoading());
    try {
      final page = await _repository.getExercises(
        type: type,
        level: level,
        categoryId: categoryId,
        search: search,
        equipment: equipment,
        perPage: 50,
      );
      emit(TrainingListLoaded(page, search: search));
    } on ApiException catch (e) {
      emit(TrainingListError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const TrainingListError('common_error'));
    }
  }
}
