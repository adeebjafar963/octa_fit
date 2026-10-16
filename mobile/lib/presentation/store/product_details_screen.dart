import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/store/cubit/product_details_cubit.dart';
import 'package:octa_fit/presentation/store/widgets/store_widgets.dart';

class ProductDetailsScreen extends StatelessWidget {
  const ProductDetailsScreen({required this.productId, super.key});

  final String productId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ProductDetailsCubit(sl<StoreRepository>())..load(productId),
      child: _ProductDetailsView(productId: productId),
    );
  }
}

class _ProductDetailsView extends StatelessWidget {
  const _ProductDetailsView({required this.productId});

  final String productId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'store_product_details'.tr(),
        accent: AppColors.primary,
        actions: [
          IconButton(
            tooltip: 'store_cart'.tr(),
            onPressed: () => context.push('/store/cart'),
            icon: const Icon(Icons.shopping_cart_outlined),
          ),
        ],
      ),
      body: BlocConsumer<ProductDetailsCubit, ProductDetailsState>(
        listener: (context, state) {
          if (state is ProductDetailsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            ProductDetailsLoading() || ProductDetailsInitial() => const AppLoadingView(),
            ProductDetailsError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                accent: AppColors.primary,
                onRetry: () => context.read<ProductDetailsCubit>().load(productId),
              ),
            ProductDetailsLoaded(:final product, :final quantity, :final busy) => Column(
                children: [
                  Expanded(
                    child: ListView(
                      padding: EdgeInsets.zero,
                      children: [
                        OctaPage(
                          padding: const EdgeInsets.fromLTRB(
                            AppSpacing.lg,
                            AppSpacing.md,
                            AppSpacing.lg,
                            AppSpacing.lg,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _ProductHero(imageUrl: product.imageUrl),
                              const SizedBox(height: AppSpacing.lg),
                              Text(
                                product.localizedName(context.locale.languageCode),
                                style: Theme.of(context).textTheme.displaySmall,
                              ),
                              const SizedBox(height: AppSpacing.md),
                              Wrap(
                                spacing: AppSpacing.sm,
                                runSpacing: AppSpacing.sm,
                                children: [
                                  if (product.category != null)
                                    OctaBadge(
                                      label: product.category!
                                          .localizedName(context.locale.languageCode),
                                      color: AppColors.primary,
                                    ),
                                  OctaBadge(
                                    label: product.inStock
                                        ? 'store_stock_available'.tr(namedArgs: {
                                            'count': '${product.stockQuantity}',
                                          })
                                        : 'store_out_of_stock'.tr(),
                                    color:
                                        product.inStock ? AppColors.success : AppColors.danger,
                                  ),
                                ],
                              ),
                              const SizedBox(height: AppSpacing.lg),
                              OctaSurface(
                                elevated: true,
                                borderColor: AppColors.primary.withValues(alpha: 0.28),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        formatStorePrice(product.price),
                                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                                              color: AppColors.primaryHover,
                                              letterSpacing: -0.8,
                                            ),
                                      ),
                                    ),
                                    if (product.compareAtPrice != null)
                                      Text(
                                        formatStorePrice(product.compareAtPrice!),
                                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                              decoration: TextDecoration.lineThrough,
                                              color: AppColors.textSecondary,
                                            ),
                                      ),
                                  ],
                                ),
                              ),
                              if (product.localizedDescription(
                                    context.locale.languageCode,
                                  ) !=
                                  null) ...[
                                const SizedBox(height: AppSpacing.xl),
                                AppSectionHeader(
                                  'store_description',
                                  accent: AppColors.primary,
                                ),
                                const SizedBox(height: AppSpacing.md),
                                OctaSurface(
                                  child: Text(
                                    product.localizedDescription(
                                      context.locale.languageCode,
                                    )!,
                                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                          color: AppColors.textSecondary,
                                          height: 1.5,
                                        ),
                                  ),
                                ),
                              ],
                              if (product.inStock) ...[
                                const SizedBox(height: AppSpacing.xl),
                                AppSectionHeader(
                                  'store_quantity',
                                  accent: AppColors.primary,
                                ),
                                const SizedBox(height: AppSpacing.md),
                                QuantityStepper(
                                  quantity: quantity,
                                  max: product.stockQuantity,
                                  enabled: !busy,
                                  onChanged: context.read<ProductDetailsCubit>().setQuantity,
                                ),
                              ],
                              const SizedBox(height: AppSpacing.xxl),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  _CheckoutBar(
                    inStock: product.inStock,
                    busy: busy,
                    onAdd: () => context.read<ProductDetailsCubit>().addToCart(),
                  ),
                ],
              ),
          };
        },
      ),
    );
  }
}

class _ProductHero extends StatelessWidget {
  const _ProductHero({this.imageUrl});

  final String? imageUrl;

  bool get _isPlaceholder =>
      imageUrl == null || imageUrl!.isEmpty || imageUrl!.contains('demo.octafit.test');

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 16 / 10,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.xl),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.soft,
          gradient: AppGradients.storeCard,
        ),
        clipBehavior: Clip.antiAlias,
        child: _isPlaceholder
            ? const Center(
                child: Icon(
                  Icons.shopping_bag_outlined,
                  color: AppColors.primaryHover,
                  size: 56,
                ),
              )
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                width: double.infinity,
                height: double.infinity,
                errorBuilder: (_, _, _) => const Center(
                  child: Icon(
                    Icons.shopping_bag_outlined,
                    color: AppColors.primaryHover,
                    size: 56,
                  ),
                ),
              ),
      ),
    );
  }
}

class _CheckoutBar extends StatelessWidget {
  const _CheckoutBar({
    required this.inStock,
    required this.busy,
    required this.onAdd,
  });

  final bool inStock;
  final bool busy;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.md + MediaQuery.paddingOf(context).bottom,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: const Border(top: BorderSide(color: AppColors.border)),
        boxShadow: [
          BoxShadow(
            color: AppColors.background.withValues(alpha: 0.45),
            blurRadius: 24,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: OctaPage(
        padding: EdgeInsets.zero,
        child: AppPrimaryButton(
          labelKey: inStock ? 'store_add_to_cart' : 'store_out_of_stock',
          icon: inStock ? Icons.add_shopping_cart_rounded : Icons.block_rounded,
          isLoading: busy,
          onPressed: inStock ? onAdd : null,
        ),
      ),
    );
  }
}
