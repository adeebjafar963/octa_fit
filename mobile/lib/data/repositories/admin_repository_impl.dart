import 'package:octa_fit/data/datasources/admin_remote_datasource.dart';
import 'package:octa_fit/domain/entities/admin.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';

class AdminRepositoryImpl implements AdminRepository {
  AdminRepositoryImpl(this._remote);

  final AdminRemoteDataSource _remote;

  @override
  Future<AdminDashboardStats> getDashboard() => _remote.fetchDashboard();

  @override
  Future<AdminPaged<AdminUserItem>> getUsers({String? search, int page = 1}) =>
      _remote.fetchUsers(search: search, page: page);

  @override
  Future<AdminUserItem> updateUserRole(int id, String role) =>
      _remote.updateUserRole(id, role);

  @override
  Future<AdminUserItem> updateUserStatus(int id, bool isActive) =>
      _remote.updateUserStatus(id, isActive);

  @override
  Future<List<Map<String, dynamic>>> getTrainingCategories() =>
      _remote.fetchTrainingCategories();

  @override
  Future<Map<String, dynamic>> createTrainingCategory(Map<String, dynamic> body) =>
      _remote.createTrainingCategory(body);

  @override
  Future<void> deleteTrainingCategory(int id) => _remote.deleteTrainingCategory(id);

  @override
  Future<AdminPaged<Map<String, dynamic>>> getExercises({String? search, int page = 1}) =>
      _remote.fetchExercises(search: search, page: page);

  @override
  Future<Map<String, dynamic>> createExercise(Map<String, dynamic> body) =>
      _remote.createExercise(body);

  @override
  Future<void> deleteExercise(int id) => _remote.deleteExercise(id);

  @override
  Future<List<Map<String, dynamic>>> getProductCategories() =>
      _remote.fetchProductCategories();

  @override
  Future<AdminPaged<Map<String, dynamic>>> getProducts({String? search, int page = 1}) =>
      _remote.fetchProducts(search: search, page: page);

  @override
  Future<Map<String, dynamic>> createProduct(Map<String, dynamic> body) =>
      _remote.createProduct(body);

  @override
  Future<Map<String, dynamic>> updateProduct(int id, Map<String, dynamic> body) =>
      _remote.updateProduct(id, body);

  @override
  Future<void> deleteProduct(int id) => _remote.deleteProduct(id);

  @override
  Future<AdminPaged<Map<String, dynamic>>> getOrders({String? search, int page = 1}) =>
      _remote.fetchOrders(search: search, page: page);

  @override
  Future<Map<String, dynamic>> getOrder(int id) => _remote.fetchOrder(id);

  @override
  Future<Map<String, dynamic>> updateOrderStatus(int id, String status) =>
      _remote.updateOrderStatus(id, status);

  @override
  Future<AdminPaged<Map<String, dynamic>>> getPosts({String? search, int page = 1}) =>
      _remote.fetchPosts(search: search, page: page);

  @override
  Future<void> deletePost(int id) => _remote.deletePost(id);

  @override
  Future<List<AdminSettingItem>> getSettings() => _remote.fetchSettings();

  @override
  Future<List<AdminSettingItem>> updateSettings(Map<String, String?> settings) =>
      _remote.updateSettings(settings);
}
