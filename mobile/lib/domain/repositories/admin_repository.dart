import 'package:octa_fit/domain/entities/admin.dart';

abstract class AdminRepository {
  Future<AdminDashboardStats> getDashboard();

  Future<AdminPaged<AdminUserItem>> getUsers({String? search, int page = 1});

  Future<AdminUserItem> updateUserRole(int id, String role);

  Future<AdminUserItem> updateUserStatus(int id, bool isActive);

  Future<List<Map<String, dynamic>>> getTrainingCategories();

  Future<Map<String, dynamic>> createTrainingCategory(Map<String, dynamic> body);

  Future<void> deleteTrainingCategory(int id);

  Future<AdminPaged<Map<String, dynamic>>> getExercises({String? search, int page = 1});

  Future<Map<String, dynamic>> createExercise(Map<String, dynamic> body);

  Future<void> deleteExercise(int id);

  Future<List<Map<String, dynamic>>> getProductCategories();

  Future<AdminPaged<Map<String, dynamic>>> getProducts({String? search, int page = 1});

  Future<Map<String, dynamic>> createProduct(Map<String, dynamic> body);

  Future<Map<String, dynamic>> updateProduct(int id, Map<String, dynamic> body);

  Future<void> deleteProduct(int id);

  Future<AdminPaged<Map<String, dynamic>>> getOrders({String? search, int page = 1});

  Future<Map<String, dynamic>> getOrder(int id);

  Future<Map<String, dynamic>> updateOrderStatus(int id, String status);

  Future<AdminPaged<Map<String, dynamic>>> getPosts({String? search, int page = 1});

  Future<void> deletePost(int id);

  Future<List<AdminSettingItem>> getSettings();

  Future<List<AdminSettingItem>> updateSettings(Map<String, String?> settings);
}
