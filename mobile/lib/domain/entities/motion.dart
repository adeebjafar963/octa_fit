import 'package:equatable/equatable.dart';

class MotionExercise extends Equatable {
  const MotionExercise(this.id, {this.analysisReady = false});

  final String id;
  final bool analysisReady;

  /// Natural library order — Squat and Deadlift are intentionally separated.
  static const catalog = [
    MotionExercise('squat', analysisReady: true),
    MotionExercise('bench_press'),
    MotionExercise('lunge'),
    MotionExercise('front_pulldown'),
    MotionExercise('shoulder_press'),
    MotionExercise('deadlift', analysisReady: true),
    MotionExercise('push_up'),
    MotionExercise('biceps_curl'),
  ];

  /// Exercises that currently have a prepared analysis path.
  static const supported = [
    MotionExercise('squat', analysisReady: true),
    MotionExercise('deadlift', analysisReady: true),
  ];

  String get localizationKey => 'motion_exercise_$id';

  /// True when squat and deadlift sit next to each other in [items].
  static bool squatAndDeadliftAreAdjacent([List<MotionExercise>? items]) {
    final list = items ?? catalog;
    for (var i = 0; i < list.length - 1; i++) {
      final a = list[i].id;
      final b = list[i + 1].id;
      if ((a == 'squat' && b == 'deadlift') || (a == 'deadlift' && b == 'squat')) {
        return true;
      }
    }
    return false;
  }

  @override
  List<Object?> get props => [id, analysisReady];
}

class MotionPositivePoint extends Equatable {
  const MotionPositivePoint({required this.title, required this.explanation});

  factory MotionPositivePoint.fromJson(Map<String, dynamic> json) {
    return MotionPositivePoint(
      title: json['title'] as String? ?? '',
      explanation: json['explanation'] as String? ?? '',
    );
  }

  final String title;
  final String explanation;

  @override
  List<Object?> get props => [title, explanation];
}

class MotionPoint extends Equatable {
  const MotionPoint({
    required this.title,
    required this.status,
    required this.explanation,
    this.statusLabel,
    this.correction,
  });

  factory MotionPoint.fromJson(Map<String, dynamic> json) {
    return MotionPoint(
      title: json['title'] as String? ?? '',
      status: json['status'] as String? ?? 'good',
      statusLabel: json['status_label'] as String?,
      explanation: json['explanation'] as String? ?? '',
      correction: json['correction'] as String?,
    );
  }

  final String title;
  final String status;
  final String? statusLabel;
  final String explanation;
  final String? correction;

  @override
  List<Object?> get props => [title, status, statusLabel, explanation, correction];
}

class MotionMistake extends Equatable {
  const MotionMistake({required this.title, required this.explanation});

  factory MotionMistake.fromJson(Map<String, dynamic> json) {
    return MotionMistake(
      title: json['title'] as String? ?? '',
      explanation: json['explanation'] as String? ?? '',
    );
  }

  final String title;
  final String explanation;

  @override
  List<Object?> get props => [title, explanation];
}

class MotionDrill extends Equatable {
  const MotionDrill({
    required this.name,
    required this.purpose,
    this.id,
    this.difficulty,
    this.sets,
    this.reps,
    this.rest,
    this.instructions = const [],
  });

  factory MotionDrill.fromJson(Map<String, dynamic> json) {
    return MotionDrill(
      id: json['id']?.toString(),
      name: json['name'] as String? ?? '',
      purpose: json['purpose'] as String? ?? '',
      difficulty: json['difficulty'] as String?,
      sets: json['sets']?.toString(),
      reps: json['reps']?.toString(),
      rest: json['rest']?.toString(),
      instructions: (json['instructions'] as List<dynamic>? ?? [])
          .whereType<String>()
          .toList(),
    );
  }

  final String? id;
  final String name;
  final String purpose;
  final String? difficulty;
  final String? sets;
  final String? reps;
  final String? rest;
  final List<String> instructions;

  @override
  List<Object?> get props =>
      [id, name, purpose, difficulty, sets, reps, rest, instructions];
}

class MotionAnalysisResult extends Equatable {
  const MotionAnalysisResult({
    required this.exercise,
    this.repetitions = 0,
    this.durationSeconds = 0,
    this.confidence = 0,
    this.movementQuality = 'good',
    this.observations = const [],
    this.tips = const [],
    this.metrics = const {},
    this.provider,
    this.framesAnalyzed = 0,
    this.disclaimer,
    this.demo = false,
    this.structured = false,
    this.title,
    this.analysisLabel,
    this.heroSubtitle,
    this.overallStatus,
    this.overallSummary,
    this.positivePoints = const [],
    this.movementPoints = const [],
    this.coachingCues = const [],
    this.commonMistakes = const [],
    this.improvementDrills = const [],
    this.nextAttempt = const [],
    this.finalSummary,
    this.language,
  });

  factory MotionAnalysisResult.fromJson(Map<String, dynamic> json) {
    final metricsRaw = json['metrics'];
    final metrics = <String, double>{};
    if (metricsRaw is Map) {
      metricsRaw.forEach((key, value) {
        if (value is num) {
          metrics[key.toString()] = value.toDouble();
        }
      });
    }

    return MotionAnalysisResult(
      exercise: json['exercise'] as String? ?? '',
      repetitions: json['repetitions'] as int? ?? 0,
      durationSeconds: (json['duration_seconds'] as num?)?.toDouble() ?? 0,
      confidence: (json['confidence'] as num?)?.toDouble() ?? 0,
      movementQuality: json['movement_quality'] as String? ?? 'unknown',
      observations: (json['observations'] as List<dynamic>? ?? [])
          .whereType<String>()
          .toList(),
      tips: (json['tips'] as List<dynamic>? ?? []).whereType<String>().toList(),
      metrics: metrics,
      provider: json['provider'] as String?,
      framesAnalyzed: json['frames_analyzed'] as int? ?? 0,
      disclaimer: json['disclaimer'] as String?,
      demo: json['demo'] as bool? ?? false,
      structured: json['structured'] as bool? ?? false,
      title: json['title'] as String?,
      analysisLabel: json['analysis_label'] as String?,
      heroSubtitle: json['hero_subtitle'] as String?,
      overallStatus: json['overall_status'] as String?,
      overallSummary: json['overall_summary'] as String?,
      positivePoints: (json['positive_points'] as List<dynamic>? ?? [])
          .whereType<Map>()
          .map((e) => MotionPositivePoint.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      movementPoints: (json['movement_points'] as List<dynamic>? ?? [])
          .whereType<Map>()
          .map((e) => MotionPoint.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      coachingCues: (json['coaching_cues'] as List<dynamic>? ?? [])
          .whereType<String>()
          .toList(),
      commonMistakes: (json['common_mistakes'] as List<dynamic>? ?? [])
          .whereType<Map>()
          .map((e) => MotionMistake.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      improvementDrills: (json['improvement_drills'] as List<dynamic>? ?? [])
          .whereType<Map>()
          .map((e) => MotionDrill.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      nextAttempt: (json['next_attempt'] as List<dynamic>? ?? [])
          .whereType<String>()
          .toList(),
      finalSummary: json['final_summary'] as String?,
      language: json['language'] as String?,
    );
  }

  final String exercise;
  final int repetitions;
  final double durationSeconds;
  final double confidence;
  final String movementQuality;
  final List<String> observations;
  final List<String> tips;
  final Map<String, double> metrics;
  final String? provider;
  final int framesAnalyzed;
  final String? disclaimer;
  final bool demo;
  final bool structured;
  final String? title;
  final String? analysisLabel;
  final String? heroSubtitle;
  final String? overallStatus;
  final String? overallSummary;
  final List<MotionPositivePoint> positivePoints;
  final List<MotionPoint> movementPoints;
  final List<String> coachingCues;
  final List<MotionMistake> commonMistakes;
  final List<MotionDrill> improvementDrills;
  final List<String> nextAttempt;
  final String? finalSummary;
  final String? language;

  bool get hasPremiumStructure =>
      structured ||
      positivePoints.isNotEmpty ||
      movementPoints.isNotEmpty ||
      coachingCues.isNotEmpty ||
      improvementDrills.isNotEmpty;

  @override
  List<Object?> get props => [
        exercise,
        repetitions,
        confidence,
        movementQuality,
        observations,
        tips,
        demo,
        structured,
        overallSummary,
        positivePoints,
        movementPoints,
        coachingCues,
        commonMistakes,
        improvementDrills,
        nextAttempt,
        finalSummary,
      ];
}
