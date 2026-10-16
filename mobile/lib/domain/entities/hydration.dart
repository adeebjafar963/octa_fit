import 'package:equatable/equatable.dart';

class WaterIntake extends Equatable {
  const WaterIntake({
    required this.id,
    required this.amountMl,
    this.consumedAt,
    this.consumedAtLocal,
  });

  factory WaterIntake.fromJson(Map<String, dynamic> json) {
    return WaterIntake(
      id: json['id'] as int,
      amountMl: json['amount_ml'] as int? ?? 0,
      consumedAt: json['consumed_at'] as String?,
      consumedAtLocal: json['consumed_at_local'] as String?,
    );
  }

  final int id;
  final int amountMl;
  final String? consumedAt;
  final String? consumedAtLocal;

  @override
  List<Object?> get props => [id, amountMl, consumedAt];
}

class HydrationSummary extends Equatable {
  const HydrationSummary({
    required this.date,
    required this.goalMl,
    required this.consumedMl,
    required this.remainingMl,
    required this.progress,
    required this.intakeCount,
    required this.goalMet,
    this.recommendedGoalMl,
    this.goalIsCustom = false,
    this.streakDays = 0,
    this.timezone,
    this.intakes = const [],
  });

  factory HydrationSummary.fromJson(Map<String, dynamic> json) {
    final intakesJson = json['intakes'];
    return HydrationSummary(
      date: json['date'] as String? ?? '',
      goalMl: json['goal_ml'] as int? ?? 2500,
      consumedMl: json['consumed_ml'] as int? ?? 0,
      remainingMl: json['remaining_ml'] as int? ?? 0,
      progress: (json['progress'] as num?)?.toDouble() ?? 0,
      intakeCount: json['intake_count'] as int? ?? 0,
      goalMet: json['goal_met'] == true,
      recommendedGoalMl: json['recommended_goal_ml'] as int?,
      goalIsCustom: json['goal_is_custom'] == true,
      streakDays: json['streak_days'] as int? ?? 0,
      timezone: json['timezone'] as String?,
      intakes: intakesJson is List
          ? intakesJson
              .whereType<Map<String, dynamic>>()
              .map(WaterIntake.fromJson)
              .toList()
          : const [],
    );
  }

  final String date;
  final int goalMl;
  final int consumedMl;
  final int remainingMl;
  final double progress;
  final int intakeCount;
  final bool goalMet;
  final int? recommendedGoalMl;
  final bool goalIsCustom;
  final int streakDays;
  final String? timezone;
  final List<WaterIntake> intakes;

  double get progressClamped => progress.clamp(0.0, 1.0);

  @override
  List<Object?> get props =>
      [date, goalMl, consumedMl, remainingMl, progress, intakeCount, goalMet, intakes];
}

class HydrationDay extends Equatable {
  const HydrationDay({
    required this.date,
    required this.goalMl,
    required this.consumedMl,
    required this.remainingMl,
    required this.progress,
    required this.goalMet,
  });

  factory HydrationDay.fromJson(Map<String, dynamic> json) {
    return HydrationDay(
      date: json['date'] as String? ?? '',
      goalMl: json['goal_ml'] as int? ?? 0,
      consumedMl: json['consumed_ml'] as int? ?? 0,
      remainingMl: json['remaining_ml'] as int? ?? 0,
      progress: (json['progress'] as num?)?.toDouble() ?? 0,
      goalMet: json['goal_met'] == true,
    );
  }

  final String date;
  final int goalMl;
  final int consumedMl;
  final int remainingMl;
  final double progress;
  final bool goalMet;

  @override
  List<Object?> get props => [date, goalMl, consumedMl, goalMet];
}

class HydrationHistory extends Equatable {
  const HydrationHistory({
    required this.days,
    this.streakDays = 0,
  });

  factory HydrationHistory.fromJson(Map<String, dynamic> json) {
    return HydrationHistory(
      days: ((json['days'] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(HydrationDay.fromJson)
          .toList(),
      streakDays: json['streak_days'] as int? ?? 0,
    );
  }

  final List<HydrationDay> days;
  final int streakDays;

  @override
  List<Object?> get props => [days, streakDays];
}
