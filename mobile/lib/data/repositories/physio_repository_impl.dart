import 'package:octa_fit/data/datasources/physio_remote_datasource.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';

class PhysioRepositoryImpl implements PhysioRepository {
  PhysioRepositoryImpl(this._remote);

  final PhysioRemoteDataSource _remote;

  @override
  Future<PhysioAssessmentPage> getAssessments({int page = 1}) =>
      _remote.fetchAssessments(page: page);

  @override
  Future<PhysioAssessment> createAssessment(PhysioCreateInput input) =>
      _remote.createAssessment(input);

  @override
  Future<PhysioAssessment> getAssessment(int id) => _remote.fetchAssessment(id);

  @override
  Future<void> deleteAssessment(int id) => _remote.deleteAssessment(id);

  @override
  Future<PhysioSendResult> sendMessage({
    required int assessmentId,
    required String message,
    String? language,
  }) =>
      _remote.sendMessage(
        assessmentId: assessmentId,
        message: message,
        language: language,
      );
}
