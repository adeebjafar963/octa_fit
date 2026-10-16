import 'package:equatable/equatable.dart';

class PhysioKeyFinding extends Equatable {
  const PhysioKeyFinding({
    required this.title,
    required this.detail,
    this.icon,
  });

  factory PhysioKeyFinding.fromJson(dynamic raw) {
    if (raw is String) {
      return PhysioKeyFinding(title: raw, detail: raw);
    }
    if (raw is Map) {
      final json = Map<String, dynamic>.from(raw);
      return PhysioKeyFinding(
        title: json['title'] as String? ?? '',
        detail: json['detail'] as String? ?? json['explanation'] as String? ?? '',
        icon: json['icon'] as String?,
      );
    }
    return const PhysioKeyFinding(title: '', detail: '');
  }

  final String title;
  final String detail;
  final String? icon;

  bool get isEmpty => title.trim().isEmpty && detail.trim().isEmpty;

  @override
  List<Object?> get props => [title, detail, icon];
}

class PhysioSelfManagement extends Equatable {
  const PhysioSelfManagement({
    this.today = const [],
    this.nextDays = const [],
  });

  factory PhysioSelfManagement.fromJson(dynamic raw) {
    if (raw is! Map) return const PhysioSelfManagement();
    final json = Map<String, dynamic>.from(raw);
    return PhysioSelfManagement(
      today: _stringList(json['today']),
      nextDays: _stringList(json['next_days'] ?? json['nextDays']),
    );
  }

  final List<String> today;
  final List<String> nextDays;

  bool get isEmpty => today.isEmpty && nextDays.isEmpty;

  @override
  List<Object?> get props => [today, nextDays];
}

class PhysioExerciseRecommendation extends Equatable {
  const PhysioExerciseRecommendation({
    required this.name,
    this.id,
    this.purpose,
    this.difficulty,
    this.instructions = const [],
    this.sets,
    this.reps,
    this.duration,
    this.repsOrDuration,
    this.rest,
    this.frequency,
    this.intensity,
    this.progression,
    this.safetyNote,
    this.caution,
  });

  factory PhysioExerciseRecommendation.fromJson(Map<String, dynamic> json) {
    final repsOrDuration = json['reps_or_duration']?.toString() ??
        json['repetitions']?.toString() ??
        json['duration']?.toString();
    return PhysioExerciseRecommendation(
      id: json['id']?.toString(),
      name: json['name'] as String? ?? 'Exercise',
      purpose: json['purpose'] as String?,
      difficulty: json['difficulty'] as String?,
      instructions: _stringList(json['instructions']),
      sets: json['sets']?.toString(),
      reps: json['reps']?.toString(),
      duration: json['duration']?.toString(),
      repsOrDuration: repsOrDuration,
      rest: json['rest'] as String?,
      frequency: json['frequency'] as String?,
      intensity: json['intensity'] as String?,
      progression: json['progression'] as String?,
      safetyNote: (json['safety_note'] as String?) ?? (json['caution'] as String?),
      caution: json['caution'] as String?,
    );
  }

  final String? id;
  final String name;
  final String? purpose;
  final String? difficulty;
  final List<String> instructions;
  final String? sets;
  final String? reps;
  final String? duration;
  final String? repsOrDuration;
  final String? rest;
  final String? frequency;
  final String? intensity;
  final String? progression;
  final String? safetyNote;
  final String? caution;

  String get doseLabel {
    if ((reps ?? '').isNotEmpty) return reps!;
    if ((duration ?? '').isNotEmpty) return duration!;
    return repsOrDuration ?? '';
  }

  @override
  List<Object?> get props => [
        id,
        name,
        purpose,
        difficulty,
        instructions,
        sets,
        reps,
        duration,
        repsOrDuration,
        rest,
        frequency,
        intensity,
        progression,
        safetyNote,
        caution,
      ];
}

class PhysioResult extends Equatable {
  const PhysioResult({
    this.summary,
    this.possibleExplanations = const [],
    this.selfCare = const [],
    this.avoid = const [],
    this.professionalCare,
    this.redFlags = const [],
    this.clinicalAssessment,
    this.initialClinicalImpression,
    this.confidence,
    this.keyFindings = const [],
    this.recommendedExercises = const [],
    this.exerciseDosage,
    this.activityAdvice,
    this.selfManagement = const PhysioSelfManagement(),
    this.whatToAvoid = const [],
    this.followUp,
    this.nextStep,
    this.evidenceBasis = const [],
    this.escalationLevel,
    this.expectedClinicalPattern,
    this.title,
    this.subtitle,
    this.medicalPriorityMessage,
    this.caseId,
    this.demo = false,
    this.structured = false,
    this.language,
  });

  factory PhysioResult.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const PhysioResult();

    final exercisesRaw = json['recommended_exercises'];
    final exercises = exercisesRaw is List
        ? exercisesRaw
            .whereType<Map>()
            .map((e) => PhysioExerciseRecommendation.fromJson(
                  Map<String, dynamic>.from(e),
                ))
            .toList()
        : const <PhysioExerciseRecommendation>[];

    final findingsRaw = json['key_findings'];
    final findings = findingsRaw is List
        ? findingsRaw
            .map(PhysioKeyFinding.fromJson)
            .where((f) => !f.isEmpty)
            .toList()
        : const <PhysioKeyFinding>[];

    final whatToAvoid = _stringList(json['what_to_avoid']);
    final avoid = whatToAvoid.isNotEmpty ? whatToAvoid : _stringList(json['avoid']);
    final evidenceRaw = json['evidence_basis'];
    final evidence = evidenceRaw is List
        ? _stringList(evidenceRaw)
        : (evidenceRaw is String && evidenceRaw.trim().isNotEmpty
            ? <String>[evidenceRaw.trim()]
            : const <String>[]);

    final impression = (json['clinical_impression'] as String?) ??
        (json['initial_clinical_impression'] as String?);
    final next = (json['next_step'] as String?) ??
        (json['follow_up'] as String?) ??
        (json['professional_care'] as String?);

    return PhysioResult(
      summary: json['summary'] as String?,
      possibleExplanations: _stringList(json['possible_explanations']),
      selfCare: _stringList(json['self_care']),
      avoid: avoid,
      professionalCare: next,
      redFlags: _stringList(json['red_flags']),
      clinicalAssessment: json['clinical_assessment'] as String?,
      initialClinicalImpression: impression,
      confidence: json['confidence'] as String?,
      keyFindings: findings,
      recommendedExercises: exercises,
      exerciseDosage: json['exercise_dosage'] as String?,
      activityAdvice: json['activity_advice'] as String?,
      selfManagement: PhysioSelfManagement.fromJson(json['self_management']),
      whatToAvoid: avoid,
      followUp: next,
      nextStep: next,
      evidenceBasis: evidence,
      escalationLevel: (json['severity'] as String?) ??
          (json['escalation_level'] as String?),
      expectedClinicalPattern: json['expected_clinical_pattern'] as String?,
      title: json['title'] as String?,
      subtitle: json['subtitle'] as String?,
      medicalPriorityMessage: json['medical_priority_message'] as String?,
      caseId: (json['demo_case_id'] as String?) ?? (json['case_id'] as String?),
      demo: json['demo'] as bool? ?? false,
      structured: json['structured'] as bool? ??
          (json['demo'] as bool? ?? false),
      language: json['language'] as String?,
    );
  }

  final String? summary;
  final List<String> possibleExplanations;
  final List<String> selfCare;
  final List<String> avoid;
  final String? professionalCare;
  final List<String> redFlags;
  final String? clinicalAssessment;
  final String? initialClinicalImpression;
  final String? confidence;
  final List<PhysioKeyFinding> keyFindings;
  final List<PhysioExerciseRecommendation> recommendedExercises;
  final String? exerciseDosage;
  final String? activityAdvice;
  final PhysioSelfManagement selfManagement;
  final List<String> whatToAvoid;
  final String? followUp;
  final String? nextStep;
  final List<String> evidenceBasis;
  final String? escalationLevel;
  final String? expectedClinicalPattern;
  final String? title;
  final String? subtitle;
  final String? medicalPriorityMessage;
  final String? caseId;
  final bool demo;
  final bool structured;
  final String? language;

  bool get hasStructuredClinicalOutput =>
      demo ||
      structured ||
      (initialClinicalImpression ?? '').trim().isNotEmpty ||
      keyFindings.isNotEmpty ||
      recommendedExercises.isNotEmpty ||
      (escalationLevel ?? '').trim().isNotEmpty;

  @override
  List<Object?> get props => [
        summary,
        possibleExplanations,
        selfCare,
        avoid,
        professionalCare,
        redFlags,
        clinicalAssessment,
        initialClinicalImpression,
        confidence,
        keyFindings,
        recommendedExercises,
        exerciseDosage,
        activityAdvice,
        selfManagement,
        whatToAvoid,
        followUp,
        nextStep,
        evidenceBasis,
        escalationLevel,
        expectedClinicalPattern,
        title,
        subtitle,
        medicalPriorityMessage,
        caseId,
        demo,
        structured,
        language,
      ];
}

class PhysioMessage extends Equatable {
  const PhysioMessage({
    required this.id,
    required this.assessmentId,
    required this.role,
    required this.content,
    this.createdAt,
  });

  factory PhysioMessage.fromJson(Map<String, dynamic> json) {
    return PhysioMessage(
      id: json['id'] as int,
      assessmentId: json['assessment_id'] as int? ?? 0,
      role: json['role'] as String? ?? 'user',
      content: json['content'] as String? ?? '',
      createdAt: json['created_at'] as String?,
    );
  }

  final int id;
  final int assessmentId;
  final String role;
  final String content;
  final String? createdAt;

  bool get isUser => role == 'user';
  bool get isAssistant => role == 'assistant';

  @override
  List<Object?> get props => [id, assessmentId, role, content];
}

class PhysioAssessment extends Equatable {
  const PhysioAssessment({
    required this.id,
    required this.bodyArea,
    required this.symptoms,
    this.painLevel,
    this.duration,
    this.onset,
    this.trigger,
    this.activity,
    this.additionalInformation,
    this.riskLevel,
    this.requiresUrgentCare = false,
    this.result = const PhysioResult(),
    this.createdAt,
    this.updatedAt,
    this.messages = const [],
  });

  factory PhysioAssessment.fromJson(Map<String, dynamic> json) {
    final messagesJson = json['messages'];
    return PhysioAssessment(
      id: json['id'] as int,
      bodyArea: json['body_area'] as String? ?? '',
      symptoms: json['symptoms'] as String? ?? '',
      painLevel: json['pain_level'] as int?,
      duration: json['duration'] as String?,
      onset: json['onset'] as String?,
      trigger: json['trigger'] as String?,
      activity: json['activity'] as String?,
      additionalInformation: json['additional_information'] as String?,
      riskLevel: json['risk_level'] as String?,
      requiresUrgentCare: json['requires_urgent_care'] as bool? ?? false,
      result: PhysioResult.fromJson(json['result'] as Map<String, dynamic>?),
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      messages: messagesJson is List
          ? messagesJson
              .whereType<Map<String, dynamic>>()
              .map(PhysioMessage.fromJson)
              .toList()
          : const [],
    );
  }

  final int id;
  final String bodyArea;
  final String symptoms;
  final int? painLevel;
  final String? duration;
  final String? onset;
  final String? trigger;
  final String? activity;
  final String? additionalInformation;
  final String? riskLevel;
  final bool requiresUrgentCare;
  final PhysioResult result;
  final String? createdAt;
  final String? updatedAt;
  final List<PhysioMessage> messages;

  String get shortSymptoms {
    final text = symptoms.trim();
    if (text.length <= 80) return text;
    return '${text.substring(0, 77)}...';
  }

  @override
  List<Object?> get props =>
      [id, bodyArea, riskLevel, requiresUrgentCare, updatedAt, messages, result];
}

class PhysioAssessmentPage extends Equatable {
  const PhysioAssessmentPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<PhysioAssessment> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class PhysioCreateInput extends Equatable {
  const PhysioCreateInput({
    required this.bodyArea,
    required this.symptoms,
    this.painLevel,
    this.duration,
    this.onset,
    this.trigger,
    this.activity,
    this.additionalInformation,
    this.language,
  });

  final String bodyArea;
  final String symptoms;
  final int? painLevel;
  final String? duration;
  final String? onset;
  final String? trigger;
  final String? activity;
  final String? additionalInformation;
  final String? language;

  Map<String, dynamic> toJson() {
    return {
      'body_area': bodyArea,
      'symptoms': symptoms,
      if (painLevel != null) 'pain_level': painLevel,
      if (duration != null && duration!.trim().isNotEmpty) 'duration': duration!.trim(),
      if (onset != null && onset!.trim().isNotEmpty) 'onset': onset!.trim(),
      if (trigger != null && trigger!.trim().isNotEmpty) 'trigger': trigger!.trim(),
      if (activity != null && activity!.trim().isNotEmpty) 'activity': activity!.trim(),
      if (additionalInformation != null &&
          additionalInformation!.trim().isNotEmpty)
        'additional_information': additionalInformation!.trim(),
      if (language != null) 'language': language,
    };
  }

  @override
  List<Object?> get props =>
      [bodyArea, symptoms, painLevel, duration, onset, trigger, activity, language];
}

class PhysioSendResult extends Equatable {
  const PhysioSendResult({
    required this.userMessage,
    required this.assistantMessage,
    required this.assessment,
  });

  factory PhysioSendResult.fromJson(Map<String, dynamic> json) {
    return PhysioSendResult(
      userMessage:
          PhysioMessage.fromJson(json['user_message'] as Map<String, dynamic>),
      assistantMessage: PhysioMessage.fromJson(
        json['assistant_message'] as Map<String, dynamic>,
      ),
      assessment:
          PhysioAssessment.fromJson(json['assessment'] as Map<String, dynamic>),
    );
  }

  final PhysioMessage userMessage;
  final PhysioMessage assistantMessage;
  final PhysioAssessment assessment;

  @override
  List<Object?> get props => [userMessage, assistantMessage, assessment];
}

List<String> _stringList(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<String>().where((e) => e.trim().isNotEmpty).toList();
}
