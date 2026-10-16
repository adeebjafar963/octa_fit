import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';

/// Ordered onboarding steps. Index is persisted as `onboarding_step`.
enum OnboardingStep {
  about,
  body,
  fitness,
  goals,
  training,
  injury,
  lifestyle,
  review,
}

class ProfileOnboardingState extends Equatable {
  const ProfileOnboardingState({
    this.stepIndex = 0,
    this.age,
    this.gender,
    this.heightCm,
    this.weightKg,
    this.fitnessLevel,
    this.trainingExperience,
    this.trainingGoal,
    this.secondaryGoals = const [],
    this.trainingFrequency,
    this.sessionDurationMinutes,
    this.trainingLocation,
    this.equipment = const [],
    this.injuryStatus,
    this.injuryAreas = const [],
    this.injuryDescription,
    this.activityLevel,
    this.dietaryPreference,
    this.mealsPerDay,
    this.sleepHours,
    this.saving = false,
    this.submitting = false,
    this.errorMessage,
    this.errorMessageKey,
    this.completed = false,
  });

  final int stepIndex;
  final int? age;
  final String? gender;
  final double? heightCm;
  final double? weightKg;
  final String? fitnessLevel;
  final String? trainingExperience;
  final String? trainingGoal;
  final List<String> secondaryGoals;
  final int? trainingFrequency;
  final int? sessionDurationMinutes;
  final String? trainingLocation;
  final List<String> equipment;
  final String? injuryStatus;
  final List<String> injuryAreas;
  final String? injuryDescription;
  final String? activityLevel;
  final String? dietaryPreference;
  final int? mealsPerDay;
  final double? sleepHours;
  final bool saving;
  final bool submitting;
  final String? errorMessage;
  final String? errorMessageKey;
  final bool completed;

  OnboardingStep get step =>
      OnboardingStep.values[stepIndex.clamp(0, OnboardingStep.values.length - 1)];

  double get progress => (stepIndex + 1) / OnboardingStep.values.length;

  bool get isFirst => stepIndex <= 0;
  bool get isLast => stepIndex >= OnboardingStep.values.length - 1;

  ProfileOnboardingState copyWith({
    int? stepIndex,
    int? age,
    String? gender,
    double? heightCm,
    double? weightKg,
    String? fitnessLevel,
    String? trainingExperience,
    String? trainingGoal,
    List<String>? secondaryGoals,
    int? trainingFrequency,
    int? sessionDurationMinutes,
    String? trainingLocation,
    List<String>? equipment,
    String? injuryStatus,
    List<String>? injuryAreas,
    String? injuryDescription,
    String? activityLevel,
    String? dietaryPreference,
    int? mealsPerDay,
    double? sleepHours,
    bool? saving,
    bool? submitting,
    String? errorMessage,
    String? errorMessageKey,
    bool? completed,
    bool clearError = false,
  }) {
    return ProfileOnboardingState(
      stepIndex: stepIndex ?? this.stepIndex,
      age: age ?? this.age,
      gender: gender ?? this.gender,
      heightCm: heightCm ?? this.heightCm,
      weightKg: weightKg ?? this.weightKg,
      fitnessLevel: fitnessLevel ?? this.fitnessLevel,
      trainingExperience: trainingExperience ?? this.trainingExperience,
      trainingGoal: trainingGoal ?? this.trainingGoal,
      secondaryGoals: secondaryGoals ?? this.secondaryGoals,
      trainingFrequency: trainingFrequency ?? this.trainingFrequency,
      sessionDurationMinutes:
          sessionDurationMinutes ?? this.sessionDurationMinutes,
      trainingLocation: trainingLocation ?? this.trainingLocation,
      equipment: equipment ?? this.equipment,
      injuryStatus: injuryStatus ?? this.injuryStatus,
      injuryAreas: injuryAreas ?? this.injuryAreas,
      injuryDescription: injuryDescription ?? this.injuryDescription,
      activityLevel: activityLevel ?? this.activityLevel,
      dietaryPreference: dietaryPreference ?? this.dietaryPreference,
      mealsPerDay: mealsPerDay ?? this.mealsPerDay,
      sleepHours: sleepHours ?? this.sleepHours,
      saving: saving ?? this.saving,
      submitting: submitting ?? this.submitting,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      errorMessageKey:
          clearError ? null : (errorMessageKey ?? this.errorMessageKey),
      completed: completed ?? this.completed,
    );
  }

  @override
  List<Object?> get props => [
        stepIndex,
        age,
        gender,
        heightCm,
        weightKg,
        fitnessLevel,
        trainingExperience,
        trainingGoal,
        secondaryGoals,
        trainingFrequency,
        sessionDurationMinutes,
        trainingLocation,
        equipment,
        injuryStatus,
        injuryAreas,
        injuryDescription,
        activityLevel,
        dietaryPreference,
        mealsPerDay,
        sleepHours,
        saving,
        submitting,
        errorMessage,
        errorMessageKey,
        completed,
      ];
}

class ProfileOnboardingCubit extends Cubit<ProfileOnboardingState> {
  ProfileOnboardingCubit(this._authCubit)
      : super(ProfileOnboardingState(
          stepIndex: _resumeStep(_authCubit.currentUser),
        )) {
    _hydrateFromUser(_authCubit.currentUser);
  }

  final AuthCubit _authCubit;

  static int _resumeStep(AuthUser? user) {
    final raw = user?.profile?.onboardingStep ?? 0;
    return raw.clamp(0, OnboardingStep.values.length - 1);
  }

  void _hydrateFromUser(AuthUser? user) {
    final p = user?.profile;
    if (p == null) return;
    emit(
      state.copyWith(
        age: p.age,
        gender: _normalizeGender(p.gender),
        heightCm: p.heightCm,
        weightKg: p.weightKg,
        fitnessLevel: p.fitnessLevel,
        trainingExperience: p.trainingExperience,
        trainingGoal: p.trainingGoal,
        secondaryGoals: p.secondaryGoals,
        trainingFrequency: p.trainingFrequency,
        sessionDurationMinutes: p.sessionDurationMinutes,
        trainingLocation: p.trainingLocation,
        equipment: p.equipment,
        injuryStatus: p.injuryStatus,
        injuryAreas: p.injuryAreas,
        injuryDescription: p.injuryDescription,
        activityLevel: _normalizeActivity(p.activityLevel),
        dietaryPreference: p.dietaryPreference,
        mealsPerDay: p.mealsPerDay,
        sleepHours: p.sleepHours,
      ),
    );
  }

  /// Canonical gender values only. Legacy/unknown values are cleared (not destroyed in DB
  /// until the user re-saves).
  static String? _normalizeGender(String? raw) {
    if (raw == null) return null;
    final g = raw.trim().toLowerCase();
    if (g == 'male' || g == 'female') return g;
    return null;
  }

  /// Map legacy activity labels to Laravel-canonical values.
  static String? _normalizeActivity(String? raw) {
    if (raw == null) return null;
    return switch (raw.trim().toLowerCase()) {
      'sedentary' => 'sedentary',
      'light' || 'lightly_active' => 'light',
      'moderate' || 'moderately_active' => 'moderate',
      'active' => 'active',
      'very_active' => 'very_active',
      _ => raw,
    };
  }

  void setAge(int? v) => emit(state.copyWith(age: v, clearError: true));
  void setGender(String? v) =>
      emit(state.copyWith(gender: _normalizeGender(v), clearError: true));
  void setHeight(double? v) => emit(state.copyWith(heightCm: v, clearError: true));
  void setWeight(double? v) => emit(state.copyWith(weightKg: v, clearError: true));
  void setFitnessLevel(String? v) =>
      emit(state.copyWith(fitnessLevel: v, clearError: true));
  void setTrainingExperience(String? v) =>
      emit(state.copyWith(trainingExperience: v, clearError: true));
  void setTrainingGoal(String? v) =>
      emit(state.copyWith(trainingGoal: v, clearError: true));
  void setTrainingFrequency(int? v) =>
      emit(state.copyWith(trainingFrequency: v, clearError: true));
  void setSessionDuration(int? v) =>
      emit(state.copyWith(sessionDurationMinutes: v, clearError: true));
  void setTrainingLocation(String? v) =>
      emit(state.copyWith(trainingLocation: v, clearError: true));
  void setInjuryStatus(String? v) =>
      emit(state.copyWith(injuryStatus: v, clearError: true));
  void setInjuryDescription(String? v) =>
      emit(state.copyWith(injuryDescription: v, clearError: true));
  void setActivityLevel(String? v) =>
      emit(state.copyWith(activityLevel: v, clearError: true));
  void setDietaryPreference(String? v) =>
      emit(state.copyWith(dietaryPreference: v, clearError: true));
  void setMealsPerDay(int? v) =>
      emit(state.copyWith(mealsPerDay: v, clearError: true));
  void setSleepHours(double? v) =>
      emit(state.copyWith(sleepHours: v, clearError: true));

  void toggleSecondaryGoal(String value) {
    final next = List<String>.from(state.secondaryGoals);
    if (next.contains(value)) {
      next.remove(value);
    } else if (next.length < 5) {
      next.add(value);
    }
    emit(state.copyWith(secondaryGoals: next, clearError: true));
  }

  void toggleEquipment(String value) {
    final next = List<String>.from(state.equipment);
    if (next.contains(value)) {
      next.remove(value);
    } else if (next.length < 20) {
      next.add(value);
    }
    emit(state.copyWith(equipment: next, clearError: true));
  }

  void toggleInjuryArea(String value) {
    final next = List<String>.from(state.injuryAreas);
    if (next.contains(value)) {
      next.remove(value);
    } else {
      next.add(value);
    }
    emit(state.copyWith(injuryAreas: next, clearError: true));
  }

  /// Returns null when valid, otherwise an i18n key for the validation message.
  String? validateCurrentStep() {
    switch (state.step) {
      case OnboardingStep.about:
        if (state.age == null || state.age! < 13 || state.age! > 100) {
          return 'onboarding_err_age';
        }
        if (state.gender == null || state.gender!.isEmpty) {
          return 'onboarding_err_gender';
        }
        return null;
      case OnboardingStep.body:
        if (state.heightCm == null ||
            state.heightCm! < 100 ||
            state.heightCm! > 250) {
          return 'onboarding_err_height';
        }
        if (state.weightKg == null ||
            state.weightKg! < 30 ||
            state.weightKg! > 300) {
          return 'onboarding_err_weight';
        }
        return null;
      case OnboardingStep.fitness:
        if (state.fitnessLevel == null) return 'onboarding_err_fitness_level';
        if (state.trainingExperience == null) {
          return 'onboarding_err_experience';
        }
        return null;
      case OnboardingStep.goals:
        if (state.trainingGoal == null) return 'onboarding_err_goal';
        return null;
      case OnboardingStep.training:
        if (state.trainingFrequency == null) {
          return 'onboarding_err_frequency';
        }
        if (state.sessionDurationMinutes == null) {
          return 'onboarding_err_duration';
        }
        if (state.trainingLocation == null) {
          return 'onboarding_err_location';
        }
        return null;
      case OnboardingStep.injury:
        if (state.injuryStatus == null) return 'onboarding_err_injury';
        return null;
      case OnboardingStep.lifestyle:
      case OnboardingStep.review:
        return null;
    }
  }

  Map<String, dynamic> _payloadForStep(int stepIndex, {bool full = false}) {
    final s = state;
    final map = <String, dynamic>{
      'onboarding_step': stepIndex,
    };

    if (full || stepIndex >= OnboardingStep.about.index) {
      if (s.age != null) map['age'] = s.age;
      if (s.gender != null) map['gender'] = s.gender;
    }
    if (full || stepIndex >= OnboardingStep.body.index) {
      if (s.heightCm != null) map['height_cm'] = s.heightCm;
      if (s.weightKg != null) map['weight_kg'] = s.weightKg;
    }
    if (full || stepIndex >= OnboardingStep.fitness.index) {
      if (s.fitnessLevel != null) map['fitness_level'] = s.fitnessLevel;
      if (s.trainingExperience != null) {
        map['training_experience'] = s.trainingExperience;
      }
    }
    if (full || stepIndex >= OnboardingStep.goals.index) {
      if (s.trainingGoal != null) map['training_goal'] = s.trainingGoal;
      map['secondary_goals'] = s.secondaryGoals;
    }
    if (full || stepIndex >= OnboardingStep.training.index) {
      if (s.trainingFrequency != null) {
        map['training_frequency'] = s.trainingFrequency;
      }
      if (s.sessionDurationMinutes != null) {
        map['session_duration_minutes'] = s.sessionDurationMinutes;
      }
      if (s.trainingLocation != null) {
        map['training_location'] = s.trainingLocation;
      }
      map['equipment'] = s.equipment;
    }
    if (full || stepIndex >= OnboardingStep.injury.index) {
      if (s.injuryStatus != null) map['injury_status'] = s.injuryStatus;
      map['injury_areas'] = s.injuryAreas;
      if (s.injuryDescription != null && s.injuryDescription!.isNotEmpty) {
        map['injury_description'] = s.injuryDescription;
      }
    }
    if (full || stepIndex >= OnboardingStep.lifestyle.index) {
      if (s.activityLevel != null) map['activity_level'] = s.activityLevel;
      if (s.dietaryPreference != null) {
        map['dietary_preference'] = s.dietaryPreference;
      }
      if (s.mealsPerDay != null) map['meals_per_day'] = s.mealsPerDay;
      if (s.sleepHours != null) map['sleep_hours'] = s.sleepHours;
    }

    return map;
  }

  Future<bool> next() async {
    final err = validateCurrentStep();
    if (err != null) {
      emit(state.copyWith(errorMessageKey: err));
      return false;
    }

    if (state.isLast) {
      return submit();
    }

    final nextIndex = state.stepIndex + 1;
    emit(state.copyWith(saving: true, clearError: true));
    final ok = await _authCubit.updateProfile(_payloadForStep(nextIndex));
    if (isClosed) return false;

    if (!ok) {
      final auth = _authCubit.state;
      emit(
        state.copyWith(
          saving: false,
          errorMessage: auth is AuthError ? auth.message : null,
          errorMessageKey: auth is AuthError ? auth.messageKey : 'common_error',
        ),
      );
      return false;
    }

    emit(state.copyWith(stepIndex: nextIndex, saving: false));
    return true;
  }

  void back() {
    if (state.isFirst || state.saving || state.submitting) return;
    emit(state.copyWith(stepIndex: state.stepIndex - 1, clearError: true));
  }

  Future<bool> submit() async {
    for (final check in [
      validateAbout,
      validateBody,
      validateFitness,
      validateGoals,
      validateTraining,
      validateInjury,
    ]) {
      final err = check();
      if (err != null) {
        emit(state.copyWith(errorMessageKey: err));
        return false;
      }
    }

    emit(state.copyWith(submitting: true, clearError: true));
    final payload = _payloadForStep(
      OnboardingStep.values.length - 1,
      full: true,
    );
    final ok = await _authCubit.updateProfile(payload);
    if (isClosed) return false;

    if (!ok) {
      final auth = _authCubit.state;
      emit(
        state.copyWith(
          submitting: false,
          errorMessage: auth is AuthError ? auth.message : null,
          errorMessageKey: auth is AuthError ? auth.messageKey : 'common_error',
        ),
      );
      return false;
    }

    await _authCubit.refreshProfile();
    if (isClosed) return false;

    final user = _authCubit.currentUser;
    final complete = user?.profileComplete ?? false;
    emit(
      state.copyWith(
        submitting: false,
        completed: complete,
        errorMessageKey: complete ? null : 'onboarding_err_incomplete',
      ),
    );
    return complete;
  }

  String? validateAbout() {
    if (state.age == null || state.age! < 13 || state.age! > 100) {
      return 'onboarding_err_age';
    }
    if (state.gender == null || state.gender!.isEmpty) {
      return 'onboarding_err_gender';
    }
    return null;
  }

  String? validateBody() {
    if (state.heightCm == null ||
        state.heightCm! < 100 ||
        state.heightCm! > 250) {
      return 'onboarding_err_height';
    }
    if (state.weightKg == null ||
        state.weightKg! < 30 ||
        state.weightKg! > 300) {
      return 'onboarding_err_weight';
    }
    return null;
  }

  String? validateFitness() {
    if (state.fitnessLevel == null) return 'onboarding_err_fitness_level';
    if (state.trainingExperience == null) return 'onboarding_err_experience';
    return null;
  }

  String? validateGoals() {
    if (state.trainingGoal == null) return 'onboarding_err_goal';
    return null;
  }

  String? validateTraining() {
    if (state.trainingFrequency == null) return 'onboarding_err_frequency';
    if (state.sessionDurationMinutes == null) return 'onboarding_err_duration';
    if (state.trainingLocation == null) return 'onboarding_err_location';
    return null;
  }

  String? validateInjury() {
    if (state.injuryStatus == null) return 'onboarding_err_injury';
    return null;
  }
}
