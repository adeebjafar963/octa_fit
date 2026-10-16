import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';

sealed class CartState extends Equatable {
  const CartState();

  @override
  List<Object?> get props => [];
}

class CartInitial extends CartState {
  const CartInitial();
}

class CartLoading extends CartState {
  const CartLoading();
}

class CartLoaded extends CartState {
  const CartLoaded(this.cart, {this.busy = false, this.feedbackKey});

  final Cart cart;
  final bool busy;
  final String? feedbackKey;

  CartLoaded copyWith({Cart? cart, bool? busy, String? feedbackKey, bool clearFeedback = false}) {
    return CartLoaded(
      cart ?? this.cart,
      busy: busy ?? this.busy,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [cart, busy, feedbackKey];
}

class CartError extends CartState {
  const CartError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class CartCubit extends Cubit<CartState> {
  CartCubit(this._repository) : super(const CartInitial());

  final StoreRepository _repository;

  Future<void> load() async {
    emit(const CartLoading());
    try {
      final cart = await _repository.getCart();
      emit(CartLoaded(cart));
    } on ApiException catch (e) {
      emit(CartError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const CartError('common_error'));
    }
  }

  Future<void> updateQuantity(int cartItemId, int quantity) async {
    final current = state;
    if (current is! CartLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      final cart = await _repository.updateCartItem(
        cartItemId: cartItemId,
        quantity: quantity,
      );
      emit(CartLoaded(cart));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }

  Future<void> removeItem(int cartItemId) async {
    final current = state;
    if (current is! CartLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      final cart = await _repository.removeCartItem(cartItemId);
      emit(CartLoaded(cart, feedbackKey: 'store_item_removed'));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }

  Future<void> clear() async {
    final current = state;
    if (current is! CartLoaded || current.busy) return;
    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      final cart = await _repository.clearCart();
      emit(CartLoaded(cart));
    } on ApiException catch (e) {
      emit(current.copyWith(busy: false, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }
}
