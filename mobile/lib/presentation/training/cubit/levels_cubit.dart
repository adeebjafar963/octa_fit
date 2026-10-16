import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class LevelsState extends Equatable {
  const LevelsState();
  @override
  List<Object?> get props => [];
}

class LevelsLoading extends LevelsState {
  const LevelsLoading();
}

class LevelsLoaded extends LevelsState {
  const LevelsLoaded(this.levels);
  final List<TrainingLevelInfo> levels;
  @override
  List<Object?> get props => [levels];
}

class LevelsError extends LevelsState {
  const LevelsError(this.messageKey);
  final String messageKey;
  @override
  List<Object?> get props => [messageKey];
}

class LevelsCubit extends Cubit<LevelsState> {
  LevelsCubit(this._repo) : super(const LevelsLoading());
  final TrainingRepository _repo;

  Future<void> load(String type) async {
    emit(const LevelsLoading());
    try {
      emit(LevelsLoaded(await _repo.getLevels(type: type)));
    } on ApiException catch (e) {
      emit(LevelsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const LevelsError('common_error'));
    }
  }
}
