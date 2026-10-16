import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';
import 'package:octa_fit/presentation/store/cubit/store_cubit.dart';
import 'package:octa_fit/presentation/store/widgets/store_widgets.dart';

class StoreHomeScreen extends StatelessWidget {
  const StoreHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => StoreCubit(sl<StoreRepository>())..load(),
      child: const _StoreHomeView(),
    );
  }
}

class _StoreHomeView extends StatefulWidget {
  const _StoreHomeView();

  @override
  State<_StoreHomeView> createState() => _StoreHomeViewState();
}

class _StoreHomeViewState extends State<_StoreHomeView> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'store_title'.tr(),
        accent: AppColors.primary,
        actions: [
          IconButton(
            tooltip: 'store_orders'.tr(),
            onPressed: () => context.push('/store/orders'),
            icon: const Icon(Icons.receipt_long_outlined),
          ),
          IconButton(
            tooltip: 'store_cart'.tr(),
            onPressed: () => context.push('/store/cart'),
            icon: const Icon(Icons.shopping_cart_outlined),
          ),
        ],
      ),
      body: Column(
        children: [
          OctaPage(
            padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 0),
            child: TextField(
              controller: _search,
              decoration: InputDecoration(
                hintText: 'store_search_hint'.tr(),
                prefixIcon: const Icon(Icons.search, color: AppColors.primaryHover),
                suffixIcon: IconButton(
                  onPressed: () {
                    _search.clear();
                    context.read<StoreCubit>().load();
                  },
                  icon: const Icon(Icons.clear),
                ),
              ),
              textInputAction: TextInputAction.search,
              onSubmitted: (value) => context.read<StoreCubit>().search(value),
            ),
          ),
          Expanded(
            child: BlocBuilder<StoreCubit, StoreState>(
              builder: (context, state) {
                return switch (state) {
                  StoreLoading() || StoreInitial() => const AppLoadingView(),
                  StoreError(:final messageKey) => AppEmptyState(
                      messageKey: messageKey,
                      accent: AppColors.primary,
                      onRetry: () => context.read<StoreCubit>().refresh(),
                    ),
                  StoreLoaded(:final categories, :final products, :final categorySlug) =>
                    RefreshIndicator(
                      color: AppColors.primary,
                      onRefresh: () => context.read<StoreCubit>().refresh(),
                      child: ListView(
                        padding: EdgeInsets.zero,
                        children: [
                          OctaPage(
                            padding: const EdgeInsets.all(AppSpacing.lg),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                AppSectionHeader(
                                  'store_categories',
                                  accent: AppColors.primary,
                                ),
                                const SizedBox(height: AppSpacing.md),
                                SizedBox(
                                  height: 42,
                                  child: ListView(
                                    scrollDirection: Axis.horizontal,
                                    children: [
                                      _CategoryChip(
                                        label: 'store_all_products'.tr(),
                                        selected: categorySlug == null,
                                        onTap: () =>
                                            context.read<StoreCubit>().filterByCategory(null),
                                      ),
                                      ...categories.map(
                                        (c) => Padding(
                                          padding: const EdgeInsetsDirectional.only(
                                            start: AppSpacing.sm,
                                          ),
                                          child: _CategoryChip(
                                            label: c.localizedName(
                                              context.locale.languageCode,
                                            ),
                                            selected: categorySlug == c.slug,
                                            onTap: () => context
                                                .read<StoreCubit>()
                                                .filterByCategory(c.slug),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: AppSpacing.xl),
                                AppSectionHeader(
                                  'store_products',
                                  accent: AppColors.primary,
                                ),
                                const SizedBox(height: AppSpacing.md),
                                if (products.items.isEmpty)
                                  const AppEmptyState(
                                    messageKey: 'store_products_empty',
                                    accent: AppColors.primary,
                                  )
                                else
                                  OctaResponsiveGrid(
                                    itemCount: products.items.length,
                                    minTileWidth: 300,
                                    itemBuilder: (context, index) {
                                      final product = products.items[index];
                                      return ProductCardTile(
                                        product: product,
                                        onTap: () =>
                                            context.push('/store/product/${product.id}'),
                                      );
                                    },
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                };
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.primaryTint : AppColors.elevated,
      shape: StadiumBorder(
        side: BorderSide(
          color: selected ? AppColors.primary : AppColors.border,
          width: selected ? 1.4 : 1,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        customBorder: const StadiumBorder(),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: selected ? AppColors.primaryHover : AppColors.textSecondary,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
          ),
        ),
      ),
    );
  }
}
