import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/store/cubit/orders_cubit.dart';
import 'package:octa_fit/presentation/store/widgets/store_widgets.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => OrdersCubit(sl<StoreRepository>())..load(),
      child: const _OrdersView(),
    );
  }
}

class _OrdersView extends StatelessWidget {
  const _OrdersView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'store_orders'.tr()),
      body: BlocBuilder<OrdersCubit, OrdersState>(
        builder: (context, state) {
          return switch (state) {
            OrdersLoading() || OrdersInitial() => const AppLoadingView(),
            OrdersError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<OrdersCubit>().load(),
              ),
            OrdersLoaded(:final page) => page.items.isEmpty
                ? AppEmptyState(
                    messageKey: 'store_orders_empty',
                    onRetry: () => context.go('/store'),
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    itemCount: page.items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
                    itemBuilder: (context, index) {
                      final order = page.items[index];
                      return Material(
                        color: AppColors.surface,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          side: const BorderSide(color: AppColors.border),
                        ),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          onTap: () => context.push('/store/orders/${order.id}'),
                          child: Padding(
                            padding: const EdgeInsets.all(AppSpacing.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  order.orderNumber,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  orderStatusLabel(order.status),
                                  style: const TextStyle(color: AppColors.primary),
                                ),
                                const SizedBox(height: 4),
                                if (order.createdAt != null)
                                  Text(
                                    order.createdAt!,
                                    style: const TextStyle(
                                      color: AppColors.textSecondary,
                                      fontSize: 13,
                                    ),
                                  ),
                                const SizedBox(height: 8),
                                Text(
                                  formatStorePrice(order.total),
                                  style: const TextStyle(fontWeight: FontWeight.w600),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          };
        },
      ),
    );
  }
}

class OrderDetailsScreen extends StatelessWidget {
  const OrderDetailsScreen({required this.orderId, super.key});

  final int orderId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => OrderDetailCubit(sl<StoreRepository>())..load(orderId),
      child: _OrderDetailsView(orderId: orderId),
    );
  }
}

class _OrderDetailsView extends StatelessWidget {
  const _OrderDetailsView({required this.orderId});

  final int orderId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'store_order_details'.tr()),
      body: BlocBuilder<OrderDetailCubit, OrderDetailState>(
        builder: (context, state) {
          return switch (state) {
            OrderDetailLoading() || OrderDetailInitial() => const AppLoadingView(),
            OrderDetailError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<OrderDetailCubit>().load(orderId),
              ),
            OrderDetailLoaded(:final order) => ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  Text(
                    order.orderNumber,
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text('${'store_status'.tr()}: ${orderStatusLabel(order.status)}'),
                  const SizedBox(height: 4),
                  Text(
                    '${'store_payment_method'.tr()}: ${paymentMethodLabel(order.paymentMethod)}',
                  ),
                  if (order.paymentMethod == 'syriatel_cash' &&
                      order.paymentPhone != null &&
                      order.paymentPhone!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text('${'store_payment_phone'.tr()}: ${order.paymentPhone}'),
                  ],
                  if (order.createdAt != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      order.createdAt!,
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    'store_items'.tr(),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  ...order.items.map((item) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.localizedName(context.locale.languageCode),
                                  style: const TextStyle(fontWeight: FontWeight.w600),
                                ),
                                Text(
                                  '${'store_quantity'.tr()}: ${item.quantity}',
                                  style: const TextStyle(color: AppColors.textSecondary),
                                ),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(formatStorePrice(item.unitPrice)),
                              Text(
                                formatStorePrice(item.lineTotal),
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        ],
                      ),
                    );
                  }),
                  const Divider(height: AppSpacing.xl),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('store_subtotal'.tr()),
                      Text(formatStorePrice(order.subtotal)),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'store_total'.tr(),
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
                      ),
                      Text(
                        formatStorePrice(order.total),
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 18,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  OutlinedButton(
                    onPressed: () => context.go('/store'),
                    child: Text('store_back_to_store'.tr()),
                  ),
                ],
              ),
          };
        },
      ),
    );
  }
}
