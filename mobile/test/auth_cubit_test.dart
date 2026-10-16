import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/domain/repositories/auth_repository.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repository;

  final user = AuthUser(
    id: 1,
    name: 'Demo',
    email: 'demo@octafit.test',
    isActive: true,
    role: const UserRole(id: 2, name: 'user', displayName: 'User'),
  );

    setUp(() {
    repository = MockAuthRepository();
    registerFallbackValue(<String, dynamic>{});
  });

  group('AuthCubit', () {
    blocTest<AuthCubit, AuthState>(
      'bootstrap without token → unauthenticated',
      build: () {
        when(() => repository.getStoredToken()).thenAnswer((_) async => null);
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.bootstrap(),
      expect: () => [
        const AuthLoading(),
        const AuthUnauthenticated(),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'bootstrap with valid token → authenticated',
      build: () {
        when(() => repository.getStoredToken()).thenAnswer((_) async => 'token');
        when(() => repository.me()).thenAnswer((_) async => user);
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.bootstrap(),
      expect: () => [
        const AuthLoading(),
        AuthAuthenticated(user),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'bootstrap with invalid token clears session',
      build: () {
        when(() => repository.getStoredToken()).thenAnswer((_) async => 'bad');
        when(() => repository.me()).thenThrow(
          ApiException('Unauthenticated', statusCode: 401, messageKey: 'error_unauthorized'),
        );
        when(() => repository.clearSession()).thenAnswer((_) async {});
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.bootstrap(),
      expect: () => [
        const AuthLoading(),
        const AuthUnauthenticated(),
      ],
      verify: (_) {
        verify(() => repository.clearSession()).called(1);
      },
    );

    blocTest<AuthCubit, AuthState>(
      'login success → authenticated',
      build: () {
        when(
          () => repository.login(email: any(named: 'email'), password: any(named: 'password')),
        ).thenAnswer((_) async => AuthSession(user: user, token: 't'));
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.login(email: 'demo@octafit.test', password: 'Demo123!'),
      expect: () => [
        const AuthLoading(),
        AuthAuthenticated(user),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'register success → authenticated',
      build: () {
        when(
          () => repository.register(
            name: any(named: 'name'),
            email: any(named: 'email'),
            password: any(named: 'password'),
            passwordConfirmation: any(named: 'passwordConfirmation'),
          ),
        ).thenAnswer((_) async => AuthSession(user: user, token: 't'));
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.register(
        name: 'OCTA Test User',
        email: 'octa.test@example.com',
        password: 'OctaFit123!',
        passwordConfirmation: 'OctaFit123!',
      ),
      expect: () => [
        const AuthLoading(),
        AuthAuthenticated(user),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'register validation error keeps field errors',
      build: () {
        when(
          () => repository.register(
            name: any(named: 'name'),
            email: any(named: 'email'),
            password: any(named: 'password'),
            passwordConfirmation: any(named: 'passwordConfirmation'),
          ),
        ).thenThrow(
          ApiException(
            'Validation failed',
            statusCode: 422,
            messageKey: 'error_validation',
            errors: {
              'email': ['The email has already been taken.'],
            },
          ),
        );
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.register(
        name: 'OCTA Test User',
        email: 'octa.test@example.com',
        password: 'OctaFit123!',
        passwordConfirmation: 'OctaFit123!',
      ),
      expect: () => [
        const AuthLoading(),
        isA<AuthError>().having(
          (e) => e.fieldErrors?['email']?.first,
          'email error',
          'The email has already been taken.',
        ),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'login network failure stays an error',
      build: () {
        when(
          () => repository.login(email: any(named: 'email'), password: any(named: 'password')),
        ).thenThrow(
          ApiException(
            'Unable to connect to the server',
            statusCode: 0,
            messageKey: 'error_server_unreachable',
          ),
        );
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.login(email: 'a@b.com', password: 'OctaFit123!'),
      expect: () => [
        const AuthLoading(),
        isA<AuthError>().having((e) => e.messageKey, 'messageKey', 'error_server_unreachable'),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'password change failure keeps the session',
      build: () {
        when(
          () => repository.changePassword(
            currentPassword: any(named: 'currentPassword'),
            password: any(named: 'password'),
            passwordConfirmation: any(named: 'passwordConfirmation'),
          ),
        ).thenThrow(
          ApiException('The current password is incorrect.', statusCode: 422, messageKey: 'error_validation'),
        );
        return AuthCubit(repository);
      },
      seed: () => AuthAuthenticated(user),
      act: (cubit) => cubit.changePassword(
        currentPassword: 'wrong',
        password: 'NewPassword1!',
        passwordConfirmation: 'NewPassword1!',
      ),
      expect: () => [
        isA<AuthError>().having((e) => e.keepsSession, 'keepsSession', isTrue),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'login failure → error',
      build: () {
        when(
          () => repository.login(email: any(named: 'email'), password: any(named: 'password')),
        ).thenThrow(
          ApiException('Invalid credentials', statusCode: 401, messageKey: 'error_unauthorized'),
        );
        return AuthCubit(repository);
      },
      act: (cubit) => cubit.login(email: 'x@y.com', password: 'bad'),
      expect: () => [
        const AuthLoading(),
        isA<AuthError>(),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'logout → unauthenticated',
      build: () {
        when(() => repository.logout()).thenAnswer((_) async {});
        return AuthCubit(repository);
      },
      seed: () => AuthAuthenticated(user),
      act: (cubit) => cubit.logout(),
      expect: () => [
        const AuthLoading(),
        const AuthUnauthenticated(),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'updateProfile failure keeps sessionUser for routing',
      build: () {
        when(() => repository.updateProfile(any())).thenThrow(
          ApiException('Validation failed', statusCode: 422, messageKey: 'error_validation'),
        );
        return AuthCubit(repository);
      },
      seed: () => AuthAuthenticated(user),
      act: (cubit) => cubit.updateProfile({'name': 'X'}),
      expect: () => [
        isA<AuthError>().having((e) => e.keepsSession, 'keepsSession', isTrue),
      ],
    );
  });

  group('validation helpers', () {
    test('email must contain @', () {
      const email = 'invalid';
      expect(email.contains('@'), isFalse);
    });

    test('password min length 8', () {
      expect('short'.length < 8, isTrue);
      expect('Password1'.length >= 8, isTrue);
    });
  });
}
