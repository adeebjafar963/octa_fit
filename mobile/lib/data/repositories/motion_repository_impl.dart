import 'package:octa_fit/data/datasources/motion_remote_datasource.dart';
import 'package:octa_fit/domain/entities/motion.dart';
import 'package:octa_fit/domain/repositories/motion_repository.dart';

class MotionRepositoryImpl implements MotionRepository {
  MotionRepositoryImpl(this._remote);

  final MotionRemoteDataSource _remote;

  @override
  Future<List<String>> getSupportedExercises() => _remote.fetchExercises();

  @override
  Future<MotionAnalysisResult> analyze({
    required String exercise,
    required String videoPath,
    String? fileName,
    String? language,
  }) =>
      _remote.analyze(
        exercise: exercise,
        videoPath: videoPath,
        fileName: fileName,
        language: language,
      );
}
