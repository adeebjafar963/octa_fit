import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

sealed class WorkoutSessionState extends Equatable {
  const WorkoutSessionState();
  @override
  List<Object?> get props => [];
}

class WorkoutSessionLoading extends WorkoutSessionState {
  const WorkoutSessionLoading();
}

class WorkoutSessionReady extends WorkoutSessionState {
  const WorkoutSessionReady({
    required this.session,
    required this.elapsedSeconds,
    this.restRemaining,
    this.restTotal,
    this.messageKey,
  });

  final WorkoutSession session;
  final int elapsedSeconds;
  final int? restRemaining;
  final int? restTotal;
  final String? messageKey;

  bool get isResting => restRemaining != null;

  @override
  List<Object?> get props => [session, elapsedSeconds, restRemaining, restTotal, messageKey];
}

class WorkoutSessionError extends WorkoutSessionState {
  const WorkoutSessionError(this.messageKey);
  final String messageKey;
  @override
  List<Object?> get props => [messageKey];
}

class WorkoutSessionCubit extends Cubit<WorkoutSessionState> {
  WorkoutSessionCubit(this._repo) : super(const WorkoutSessionLoading());

  final TrainingRepository _repo;
  Timer? _tick;
  Timer? _restTick;
  WorkoutSession? _session;
  int? _restRemaining;
  int? _restTotal;

  Future<void> start({int? programId, List<int>? exerciseIds}) async {
    emit(const WorkoutSessionLoading());
    try {
      _session = await _repo.startSession(programId: programId, exerciseIds: exerciseIds);
      _startTick();
      _emitReady();
    } on ApiException catch (e) {
      emit(WorkoutSessionError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const WorkoutSessionError('common_error'));
    }
  }

  Future<void> load(int sessionId) async {
    emit(const WorkoutSessionLoading());
    try {
      _session = await _repo.getSession(sessionId);
      _startTick();
      _emitReady();
    } on ApiException catch (e) {
      emit(WorkoutSessionError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const WorkoutSessionError('common_error'));
    }
  }

  Future<void> pause() async {
    final s = _session;
    if (s == null) return;
    try {
      _session = await _repo.pauseSession(s.id);
      _emitReady();
    } on ApiException catch (e) {
      _emitReady(messageKey: e.messageKey ?? 'common_error');
    }
  }

  Future<void> resume() async {
    final s = _session;
    if (s == null) return;
    try {
      _session = await _repo.resumeSession(s.id);
      _emitReady();
    } on ApiException catch (e) {
      _emitReady(messageKey: e.messageKey ?? 'common_error');
    }
  }

  Future<void> completeSet({int? reps, double? weightKg, int? durationSeconds}) async {
    final s = _session;
    final active = s?.activeExercise;
    if (s == null || active == null) return;
    try {
      final nextSet = active.setsCompleted + 1;
      _session = await _repo.completeSet(
        s.id,
        sessionExerciseId: active.id,
        setNumber: nextSet,
        reps: reps ?? active.targetRepsMin,
        weightKg: weightKg,
        durationSeconds: durationSeconds ?? active.targetDurationSeconds,
      );
      final rest = active.restSeconds ?? active.roundRestSeconds;
      if (rest != null && rest > 0 && _session!.status != 'completed') {
        _beginRest(rest);
      } else {
        _emitReady();
      }
    } on ApiException catch (e) {
      _emitReady(messageKey: e.messageKey ?? 'common_error');
    }
  }

  void adjustRest(int delta) {
    if (_restRemaining == null) return;
    _restRemaining = (_restRemaining! + delta).clamp(0, 600);
    if (_restTotal != null) {
      _restTotal = (_restTotal! + delta).clamp(_restRemaining!, 600);
    }
    _emitReady();
  }

  void skipRest() {
    _restTick?.cancel();
    _restRemaining = null;
    _restTotal = null;
    _emitReady();
  }

  Future<void> completeWorkout() async {
    final s = _session;
    if (s == null) return;
    try {
      _restTick?.cancel();
      _restRemaining = null;
      _session = await _repo.completeSession(s.id);
      _emitReady();
    } on ApiException catch (e) {
      _emitReady(messageKey: e.messageKey ?? 'common_error');
    }
  }

  Future<void> cancelWorkout() async {
    final s = _session;
    if (s == null) return;
    try {
      _restTick?.cancel();
      _session = await _repo.cancelSession(s.id);
      _emitReady();
    } on ApiException catch (e) {
      _emitReady(messageKey: e.messageKey ?? 'common_error');
    }
  }

  void _beginRest(int seconds) {
    _restTick?.cancel();
    _restTotal = seconds;
    _restRemaining = seconds;
    _restTick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_restRemaining == null) return;
      if (_restRemaining! <= 1) {
        skipRest();
        return;
      }
      _restRemaining = _restRemaining! - 1;
      _emitReady();
    });
    _emitReady();
  }

  void _startTick() {
    _tick?.cancel();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => _emitReady());
  }

  void _emitReady({String? messageKey}) {
    final s = _session;
    if (s == null) return;
    emit(WorkoutSessionReady(
      session: s,
      elapsedSeconds: s.computeElapsedSeconds(),
      restRemaining: _restRemaining,
      restTotal: _restTotal,
      messageKey: messageKey,
    ));
  }

  @override
  Future<void> close() {
    _tick?.cancel();
    _restTick?.cancel();
    return super.close();
  }
}
