import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/admin.dart';
import 'package:octa_fit/domain/entities/auth_user.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/admin/cubit/admin_dashboard_cubit.dart';
import 'package:octa_fit/presentation/admin/cubit/admin_users_cubit.dart';

class MockAdminRepository extends Mock implements AdminRepository {}

void main() {
  late MockAdminRepository repository;

  const stats = AdminDashboardStats(
    totalUsers: 5,
    totalAdmins: 1,
    totalExercises: 10,
    totalProducts: 8,
    activeProducts: 7,
    totalOrders: 3,
    totalPosts: 4,
    totalCoachConversations: 2,
    totalPhysioAssessments: 1,
  );

  final user = AdminUserItem(
    id: 2,
    name: 'Demo',
    email: 'demo@octafit.test',
    isActive: true,
    roleName: 'user',
    roleDisplayName: 'User',
  );

  setUp(() {
    repository = MockAdminRepository();
  });

  test('AuthUser.isAdmin reflects role', () {
    const admin = AuthUser(
      id: 1,
      name: 'Admin',
      email: 'a@test.com',
      isActive: true,
      role: UserRole(id: 1, name: 'admin', displayName: 'Administrator'),
    );
    const normal = AuthUser(
      id: 2,
      name: 'User',
      email: 'u@test.com',
      isActive: true,
      role: UserRole(id: 2, name: 'user'),
    );
    expect(admin.isAdmin, isTrue);
    expect(normal.isAdmin, isFalse);
  });

  group('AdminDashboardCubit', () {
    blocTest<AdminDashboardCubit, AdminDashboardState>(
      'loads dashboard stats',
      build: () {
        when(() => repository.getDashboard()).thenAnswer((_) async => stats);
        return AdminDashboardCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const AdminDashboardLoading(),
        const AdminDashboardLoaded(stats),
      ],
    );

    blocTest<AdminDashboardCubit, AdminDashboardState>(
      'emits error',
      build: () {
        when(() => repository.getDashboard()).thenThrow(
          ApiException('denied', messageKey: 'admin_access_denied'),
        );
        return AdminDashboardCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const AdminDashboardLoading(),
        const AdminDashboardError('admin_access_denied'),
      ],
    );
  });

  group('AdminUsersCubit', () {
    blocTest<AdminUsersCubit, AdminUsersState>(
      'loads users',
      build: () {
        when(() => repository.getUsers(search: any(named: 'search'), page: any(named: 'page')))
            .thenAnswer(
          (_) async => AdminPaged(
            items: [user],
            total: 1,
            currentPage: 1,
            lastPage: 1,
          ),
        );
        return AdminUsersCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const AdminUsersLoading(),
        AdminUsersLoaded([user]),
      ],
    );
  });
}
