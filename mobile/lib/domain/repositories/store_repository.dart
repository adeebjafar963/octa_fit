import 'package:octa_fit/domain/entities/store.dart';

abstract class StoreRepository {
  Future<List<ProductCategory>> getCategories();

  Future<ProductPage> getProducts({
    String? category,
    String? search,
    int page = 1,
  });

  Future<Product> getProduct(String idOrSlug);

  Future<Cart> getCart();

  Future<Cart> addToCart({required int productId, int quantity = 1});

  Future<Cart> updateCartItem({required int cartItemId, required int quantity});

  Future<Cart> removeCartItem(int cartItemId);

  Future<Cart> clearCart();

  Future<StoreOrder> checkout({
    required String paymentMethod,
    String? paymentPhone,
    String? notes,
  });

  Future<OrderPage> getOrders({int page = 1});

  Future<StoreOrder> getOrder(int id);
}
