import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/store/cubit/cart_cubit.dart';
import 'package:octa_fit/presentation/store/cubit/orders_cubit.dart';
import 'package:octa_fit/presentation/store/cubit/product_details_cubit.dart';
import 'package:octa_fit/presentation/store/cubit/store_cubit.dart';

class MockStoreRepository extends Mock implements StoreRepository {}

void main() {
  late MockStoreRepository repository;

  final category = ProductCategory(
    id: 1,
    nameEn: 'Supplements',
    nameAr: 'المكملات',
    slug: 'supplements',
  );

  final product = Product(
    id: 1,
    categoryId: 1,
    nameEn: 'Whey Protein',
    nameAr: 'بروتين مصل اللبن',
    slug: 'whey-protein',
    price: '49.99',
    stockQuantity: 10,
    inStock: true,
    category: category,
  );

  final page = ProductPage(
    items: [product],
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 1,
  );

  final cart = Cart(
    id: 1,
    items: [
      CartItem(
        id: 9,
        productId: 1,
        quantity: 1,
        unitPrice: '49.99',
        lineTotal: '49.99',
        product: product,
      ),
    ],
    itemsCount: 1,
    subtotal: '49.99',
    total: '49.99',
  );

  final emptyCart = Cart(
    id: 1,
    items: const [],
    itemsCount: 0,
    subtotal: '0.00',
    total: '0.00',
  );

  final order = StoreOrder(
    id: 3,
    orderNumber: 'OCTA-20260920-0001',
    status: 'pending',
    paymentMethod: 'demo',
    subtotal: '49.99',
    total: '49.99',
    items: const [],
  );

  setUp(() {
    repository = MockStoreRepository();
  });

  group('entity mapping', () {
    test('Product localized names', () {
      expect(product.localizedName('ar'), 'بروتين مصل اللبن');
      expect(product.localizedName('en'), 'Whey Protein');
    });

    test('Cart.fromJson maps totals', () {
      final parsed = Cart.fromJson({
        'id': 1,
        'items': [],
        'items_count': 0,
        'subtotal': '0.00',
        'total': '0.00',
      });
      expect(parsed.isEmpty, isTrue);
    });
  });

  group('StoreCubit', () {
    blocTest<StoreCubit, StoreState>(
      'loads categories and products',
      build: () {
        when(() => repository.getCategories()).thenAnswer((_) async => [category]);
        when(
          () => repository.getProducts(
            category: any(named: 'category'),
            search: any(named: 'search'),
            page: any(named: 'page'),
          ),
        ).thenAnswer((_) async => page);
        return StoreCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const StoreLoading(),
        StoreLoaded(categories: [category], products: page),
      ],
    );

    blocTest<StoreCubit, StoreState>(
      'filters by category',
      build: () {
        when(() => repository.getCategories()).thenAnswer((_) async => [category]);
        when(
          () => repository.getProducts(
            category: any(named: 'category'),
            search: any(named: 'search'),
            page: any(named: 'page'),
          ),
        ).thenAnswer((_) async => page);
        return StoreCubit(repository);
      },
      act: (cubit) => cubit.filterByCategory('supplements'),
      expect: () => [
        const StoreLoading(),
        StoreLoaded(
          categories: [category],
          products: page,
          categorySlug: 'supplements',
        ),
      ],
      verify: (_) {
        verify(
          () => repository.getProducts(
            category: 'supplements',
            search: null,
            page: 1,
          ),
        ).called(1);
      },
    );
  });

  group('ProductDetailsCubit', () {
    blocTest<ProductDetailsCubit, ProductDetailsState>(
      'loads product details',
      build: () {
        when(() => repository.getProduct('1')).thenAnswer((_) async => product);
        return ProductDetailsCubit(repository);
      },
      act: (cubit) => cubit.load('1'),
      expect: () => [
        const ProductDetailsLoading(),
        ProductDetailsLoaded(product),
      ],
    );

    blocTest<ProductDetailsCubit, ProductDetailsState>(
      'emits error on failure',
      build: () {
        when(() => repository.getProduct('9'))
            .thenThrow(ApiException('gone', messageKey: 'common_error'));
        return ProductDetailsCubit(repository);
      },
      act: (cubit) => cubit.load('9'),
      expect: () => [
        const ProductDetailsLoading(),
        const ProductDetailsError('common_error'),
      ],
    );
  });

  group('CartCubit', () {
    blocTest<CartCubit, CartState>(
      'loads cart',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        return CartCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CartLoading(),
        CartLoaded(cart),
      ],
    );

    blocTest<CartCubit, CartState>(
      'updates quantity',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        when(
          () => repository.updateCartItem(cartItemId: 9, quantity: 2),
        ).thenAnswer(
          (_) async => Cart(
            id: 1,
            items: [
              CartItem(
                id: 9,
                productId: 1,
                quantity: 2,
                unitPrice: '49.99',
                lineTotal: '99.98',
                product: product,
              ),
            ],
            itemsCount: 2,
            subtotal: '99.98',
            total: '99.98',
          ),
        );
        return CartCubit(repository);
      },
      seed: () => CartLoaded(cart),
      act: (cubit) => cubit.updateQuantity(9, 2),
      expect: () => [
        CartLoaded(cart, busy: true),
        isA<CartLoaded>().having((s) => s.cart.itemsCount, 'count', 2),
      ],
    );

    blocTest<CartCubit, CartState>(
      'empty cart state',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => emptyCart);
        return CartCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CartLoading(),
        CartLoaded(emptyCart),
      ],
      verify: (cubit) {
        final state = cubit.state;
        expect(state, isA<CartLoaded>());
        expect((state as CartLoaded).cart.isEmpty, isTrue);
      },
    );
  });

  group('CheckoutCubit', () {
    blocTest<CheckoutCubit, CheckoutState>(
      'checkout success',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        when(
          () => repository.checkout(
            paymentMethod: any(named: 'paymentMethod'),
            paymentPhone: any(named: 'paymentPhone'),
            notes: any(named: 'notes'),
          ),
        ).thenAnswer((_) async => order);
        return CheckoutCubit(repository);
      },
      act: (cubit) async {
        await cubit.load();
        await cubit.placeOrder(notes: 'Demo');
      },
      expect: () => [
        const CheckoutLoadingCart(),
        CheckoutReady(cart),
        CheckoutReady(cart, busy: true),
        CheckoutSuccess(order),
      ],
    );

    blocTest<CheckoutCubit, CheckoutState>(
      'syriatel cash without a phone stays on checkout',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        return CheckoutCubit(repository);
      },
      act: (cubit) async {
        await cubit.load();
        cubit.setPaymentMethod('syriatel_cash');
        await cubit.placeOrder();
      },
      expect: () => [
        const CheckoutLoadingCart(),
        CheckoutReady(cart),
        CheckoutReady(cart, paymentMethod: 'syriatel_cash'),
        CheckoutReady(
          cart,
          paymentMethod: 'syriatel_cash',
          errorKey: 'store_payment_phone_required',
        ),
      ],
    );

    blocTest<CheckoutCubit, CheckoutState>(
      'syriatel cash rejects an invalid phone',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        return CheckoutCubit(repository);
      },
      act: (cubit) async {
        await cubit.load();
        cubit.setPaymentMethod('syriatel_cash');
        cubit.setPaymentPhone('12345');
        await cubit.placeOrder();
      },
      expect: () => [
        const CheckoutLoadingCart(),
        CheckoutReady(cart),
        CheckoutReady(cart, paymentMethod: 'syriatel_cash'),
        CheckoutReady(cart, paymentMethod: 'syriatel_cash', paymentPhone: '12345'),
        CheckoutReady(
          cart,
          paymentMethod: 'syriatel_cash',
          paymentPhone: '12345',
          errorKey: 'store_payment_phone_invalid',
        ),
      ],
    );

    blocTest<CheckoutCubit, CheckoutState>(
      'syriatel cash with a valid phone checks out',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => cart);
        when(
          () => repository.checkout(
            paymentMethod: 'syriatel_cash',
            paymentPhone: '0933123456',
            notes: any(named: 'notes'),
          ),
        ).thenAnswer((_) async => order);
        return CheckoutCubit(repository);
      },
      act: (cubit) async {
        await cubit.load();
        cubit.setPaymentMethod('syriatel_cash');
        cubit.setPaymentPhone('0933123456');
        await cubit.placeOrder();
      },
      verify: (cubit) {
        expect(cubit.state, isA<CheckoutSuccess>());
      },
    );

    blocTest<CheckoutCubit, CheckoutState>(
      'checkout error on empty cart',
      build: () {
        when(() => repository.getCart()).thenAnswer((_) async => emptyCart);
        return CheckoutCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CheckoutLoadingCart(),
        const CheckoutError('store_cart_empty'),
      ],
    );
  });

  group('OrdersCubit', () {
    blocTest<OrdersCubit, OrdersState>(
      'loads order history',
      build: () {
        when(() => repository.getOrders(page: any(named: 'page'))).thenAnswer(
          (_) async => OrderPage(
            items: [order],
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 1,
          ),
        );
        return OrdersCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const OrdersLoading(),
        isA<OrdersLoaded>().having((s) => s.page.total, 'total', 1),
      ],
    );
  });
}
