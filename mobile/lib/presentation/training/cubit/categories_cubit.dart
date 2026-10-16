import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class CategoriesState extends Equatable {
  const CategoriesState();
  @override
  List<Object?> get props => [];
}

class CategoriesLoading extends CategoriesState {
  const CategoriesLoading();
}

class CategoriesLoaded extends CategoriesState {
  const CategoriesLoaded(this.categories, {this.programs = const []});
  final List<TrainingCategory> categories;
  final List<TrainingProgram> programs;
  @override
  List<Object?> get props => [categories, programs];
}

class CategoriesError extends CategoriesState {
  const CategoriesError(this.messageKey);
  final String messageKey;
  @override
  List<Object?> get props => [messageKey];
}

class CategoriesCubit extends Cubit<CategoriesState> {
  CategoriesCubit(this._repo) : super(const CategoriesLoading());
  final TrainingRepository _repo;

  Future<void> load({required String type, required String level}) async {
    emit(const CategoriesLoading());
    try {
      final cats = await _repo.getCategories(type: type, level: level);
      final programs = await _repo.getPrograms(type: type, level: level);
      emit(CategoriesLoaded(cats, programs: programs));
    } on ApiException catch (e) {
      emit(CategoriesError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CategoriesError('common_error'));
    }
  }
}
