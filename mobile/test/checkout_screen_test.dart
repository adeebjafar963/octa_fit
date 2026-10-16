import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/store/checkout_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MockStoreRepository extends Mock implements StoreRepository {}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late MockStoreRepository repository;

  final cart = Cart(
    id: 1,
    items: [
      CartItem(
        id: 9,
        productId: 1,
        quantity: 1,
        unitPrice: '14.99',
        lineTotal: '14.99',
      ),
    ],
    itemsCount: 1,
    subtotal: '14.99',
    total: '14.99',
  );

  final order = StoreOrder(
    id: 4,
    orderNumber: 'OCTA-20260924-0001',
    status: 'pending',
    paymentMethod: 'syriatel_cash',
    paymentPhone: '0933123456',
    subtotal: '14.99',
    total: '14.99',
  );

  setUpAll(() async {
    SharedPreferences.setMockInitialValues({});
    await EasyLocalization.ensureInitialized();
  });

  setUp(() {
    repository = MockStoreRepository();
    when(() => repository.getCart()).thenAnswer((_) async => cart);
  });

  Future<void> pumpCheckout(WidgetTester tester, Locale locale) async {
    await tester.binding.setSurfaceSize(const Size(1200, 1600));
    await tester.pumpWidget(
      EasyLocalization(
        supportedLocales: const [Locale('en'), Locale('ar')],
        path: 'assets/translations',
        fallbackLocale: const Locale('en'),
        startLocale: locale,
        saveLocale: false,
        child: Builder(
          builder: (context) {
            return MaterialApp(
              theme: AppTheme.dark(),
              locale: context.locale,
              supportedLocales: context.supportedLocales,
              localizationsDelegates: context.localizationDelegates,
              home: CheckoutScreen(repository: repository),
            );
          },
        ),
      ),
    );
    for (var i = 0; i < 30; i++) {
      await tester.pump(const Duration(milliseconds: 100));
      if (find.byKey(const Key('checkout-payment-cod')).evaluate().isNotEmpty) {
        return;
      }
    }
    fail('Checkout payment options did not appear');
  }

  testWidgets('english checkout validates syriatel cash and confirms a valid number', (tester) async {
    when(
      () => repository.checkout(
        paymentMethod: 'syriatel_cash',
        paymentPhone: '0933123456',
        notes: any(named: 'notes'),
      ),
    ).thenAnswer((_) async => order);

    await pumpCheckout(tester, const Locale('en'));
    expect(find.text('Cash on delivery'), findsOneWidget);
    expect(find.text('Confirm Order & Invoice'), findsOneWidget);
    expect(find.byKey(const Key('checkout-payment-phone')), findsNothing);

    final syriatel = find.byKey(const Key('checkout-payment-syriatel'), skipOffstage: false);
    await tester.ensureVisible(syriatel);
    await tester.tap(syriatel);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('checkout-payment-phone')), findsOneWidget);
    expect(find.text('Syriatel Cash Mobile Number'), findsOneWidget);

    final confirm = find.text('Confirm Order & Invoice', skipOffstage: false);
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(find.text('Mobile number is required'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('checkout-payment-phone'), skipOffstage: false), '12345');
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(find.text('Invalid mobile number'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('checkout-payment-phone'), skipOffstage: false), '0933123456');
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(find.text('Order Confirmed Successfully'), findsOneWidget);
    expect(find.textContaining('09******56'), findsOneWidget);
  });

  testWidgets('a valid syriatel number confirms the order', (tester) async {
    when(
      () => repository.checkout(
        paymentMethod: 'syriatel_cash',
        paymentPhone: '0933123456',
        notes: any(named: 'notes'),
      ),
    ).thenAnswer((_) async => order);

    await pumpCheckout(tester, const Locale('ar'));
    expect(find.text('الدفع عند الاستلام'), findsOneWidget);
    expect(find.text('سيرياتيل كاش'), findsOneWidget);

    final syriatel = find.byKey(const Key('checkout-payment-syriatel'), skipOffstage: false);
    await tester.ensureVisible(syriatel);
    await tester.tap(syriatel);
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('checkout-payment-phone')), '0933123456');
    final confirm = find.text('تثبيت الطلب والفاتورة', skipOffstage: false);
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.pumpAndSettle();

    expect(find.text('تم تثبيت الطلب بنجاح'), findsOneWidget);
    expect(find.textContaining('09******56'), findsOneWidget);
    expect(find.textContaining('OCTA-20260924-0001'), findsOneWidget);
    expect(find.textContaining('سيرياتيل كاش'), findsWidgets);
  });
}
