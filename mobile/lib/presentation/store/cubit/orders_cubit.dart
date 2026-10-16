import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/store/store_payment.dart';

sealed class OrdersState extends Equatable {
  const OrdersState();

  @override
  List<Object?> get props => [];
}

class OrdersInitial extends OrdersState {
  const OrdersInitial();
}

class OrdersLoading extends OrdersState {
  const OrdersLoading();
}

class OrdersLoaded extends OrdersState {
  const OrdersLoaded(this.page);

  final OrderPage page;

  @override
  List<Object?> get props => [page];
}

class OrdersError extends OrdersState {
  const OrdersError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class OrdersCubit extends Cubit<OrdersState> {
  OrdersCubit(this._repository) : super(const OrdersInitial());

  final StoreRepository _repository;

  Future<void> load() async {
    emit(const OrdersLoading());
    try {
      final page = await _repository.getOrders();
      emit(OrdersLoaded(page));
    } on ApiException catch (e) {
      emit(OrdersError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const OrdersError('common_error'));
    }
  }
}

sealed class OrderDetailState extends Equatable {
  const OrderDetailState();

  @override
  List<Object?> get props => [];
}

class OrderDetailInitial extends OrderDetailState {
  const OrderDetailInitial();
}

class OrderDetailLoading extends OrderDetailState {
  const OrderDetailLoading();
}

class OrderDetailLoaded extends OrderDetailState {
  const OrderDetailLoaded(this.order);

  final StoreOrder order;

  @override
  List<Object?> get props => [order];
}

class OrderDetailError extends OrderDetailState {
  const OrderDetailError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class OrderDetailCubit extends Cubit<OrderDetailState> {
  OrderDetailCubit(this._repository) : super(const OrderDetailInitial());

  final StoreRepository _repository;

  Future<void> load(int id) async {
    emit(const OrderDetailLoading());
    try {
      final order = await _repository.getOrder(id);
      emit(OrderDetailLoaded(order));
    } on ApiException catch (e) {
      emit(OrderDetailError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const OrderDetailError('common_error'));
    }
  }
}

sealed class CheckoutState extends Equatable {
  const CheckoutState();

  @override
  List<Object?> get props => [];
}

class CheckoutInitial extends CheckoutState {
  const CheckoutInitial();
}

class CheckoutLoadingCart extends CheckoutState {
  const CheckoutLoadingCart();
}

class CheckoutReady extends CheckoutState {
  const CheckoutReady(
    this.cart, {
    this.paymentMethod = 'cash_on_delivery',
    this.paymentPhone = '',
    this.busy = false,
    this.errorKey,
  });

  final Cart cart;
  final String paymentMethod;
  final String paymentPhone;
  final bool busy;
  final String? errorKey;

  CheckoutReady copyWith({
    Cart? cart,
    String? paymentMethod,
    String? paymentPhone,
    bool? busy,
    String? errorKey,
    bool clearError = false,
  }) {
    return CheckoutReady(
      cart ?? this.cart,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentPhone: paymentPhone ?? this.paymentPhone,
      busy: busy ?? this.busy,
      errorKey: clearError ? null : (errorKey ?? this.errorKey),
    );
  }

  @override
  List<Object?> get props => [cart, paymentMethod, paymentPhone, busy, errorKey];
}

class CheckoutSuccess extends CheckoutState {
  const CheckoutSuccess(this.order);

  final StoreOrder order;

  @override
  List<Object?> get props => [order];
}

class CheckoutError extends CheckoutState {
  const CheckoutError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CheckoutCubit extends Cubit<CheckoutState> {
  CheckoutCubit(this._repository) : super(const CheckoutInitial());

  final StoreRepository _repository;

  Future<void> load() async {
    emit(const CheckoutLoadingCart());
    try {
      final cart = await _repository.getCart();
      if (cart.isEmpty) {
        emit(const CheckoutError('store_cart_empty'));
        return;
      }
      emit(CheckoutReady(cart));
    } on ApiException catch (e) {
      emit(CheckoutError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CheckoutError('common_error'));
    }
  }

  void setPaymentMethod(String method) {
    final current = state;
    if (current is! CheckoutReady) return;
    emit(current.copyWith(paymentMethod: method, clearError: true));
  }

  void setPaymentPhone(String phone) {
    final current = state;
    if (current is! CheckoutReady) return;
    emit(current.copyWith(paymentPhone: phone, clearError: true));
  }

  Future<void> placeOrder({String? notes}) async {
    final current = state;
    if (current is! CheckoutReady || current.busy) return;

    String? phone;
    if (current.paymentMethod == 'syriatel_cash') {
      final errorKey = syriatelCashPhoneErrorKey(current.paymentPhone);
      if (errorKey != null) {
        emit(current.copyWith(errorKey: errorKey));
        return;
      }
      phone = current.paymentPhone.trim();
    }

    emit(current.copyWith(busy: true, clearError: true));
    try {
      final order = await _repository.checkout(
        paymentMethod: current.paymentMethod,
        paymentPhone: phone,
        notes: notes,
      );
      emit(CheckoutSuccess(order));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, errorKey: _checkoutErrorKey(e)));
    } catch (_) {
      emit(current.copyWith(busy: false, errorKey: 'common_error'));
    }
  }

  String _checkoutErrorKey(ApiException error) {
    final phoneErrors = error.errors?['payment_phone'];
    if (phoneErrors != null && phoneErrors.isNotEmpty) {
      final text = phoneErrors.first.toLowerCase();
      if (text.contains('required')) return 'store_payment_phone_required';
      return 'store_payment_phone_invalid';
    }
    return error.messageKey ?? 'common_error';
  }
}
