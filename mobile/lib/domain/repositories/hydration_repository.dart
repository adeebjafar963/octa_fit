import 'package:octa_fit/domain/entities/hydration.dart';

abstract class HydrationRepository {
  Future<HydrationSummary> today({String? timezone});
  Future<HydrationHistory> history({int days = 14, String? timezone});
  Future<HydrationSummary> addIntake({
    required int amountMl,
    String? timezone,
  });
  Future<HydrationSummary> deleteIntake({
    required int intakeId,
    String? timezone,
  });
  Future<HydrationSummary> updateGoal({
    required int goalMl,
    String? timezone,
    bool resetToRecommended = false,
  });
}
