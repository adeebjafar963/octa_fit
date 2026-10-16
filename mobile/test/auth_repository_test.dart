import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/storage/token_storage.dart';
import 'package:octa_fit/data/datasources/auth_remote_datasource.dart';
import 'package:octa_fit/data/repositories/auth_repository_impl.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';

class MockAuthRemote extends Mock implements AuthRemoteDataSource {}

class MockTokenStorage extends Mock implements TokenStorage {}

void main() {
  late MockAuthRemote remote;
  late MockTokenStorage storage;
  late AuthRepositoryImpl repository;

  final user = AuthUser(
    id: 1,
    name: 'Demo',
    email: 'demo@octafit.test',
    isActive: true,
  );

  setUp(() {
    remote = MockAuthRemote();
    storage = MockTokenStorage();
    repository = AuthRepositoryImpl(remote, storage);
  });

  test('login stores token', () async {
    when(() => remote.login(any(), any())).thenAnswer(
      (_) async => AuthSession(user: user, token: 'abc'),
    );
    when(() => storage.saveToken(any())).thenAnswer((_) async {});

    final session = await repository.login(email: 'a@b.com', password: 'x');
    expect(session.token, 'abc');
    verify(() => storage.saveToken('abc')).called(1);
  });

  test('logout clears token even if remote fails', () async {
    when(() => remote.logout()).thenThrow(Exception('offline'));
    when(() => storage.clearToken()).thenAnswer((_) async {});

    await repository.logout();
    verify(() => storage.clearToken()).called(1);
  });

  test('clearSession delegates to storage', () async {
    when(() => storage.clearToken()).thenAnswer((_) async {});
    await repository.clearSession();
    verify(() => storage.clearToken()).called(1);
  });
}
