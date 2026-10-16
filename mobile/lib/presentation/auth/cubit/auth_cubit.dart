import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/domain/repositories/auth_repository.dart';

sealed class AuthState extends Equatable {
  const AuthState();

  @override
  List<Object?> get props => [];
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);

  final AuthUser user;

  @override
  List<Object?> get props => [user];
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

/// Form or credential failure. When [sessionUser] is set, the user remains
/// logged in for routing (profile/password errors must not force logout).
class AuthError extends AuthState {
  const AuthError({
    required this.message,
    this.messageKey,
    this.fieldErrors,
    this.sessionUser,
  });

  final String message;
  final String? messageKey;
  final Map<String, List<String>>? fieldErrors;
  final AuthUser? sessionUser;

  bool get keepsSession => sessionUser != null;

  @override
  List<Object?> get props => [message, messageKey, fieldErrors, sessionUser];
}

class AuthCubit extends Cubit<AuthState> {
  AuthCubit(this._repository) : super(const AuthInitial());

  final AuthRepository _repository;

  AuthUser? get currentUser {
    final s = state;
    if (s is AuthAuthenticated) return s.user;
    if (s is AuthError) return s.sessionUser;
    return null;
  }

  Future<void> bootstrap() async {
    emit(const AuthLoading());
    try {
      final token = await _repository.getStoredToken();
      if (token == null || token.isEmpty) {
        emit(const AuthUnauthenticated());
        return;
      }
      final user = await _repository.me();
      emit(AuthAuthenticated(user));
    } on ApiException {
      await _repository.clearSession();
      emit(const AuthUnauthenticated());
    } catch (_) {
      await _repository.clearSession();
      emit(const AuthUnauthenticated());
    }
  }

  Future<void> login({required String email, required String password}) async {
    emit(const AuthLoading());
    try {
      final session = await _repository.login(email: email, password: password);
      emit(AuthAuthenticated(session.user));
    } on ApiException catch (e) {
      emit(
        AuthError(
          message: e.message,
          messageKey: e.messageKey,
          fieldErrors: e.errors,
        ),
      );
    } catch (_) {
      emit(const AuthError(message: 'Something went wrong', messageKey: 'common_error'));
    }
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    emit(const AuthLoading());
    try {
      final session = await _repository.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      emit(AuthAuthenticated(session.user));
    } on ApiException catch (e) {
      emit(
        AuthError(
          message: e.message,
          messageKey: e.messageKey,
          fieldErrors: e.errors,
        ),
      );
    } catch (_) {
      emit(const AuthError(message: 'Something went wrong', messageKey: 'common_error'));
    }
  }

  Future<void> logout() async {
    emit(const AuthLoading());
    await _repository.logout();
    emit(const AuthUnauthenticated());
  }

  Future<void> refreshProfile() async {
    try {
      final user = await _repository.getProfile();
      emit(AuthAuthenticated(user));
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _repository.clearSession();
        emit(const AuthUnauthenticated());
      }
    }
  }

  Future<bool> updateProfile(Map<String, dynamic> data) async {
    final previous = currentUser;
    if (previous == null) {
      emit(const AuthUnauthenticated());
      return false;
    }
    try {
      final user = await _repository.updateProfile(data);
      emit(AuthAuthenticated(user));
      return true;
    } on ApiException catch (e) {
      emit(
        AuthError(
          message: e.message,
          messageKey: e.messageKey,
          fieldErrors: e.errors,
          sessionUser: previous,
        ),
      );
      return false;
    } catch (_) {
      emit(AuthAuthenticated(previous));
      return false;
    }
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final previous = currentUser;
    if (previous == null) {
      emit(const AuthUnauthenticated());
      return false;
    }
    try {
      final session = await _repository.changePassword(
        currentPassword: currentPassword,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      emit(AuthAuthenticated(session.user));
      return true;
    } on ApiException catch (e) {
      emit(
        AuthError(
          message: e.message,
          messageKey: e.messageKey,
          fieldErrors: e.errors,
          sessionUser: previous,
        ),
      );
      return false;
    } catch (_) {
      emit(AuthAuthenticated(previous));
      return false;
    }
  }

  void forceUnauthenticated() {
    emit(const AuthUnauthenticated());
  }

  void clearErrorToUnauthenticated() {
    if (state is AuthError && !(state as AuthError).keepsSession) {
      emit(const AuthUnauthenticated());
    }
  }

  /// Restore clean authenticated state after dismissing a session-keeping form error.
  void dismissFormError() {
    final s = state;
    if (s is AuthError && s.sessionUser != null) {
      emit(AuthAuthenticated(s.sessionUser!));
    }
  }
}
