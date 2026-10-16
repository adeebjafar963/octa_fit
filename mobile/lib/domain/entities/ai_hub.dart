import 'package:equatable/equatable.dart';

class AiHubCapabilities extends Equatable {
  const AiHubCapabilities({
    required this.coach,
    required this.physio,
    required this.motion,
  });

  factory AiHubCapabilities.fromJson(Map<String, dynamic> json) {
    return AiHubCapabilities(
      coach: json['coach'] == true,
      physio: json['physio'] == true,
      motion: json['motion'] == true,
    );
  }

  final bool coach;
  final bool physio;
  final bool motion;

  bool get anyEnabled => coach || physio || motion;

  @override
  List<Object?> get props => [coach, physio, motion];
}

class AiHubGreeting extends Equatable {
  const AiHubGreeting({
    required this.name,
    this.trainingGoal,
    this.fitnessLevel,
    this.profileComplete = false,
  });

  factory AiHubGreeting.fromJson(Map<String, dynamic> json) {
    return AiHubGreeting(
      name: json['name'] as String? ?? '',
      trainingGoal: json['training_goal'] as String?,
      fitnessLevel: json['fitness_level'] as String?,
      profileComplete: json['profile_complete'] == true,
    );
  }

  final String name;
  final String? trainingGoal;
  final String? fitnessLevel;
  final bool profileComplete;

  @override
  List<Object?> get props => [name, trainingGoal, fitnessLevel, profileComplete];
}

class AiRecentConversation extends Equatable {
  const AiRecentConversation({
    required this.id,
    this.title,
    this.updatedAt,
  });

  factory AiRecentConversation.fromJson(Map<String, dynamic> json) {
    return AiRecentConversation(
      id: json['id'] as int,
      title: json['title'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  final int id;
  final String? title;
  final String? updatedAt;

  @override
  List<Object?> get props => [id, title, updatedAt];
}

class AiRecentAssessment extends Equatable {
  const AiRecentAssessment({
    required this.id,
    this.bodyArea,
    this.riskLevel,
    this.requiresUrgentCare = false,
    this.updatedAt,
  });

  factory AiRecentAssessment.fromJson(Map<String, dynamic> json) {
    return AiRecentAssessment(
      id: json['id'] as int,
      bodyArea: json['body_area'] as String?,
      riskLevel: json['risk_level'] as String?,
      requiresUrgentCare: json['requires_urgent_care'] == true,
      updatedAt: json['updated_at'] as String?,
    );
  }

  final int id;
  final String? bodyArea;
  final String? riskLevel;
  final bool requiresUrgentCare;
  final String? updatedAt;

  @override
  List<Object?> get props => [id, bodyArea, riskLevel, requiresUrgentCare];
}

class AiHubSnapshot extends Equatable {
  const AiHubSnapshot({
    required this.capabilities,
    required this.greeting,
    this.conversations = const [],
    this.assessments = const [],
    this.recommendedAction,
  });

  factory AiHubSnapshot.fromJson(Map<String, dynamic> json) {
    final caps = json['capabilities'];
    final greet = json['greeting'];
    final recent = json['recent'];
    final rec = json['recommended_action'];

    return AiHubSnapshot(
      capabilities: caps is Map<String, dynamic>
          ? AiHubCapabilities.fromJson(caps)
          : const AiHubCapabilities(coach: true, physio: true, motion: true),
      greeting: greet is Map<String, dynamic>
          ? AiHubGreeting.fromJson(greet)
          : const AiHubGreeting(name: ''),
      conversations: recent is Map
          ? ((recent['conversations'] as List?) ?? const [])
              .whereType<Map<String, dynamic>>()
              .map(AiRecentConversation.fromJson)
              .toList()
          : const [],
      assessments: recent is Map
          ? ((recent['assessments'] as List?) ?? const [])
              .whereType<Map<String, dynamic>>()
              .map(AiRecentAssessment.fromJson)
              .toList()
          : const [],
      recommendedAction: rec is Map<String, dynamic>
          ? AiRecommendedAction.fromJson(rec)
          : null,
    );
  }

  final AiHubCapabilities capabilities;
  final AiHubGreeting greeting;
  final List<AiRecentConversation> conversations;
  final List<AiRecentAssessment> assessments;
  final AiRecommendedAction? recommendedAction;

  @override
  List<Object?> get props =>
      [capabilities, greeting, conversations, assessments, recommendedAction];
}

class AiRecommendedAction extends Equatable {
  const AiRecommendedAction({
    required this.action,
    required this.capability,
    required this.labelKey,
  });

  factory AiRecommendedAction.fromJson(Map<String, dynamic> json) {
    return AiRecommendedAction(
      action: json['action'] as String? ?? 'open_coach',
      capability: json['capability'] as String? ?? 'coach',
      labelKey: json['label_key'] as String? ?? 'ai_hub_rec_ask',
    );
  }

  final String action;
  final String capability;
  final String labelKey;

  @override
  List<Object?> get props => [action, capability, labelKey];
}

class AiRouteDecision extends Equatable {
  const AiRouteDecision({
    required this.intent,
    required this.capability,
    required this.action,
    required this.priority,
    required this.requiresFeature,
    required this.message,
    required this.navigationRoute,
    required this.createsConversation,
    this.confidence = 0,
    this.language = 'en',
  });

  factory AiRouteDecision.fromJson(Map<String, dynamic> json) {
    final nav = json['navigation'];
    return AiRouteDecision(
      intent: json['intent'] as String? ?? 'general',
      capability: json['capability'] as String? ?? 'coach',
      action: json['action'] as String? ?? 'open_coach_chat',
      priority: json['priority'] as String? ?? 'general',
      requiresFeature: json['requires_feature'] as String? ?? 'coach',
      message: json['message'] as String? ?? '',
      confidence: (json['confidence'] as num?)?.toDouble() ?? 0,
      language: json['language'] as String? ?? 'en',
      navigationRoute: nav is Map
          ? (nav['route'] as String? ?? '/coach')
          : '/coach',
      createsConversation: nav is Map ? nav['creates_conversation'] == true : false,
    );
  }

  final String intent;
  final String capability;
  final String action;
  final String priority;
  final String requiresFeature;
  final String message;
  final double confidence;
  final String language;
  final String navigationRoute;
  final bool createsConversation;

  @override
  List<Object?> get props =>
      [intent, capability, action, priority, message, navigationRoute];
}
