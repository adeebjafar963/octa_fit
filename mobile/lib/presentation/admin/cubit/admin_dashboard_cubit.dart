import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/admin.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';

sealed class AdminDashboardState extends Equatable {
  const AdminDashboardState();

  @override
  List<Object?> get props => [];
}

class AdminDashboardInitial extends AdminDashboardState {
  const AdminDashboardInitial();
}

class AdminDashboardLoading extends AdminDashboardState {
  const AdminDashboardLoading();
}

class AdminDashboardLoaded extends AdminDashboardState {
  const AdminDashboardLoaded(this.stats);

  final AdminDashboardStats stats;

  @override
  List<Object?> get props => [stats];
}

class AdminDashboardError extends AdminDashboardState {
  const AdminDashboardError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class AdminDashboardCubit extends Cubit<AdminDashboardState> {
  AdminDashboardCubit(this._repository) : super(const AdminDashboardInitial());

  final AdminRepository _repository;

  Future<void> load() async {
    emit(const AdminDashboardLoading());
    try {
      final stats = await _repository.getDashboard();
      emit(AdminDashboardLoaded(stats));
    } on ApiException catch (e) {
      emit(AdminDashboardError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const AdminDashboardError('common_error'));
    }
  }
}
