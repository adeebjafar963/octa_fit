import 'package:equatable/equatable.dart';

class CoachMessage extends Equatable {
  const CoachMessage({
    required this.id,
    required this.conversationId,
    required this.role,
    required this.content,
    this.createdAt,
  });

  factory CoachMessage.fromJson(Map<String, dynamic> json) {
    return CoachMessage(
      id: json['id'] as int,
      conversationId: json['conversation_id'] as int? ?? 0,
      role: json['role'] as String? ?? 'user',
      content: json['content'] as String? ?? '',
      createdAt: json['created_at'] as String?,
    );
  }

  final int id;
  final int conversationId;
  final String role;
  final String content;
  final String? createdAt;

  bool get isUser => role == 'user';
  bool get isAssistant => role == 'assistant';

  @override
  List<Object?> get props => [id, conversationId, role, content];
}

class CoachConversation extends Equatable {
  const CoachConversation({
    required this.id,
    this.title,
    this.createdAt,
    this.updatedAt,
    this.messages = const [],
  });

  factory CoachConversation.fromJson(Map<String, dynamic> json) {
    final messagesJson = json['messages'];
    return CoachConversation(
      id: json['id'] as int,
      title: json['title'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      messages: messagesJson is List
          ? messagesJson
              .whereType<Map<String, dynamic>>()
              .map(CoachMessage.fromJson)
              .toList()
          : const [],
    );
  }

  final int id;
  final String? title;
  final String? createdAt;
  final String? updatedAt;
  final List<CoachMessage> messages;

  String displayTitle(String fallback) =>
      (title != null && title!.trim().isNotEmpty) ? title!.trim() : fallback;

  @override
  List<Object?> get props => [id, title, updatedAt, messages];
}

class CoachConversationPage extends Equatable {
  const CoachConversationPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<CoachConversation> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class CoachSendResult extends Equatable {
  const CoachSendResult({
    required this.userMessage,
    required this.assistantMessage,
    required this.conversation,
    this.groundedExercises = const [],
    this.trainingPlan,
    this.nutrition,
    this.nutritionPlan,
    this.knowledgeContext,
  });

  factory CoachSendResult.fromJson(Map<String, dynamic> json) {
    final groundedJson = json['grounded_exercises'];
    final planJson = json['training_plan'];
    final nutritionJson = json['nutrition'];
    final nutritionPlanJson = json['nutrition_plan'];
    final knowledgeJson = json['knowledge_context'];
    return CoachSendResult(
      userMessage: CoachMessage.fromJson(json['user_message'] as Map<String, dynamic>),
      assistantMessage:
          CoachMessage.fromJson(json['assistant_message'] as Map<String, dynamic>),
      conversation:
          CoachConversation.fromJson(json['conversation'] as Map<String, dynamic>),
      groundedExercises: groundedJson is List
          ? groundedJson
                .whereType<Map>()
                .map((e) => CoachGroundedExercise.fromJson(Map<String, dynamic>.from(e)))
                .toList()
          : const [],
      // Accept any Map shape from JSON decode (Phase 11I hardening).
      trainingPlan: planJson is Map ? Map<String, dynamic>.from(planJson) : null,
      nutrition: nutritionJson is Map ? Map<String, dynamic>.from(nutritionJson) : null,
      nutritionPlan:
          nutritionPlanJson is Map ? Map<String, dynamic>.from(nutritionPlanJson) : null,
      knowledgeContext:
          knowledgeJson is Map ? Map<String, dynamic>.from(knowledgeJson) : null,
    );
  }

  final CoachMessage userMessage;
  final CoachMessage assistantMessage;
  final CoachConversation conversation;

  /// Phase 11B — OCTA FIT exercise IDs grounded against the database.
  /// Open via existing route: `/training/exercises/{exerciseId}`.
  final List<CoachGroundedExercise> groundedExercises;

  /// Phase 11C — structured training plan contract (UI rendering in 11G).
  final Map<String, dynamic>? trainingPlan;

  /// Phase 11D — deterministic nutrition lookup (UI rendering in 11G).
  final Map<String, dynamic>? nutrition;

  /// Phase 11E — personalized nutrition plan contract (UI rendering in 11G).
  final Map<String, dynamic>? nutritionPlan;

  /// Phase 11F — retrieved knowledge / evidence context (citation UI in 11G).
  final Map<String, dynamic>? knowledgeContext;

  @override
  List<Object?> get props => [
        userMessage,
        assistantMessage,
        conversation,
        groundedExercises,
        trainingPlan,
        nutrition,
        nutritionPlan,
        knowledgeContext,
      ];
}

/// Grounded exercise reference from AI Coach (Phase 11B).
class CoachGroundedExercise extends Equatable {
  const CoachGroundedExercise({
    required this.exerciseId,
    required this.name,
    this.slug,
    this.discipline,
    this.level,
    this.flutterRoute,
    this.grounded = true,
  });

  factory CoachGroundedExercise.fromJson(Map<String, dynamic> json) {
    final id = json['exercise_id'] ?? json['id'];
    return CoachGroundedExercise(
      exerciseId: id is int ? id : int.tryParse('$id') ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      discipline: json['discipline'] as String?,
      level: json['level'] as String?,
      flutterRoute: json['flutter_route'] as String?,
      grounded: json['grounded'] as bool? ?? true,
    );
  }

  final int exerciseId;
  final String name;
  final String? slug;
  final String? discipline;
  final String? level;
  final String? flutterRoute;
  final bool grounded;

  /// Existing GoRouter path — do not invent a second detail screen.
  String get detailRoute =>
      flutterRoute ?? '/training/exercises/$exerciseId';

  @override
  List<Object?> get props => [exerciseId, name, grounded];
}
