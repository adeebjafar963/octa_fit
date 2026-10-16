import 'package:equatable/equatable.dart';
import 'package:octa_fit/domain/entities/coach.dart';

/// Phase 11G — structured payloads attached to an assistant turn (client-side).
class CoachMessageExtras extends Equatable {
  const CoachMessageExtras({
    this.groundedExercises = const [],
    this.trainingPlan,
    this.nutrition,
    this.nutritionPlan,
    this.knowledgeContext,
  });

  factory CoachMessageExtras.fromSendResult(CoachSendResult result) {
    return CoachMessageExtras(
      groundedExercises: result.groundedExercises,
      trainingPlan: result.trainingPlan,
      nutrition: result.nutrition,
      nutritionPlan: result.nutritionPlan,
      knowledgeContext: result.knowledgeContext,
    );
  }

  final List<CoachGroundedExercise> groundedExercises;
  final Map<String, dynamic>? trainingPlan;
  final Map<String, dynamic>? nutrition;
  final Map<String, dynamic>? nutritionPlan;
  final Map<String, dynamic>? knowledgeContext;

  bool get hasTrainingPlan => trainingPlan != null && trainingPlan!.isNotEmpty;
  bool get hasNutritionPlan => nutritionPlan != null && nutritionPlan!.isNotEmpty;
  bool get hasNutritionLookup => nutrition != null && nutrition!.isNotEmpty;
  bool get hasKnowledge {
    final items = knowledgeContext?['items'];
    return items is List && items.isNotEmpty;
  }

  bool get hasGroundedExercises => groundedExercises.any((e) => e.exerciseId > 0);
  bool get hasStructured =>
      hasTrainingPlan ||
      hasNutritionPlan ||
      hasNutritionLookup ||
      hasKnowledge ||
      hasGroundedExercises;

  @override
  List<Object?> get props => [
        groundedExercises,
        trainingPlan,
        nutrition,
        nutritionPlan,
        knowledgeContext,
      ];
}

/// Safe helpers for map-based 11C/11D/11E/11F contracts.
class CoachPlanParsing {
  CoachPlanParsing._();

  static String? asString(dynamic v) {
    if (v == null) return null;
    if (v is String) {
      final t = v.trim();
      return t.isEmpty ? null : t;
    }
    if (v is num || v is bool) return '$v';
    return null;
  }

  static num? asNum(dynamic v) {
    if (v is num) return v;
    if (v is String) return num.tryParse(v);
    return null;
  }

  static int? asInt(dynamic v) {
    final n = asNum(v);
    return n?.round();
  }

  static double? asDouble(dynamic v) {
    final n = asNum(v);
    return n?.toDouble();
  }

  static List<Map<String, dynamic>> asMapList(dynamic v) {
    if (v is! List) return const [];
    return v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  static Map<String, dynamic>? asMap(dynamic v) {
    if (v is Map<String, dynamic>) return v;
    if (v is Map) return Map<String, dynamic>.from(v);
    return null;
  }

  static String formatNumber(num? value, {int fractionDigits = 0}) {
    if (value == null) return '—';
    if (fractionDigits == 0) {
      return value.round().toString();
    }
    return value.toStringAsFixed(fractionDigits);
  }
}
