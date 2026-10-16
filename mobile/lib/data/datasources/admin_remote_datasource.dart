import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/admin.dart';

class AdminRemoteDataSource {
  AdminRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<AdminDashboardStats> fetchDashboard() async {
    final data = await _get('/admin/dashboard') as Map<String, dynamic>;
    return AdminDashboardStats.fromJson(data['stats'] as Map<String, dynamic>);
  }

  Future<AdminPaged<AdminUserItem>> fetchUsers({String? search, int page = 1}) async {
    final data = await _get('/admin/users', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return _paged(data, AdminUserItem.fromJson);
  }

  Future<AdminUserItem> updateUserRole(int id, String role) async {
    final data = await _put('/admin/users/$id/role', {'role': role});
    return AdminUserItem.fromJson(data as Map<String, dynamic>);
  }

  Future<AdminUserItem> updateUserStatus(int id, bool isActive) async {
    final data = await _put('/admin/users/$id/status', {'is_active': isActive});
    return AdminUserItem.fromJson(data as Map<String, dynamic>);
  }

  Future<List<Map<String, dynamic>>> fetchTrainingCategories() async {
    final data = await _get('/admin/training/categories') as Map<String, dynamic>;
    return (data['items'] as List<dynamic>? ?? []).whereType<Map<String, dynamic>>().toList();
  }

  Future<Map<String, dynamic>> createTrainingCategory(Map<String, dynamic> body) async {
    return await _post('/admin/training/categories', body) as Map<String, dynamic>;
  }

  Future<void> deleteTrainingCategory(int id) => _delete('/admin/training/categories/$id');

  Future<AdminPaged<Map<String, dynamic>>> fetchExercises({String? search, int page = 1}) async {
    final data = await _get('/admin/training/exercises', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return _pagedMap(data);
  }

  Future<Map<String, dynamic>> createExercise(Map<String, dynamic> body) async {
    return await _post('/admin/training/exercises', body) as Map<String, dynamic>;
  }

  Future<void> deleteExercise(int id) => _delete('/admin/training/exercises/$id');

  Future<List<Map<String, dynamic>>> fetchProductCategories() async {
    final data = await _get('/admin/store/categories') as Map<String, dynamic>;
    return (data['items'] as List<dynamic>? ?? []).whereType<Map<String, dynamic>>().toList();
  }

  Future<AdminPaged<Map<String, dynamic>>> fetchProducts({String? search, int page = 1}) async {
    final data = await _get('/admin/store/products', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return _pagedMap(data);
  }

  Future<Map<String, dynamic>> createProduct(Map<String, dynamic> body) async {
    return await _post('/admin/store/products', body) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateProduct(int id, Map<String, dynamic> body) async {
    return await _put('/admin/store/products/$id', body) as Map<String, dynamic>;
  }

  Future<void> deleteProduct(int id) => _delete('/admin/store/products/$id');

  Future<AdminPaged<Map<String, dynamic>>> fetchOrders({String? search, int page = 1}) async {
    final data = await _get('/admin/orders', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return _pagedMap(data);
  }

  Future<Map<String, dynamic>> fetchOrder(int id) async {
    return await _get('/admin/orders/$id') as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateOrderStatus(int id, String status) async {
    return await _put('/admin/orders/$id/status', {'status': status}) as Map<String, dynamic>;
  }

  Future<AdminPaged<Map<String, dynamic>>> fetchPosts({String? search, int page = 1}) async {
    final data = await _get('/admin/community/posts', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return _pagedMap(data);
  }

  Future<void> deletePost(int id) => _delete('/admin/community/posts/$id');

  Future<List<AdminSettingItem>> fetchSettings() async {
    final data = await _get('/admin/settings') as Map<String, dynamic>;
    return (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(AdminSettingItem.fromJson)
        .toList();
  }

  Future<List<AdminSettingItem>> updateSettings(Map<String, String?> settings) async {
    final data = await _put('/admin/settings', {'settings': settings}) as Map<String, dynamic>;
    return (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(AdminSettingItem.fromJson)
        .toList();
  }

  AdminPaged<Map<String, dynamic>> _pagedMap(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return AdminPaged(
      items: items,
      total: meta['total'] as int? ?? items.length,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
    );
  }

  AdminPaged<T> _paged<T>(
    Map<String, dynamic> data,
    T Function(Map<String, dynamic>) mapper,
  ) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(mapper)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return AdminPaged(
      items: items,
      total: meta['total'] as int? ?? items.length,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
    );
  }

  Future<dynamic> _get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dioClient.client.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.post<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<dynamic> _put(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dioClient.client.put<Map<String, dynamic>>(path, data: body);
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<void> _delete(String path) async {
    try {
      await _dioClient.client.delete<Map<String, dynamic>>(path);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  dynamic _unwrap(Map<String, dynamic>? envelope) {
    if (envelope == null) {
      throw ApiException('Empty response', messageKey: 'common_error');
    }
    return envelope['data'];
  }
}
