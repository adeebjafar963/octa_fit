import 'package:equatable/equatable.dart';

class ProductCategory extends Equatable {
  const ProductCategory({
    required this.id,
    required this.nameEn,
    required this.nameAr,
    required this.slug,
    this.descriptionEn,
    this.descriptionAr,
    this.imageUrl,
    this.productsCount,
    this.sortOrder = 0,
  });

  factory ProductCategory.fromJson(Map<String, dynamic> json) {
    return ProductCategory(
      id: json['id'] as int,
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      descriptionEn: json['description_en'] as String?,
      descriptionAr: json['description_ar'] as String?,
      imageUrl: json['image_url'] as String?,
      productsCount: json['products_count'] as int?,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }

  final int id;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String? descriptionEn;
  final String? descriptionAr;
  final String? imageUrl;
  final int? productsCount;
  final int sortOrder;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  String? localizedDescription(String locale) =>
      locale.startsWith('ar') ? descriptionAr : descriptionEn;

  @override
  List<Object?> get props => [id, slug];
}

class Product extends Equatable {
  const Product({
    required this.id,
    required this.categoryId,
    required this.nameEn,
    required this.nameAr,
    required this.slug,
    required this.price,
    required this.stockQuantity,
    required this.inStock,
    this.descriptionEn,
    this.descriptionAr,
    this.compareAtPrice,
    this.imageUrl,
    this.sku,
    this.isDemo = true,
    this.category,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      categoryId: json['category_id'] as int? ?? 0,
      nameEn: json['name_en'] as String? ?? '',
      nameAr: json['name_ar'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      descriptionEn: json['description_en'] as String?,
      descriptionAr: json['description_ar'] as String?,
      price: _money(json['price']),
      compareAtPrice: json['compare_at_price'] == null ? null : _money(json['compare_at_price']),
      stockQuantity: json['stock_quantity'] as int? ?? 0,
      inStock: json['in_stock'] as bool? ?? ((json['stock_quantity'] as int? ?? 0) > 0),
      imageUrl: json['image_url'] as String?,
      sku: json['sku'] as String?,
      isDemo: json['is_demo'] as bool? ?? true,
      category: json['category'] is Map<String, dynamic>
          ? ProductCategory.fromJson(json['category'] as Map<String, dynamic>)
          : null,
    );
  }

  final int id;
  final int categoryId;
  final String nameEn;
  final String nameAr;
  final String slug;
  final String? descriptionEn;
  final String? descriptionAr;
  final String price;
  final String? compareAtPrice;
  final int stockQuantity;
  final bool inStock;
  final String? imageUrl;
  final String? sku;
  final bool isDemo;
  final ProductCategory? category;

  String localizedName(String locale) => locale.startsWith('ar') ? nameAr : nameEn;

  String? localizedDescription(String locale) =>
      locale.startsWith('ar') ? descriptionAr : descriptionEn;

  @override
  List<Object?> get props => [id, slug, price, stockQuantity];
}

class ProductPage extends Equatable {
  const ProductPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<Product> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

class CartItem extends Equatable {
  const CartItem({
    required this.id,
    required this.productId,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
    this.product,
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      quantity: json['quantity'] as int? ?? 1,
      unitPrice: _money(json['unit_price']),
      lineTotal: _money(json['line_total']),
      product: json['product'] is Map<String, dynamic>
          ? Product.fromJson(json['product'] as Map<String, dynamic>)
          : null,
    );
  }

  final int id;
  final int productId;
  final int quantity;
  final String unitPrice;
  final String lineTotal;
  final Product? product;

  int get maxStock => product?.stockQuantity ?? quantity;

  @override
  List<Object?> get props => [id, productId, quantity, unitPrice, lineTotal];
}

class Cart extends Equatable {
  const Cart({
    required this.id,
    required this.items,
    required this.itemsCount,
    required this.subtotal,
    required this.total,
  });

  factory Cart.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'];
    return Cart(
      id: json['id'] as int,
      items: itemsJson is List
          ? itemsJson.whereType<Map<String, dynamic>>().map(CartItem.fromJson).toList()
          : const [],
      itemsCount: json['items_count'] as int? ?? 0,
      subtotal: _money(json['subtotal']),
      total: _money(json['total']),
    );
  }

  final int id;
  final List<CartItem> items;
  final int itemsCount;
  final String subtotal;
  final String total;

  bool get isEmpty => items.isEmpty || itemsCount == 0;

  @override
  List<Object?> get props => [id, items, itemsCount, subtotal, total];
}

class OrderItem extends Equatable {
  const OrderItem({
    required this.id,
    required this.productNameEn,
    required this.productNameAr,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
    this.productId,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: json['id'] as int,
      productId: json['product_id'] as int?,
      productNameEn: json['product_name_en'] as String? ?? '',
      productNameAr: json['product_name_ar'] as String? ?? '',
      quantity: json['quantity'] as int? ?? 1,
      unitPrice: _money(json['unit_price']),
      lineTotal: _money(json['line_total']),
    );
  }

  final int id;
  final int? productId;
  final String productNameEn;
  final String productNameAr;
  final int quantity;
  final String unitPrice;
  final String lineTotal;

  String localizedName(String locale) =>
      locale.startsWith('ar') ? productNameAr : productNameEn;

  @override
  List<Object?> get props => [id, productId, quantity, lineTotal];
}

class StoreOrder extends Equatable {
  const StoreOrder({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.paymentMethod,
    required this.subtotal,
    required this.total,
    this.paymentPhone,
    this.notes,
    this.createdAt,
    this.items = const [],
  });

  factory StoreOrder.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'];
    return StoreOrder(
      id: json['id'] as int,
      orderNumber: json['order_number'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      paymentMethod: json['payment_method'] as String? ?? 'cash_on_delivery',
      paymentPhone: json['payment_phone'] as String?,
      subtotal: _money(json['subtotal']),
      total: _money(json['total']),
      notes: json['notes'] as String?,
      createdAt: json['created_at'] as String?,
      items: itemsJson is List
          ? itemsJson.whereType<Map<String, dynamic>>().map(OrderItem.fromJson).toList()
          : const [],
    );
  }

  final int id;
  final String orderNumber;
  final String status;
  final String paymentMethod;
  final String? paymentPhone;
  final String subtotal;
  final String total;
  final String? notes;
  final String? createdAt;
  final List<OrderItem> items;

  @override
  List<Object?> get props => [id, orderNumber, status, total];
}

class OrderPage extends Equatable {
  const OrderPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final List<StoreOrder> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  @override
  List<Object?> get props => [items, currentPage, total];
}

String _money(dynamic value) {
  if (value == null) return '0.00';
  if (value is num) return value.toStringAsFixed(2);
  final text = value.toString();
  final parsed = double.tryParse(text);
  return parsed?.toStringAsFixed(2) ?? text;
}
