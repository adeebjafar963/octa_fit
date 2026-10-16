import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';

sealed class ProductDetailsState extends Equatable {
  const ProductDetailsState();

  @override
  List<Object?> get props => [];
}

class ProductDetailsInitial extends ProductDetailsState {
  const ProductDetailsInitial();
}

class ProductDetailsLoading extends ProductDetailsState {
  const ProductDetailsLoading();
}

class ProductDetailsLoaded extends ProductDetailsState {
  const ProductDetailsLoaded(this.product, {this.quantity = 1, this.busy = false, this.feedbackKey});

  final Product product;
  final int quantity;
  final bool busy;
  final String? feedbackKey;

  ProductDetailsLoaded copyWith({
    Product? product,
    int? quantity,
    bool? busy,
    String? feedbackKey,
    bool clearFeedback = false,
  }) {
    return ProductDetailsLoaded(
      product ?? this.product,
      quantity: quantity ?? this.quantity,
      busy: busy ?? this.busy,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [product, quantity, busy, feedbackKey];
}

class ProductDetailsError extends ProductDetailsState {
  const ProductDetailsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class ProductDetailsCubit extends Cubit<ProductDetailsState> {
  ProductDetailsCubit(this._repository) : super(const ProductDetailsInitial());

  final StoreRepository _repository;

  Future<void> load(String idOrSlug) async {
    emit(const ProductDetailsLoading());
    try {
      final product = await _repository.getProduct(idOrSlug);
      emit(ProductDetailsLoaded(product));
    } on ApiException catch (e) {
      emit(ProductDetailsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const ProductDetailsError('common_error'));
    }
  }

  void setQuantity(int quantity) {
    final current = state;
    if (current is! ProductDetailsLoaded) return;
    final max = current.product.stockQuantity;
    if (max < 1) return;
    final next = quantity.clamp(1, max);
    emit(current.copyWith(quantity: next, clearFeedback: true));
  }

  Future<void> addToCart() async {
    final current = state;
    if (current is! ProductDetailsLoaded) return;
    if (!current.product.inStock || current.busy) return;

    emit(current.copyWith(busy: true, clearFeedback: true));
    try {
      await _repository.addToCart(
        productId: current.product.id,
        quantity: current.quantity,
      );
      emit(current.copyWith(busy: false, feedbackKey: 'store_added_to_cart'));
    } on ApiException catch (e) {
      emit(current.copyWith(
        busy: false,
        feedbackKey: e.messageKey ?? 'common_error',
      ));
    } catch (_) {
      emit(current.copyWith(busy: false, feedbackKey: 'common_error'));
    }
  }
}
