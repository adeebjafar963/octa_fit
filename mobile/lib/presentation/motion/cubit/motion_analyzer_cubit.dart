import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/motion.dart';
import 'package:octa_fit/domain/repositories/motion_repository.dart';

sealed class MotionAnalyzerState extends Equatable {
  const MotionAnalyzerState();

  @override
  List<Object?> get props => [];
}

class MotionAnalyzerInitial extends MotionAnalyzerState {
  const MotionAnalyzerInitial();
}

class MotionAnalyzerSelecting extends MotionAnalyzerState {
  const MotionAnalyzerSelecting({
    this.exercises = MotionExercise.catalog,
    this.selectedExercise,
    this.videoPath,
    this.videoName,
    this.videoReady = false,
    this.videoStaging = false,
  });

  final List<MotionExercise> exercises;
  final String? selectedExercise;
  final String? videoPath;
  final String? videoName;
  final bool videoReady;
  final bool videoStaging;

  MotionAnalyzerSelecting copyWith({
    List<MotionExercise>? exercises,
    String? selectedExercise,
    String? videoPath,
    String? videoName,
    bool? videoReady,
    bool? videoStaging,
    bool clearVideo = false,
  }) {
    return MotionAnalyzerSelecting(
      exercises: exercises ?? this.exercises,
      selectedExercise: selectedExercise ?? this.selectedExercise,
      videoPath: clearVideo ? null : (videoPath ?? this.videoPath),
      videoName: clearVideo ? null : (videoName ?? this.videoName),
      videoReady: clearVideo ? false : (videoReady ?? this.videoReady),
      videoStaging: clearVideo ? false : (videoStaging ?? this.videoStaging),
    );
  }

  @override
  List<Object?> get props => [
        exercises,
        selectedExercise,
        videoPath,
        videoName,
        videoReady,
        videoStaging,
      ];
}

class MotionAnalyzerUploading extends MotionAnalyzerState {
  const MotionAnalyzerUploading(this.exercise, {this.videoPath});

  final String exercise;
  final String? videoPath;

  @override
  List<Object?> get props => [exercise, videoPath];
}

class MotionAnalyzerAnalyzing extends MotionAnalyzerState {
  const MotionAnalyzerAnalyzing(this.exercise, {this.videoPath});

  final String exercise;
  final String? videoPath;

  @override
  List<Object?> get props => [exercise, videoPath];
}

class MotionAnalyzerSuccess extends MotionAnalyzerState {
  const MotionAnalyzerSuccess(this.result, {this.videoPath});

  final MotionAnalysisResult result;
  final String? videoPath;

  @override
  List<Object?> get props => [result, videoPath];
}

class MotionAnalyzerFailure extends MotionAnalyzerState {
  const MotionAnalyzerFailure(
    this.messageKey, {
    this.selectedExercise,
    this.videoPath,
  });

  final String messageKey;
  final String? selectedExercise;
  final String? videoPath;

  @override
  List<Object?> get props => [messageKey, selectedExercise, videoPath];
}

class MotionAnalyzerCubit extends Cubit<MotionAnalyzerState> {
  MotionAnalyzerCubit(this._repository) : super(const MotionAnalyzerSelecting());

  final MotionRepository _repository;

  void selectExercise(String exerciseId) {
    final exists = MotionExercise.catalog.any((e) => e.id == exerciseId);
    if (!exists) return;

    final current = state;
    if (current is MotionAnalyzerSelecting) {
      emit(current.copyWith(selectedExercise: exerciseId));
    } else if (current is MotionAnalyzerFailure) {
      emit(MotionAnalyzerSelecting(
        selectedExercise: exerciseId,
        videoPath: current.videoPath,
      ));
    } else {
      emit(MotionAnalyzerSelecting(selectedExercise: exerciseId));
    }
  }

  void beginVideoStaging() {
    final current = state;
    if (current is MotionAnalyzerSelecting) {
      emit(current.copyWith(videoStaging: true, videoReady: false));
    } else if (current is MotionAnalyzerFailure) {
      emit(MotionAnalyzerSelecting(
        selectedExercise: current.selectedExercise,
        videoPath: current.videoPath,
        videoStaging: true,
      ));
    }
  }

  void setVideo({required String path, String? name}) {
    final current = state;
    if (current is MotionAnalyzerSelecting) {
      emit(current.copyWith(
        videoPath: path,
        videoName: name,
        videoReady: false,
        videoStaging: false,
      ));
    } else if (current is MotionAnalyzerFailure) {
      emit(MotionAnalyzerSelecting(
        selectedExercise: current.selectedExercise,
        videoPath: path,
        videoName: name,
        videoReady: false,
        videoStaging: false,
      ));
    } else {
      emit(MotionAnalyzerSelecting(
        videoPath: path,
        videoName: name,
        videoReady: false,
        videoStaging: false,
      ));
    }
  }

  void setVideoReady(bool ready) {
    final current = state;
    if (current is MotionAnalyzerSelecting && current.videoPath != null) {
      emit(current.copyWith(videoReady: ready, videoStaging: false));
    }
  }

  void clearVideo() {
    final current = state;
    if (current is MotionAnalyzerSelecting) {
      emit(current.copyWith(clearVideo: true));
    }
  }

  void resetToSelection() {
    final exercise = switch (state) {
      MotionAnalyzerSelecting(:final selectedExercise) => selectedExercise,
      MotionAnalyzerSuccess(:final result) => result.exercise,
      MotionAnalyzerFailure(:final selectedExercise) => selectedExercise,
      _ => null,
    };
    final videoPath = switch (state) {
      MotionAnalyzerSelecting(:final videoPath) => videoPath,
      MotionAnalyzerSuccess(:final videoPath) => videoPath,
      MotionAnalyzerFailure(:final videoPath) => videoPath,
      MotionAnalyzerUploading(:final videoPath) => videoPath,
      MotionAnalyzerAnalyzing(:final videoPath) => videoPath,
      _ => null,
    };
    emit(MotionAnalyzerSelecting(
      selectedExercise: exercise,
      videoPath: videoPath,
      videoReady: videoPath != null && videoPath.isNotEmpty,
    ));
  }

  Future<void> analyze({String? language}) async {
    final current = state;
    if (current is! MotionAnalyzerSelecting) return;

    final exercise = current.selectedExercise;
    final path = current.videoPath;
    if (exercise == null || exercise.isEmpty) {
      emit(MotionAnalyzerFailure(
        'motion_exercise_required',
        selectedExercise: exercise,
        videoPath: path,
      ));
      return;
    }
    if (path == null || path.isEmpty) {
      emit(MotionAnalyzerFailure(
        'motion_video_required',
        selectedExercise: exercise,
        videoPath: path,
      ));
      return;
    }
    if (!current.videoReady) {
      emit(MotionAnalyzerFailure(
        'motion_video_not_ready',
        selectedExercise: exercise,
        videoPath: path,
      ));
      return;
    }

    final prepared = MotionExercise.supported.any((e) => e.id == exercise);
    if (!prepared) {
      emit(MotionAnalyzerFailure(
        'motion_unable_analyze',
        selectedExercise: exercise,
        videoPath: path,
      ));
      return;
    }

    emit(MotionAnalyzerUploading(exercise, videoPath: path));
    await Future<void>.delayed(const Duration(milliseconds: 220));
    emit(MotionAnalyzerAnalyzing(exercise, videoPath: path));

    try {
      final result = await _repository.analyze(
        exercise: exercise,
        videoPath: path,
        fileName: current.videoName,
        language: language,
      );
      emit(MotionAnalyzerSuccess(result, videoPath: path));
    } on ApiException catch (e) {
      emit(MotionAnalyzerFailure(
        e.messageKey ?? 'motion_unavailable',
        selectedExercise: exercise,
        videoPath: path,
      ));
    } catch (_) {
      emit(MotionAnalyzerFailure(
        'motion_unavailable',
        selectedExercise: exercise,
        videoPath: path,
      ));
    }
  }
}
