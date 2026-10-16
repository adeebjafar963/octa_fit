import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/training.dart';

class TrainingRemoteDataSource {
  TrainingRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<List<TrainingDiscipline>> fetchDisciplines() async {
    final data = await _get('/training/disciplines');
    final list = data as List<dynamic>? ?? [];
    return list
        .whereType<Map<String, dynamic>>()
        .map(TrainingDiscipline.fromJson)
        .toList();
  }

  Future<List<TrainingLevelInfo>> fetchLevels({String? type}) async {
    final query = <String, dynamic>{};
    if (type != null) query['type'] = type;
    final data = await _get('/training/levels', query: query);
    final list = data as List<dynamic>? ?? [];
    return list
        .whereType<Map<String, dynamic>>()
        .map(TrainingLevelInfo.fromJson)
        .toList();
  }

  Future<List<TrainingCategory>> fetchCategories({
    String? type,
    String? level,
  }) async {
    final query = <String, dynamic>{};
    if (type != null) query['type'] = type;
    if (level != null) query['level'] = level;
    final data = await _get('/training/categories', query: query);
    final list = data as List<dynamic>? ?? [];
    return list
        .whereType<Map<String, dynamic>>()
        .map(TrainingCategory.fromJson)
        .toList();
  }

  Future<List<TrainingProgram>> fetchPrograms({
    String? type,
    String? level,
    int? categoryId,
  }) async {
    final query = <String, dynamic>{};
    if (type != null) query['type'] = type;
    if (level != null) query['level'] = level;
    if (categoryId != null) query['category_id'] = categoryId;
    final data = await _get('/training/programs', query: query);
    final list = data as List<dynamic>? ?? [];
    return list
        .whereType<Map<String, dynamic>>()
        .map(TrainingProgram.fromJson)
        .toList();
  }

  Future<TrainingProgram> fetchProgram(int id) async {
    final data = await _get('/training/programs/$id');
    return TrainingProgram.fromJson(data as Map<String, dynamic>);
  }

  Future<ExercisePage> fetchExercises({
    String? type,
    String? level,
    String? search,
    String? difficulty,
    String? equipment,
    int? categoryId,
    int page = 1,
    int perPage = 50,
  }) async {
    final query = <String, dynamic>{'page': page, 'per_page': perPage};
    if (type != null) query['type'] = type;
    if (level != null) query['level'] = level;
    if (search != null && search.isNotEmpty) query['search'] = search;
    if (difficulty != null) query['difficulty'] = difficulty;
    if (equipment != null) query['equipment'] = equipment;
    if (categoryId != null) query['category_id'] = categoryId;
    final data = await _get('/training/exercises', query: query);
    return _pageFrom(data as Map<String, dynamic>);
  }

  Future<ExercisePage> fetchBodybuilding({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  }) async {
    final query = <String, dynamic>{'page': page};
    if (search != null && search.isNotEmpty) query['search'] = search;
    if (level != null) query['level'] = level;
    if (categoryId != null) query['category_id'] = categoryId;
    final data = await _get('/training/bodybuilding', query: query);
    return _pageFrom(data as Map<String, dynamic>);
  }

  Future<ExercisePage> fetchMma({
    String? search,
    String? level,
    int? categoryId,
    int page = 1,
  }) async {
    final query = <String, dynamic>{'page': page};
    if (search != null && search.isNotEmpty) query['search'] = search;
    if (level != null) query['level'] = level;
    if (categoryId != null) query['category_id'] = categoryId;
    final data = await _get('/training/mma', query: query);
    return _pageFrom(data as Map<String, dynamic>);
  }

  Future<Exercise> fetchExercise(int id) async {
    final data = await _get('/training/exercises/$id');
    return Exercise.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> startSession({
    int? programId,
    List<int>? exerciseIds,
  }) async {
    final body = <String, dynamic>{};
    if (programId != null) body['program_id'] = programId;
    if (exerciseIds != null) body['exercise_ids'] = exerciseIds;
    final data = await _post('/workouts/sessions', body);
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSessionPage> fetchSessions({int page = 1}) async {
    final data = await _get('/workouts/sessions', query: {'page': page});
    final map = data as Map<String, dynamic>;
    final items = (map['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(WorkoutSession.fromJson)
        .toList();
    final meta = map['meta'] as Map<String, dynamic>? ?? {};
    return WorkoutSessionPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
    );
  }

  Future<WorkoutSession> fetchSession(int id) async {
    final data = await _get('/workouts/sessions/$id');
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> pauseSession(int id) async {
    final data = await _post('/workouts/sessions/$id/pause', {});
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> resumeSession(int id) async {
    final data = await _post('/workouts/sessions/$id/resume', {});
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> completeSet(
    int sessionId, {
    required int sessionExerciseId,
    int? setNumber,
    int? reps,
    double? weightKg,
    int? durationSeconds,
  }) async {
    final body = <String, dynamic>{
      'session_exercise_id': sessionExerciseId,
      if (setNumber != null) 'set_number': setNumber,
      if (reps != null) 'reps': reps,
      if (weightKg != null) 'weight_kg': weightKg,
      if (durationSeconds != null) 'duration_seconds': durationSeconds,
    };
    final data = await _post('/workouts/sessions/$sessionId/sets', body);
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> completeSession(int id) async {
    final data = await _post('/workouts/sessions/$id/complete', {});
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  Future<WorkoutSession> cancelSession(int id) async {
    final data = await _post('/workouts/sessions/$id/cancel', {});
    return WorkoutSession.fromJson(data as Map<String, dynamic>);
  }

  ExercisePage _pageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(Exercise.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return ExercisePage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
    );
  }

  Future<dynamic> _get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      final envelope = response.data;
      if (envelope == null) {
        throw ApiException('Empty response', messageKey: 'common_error');
      }
      return envelope['data'];
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(
        path,
        data: body,
      );
      final envelope = response.data;
      if (envelope == null) {
        throw ApiException('Empty response', messageKey: 'common_error');
      }
      return envelope['data'];
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }
}
