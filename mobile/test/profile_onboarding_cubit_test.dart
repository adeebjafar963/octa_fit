import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/profile/onboarding/cubit/profile_onboarding_cubit.dart';

class _MockAuthCubit extends MockCubit<AuthState> implements AuthCubit {}

void main() {
  late _MockAuthCubit auth;

  AuthUser incomplete({int step = 0}) => AuthUser(
        id: 1,
        name: 'A',
        email: 'a@test.com',
        isActive: true,
        profileComplete: false,
        profile: UserProfile(onboardingStep: step, heightCm: step >= 1 ? 180 : null),
      );

  setUp(() {
    auth = _MockAuthCubit();
    when(() => auth.state).thenReturn(AuthAuthenticated(incomplete()));
    when(() => auth.currentUser).thenReturn(incomplete());
  });

  test('AuthUser parses profile_complete from JSON', () {
    final user = AuthUser.fromJson({
      'id': 1,
      'name': 'A',
      'email': 'a@test.com',
      'is_active': true,
      'profile_complete': true,
      'completion_percentage': 100,
      'missing_fields': <String>[],
      'profile': {
        'height_cm': 180,
        'weight_kg': 82,
        'fitness_level': 'intermediate',
        'training_goal': 'muscle_gain',
        'training_experience': '1_3_years',
        'injury_status': 'none',
      },
    });
    expect(user.profileComplete, isTrue);
    expect(user.profile?.weightKg, 82);
    expect(user.profile?.trainingGoal, 'muscle_gain');
  });

  test('resumes onboarding step from profile', () {
    when(() => auth.currentUser).thenReturn(incomplete(step: 2));
    final cubit = ProfileOnboardingCubit(auth);
    expect(cubit.state.stepIndex, 2);
    cubit.close();
  });

  blocTest<ProfileOnboardingCubit, ProfileOnboardingState>(
    'next advances step after local field update',
    build: () => ProfileOnboardingCubit(auth),
    act: (c) {
      c.setAge(25);
      c.setGender('male');
    },
    verify: (c) {
      expect(c.state.age, 25);
      expect(c.state.gender, 'male');
    },
  );
}
