import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/data/datasources/health_remote_datasource.dart';
import 'package:octa_fit/domain/entities/health_status.dart';
import 'package:octa_fit/domain/repositories/health_repository.dart';

class HealthRepositoryImpl implements HealthRepository {
  HealthRepositoryImpl(this._remote);

  final HealthRemoteDataSource _remote;

  @override
  Future<HealthStatus> checkHealth() async {
    try {
      final dto = await _remote.fetchHealth();
      return HealthStatus(success: dto.success, message: dto.message);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
