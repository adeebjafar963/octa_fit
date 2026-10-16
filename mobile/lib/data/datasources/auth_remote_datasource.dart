import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';

class AuthRemoteDataSource {
  AuthRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<AuthSession> login(String email, String password) async {
    final data = await _post('/login', {
      'email': email,
      'password': password,
    });
    return _sessionFrom(data);
  }

  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final data = await _post('/register', {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
    }, created: true);
    return _sessionFrom(data);
  }

  Future<AuthUser> me() async {
    final data = await _get('/me');
    return AuthUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    await _post('/logout', {});
  }

  Future<AuthUser> profile() async {
    final data = await _get('/profile');
    return AuthUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<AuthUser> updateProfile(Map<String, dynamic> body) async {
    final data = await _put('/profile', body);
    return AuthUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<AuthSession> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final data = await _put('/profile/password', {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
    return _sessionFrom(data);
  }

  AuthSession _sessionFrom(Map<String, dynamic> data) {
    return AuthSession(
      user: AuthUser.fromJson(data['user'] as Map<String, dynamic>),
      token: data['token'] as String,
    );
  }

  Future<Map<String, dynamic>> _get(String path) async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>(path);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body, {
    bool created = false,
  }) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<Map<String, dynamic>> _put(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.put<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? envelope) {
    if (envelope == null) {
      throw ApiException('Empty response', messageKey: 'common_error');
    }
    final data = envelope['data'];
    if (data is Map<String, dynamic>) return data;
    return <String, dynamic>{};
  }
}
