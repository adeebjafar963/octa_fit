import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/admin.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';

sealed class AdminUsersState extends Equatable {
  const AdminUsersState();

  @override
  List<Object?> get props => [];
}

class AdminUsersLoading extends AdminUsersState {
  const AdminUsersLoading();
}

class AdminUsersLoaded extends AdminUsersState {
  const AdminUsersLoaded(this.users, {this.feedbackKey});

  final List<AdminUserItem> users;
  final String? feedbackKey;

  @override
  List<Object?> get props => [users, feedbackKey];
}

class AdminUsersError extends AdminUsersState {
  const AdminUsersError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class AdminUsersCubit extends Cubit<AdminUsersState> {
  AdminUsersCubit(this._repository) : super(const AdminUsersLoading());

  final AdminRepository _repository;

  Future<void> load({String? search}) async {
    emit(const AdminUsersLoading());
    try {
      final page = await _repository.getUsers(search: search);
      emit(AdminUsersLoaded(page.items));
    } on ApiException catch (e) {
      emit(AdminUsersError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const AdminUsersError('common_error'));
    }
  }

  Future<void> setRole(int id, String role) async {
    try {
      await _repository.updateUserRole(id, role);
      await load();
    } on ApiException catch (e) {
      final current = state;
      if (current is AdminUsersLoaded) {
        emit(AdminUsersLoaded(current.users, feedbackKey: e.messageKey ?? 'common_error'));
      } else {
        emit(AdminUsersError(e.messageKey ?? 'common_error'));
      }
    }
  }

  Future<void> setStatus(int id, bool active) async {
    try {
      await _repository.updateUserStatus(id, active);
      await load();
    } on ApiException catch (e) {
      final current = state;
      if (current is AdminUsersLoaded) {
        emit(AdminUsersLoaded(current.users, feedbackKey: e.messageKey ?? 'common_error'));
      }
    }
  }
}
