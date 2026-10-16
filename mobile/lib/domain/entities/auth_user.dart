import 'package:equatable/equatable.dart';

class UserProfile extends Equatable {
  const UserProfile({
    this.avatarPath,
    this.age,
    this.gender,
    this.heightCm,
    this.weightKg,
    this.trainingGoal,
    this.locale,
    this.fitnessLevel,
    this.trainingExperience,
    this.trainingFrequency,
    this.sessionDurationMinutes,
    this.trainingLocation,
    this.equipment = const [],
    this.secondaryGoals = const [],
    this.activityLevel,
    this.dietaryPreference,
    this.mealsPerDay,
    this.sleepHours,
    this.injuryStatus,
    this.injuryAreas = const [],
    this.injuryDescription,
    this.professionalEvaluation,
    this.onboardingStep = 0,
    this.profileCompletedAt,
  });

  factory UserProfile.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const UserProfile();
    return UserProfile(
      avatarPath: json['avatar_path'] as String?,
      age: json['age'] as int?,
      gender: json['gender'] as String?,
      heightCm: (json['height_cm'] as num?)?.toDouble(),
      weightKg: (json['weight_kg'] as num?)?.toDouble(),
      trainingGoal: json['training_goal'] as String?,
      locale: json['locale'] as String?,
      fitnessLevel: json['fitness_level'] as String?,
      trainingExperience: json['training_experience'] as String?,
      trainingFrequency: json['training_frequency'] as int?,
      sessionDurationMinutes: json['session_duration_minutes'] as int?,
      trainingLocation: json['training_location'] as String?,
      equipment: _stringList(json['equipment']),
      secondaryGoals: _stringList(json['secondary_goals']),
      activityLevel: json['activity_level'] as String?,
      dietaryPreference: json['dietary_preference'] as String?,
      mealsPerDay: json['meals_per_day'] as int?,
      sleepHours: (json['sleep_hours'] as num?)?.toDouble(),
      injuryStatus: json['injury_status'] as String?,
      injuryAreas: _stringList(json['injury_areas']),
      injuryDescription: json['injury_description'] as String?,
      professionalEvaluation: json['professional_evaluation'] as bool?,
      onboardingStep: json['onboarding_step'] as int? ?? 0,
      profileCompletedAt: json['profile_completed_at'] as String?,
    );
  }

  static List<String> _stringList(dynamic value) {
    if (value is! List) return const [];
    return value.map((e) => e.toString()).toList();
  }

  final String? avatarPath;
  final int? age;
  final String? gender;
  final double? heightCm;
  final double? weightKg;
  final String? trainingGoal;
  final String? locale;
  final String? fitnessLevel;
  final String? trainingExperience;
  final int? trainingFrequency;
  final int? sessionDurationMinutes;
  final String? trainingLocation;
  final List<String> equipment;
  final List<String> secondaryGoals;
  final String? activityLevel;
  final String? dietaryPreference;
  final int? mealsPerDay;
  final double? sleepHours;
  final String? injuryStatus;
  final List<String> injuryAreas;
  final String? injuryDescription;
  final bool? professionalEvaluation;
  final int onboardingStep;
  final String? profileCompletedAt;

  Map<String, dynamic> toUpdateJson() {
    return {
      if (age != null) 'age': age,
      if (gender != null) 'gender': gender,
      if (heightCm != null) 'height_cm': heightCm,
      if (weightKg != null) 'weight_kg': weightKg,
      if (trainingGoal != null) 'training_goal': trainingGoal,
      if (locale != null) 'locale': locale,
      if (fitnessLevel != null) 'fitness_level': fitnessLevel,
      if (trainingExperience != null) 'training_experience': trainingExperience,
      if (trainingFrequency != null) 'training_frequency': trainingFrequency,
      if (sessionDurationMinutes != null) 'session_duration_minutes': sessionDurationMinutes,
      if (trainingLocation != null) 'training_location': trainingLocation,
      'equipment': equipment,
      'secondary_goals': secondaryGoals,
      if (activityLevel != null) 'activity_level': activityLevel,
      if (dietaryPreference != null) 'dietary_preference': dietaryPreference,
      if (mealsPerDay != null) 'meals_per_day': mealsPerDay,
      if (sleepHours != null) 'sleep_hours': sleepHours,
      if (injuryStatus != null) 'injury_status': injuryStatus,
      'injury_areas': injuryAreas,
      if (injuryDescription != null) 'injury_description': injuryDescription,
      if (professionalEvaluation != null) 'professional_evaluation': professionalEvaluation,
      'onboarding_step': onboardingStep,
    };
  }

  UserProfile copyWith({
    int? age,
    String? gender,
    double? heightCm,
    double? weightKg,
    String? trainingGoal,
    String? locale,
    String? fitnessLevel,
    String? trainingExperience,
    int? trainingFrequency,
    int? sessionDurationMinutes,
    String? trainingLocation,
    List<String>? equipment,
    List<String>? secondaryGoals,
    String? activityLevel,
    String? dietaryPreference,
    int? mealsPerDay,
    double? sleepHours,
    String? injuryStatus,
    List<String>? injuryAreas,
    String? injuryDescription,
    bool? professionalEvaluation,
    int? onboardingStep,
  }) {
    return UserProfile(
      avatarPath: avatarPath,
      age: age ?? this.age,
      gender: gender ?? this.gender,
      heightCm: heightCm ?? this.heightCm,
      weightKg: weightKg ?? this.weightKg,
      trainingGoal: trainingGoal ?? this.trainingGoal,
      locale: locale ?? this.locale,
      fitnessLevel: fitnessLevel ?? this.fitnessLevel,
      trainingExperience: trainingExperience ?? this.trainingExperience,
      trainingFrequency: trainingFrequency ?? this.trainingFrequency,
      sessionDurationMinutes: sessionDurationMinutes ?? this.sessionDurationMinutes,
      trainingLocation: trainingLocation ?? this.trainingLocation,
      equipment: equipment ?? this.equipment,
      secondaryGoals: secondaryGoals ?? this.secondaryGoals,
      activityLevel: activityLevel ?? this.activityLevel,
      dietaryPreference: dietaryPreference ?? this.dietaryPreference,
      mealsPerDay: mealsPerDay ?? this.mealsPerDay,
      sleepHours: sleepHours ?? this.sleepHours,
      injuryStatus: injuryStatus ?? this.injuryStatus,
      injuryAreas: injuryAreas ?? this.injuryAreas,
      injuryDescription: injuryDescription ?? this.injuryDescription,
      professionalEvaluation: professionalEvaluation ?? this.professionalEvaluation,
      onboardingStep: onboardingStep ?? this.onboardingStep,
      profileCompletedAt: profileCompletedAt,
    );
  }

  @override
  List<Object?> get props => [
        avatarPath,
        age,
        gender,
        heightCm,
        weightKg,
        trainingGoal,
        locale,
        fitnessLevel,
        trainingExperience,
        trainingFrequency,
        sessionDurationMinutes,
        trainingLocation,
        equipment,
        secondaryGoals,
        activityLevel,
        dietaryPreference,
        mealsPerDay,
        sleepHours,
        injuryStatus,
        injuryAreas,
        injuryDescription,
        professionalEvaluation,
        onboardingStep,
        profileCompletedAt,
      ];
}

class UserRole extends Equatable {
  const UserRole({
    required this.id,
    required this.name,
    this.displayName,
  });

  factory UserRole.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const UserRole(id: 0, name: 'user');
    }
    return UserRole(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? 'user',
      displayName: json['display_name'] as String?,
    );
  }

  final int id;
  final String name;
  final String? displayName;

  bool get isAdmin => name == 'admin';

  @override
  List<Object?> get props => [id, name, displayName];
}

class AuthUser extends Equatable {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.isActive,
    this.role,
    this.profile,
    this.profileComplete = false,
    this.completionPercentage = 0,
    this.missingFields = const [],
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
      role: json['role'] != null
          ? UserRole.fromJson(json['role'] as Map<String, dynamic>)
          : null,
      profile: json['profile'] != null
          ? UserProfile.fromJson(json['profile'] as Map<String, dynamic>)
          : null,
      profileComplete: json['profile_complete'] as bool? ?? false,
      completionPercentage: json['completion_percentage'] as int? ?? 0,
      missingFields: (json['missing_fields'] as List?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }

  final int id;
  final String name;
  final String email;
  final bool isActive;
  final UserRole? role;
  final UserProfile? profile;
  final bool profileComplete;
  final int completionPercentage;
  final List<String> missingFields;

  bool get isAdmin => role?.isAdmin ?? false;

  @override
  List<Object?> get props => [
        id,
        name,
        email,
        isActive,
        role,
        profile,
        profileComplete,
        completionPercentage,
        missingFields,
      ];
}

class AuthSession extends Equatable {
  const AuthSession({required this.user, required this.token});

  final AuthUser user;
  final String token;

  @override
  List<Object?> get props => [user, token];
}
