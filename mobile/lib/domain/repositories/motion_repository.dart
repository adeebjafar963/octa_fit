import 'package:octa_fit/domain/entities/motion.dart';

abstract class MotionRepository {
  Future<List<String>> getSupportedExercises();

  Future<MotionAnalysisResult> analyze({
    required String exercise,
    required String videoPath,
    String? fileName,
    String? language,
  });
}
