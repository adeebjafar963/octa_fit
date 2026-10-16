import 'package:octa_fit/data/datasources/store_remote_datasource.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';

class StoreRepositoryImpl implements StoreRepository {
  StoreRepositoryImpl(this._remote);

  final StoreRemoteDataSource _remote;

  @override
  Future<List<ProductCategory>> getCategories() => _remote.fetchCategories();

  @override
  Future<ProductPage> getProducts({
    String? category,
    String? search,
    int page = 1,
  }) =>
      _remote.fetchProducts(category: category, search: search, page: page);

  @override
  Future<Product> getProduct(String idOrSlug) => _remote.fetchProduct(idOrSlug);

  @override
  Future<Cart> getCart() => _remote.fetchCart();

  @override
  Future<Cart> addToCart({required int productId, int quantity = 1}) =>
      _remote.addCartItem(productId: productId, quantity: quantity);

  @override
  Future<Cart> updateCartItem({required int cartItemId, required int quantity}) =>
      _remote.updateCartItem(cartItemId: cartItemId, quantity: quantity);

  @override
  Future<Cart> removeCartItem(int cartItemId) => _remote.removeCartItem(cartItemId);

  @override
  Future<Cart> clearCart() => _remote.clearCart();

  @override
  Future<StoreOrder> checkout({
    required String paymentMethod,
    String? paymentPhone,
    String? notes,
  }) =>
      _remote.checkout(
        paymentMethod: paymentMethod,
        paymentPhone: paymentPhone,
        notes: notes,
      );

  @override
  Future<OrderPage> getOrders({int page = 1}) => _remote.fetchOrders(page: page);

  @override
  Future<StoreOrder> getOrder(int id) => _remote.fetchOrder(id);
}
