import 'package:octa_fit/domain/entities/training.dart';

abstract class TrainingRepository {
  Future<List<TrainingDiscipline>> getDisciplines();

  Future<List<TrainingLevelInfo>> getLevels({String? type});

  Future<List<TrainingCategory>> getCategories({String? type, String? level});

  Future<List<TrainingProgram>> getPrograms({
    String? type,
    String? level,
    int? categoryId,
  });

  Future<TrainingProgram> getProgram(int id);

  Future<ExercisePage> getExercises({
    String? type,
    String? level,
    String? search,
    String? difficulty,
    String? equipment,
    int? categoryId,
    int page = 1,
    int perPage = 50,
  });

  Future<ExercisePage> getBodybuilding({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  });

  Future<ExercisePage> getMma({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  });

  Future<Exercise> getExercise(int id);

  Future<WorkoutSession> startSession({int? programId, List<int>? exerciseIds});

  Future<WorkoutSessionPage> getSessions({int page = 1});

  Future<WorkoutSession> getSession(int id);

  Future<WorkoutSession> pauseSession(int id);

  Future<WorkoutSession> resumeSession(int id);

  Future<WorkoutSession> completeSet(
    int sessionId, {
    required int sessionExerciseId,
    int? setNumber,
    int? reps,
    double? weightKg,
    int? durationSeconds,
  });

  Future<WorkoutSession> completeSession(int id);

  Future<WorkoutSession> cancelSession(int id);
}
