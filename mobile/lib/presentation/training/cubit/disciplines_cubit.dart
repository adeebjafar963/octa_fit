import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class DisciplinesState extends Equatable {
  const DisciplinesState();
  @override
  List<Object?> get props => [];
}

class DisciplinesInitial extends DisciplinesState {
  const DisciplinesInitial();
}

class DisciplinesLoading extends DisciplinesState {
  const DisciplinesLoading();
}

class DisciplinesLoaded extends DisciplinesState {
  const DisciplinesLoaded(this.disciplines);
  final List<TrainingDiscipline> disciplines;
  @override
  List<Object?> get props => [disciplines];
}

class DisciplinesError extends DisciplinesState {
  const DisciplinesError(this.messageKey);
  final String messageKey;
  @override
  List<Object?> get props => [messageKey];
}

class DisciplinesCubit extends Cubit<DisciplinesState> {
  DisciplinesCubit(this._repo) : super(const DisciplinesInitial());
  final TrainingRepository _repo;

  Future<void> load() async {
    emit(const DisciplinesLoading());
    try {
      emit(DisciplinesLoaded(await _repo.getDisciplines()));
    } on ApiException catch (e) {
      emit(DisciplinesError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const DisciplinesError('common_error'));
    }
  }
}
