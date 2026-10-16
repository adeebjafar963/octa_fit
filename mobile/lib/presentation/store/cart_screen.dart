import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/store/cubit/cart_cubit.dart';
import 'package:octa_fit/presentation/store/widgets/store_widgets.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CartCubit(sl<StoreRepository>())..load(),
      child: const _CartView(),
    );
  }
}

class _CartView extends StatelessWidget {
  const _CartView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'store_cart'.tr(),
        accent: AppColors.primary,
      ),
      body: BlocConsumer<CartCubit, CartState>(
        listener: (context, state) {
          if (state is CartLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            CartLoading() || CartInitial() => const AppLoadingView(),
            CartError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                accent: AppColors.primary,
                onRetry: () => context.read<CartCubit>().load(),
              ),
            CartLoaded(:final cart, :final busy) => cart.isEmpty
                ? AppEmptyState(
                    messageKey: 'store_cart_empty',
                    icon: Icons.shopping_cart_outlined,
                    accent: AppColors.primary,
                    actionKey: 'store_title',
                    onRetry: () => context.go('/store'),
                  )
                : Column(
                    children: [
                      Expanded(
                        child: ListView.separated(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          itemCount: cart.items.length,
                          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
                          itemBuilder: (context, index) {
                            final item = cart.items[index];
                            final locale = context.locale.languageCode;
                            final name =
                                item.product?.localizedName(locale) ?? '#${item.productId}';
                            return OctaSurface(
                              elevated: true,
                              borderColor: AppColors.primary.withValues(alpha: 0.18),
                              padding: const EdgeInsets.all(AppSpacing.md),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  StoreProductImage(
                                    imageUrl: item.product?.imageUrl,
                                    size: 88,
                                    radius: AppRadii.md,
                                  ),
                                  const SizedBox(width: AppSpacing.md),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(name, style: Theme.of(context).textTheme.titleLarge),
                                        const SizedBox(height: 4),
                                        Text(
                                          formatStorePrice(item.unitPrice),
                                          style: Theme.of(context).textTheme.bodySmall,
                                        ),
                                        const SizedBox(height: AppSpacing.sm),
                                        QuantityStepper(
                                          quantity: item.quantity,
                                          max: item.maxStock,
                                          enabled: !busy,
                                          onChanged: (q) => context
                                              .read<CartCubit>()
                                              .updateQuantity(item.id, q),
                                        ),
                                        const SizedBox(height: AppSpacing.sm),
                                        Text(
                                          '${'store_line_total'.tr()}: ${formatStorePrice(item.lineTotal)}',
                                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                                color: AppColors.primaryHover,
                                              ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  IconButton(
                                    tooltip: 'store_remove'.tr(),
                                    onPressed: busy
                                        ? null
                                        : () => context.read<CartCubit>().removeItem(item.id),
                                    icon: const Icon(
                                      Icons.delete_outline_rounded,
                                      color: AppColors.danger,
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                      Container(
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
                              blurRadius: 20,
                              offset: const Offset(0, -6),
                            ),
                          ],
                        ),
                        child: OctaPage(
                          padding: EdgeInsets.zero,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              OctaSurface(
                                elevated: true,
                                borderColor: AppColors.primary.withValues(alpha: 0.28),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: AppSpacing.md,
                                  vertical: AppSpacing.md,
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      'store_subtotal'.tr(),
                                      style: Theme.of(context).textTheme.titleMedium,
                                    ),
                                    Text(
                                      formatStorePrice(cart.subtotal),
                                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                                            color: AppColors.primaryHover,
                                            letterSpacing: -0.4,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: AppSpacing.md),
                              AppPrimaryButton(
                                labelKey: 'store_checkout',
                                icon: Icons.lock_outline_rounded,
                                onPressed: busy ? null : () => context.push('/store/checkout'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
          };
        },
      ),
    );
  }
}
