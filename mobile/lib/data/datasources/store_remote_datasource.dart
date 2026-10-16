import 'package:dio/dio.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/network/auth_interceptor.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/domain/entities/store.dart';

class StoreRemoteDataSource {
  StoreRemoteDataSource(this._dioClient);

  final DioClient _dioClient;

  Future<List<ProductCategory>> fetchCategories() async {
    final data = await _get('/store/categories');
    final list = data as List<dynamic>? ?? [];
    return list.whereType<Map<String, dynamic>>().map(ProductCategory.fromJson).toList();
  }

  Future<ProductPage> fetchProducts({
    String? category,
    String? search,
    int page = 1,
  }) async {
    final query = <String, dynamic>{'page': page};
    if (category != null && category.isNotEmpty) query['category'] = category;
    if (search != null && search.isNotEmpty) query['search'] = search;
    final data = await _get('/store/products', query: query);
    return _productPageFrom(data as Map<String, dynamic>);
  }

  Future<Product> fetchProduct(String idOrSlug) async {
    final data = await _get('/store/products/$idOrSlug');
    return Product.fromJson(data as Map<String, dynamic>);
  }

  Future<Cart> fetchCart() async {
    final data = await _get('/store/cart');
    return Cart.fromJson(data as Map<String, dynamic>);
  }

  Future<Cart> addCartItem({required int productId, int quantity = 1}) async {
    final data = await _post('/store/cart/items', {
      'product_id': productId,
      'quantity': quantity,
    });
    return Cart.fromJson(data as Map<String, dynamic>);
  }

  Future<Cart> updateCartItem({required int cartItemId, required int quantity}) async {
    final data = await _put('/store/cart/items/$cartItemId', {'quantity': quantity});
    return Cart.fromJson(data as Map<String, dynamic>);
  }

  Future<Cart> removeCartItem(int cartItemId) async {
    final data = await _delete('/store/cart/items/$cartItemId');
    return Cart.fromJson(data as Map<String, dynamic>);
  }

  Future<Cart> clearCart() async {
    final data = await _delete('/store/cart');
    return Cart.fromJson(data as Map<String, dynamic>);
  }

  Future<StoreOrder> checkout({
    required String paymentMethod,
    String? paymentPhone,
    String? notes,
  }) async {
    final body = <String, dynamic>{'payment_method': paymentMethod};
    if (paymentPhone != null && paymentPhone.isNotEmpty) {
      body['payment_phone'] = paymentPhone;
    }
    if (notes != null && notes.isNotEmpty) body['notes'] = notes;
    final data = await _post('/store/orders', body, created: true);
    return StoreOrder.fromJson(data as Map<String, dynamic>);
  }

  Future<OrderPage> fetchOrders({int page = 1}) async {
    final data = await _get('/store/orders', query: {'page': page});
    return _orderPageFrom(data as Map<String, dynamic>);
  }

  Future<StoreOrder> fetchOrder(int id) async {
    final data = await _get('/store/orders/$id');
    return StoreOrder.fromJson(data as Map<String, dynamic>);
  }

  ProductPage _productPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(Product.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return ProductPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
    );
  }

  OrderPage _orderPageFrom(Map<String, dynamic> data) {
    final items = (data['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(StoreOrder.fromJson)
        .toList();
    final meta = data['meta'] as Map<String, dynamic>? ?? {};
    return OrderPage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? items.length,
      total: meta['total'] as int? ?? items.length,
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

  Future<dynamic> _post(String path, Map<String, dynamic> body, {bool created = false}) async {
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

  Future<dynamic> _delete(String path) async {
    try {
      final response = await _dioClient.client.delete<Map<String, dynamic>>(path);
      return _unwrap(response.data);
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
