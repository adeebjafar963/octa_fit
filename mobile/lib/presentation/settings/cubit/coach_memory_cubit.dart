import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';

sealed class CoachMemoryState extends Equatable {
  const CoachMemoryState();

  @override
  List<Object?> get props => [];
}

class CoachMemoryInitial extends CoachMemoryState {
  const CoachMemoryInitial();
}

class CoachMemoryLoading extends CoachMemoryState {
  const CoachMemoryLoading();
}

class CoachMemoryLoaded extends CoachMemoryState {
  const CoachMemoryLoaded({
    required this.items,
    this.busy = false,
    this.errorKey,
  });

  final List<CoachMemoryItem> items;
  final bool busy;
  final String? errorKey;

  CoachMemoryLoaded copyWith({
    List<CoachMemoryItem>? items,
    bool? busy,
    String? errorKey,
    bool clearError = false,
  }) {
    return CoachMemoryLoaded(
      items: items ?? this.items,
      busy: busy ?? this.busy,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
    );
  }

  @override
  List<Object?> get props => [items, busy, errorKey];
}

class CoachMemoryError extends CoachMemoryState {
  const CoachMemoryError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CoachMemoryCubit extends Cubit<CoachMemoryState> {
  CoachMemoryCubit(this._repository) : super(const CoachMemoryInitial());

  final CoachRepository _repository;

  Future<void> load() async {
    emit(const CoachMemoryLoading());
    try {
      final page = await _repository.getMemories();
      emit(CoachMemoryLoaded(items: page.items));
    } on ApiException catch (e) {
      emit(CoachMemoryError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CoachMemoryError('common_error'));
    }
  }

  Future<void> deactivate(int id) async {
    final current = state;
    if (current is! CoachMemoryLoaded) return;
    emit(current.copyWith(busy: true, clearError: true));
    try {
      await _repository.deactivateMemory(id);
      final page = await _repository.getMemories();
      emit(CoachMemoryLoaded(items: page.items));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, errorKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }

  Future<void> clearAll() async {
    final current = state;
    if (current is! CoachMemoryLoaded) return;
    emit(current.copyWith(busy: true, clearError: true));
    try {
      await _repository.clearMemories();
      emit(const CoachMemoryLoaded(items: []));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, errorKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }
}
