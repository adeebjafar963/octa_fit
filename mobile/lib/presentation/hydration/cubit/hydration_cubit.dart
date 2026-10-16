import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/hydration.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';

sealed class HydrationState extends Equatable {
  const HydrationState();
  @override
  List<Object?> get props => [];
}

class HydrationInitial extends HydrationState {
  const HydrationInitial();
}

class HydrationLoading extends HydrationState {
  const HydrationLoading();
}

class HydrationLoaded extends HydrationState {
  const HydrationLoaded(
    this.summary, {
    this.history,
    this.busy = false,
    this.errorKey,
    this.errorMessage,
    this.lastAddedMl,
  });

  final HydrationSummary summary;
  final HydrationHistory? history;
  final bool busy;
  final String? errorKey;
  final String? errorMessage;
  final int? lastAddedMl;

  HydrationLoaded copyWith({
    HydrationSummary? summary,
    HydrationHistory? history,
    bool? busy,
    String? errorKey,
    String? errorMessage,
    int? lastAddedMl,
    bool clearError = false,
    bool clearFlash = false,
  }) {
    return HydrationLoaded(
      summary ?? this.summary,
      history: history ?? this.history,
      busy: busy ?? this.busy,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      lastAddedMl: clearFlash ? null : (lastAddedMl ?? this.lastAddedMl),
    );
  }

  @override
  List<Object?> get props => [
    summary,
    history,
    busy,
    errorKey,
    errorMessage,
    lastAddedMl,
  ];
}

class HydrationError extends HydrationState {
  const HydrationError({this.messageKey, this.message});
  final String? messageKey;
  final String? message;
  @override
  List<Object?> get props => [messageKey, message];
}

class HydrationCubit extends Cubit<HydrationState> {
  HydrationCubit(this._repo) : super(const HydrationInitial());

  final HydrationRepository _repo;

  String get _tz {
    final offset = DateTime.now().timeZoneOffset;
    final totalMinutes = offset.inMinutes;
    final sign = totalMinutes >= 0 ? '+' : '-';
    final abs = totalMinutes.abs();
    final hh = (abs ~/ 60).toString().padLeft(2, '0');
    final mm = (abs % 60).toString().padLeft(2, '0');
    // Carbon accepts fixed offsets like "+03:00".
    return '$sign$hh:$mm';
  }

  Future<void> load({bool withHistory = false}) async {
    final previous = state;
    if (previous is! HydrationLoaded) {
      emit(const HydrationLoading());
    }
    try {
      final summary = await _repo.today(timezone: _tz);
      HydrationHistory? history;
      if (withHistory) {
        history = await _repo.history(days: 14, timezone: _tz);
      } else if (previous is HydrationLoaded) {
        history = previous.history;
      }
      emit(HydrationLoaded(summary, history: history));
    } on ApiException catch (e) {
      if (previous is HydrationLoaded) {
        emit(
          previous.copyWith(errorKey: e.messageKey, errorMessage: e.message),
        );
      } else {
        emit(HydrationError(messageKey: e.messageKey, message: e.message));
      }
    } catch (_) {
      if (previous is HydrationLoaded) {
        emit(previous.copyWith(errorKey: 'common_error'));
      } else {
        emit(const HydrationError(messageKey: 'common_error'));
      }
    }
  }

  Future<void> addWater(int amountMl) async {
    if (amountMl < 1 || amountMl > 5000) {
      final s = state;
      if (s is HydrationLoaded) {
        emit(s.copyWith(errorKey: 'hydration_invalid_amount'));
      }
      return;
    }

    final current = state;
    if (current is! HydrationLoaded || current.busy) return;

    final optimistic = _optimisticAdd(current.summary, amountMl);
    emit(
      current.copyWith(
        summary: optimistic,
        busy: true,
        clearError: true,
        lastAddedMl: amountMl,
      ),
    );

    try {
      final summary = await _repo.addIntake(amountMl: amountMl, timezone: _tz);
      emit(
        current.copyWith(
          summary: summary,
          busy: false,
          lastAddedMl: amountMl,
          clearError: true,
        ),
      );
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          summary: current.summary,
          busy: false,
          errorKey: e.messageKey,
          errorMessage: e.message,
          clearFlash: true,
        ),
      );
    } catch (_) {
      emit(
        current.copyWith(
          summary: current.summary,
          busy: false,
          errorKey: 'common_error',
          clearFlash: true,
        ),
      );
    }
  }

  Future<void> deleteIntake(int id) async {
    final current = state;
    if (current is! HydrationLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearError: true));
    try {
      final summary = await _repo.deleteIntake(intakeId: id, timezone: _tz);
      emit(current.copyWith(summary: summary, busy: false, clearFlash: true));
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          busy: false,
          errorKey: e.messageKey,
          errorMessage: e.message,
        ),
      );
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }

  Future<void> updateGoal(int goalMl) async {
    final current = state;
    if (current is! HydrationLoaded || current.busy) return;
    if (goalMl < 1500 || goalMl > 4000) {
      emit(current.copyWith(errorKey: 'hydration_invalid_amount'));
      return;
    }
    emit(current.copyWith(busy: true, clearError: true));
    try {
      final summary = await _repo.updateGoal(goalMl: goalMl, timezone: _tz);
      emit(current.copyWith(summary: summary, busy: false));
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          busy: false,
          errorKey: e.messageKey,
          errorMessage: e.message,
        ),
      );
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }

  Future<void> useRecommendedGoal() async {
    final current = state;
    if (current is! HydrationLoaded || current.busy) return;
    final recommended =
        current.summary.recommendedGoalMl ?? current.summary.goalMl;
    emit(current.copyWith(busy: true, clearError: true));
    try {
      final summary = await _repo.updateGoal(
        goalMl: recommended,
        timezone: _tz,
        resetToRecommended: true,
      );
      emit(current.copyWith(summary: summary, busy: false));
    } on ApiException catch (e) {
      emit(
        current.copyWith(
          busy: false,
          errorKey: e.messageKey,
          errorMessage: e.message,
        ),
      );
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }

  HydrationSummary _optimisticAdd(HydrationSummary s, int amountMl) {
    final consumed = s.consumedMl + amountMl;
    final remaining = (s.goalMl - consumed).clamp(0, s.goalMl);
    final progress = s.goalMl > 0 ? (consumed / s.goalMl).clamp(0.0, 1.0) : 0.0;
    final nowIso = DateTime.now().toIso8601String();
    final optimisticIntake = WaterIntake(
      id: -DateTime.now().millisecondsSinceEpoch,
      amountMl: amountMl,
      consumedAt: nowIso,
      consumedAtLocal: nowIso,
    );
    return HydrationSummary(
      date: s.date,
      goalMl: s.goalMl,
      consumedMl: consumed,
      remainingMl: remaining,
      progress: progress,
      intakeCount: s.intakeCount + 1,
      goalMet: consumed >= s.goalMl,
      recommendedGoalMl: s.recommendedGoalMl,
      goalIsCustom: s.goalIsCustom,
      streakDays: s.streakDays,
      timezone: s.timezone,
      intakes: [...s.intakes, optimisticIntake],
    );
  }
}
