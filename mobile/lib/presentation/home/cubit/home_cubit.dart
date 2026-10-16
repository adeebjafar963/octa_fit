import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/health_status.dart';
import 'package:octa_fit/domain/repositories/health_repository.dart';

sealed class HomeState extends Equatable {
  const HomeState();

  @override
  List<Object?> get props => [];
}

class HomeInitial extends HomeState {
  const HomeInitial();
}

class HomeLoading extends HomeState {
  const HomeLoading();
}

class HomeLoaded extends HomeState {
  const HomeLoaded(this.health);

  final HealthStatus health;

  @override
  List<Object?> get props => [health];
}

class HomeError extends HomeState {
  const HomeError(this.message);

  final String message;

  @override
  List<Object?> get props => [message];
}

class HomeCubit extends Cubit<HomeState> {
  HomeCubit(this._repository) : super(const HomeInitial());

  final HealthRepository _repository;

  Future<void> load() async {
    emit(const HomeLoading());
    try {
      final health = await _repository.checkHealth();
      emit(HomeLoaded(health));
    } on ApiException catch (e) {
      emit(HomeError(e.message));
    } catch (_) {
      emit(const HomeError('common_error'));
    }
  }
}
