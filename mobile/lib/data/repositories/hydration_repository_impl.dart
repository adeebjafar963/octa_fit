import 'package:octa_fit/data/datasources/hydration_remote_datasource.dart';
import 'package:octa_fit/domain/entities/hydration.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';

class HydrationRepositoryImpl implements HydrationRepository {
  HydrationRepositoryImpl(this._remote);

  final HydrationRemoteDataSource _remote;

  @override
  Future<HydrationSummary> today({String? timezone}) =>
      _remote.fetchToday(timezone: timezone);

  @override
  Future<HydrationHistory> history({int days = 14, String? timezone}) =>
      _remote.fetchHistory(days: days, timezone: timezone);

  @override
  Future<HydrationSummary> addIntake({
    required int amountMl,
    String? timezone,
  }) =>
      _remote.addIntake(amountMl: amountMl, timezone: timezone);

  @override
  Future<HydrationSummary> deleteIntake({
    required int intakeId,
    String? timezone,
  }) =>
      _remote.deleteIntake(intakeId: intakeId, timezone: timezone);

  @override
  Future<HydrationSummary> updateGoal({
    required int goalMl,
    String? timezone,
    bool resetToRecommended = false,
  }) =>
      _remote.updateGoal(
        goalMl: goalMl,
        timezone: timezone,
        resetToRecommended: resetToRecommended,
      );
}
