import 'package:octa_fit/domain/entities/physio.dart';

abstract class PhysioRepository {
  Future<PhysioAssessmentPage> getAssessments({int page = 1});

  Future<PhysioAssessment> createAssessment(PhysioCreateInput input);

  Future<PhysioAssessment> getAssessment(int id);

  Future<void> deleteAssessment(int id);

  Future<PhysioSendResult> sendMessage({
    required int assessmentId,
    required String message,
    String? language,
  });
}
