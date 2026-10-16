import 'package:octa_fit/domain/entities/auth_user.dart';

abstract class AuthRepository {
  Future<AuthSession> login({required String email, required String password});

  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  });

  Future<AuthUser> me();

  Future<void> logout();

  Future<AuthUser> getProfile();

  Future<AuthUser> updateProfile(Map<String, dynamic> data);

  Future<AuthSession> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });

  Future<String?> getStoredToken();

  Future<void> clearSession();
}
