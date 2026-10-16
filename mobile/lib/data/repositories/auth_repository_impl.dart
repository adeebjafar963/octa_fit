import 'package:octa_fit/core/storage/token_storage.dart';
import 'package:octa_fit/data/datasources/auth_remote_datasource.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/domain/repositories/auth_repository.dart';

class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._remote, this._tokenStorage);

  final AuthRemoteDataSource _remote;
  final TokenStorage _tokenStorage;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    final session = await _remote.login(email, password);
    await _tokenStorage.saveToken(session.token);
    return session;
  }

  @override
  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final session = await _remote.register(
      name: name,
      email: email,
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    await _tokenStorage.saveToken(session.token);
    return session;
  }

  @override
  Future<AuthUser> me() => _remote.me();

  @override
  Future<void> logout() async {
    try {
      await _remote.logout();
    } catch (_) {
      // Still clear local session even if remote logout fails.
    } finally {
      await _tokenStorage.clearToken();
    }
  }

  @override
  Future<AuthUser> getProfile() => _remote.profile();

  @override
  Future<AuthUser> updateProfile(Map<String, dynamic> data) =>
      _remote.updateProfile(data);

  @override
  Future<AuthSession> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final session = await _remote.changePassword(
      currentPassword: currentPassword,
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    await _tokenStorage.saveToken(session.token);
    return session;
  }

  @override
  Future<String?> getStoredToken() => _tokenStorage.readToken();

  @override
  Future<void> clearSession() => _tokenStorage.clearToken();
}
