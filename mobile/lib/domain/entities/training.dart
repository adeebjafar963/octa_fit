import 'package:equatable/equatable.dart';

class TrainingDiscipline extends Equatable {
  const TrainingDiscipline({
    required this.slug,
    required this.nameEn,
    required this.nameAr,
    required this.descriptionEn,
    required this.descriptionAr,
    required this.accent,
    required this.categoriesCount,
    required this.exercisesCount,
    required this.levels,
  });

  factory TrainingDiscipline.fromJson(Map<String, dynamic> json) {
    return TrainingDiscipline(
      slug: json['slug'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      descriptionEn: json['description_en'] as String? ?? '',
      descriptionAr: json['description_ar'] as String? ?? '',
      accent: json['accent'] as String? ?? 'primary',
      categoriesCount: json['categories_count'] as int? ?? 0,
      exercisesCount: json['exercises_count'] as int? ?? 0,
      levels: (json['levels'] as List<dynamic>? ?? []).map((e) => '$e').toList(),
    );
  }

  final String slug;
  final String nameEn;
  final String nameAr;
  final String descriptionEn;
  final String descriptionAr;
  final String accent;
  final int categoriesCount;
  final int exercisesCount;
  final List<String> levels;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  String localizedDescription(String locale) =>
      locale.startsWith('ar') ? descriptionAr : descriptionEn;

  @override
  List<Object?> get props => [slug];
}

class TrainingLevelInfo extends Equatable {
  const TrainingLevelInfo({
    required this.slug,
    required this.nameEn,
    required this.nameAr,
    required this.categoriesCount,
    required this.exercisesCount,
  });

  factory TrainingLevelInfo.fromJson(Map<String, dynamic> json) {
    return TrainingLevelInfo(
      slug: json['slug'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      categoriesCount: json['categories_count'] as int? ?? 0,
      exercisesCount: json['exercises_count'] as int? ?? 0,
    );
  }

  final String slug;
  final String nameEn;
  final String nameAr;
  final int categoriesCount;
  final int exercisesCount;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  @override
  List<Object?> get props => [slug];
}

class TrainingCategory extends Equatable {
  const TrainingCategory({
    required this.id,
    required this.nameEn,
    required this.nameAr,
    required this.slug,
    required this.type,
    this.level,
    this.descriptionEn,
    this.descriptionAr,
    this.exercisesCount,
    this.sortOrder = 0,
  });

  factory TrainingCategory.fromJson(Map<String, dynamic> json) {
    return TrainingCategory(
      id: json['id'] as int,
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      type: json['type'] as String? ?? '',
      level: json['level'] as String?,
      descriptionEn: json['description_en'] as String?,
      descriptionAr: json['description_ar'] as String?,
      exercisesCount: json['exercises_count'] as int?,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }

  final int id;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String type;
  final String? level;
  final String? descriptionEn;
  final String? descriptionAr;
  final int? exercisesCount;
  final int sortOrder;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  String? localizedDescription(String locale) =>
      locale.startsWith('ar') ? descriptionAr : descriptionEn;

  @override
  List<Object?> get props => [id, slug, type, level];
}

class ExerciseVideo extends Equatable {
  const ExerciseVideo({
    required this.id,
    required this.titleEn,
    required this.titleAr,
    required this.videoUrl,
    required this.isPlaceholder,
    this.mediaType = 'video',
    this.thumbnailUrl,
    this.durationSeconds,
  });

  factory ExerciseVideo.fromJson(Map<String, dynamic> json) {
    return ExerciseVideo(
      id: json['id'] as int,
      titleEn: json['title_en'] as String? ?? '',
      titleAr: json['title_ar'] as String? ?? '',
      videoUrl: json['video_url'] as String? ?? '',
      mediaType: json['media_type'] as String? ?? 'video',
      thumbnailUrl: json['thumbnail_url'] as String?,
      durationSeconds: json['duration_seconds'] as int?,
      isPlaceholder: json['is_placeholder'] as bool? ?? true,
    );
  }

  final int id;
  final String titleEn;
  final String titleAr;
  final String videoUrl;
  final String mediaType;
  final String? thumbnailUrl;
  final int? durationSeconds;
  final bool isPlaceholder;

  String localizedTitle(String locale) => locale.startsWith('ar') ? titleAr : titleEn;

  @override
  List<Object?> get props => [id, videoUrl, mediaType, isPlaceholder];
}

class Exercise extends Equatable {
  const Exercise({
    required this.id,
    required this.trainingCategoryId,
    required this.nameEn,
    required this.nameAr,
    required this.difficulty,
    this.slug = '',
    this.descriptionEn,
    this.descriptionAr,
    this.instructionsEn,
    this.instructionsAr,
    this.safetyNotesEn,
    this.safetyNotesAr,
    this.thumbnailUrl,
    this.target,
    this.equipment,
    this.primaryMuscles = const [],
    this.secondaryMuscles = const [],
    this.metricType = 'reps',
    this.exerciseType = 'sets_reps',
    this.evidenceNotes,
    this.sets,
    this.repsMin,
    this.repsMax,
    this.durationSeconds,
    this.restSeconds,
    this.rounds,
    this.roundSeconds,
    this.roundRestSeconds,
    this.isDemo = true,
    this.videos = const [],
    this.category,
  });

  factory Exercise.fromJson(Map<String, dynamic> json) {
    final videosJson = json['videos'];
    return Exercise(
      id: json['id'] as int,
      trainingCategoryId: json['training_category_id'] as int? ?? 0,
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      descriptionEn: json['description_en'] as String?,
      descriptionAr: json['description_ar'] as String?,
      difficulty: json['difficulty'] as String? ?? 'beginner',
      instructionsEn: json['instructions_en'] as String?,
      instructionsAr: json['instructions_ar'] as String?,
      safetyNotesEn: json['safety_notes_en'] as String?,
      safetyNotesAr: json['safety_notes_ar'] as String?,
      thumbnailUrl: json['thumbnail_url'] as String?,
      target: json['target'] as String?,
      equipment: json['equipment'] as String?,
      primaryMuscles: _stringList(json['primary_muscles']),
      secondaryMuscles: _stringList(json['secondary_muscles']),
      metricType: json['metric_type'] as String? ?? 'reps',
      exerciseType: json['exercise_type'] as String? ?? 'sets_reps',
      evidenceNotes: json['evidence_notes'] as String?,
      sets: json['sets'] as int?,
      repsMin: json['reps_min'] as int?,
      repsMax: json['reps_max'] as int?,
      durationSeconds: json['duration_seconds'] as int?,
      restSeconds: json['rest_seconds'] as int?,
      rounds: json['rounds'] as int?,
      roundSeconds: json['round_seconds'] as int?,
      roundRestSeconds: json['round_rest_seconds'] as int?,
      isDemo: json['is_demo'] as bool? ?? true,
      category: json['category'] is Map<String, dynamic>
          ? TrainingCategory.fromJson(json['category'] as Map<String, dynamic>)
          : null,
      videos: videosJson is List
          ? videosJson
              .whereType<Map<String, dynamic>>()
              .map(ExerciseVideo.fromJson)
              .toList()
          : const [],
    );
  }

  final int id;
  final int trainingCategoryId;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String? descriptionEn;
  final String? descriptionAr;
  final String difficulty;
  final String? instructionsEn;
  final String? instructionsAr;
  final String? safetyNotesEn;
  final String? safetyNotesAr;
  final String? thumbnailUrl;
  final String? target;
  final String? equipment;
  final List<String> primaryMuscles;
  final List<String> secondaryMuscles;
  final String metricType;
  final String exerciseType;
  final String? evidenceNotes;
  final int? sets;
  final int? repsMin;
  final int? repsMax;
  final int? durationSeconds;
  final int? restSeconds;
  final int? rounds;
  final int? roundSeconds;
  final int? roundRestSeconds;
  final bool isDemo;
  final List<ExerciseVideo> videos;
  final TrainingCategory? category;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  String? localizedDescription(String locale) =>
      locale.startsWith('ar') ? descriptionAr : descriptionEn;

  String? localizedInstructions(String locale) =>
      locale.startsWith('ar') ? instructionsAr : instructionsEn;

  String? localizedSafetyNotes(String locale) =>
      locale.startsWith('ar') ? safetyNotesAr : safetyNotesEn;

  ExerciseVideo? get primaryVideo => videos.isEmpty ? null : videos.first;

  String get prescriptionLabel {
    if (metricType == 'duration' && durationSeconds != null) {
      return '${durationSeconds}s';
    }
    if (metricType == 'rounds' && rounds != null) {
      return '$rounds rounds';
    }
    final s = sets ?? 0;
    if (repsMin != null && repsMax != null) {
      return '$s × $repsMin-$repsMax';
    }
    if (repsMin != null) return '$s × $repsMin';
    return s > 0 ? '$s sets' : '';
  }

  @override
  List<Object?> get props => [id, nameEn, nameAr, difficulty];
}

class TrainingProgram extends Equatable {
  const TrainingProgram({
    required this.id,
    required this.nameEn,
    required this.nameAr,
    required this.slug,
    required this.type,
    required this.level,
    this.trainingCategoryId,
    this.descriptionEn,
    this.descriptionAr,
    this.programExercisesCount,
    this.exercises = const [],
  });

  factory TrainingProgram.fromJson(Map<String, dynamic> json) {
    final ex = json['exercises'];
    return TrainingProgram(
      id: json['id'] as int,
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      type: json['type'] as String? ?? '',
      level: json['level'] as String? ?? '',
      trainingCategoryId: json['training_category_id'] as int?,
      descriptionEn: json['description_en'] as String?,
      descriptionAr: json['description_ar'] as String?,
      programExercisesCount: json['program_exercises_count'] as int?,
      exercises: ex is List
          ? ex.whereType<Map<String, dynamic>>().map(ProgramExercise.fromJson).toList()
          : const [],
    );
  }

  final int id;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String type;
  final String level;
  final int? trainingCategoryId;
  final String? descriptionEn;
  final String? descriptionAr;
  final int? programExercisesCount;
  final List<ProgramExercise> exercises;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  @override
  List<Object?> get props => [id, slug];
}

class ProgramExercise extends Equatable {
  const ProgramExercise({
    required this.id,
    required this.sortOrder,
    this.targetSets,
    this.targetRepsMin,
    this.targetRepsMax,
    this.targetDurationSeconds,
    this.restSeconds,
    this.exercise,
  });

  factory ProgramExercise.fromJson(Map<String, dynamic> json) {
    return ProgramExercise(
      id: json['id'] as int,
      sortOrder: json['sort_order'] as int? ?? 0,
      targetSets: json['target_sets'] as int?,
      targetRepsMin: json['target_reps_min'] as int?,
      targetRepsMax: json['target_reps_max'] as int?,
      targetDurationSeconds: json['target_duration_seconds'] as int?,
      restSeconds: json['rest_seconds'] as int?,
      exercise: json['exercise'] is Map<String, dynamic>
          ? Exercise.fromJson(json['exercise'] as Map<String, dynamic>)
          : null,
    );
  }

  final int id;
  final int sortOrder;
  final int? targetSets;
  final int? targetRepsMin;
  final int? targetRepsMax;
  final int? targetDurationSeconds;
  final int? restSeconds;
  final Exercise? exercise;

  @override
  List<Object?> get props => [id];
}

class WorkoutSetLog extends Equatable {
  const WorkoutSetLog({
    required this.id,
    required this.setNumber,
    this.reps,
    this.weightKg,
    this.durationSeconds,
    this.isWarmup = false,
    this.completedAt,
  });

  factory WorkoutSetLog.fromJson(Map<String, dynamic> json) {
    return WorkoutSetLog(
      id: json['id'] as int,
      setNumber: json['set_number'] as int? ?? 0,
      reps: json['reps'] as int?,
      weightKg: (json['weight_kg'] as num?)?.toDouble(),
      durationSeconds: json['duration_seconds'] as int?,
      isWarmup: json['is_warmup'] as bool? ?? false,
      completedAt: json['completed_at'] as String?,
    );
  }

  final int id;
  final int setNumber;
  final int? reps;
  final double? weightKg;
  final int? durationSeconds;
  final bool isWarmup;
  final String? completedAt;

  @override
  List<Object?> get props => [id, setNumber];
}

class SessionExercise extends Equatable {
  const SessionExercise({
    required this.id,
    required this.exerciseId,
    required this.sortOrder,
    required this.status,
    this.targetSets,
    this.targetRepsMin,
    this.targetRepsMax,
    this.targetDurationSeconds,
    this.restSeconds,
    this.rounds,
    this.roundSeconds,
    this.roundRestSeconds,
    this.setsCompleted = 0,
    this.exercise,
    this.sets = const [],
  });

  factory SessionExercise.fromJson(Map<String, dynamic> json) {
    return SessionExercise(
      id: json['id'] as int,
      exerciseId: json['exercise_id'] as int? ?? 0,
      sortOrder: json['sort_order'] as int? ?? 0,
      status: json['status'] as String? ?? 'pending',
      targetSets: json['target_sets'] as int?,
      targetRepsMin: json['target_reps_min'] as int?,
      targetRepsMax: json['target_reps_max'] as int?,
      targetDurationSeconds: json['target_duration_seconds'] as int?,
      restSeconds: json['rest_seconds'] as int?,
      rounds: json['rounds'] as int?,
      roundSeconds: json['round_seconds'] as int?,
      roundRestSeconds: json['round_rest_seconds'] as int?,
      setsCompleted: json['sets_completed'] as int? ?? 0,
      exercise: json['exercise'] is Map<String, dynamic>
          ? Exercise.fromJson(json['exercise'] as Map<String, dynamic>)
          : null,
      sets: (json['sets'] as List<dynamic>? ?? [])
          .whereType<Map<String, dynamic>>()
          .map(WorkoutSetLog.fromJson)
          .toList(),
    );
  }

  final int id;
  final int exerciseId;
  final int sortOrder;
  final String status;
  final int? targetSets;
  final int? targetRepsMin;
  final int? targetRepsMax;
  final int? targetDurationSeconds;
  final int? restSeconds;
  final int? rounds;
  final int? roundSeconds;
  final int? roundRestSeconds;
  final int setsCompleted;
  final Exercise? exercise;
  final List<WorkoutSetLog> sets;

  int get targetSetCount => targetSets ?? rounds ?? 1;

  @override
  List<Object?> get props => [id, status, setsCompleted];
}

class WorkoutSession extends Equatable {
  const WorkoutSession({
    required this.id,
    required this.status,
    this.trainingProgramId,
    this.type,
    this.level,
    this.titleEn,
    this.titleAr,
    this.startedAt,
    this.pausedAt,
    this.pausedTotalSeconds = 0,
    this.completedAt,
    this.durationSeconds,
    this.elapsedSeconds = 0,
    this.exercisesCompleted = 0,
    this.setsCompleted = 0,
    this.totalReps = 0,
    this.sessionExercises = const [],
  });

  factory WorkoutSession.fromJson(Map<String, dynamic> json) {
    return WorkoutSession(
      id: json['id'] as int,
      trainingProgramId: json['training_program_id'] as int?,
      type: json['type'] as String?,
      level: json['level'] as String?,
      titleEn: json['title_en'] as String?,
      titleAr: json['title_ar'] as String?,
      status: json['status'] as String? ?? 'active',
      startedAt: json['started_at'] as String?,
      pausedAt: json['paused_at'] as String?,
      pausedTotalSeconds: json['paused_total_seconds'] as int? ?? 0,
      completedAt: json['completed_at'] as String?,
      durationSeconds: json['duration_seconds'] as int?,
      elapsedSeconds: json['elapsed_seconds'] as int? ?? 0,
      exercisesCompleted: json['exercises_completed'] as int? ?? 0,
      setsCompleted: json['sets_completed'] as int? ?? 0,
      totalReps: json['total_reps'] as int? ?? 0,
      sessionExercises: (json['session_exercises'] as List<dynamic>? ?? [])
          .whereType<Map<String, dynamic>>()
          .map(SessionExercise.fromJson)
          .toList(),
    );
  }

  final int id;
  final int? trainingProgramId;
  final String? type;
  final String? level;
  final String? titleEn;
  final String? titleAr;
  final String status;
  final String? startedAt;
  final String? pausedAt;
  final int pausedTotalSeconds;
  final String? completedAt;
  final int? durationSeconds;
  final int elapsedSeconds;
  final int exercisesCompleted;
  final int setsCompleted;
  final int totalReps;
  final List<SessionExercise> sessionExercises;

  String localizedTitle(String locale) =>
      locale.startsWith('ar') ? (titleAr ?? titleEn ?? '') : (titleEn ?? titleAr ?? '');

  SessionExercise? get activeExercise {
    for (final e in sessionExercises) {
      if (e.status == 'active') return e;
    }
    for (final e in sessionExercises) {
      if (e.status == 'pending') return e;
    }
    return null;
  }

  int computeElapsedSeconds({DateTime? now}) {
    if (status == 'completed' || status == 'cancelled') {
      return durationSeconds ?? elapsedSeconds;
    }
    if (startedAt == null) return elapsedSeconds;
    final start = DateTime.tryParse(startedAt!);
    if (start == null) return elapsedSeconds;
    final n = now ?? DateTime.now().toUtc();
    var paused = pausedTotalSeconds;
    if (status == 'paused' && pausedAt != null) {
      final p = DateTime.tryParse(pausedAt!);
      if (p != null) {
        paused += n.difference(p.toUtc()).inSeconds.clamp(0, 86400);
      }
    }
    return n.difference(start.toUtc()).inSeconds.clamp(0, 86400) - paused;
  }

  @override
  List<Object?> get props => [id, status, setsCompleted, exercisesCompleted];
}

class ExercisePage extends Equatable {
  const ExercisePage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<Exercise> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class WorkoutSessionPage extends Equatable {
  const WorkoutSessionPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<WorkoutSession> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

List<String> _stringList(dynamic value) {
  if (value is! List) return const [];
  return value.map((e) => '$e').where((e) => e.isNotEmpty).toList();
}
