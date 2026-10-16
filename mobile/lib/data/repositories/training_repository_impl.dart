import 'package:octa_fit/data/datasources/training_remote_datasource.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';

class TrainingRepositoryImpl implements TrainingRepository {
  TrainingRepositoryImpl(this._remote);

  final TrainingRemoteDataSource _remote;

  @override
  Future<List<TrainingDiscipline>> getDisciplines() => _remote.fetchDisciplines();

  @override
  Future<List<TrainingLevelInfo>> getLevels({String? type}) =>
      _remote.fetchLevels(type: type);

  @override
  Future<List<TrainingCategory>> getCategories({String? type, String? level}) =>
      _remote.fetchCategories(type: type, level: level);

  @override
  Future<List<TrainingProgram>> getPrograms({
    String? type,
    String? level,
    int? categoryId,
  }) =>
      _remote.fetchPrograms(type: type, level: level, categoryId: categoryId);

  @override
  Future<TrainingProgram> getProgram(int id) => _remote.fetchProgram(id);

  @override
  Future<ExercisePage> getExercises({
    String? type,
    String? level,
    String? search,
    String? difficulty,
    String? equipment,
    int? categoryId,
    int page = 1,
    int perPage = 50,
  }) =>
      _remote.fetchExercises(
        type: type,
        level: level,
        search: search,
        difficulty: difficulty,
        equipment: equipment,
        categoryId: categoryId,
        page: page,
        perPage: perPage,
      );

  @override
  Future<ExercisePage> getBodybuilding({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  }) =>
      _remote.fetchBodybuilding(
        search: search,
        level: level,
        categoryId: categoryId,
        page: page,
      );

  @override
  Future<ExercisePage> getMma({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  }) =>
      _remote.fetchMma(
        search: search,
        level: level,
        categoryId: categoryId,
        page: page,
      );

  @override
  Future<Exercise> getExercise(int id) => _remote.fetchExercise(id);

  @override
  Future<WorkoutSession> startSession({int? programId, List<int>? exerciseIds}) =>
      _remote.startSession(programId: programId, exerciseIds: exerciseIds);

  @override
  Future<WorkoutSessionPage> getSessions({int page = 1}) =>
      _remote.fetchSessions(page: page);

  @override
  Future<WorkoutSession> getSession(int id) => _remote.fetchSession(id);

  @override
  Future<WorkoutSession> pauseSession(int id) => _remote.pauseSession(id);

  @override
  Future<WorkoutSession> resumeSession(int id) => _remote.resumeSession(id);

  @override
  Future<WorkoutSession> completeSet(
    int sessionId, {
    required int sessionExerciseId,
    int? setNumber,
    int? reps,
    double? weightKg,
    int? durationSeconds,
  }) =>
      _remote.completeSet(
        sessionId,
        sessionExerciseId: sessionExerciseId,
        setNumber: setNumber,
        reps: reps,
        weightKg: weightKg,
        durationSeconds: durationSeconds,
      );

  @override
  Future<WorkoutSession> completeSession(int id) => _remote.completeSession(id);

  @override
  Future<WorkoutSession> cancelSession(int id) => _remote.cancelSession(id);
}
