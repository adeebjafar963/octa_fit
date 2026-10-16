import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/store/cubit/orders_cubit.dart';
import 'package:octa_fit/presentation/store/store_payment.dart';
import 'package:octa_fit/presentation/store/widgets/store_widgets.dart';

class CheckoutScreen extends StatelessWidget {
  const CheckoutScreen({super.key, this.repository});

  final StoreRepository? repository;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CheckoutCubit(repository ?? sl<StoreRepository>())..load(),
      child: const _CheckoutView(),
    );
  }
}

class _CheckoutView extends StatelessWidget {
  const _CheckoutView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(
        title: 'store_checkout'.tr(),
        accent: AppColors.primary,
      ),
      body: BlocBuilder<CheckoutCubit, CheckoutState>(
        builder: (context, state) {
          return switch (state) {
            CheckoutLoadingCart() || CheckoutInitial() => const AppLoadingView(),
            CheckoutError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                accent: AppColors.primary,
                onRetry: () => context.read<CheckoutCubit>().load(),
              ),
            CheckoutSuccess(:final order) => _OrderConfirmation(order: order),
            CheckoutReady(
              :final cart,
              :final paymentMethod,
              :final busy,
              :final errorKey,
            ) =>
              Column(
                children: [
                  Expanded(
                    child: ListView(
                      padding: EdgeInsets.zero,
                      children: [
                        OctaPage(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              AppSectionHeader(
                                'store_order_summary',
                                accent: AppColors.primary,
                              ),
                              const SizedBox(height: AppSpacing.md),
                              OctaSurface(
                                elevated: true,
                                child: Column(
                                  children: [
                                    ...cart.items.map((item) {
                                      final locale = context.locale.languageCode;
                                      final name = item.product?.localizedName(locale) ??
                                          '#${item.productId}';
                                      return Padding(
                                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                                        child: Row(
                                          children: [
                                            Container(
                                              width: 36,
                                              height: 36,
                                              alignment: Alignment.center,
                                              decoration: BoxDecoration(
                                                color: AppColors.primaryTint,
                                                borderRadius: BorderRadius.circular(AppRadii.sm),
                                              ),
                                              child: Text(
                                                '×${item.quantity}',
                                                style: Theme.of(context)
                                                    .textTheme
                                                    .labelLarge
                                                    ?.copyWith(color: AppColors.primaryHover),
                                              ),
                                            ),
                                            const SizedBox(width: AppSpacing.md),
                                            Expanded(
                                              child: Text(
                                                name,
                                                style: Theme.of(context).textTheme.titleMedium,
                                              ),
                                            ),
                                            Text(
                                              formatStorePrice(item.lineTotal),
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .titleMedium
                                                  ?.copyWith(fontWeight: FontWeight.w700),
                                            ),
                                          ],
                                        ),
                                      );
                                    }),
                                    const Divider(height: AppSpacing.lg),
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          'store_subtotal'.tr(),
                                          style: Theme.of(context).textTheme.titleMedium,
                                        ),
                                        Text(
                                          formatStorePrice(cart.subtotal),
                                          style: Theme.of(context)
                                              .textTheme
                                              .headlineMedium
                                              ?.copyWith(
                                                color: AppColors.primaryHover,
                                                letterSpacing: -0.4,
                                              ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: AppSpacing.xl),
                              AppSectionHeader(
                                'store_payment_method',
                                accent: AppColors.primary,
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              Text(
                                'store_payment_choose'.tr(),
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      color: AppColors.textSecondary,
                                    ),
                              ),
                              const SizedBox(height: AppSpacing.md),
                              _PaymentOption(
                                fieldKey: const Key('checkout-payment-cod'),
                                titleKey: 'store_payment_cod',
                                subtitleKey: 'store_payment_cod_hint',
                                icon: Icons.local_shipping_outlined,
                                selected: paymentMethod == 'cash_on_delivery',
                                enabled: !busy,
                                onTap: () => context
                                    .read<CheckoutCubit>()
                                    .setPaymentMethod('cash_on_delivery'),
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              _PaymentOption(
                                fieldKey: const Key('checkout-payment-syriatel'),
                                titleKey: 'store_payment_syriatel_cash',
                                subtitleKey: 'store_payment_syriatel_cash_hint',
                                icon: Icons.phone_android_rounded,
                                selected: paymentMethod == 'syriatel_cash',
                                enabled: !busy,
                                onTap: () => context
                                    .read<CheckoutCubit>()
                                    .setPaymentMethod('syriatel_cash'),
                              ),
                              if (paymentMethod == 'syriatel_cash') ...[
                                const SizedBox(height: AppSpacing.md),
                                Text(
                                  'store_payment_phone_label'.tr(),
                                  style: Theme.of(context).textTheme.titleMedium,
                                ),
                                const SizedBox(height: AppSpacing.sm),
                                TextFormField(
                                  key: const Key('checkout-payment-phone'),
                                  enabled: !busy,
                                  keyboardType: TextInputType.phone,
                                  textAlign: TextAlign.left,
                                  maxLength: 10,
                                  decoration: InputDecoration(
                                    hintText: 'store_payment_phone_hint'.tr(),
                                    prefixIcon: const Icon(Icons.phone_outlined),
                                    counterText: '',
                                  ),
                                  onChanged: context.read<CheckoutCubit>().setPaymentPhone,
                                ),
                              ],
                              if (errorKey != null) ...[
                                const SizedBox(height: AppSpacing.md),
                                Text(
                                  errorKey.tr(),
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                        color: AppColors.danger,
                                      ),
                                ),
                              ],
                              const SizedBox(height: AppSpacing.xxl),
                            ],
                          ),
                        ),
                      ],
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
                      child: AppPrimaryButton(
                        labelKey: 'store_confirm_order',
                        icon: Icons.check_circle_outline_rounded,
                        isLoading: busy,
                        onPressed: paymentMethod.isEmpty
                            ? null
                            : () => context.read<CheckoutCubit>().placeOrder(),
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

class _OrderConfirmation extends StatelessWidget {
  const _OrderConfirmation({required this.order});

  final StoreOrder order;

  @override
  Widget build(BuildContext context) {
    final phone = order.paymentPhone;
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        OctaPage(
          padding: EdgeInsets.zero,
          child: OctaSurface(
            elevated: true,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.check_circle_rounded, color: AppColors.primary, size: 42),
                const SizedBox(height: AppSpacing.md),
                Text(
                  'store_order_confirmed'.tr(),
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text('${'store_order_number'.tr()}: ${order.orderNumber}'),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  '${'store_payment_method'.tr()}: ${paymentMethodLabel(order.paymentMethod)}',
                ),
                if (order.paymentMethod == 'syriatel_cash' && phone != null && phone.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Text('${'store_payment_phone'.tr()}: ${maskSyriatelPhone(phone)}'),
                ],
                const SizedBox(height: AppSpacing.sm),
                Text(
                  '${'store_total'.tr()}: ${formatStorePrice(order.total)}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: AppSpacing.xl),
                AppPrimaryButton(
                  labelKey: 'store_order_details',
                  onPressed: () => context.go('/store/orders/${order.id}'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _PaymentOption extends StatelessWidget {
  const _PaymentOption({
    required this.titleKey,
    required this.subtitleKey,
    required this.icon,
    required this.selected,
    required this.enabled,
    required this.onTap,
    this.fieldKey,
  });

  final Key? fieldKey;
  final String titleKey;
  final String subtitleKey;
  final IconData icon;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      key: fieldKey,
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
      decoration: BoxDecoration(
        color: selected
            ? AppColors.primary.withValues(alpha: 0.10)
            : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(
          color: selected ? AppColors.primary : AppColors.border,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: enabled ? onTap : null,
          borderRadius: BorderRadius.circular(AppRadii.md),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: selected
                        ? AppColors.primary.withValues(alpha: 0.16)
                        : AppColors.elevated,
                    borderRadius: BorderRadius.circular(AppRadii.sm),
                  ),
                  child: Icon(
                    icon,
                    color: selected ? AppColors.primaryHover : AppColors.textSecondary,
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        titleKey.tr(),
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary,
                            ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitleKey.tr(),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                      ),
                    ],
                  ),
                ),
                Icon(
                  selected ? Icons.radio_button_checked : Icons.radio_button_off,
                  color: selected ? AppColors.primary : AppColors.textSecondary,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
