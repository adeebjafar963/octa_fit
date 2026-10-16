import 'package:octa_fit/domain/entities/health_status.dart';

abstract class HealthRepository {
  Future<HealthStatus> checkHealth();
}
