import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';

sealed class StoreState extends Equatable {
  const StoreState();

  @override
  List<Object?> get props => [];
}

class StoreInitial extends StoreState {
  const StoreInitial();
}

class StoreLoading extends StoreState {
  const StoreLoading();
}

class StoreLoaded extends StoreState {
  const StoreLoaded({
    required this.categories,
    required this.products,
    this.search = '',
    this.categorySlug,
  });

  final List<ProductCategory> categories;
  final ProductPage products;
  final String search;
  final String? categorySlug;

  @override
  List<Object?> get props => [categories, products, search, categorySlug];
}

class StoreError extends StoreState {
  const StoreError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class StoreCubit extends Cubit<StoreState> {
  StoreCubit(this._repository) : super(const StoreInitial());

  final StoreRepository _repository;

  List<ProductCategory> _categories = const [];
  String _search = '';
  String? _categorySlug;

  Future<void> load({String search = '', String? categorySlug}) async {
    _search = search;
    _categorySlug = categorySlug;
    emit(const StoreLoading());
    try {
      final categories = await _repository.getCategories();
      _categories = categories;
      final products = await _repository.getProducts(
        search: search.isEmpty ? null : search,
        category: categorySlug,
      );
      emit(StoreLoaded(
        categories: categories,
        products: products,
        search: search,
        categorySlug: categorySlug,
      ));
    } on ApiException catch (e) {
      emit(StoreError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const StoreError('common_error'));
    }
  }

  Future<void> search(String query) => load(search: query.trim(), categorySlug: _categorySlug);

  Future<void> filterByCategory(String? slug) =>
      load(search: _search, categorySlug: slug);

  Future<void> refresh() => load(search: _search, categorySlug: _categorySlug);

  List<ProductCategory> get categories => _categories;
}
