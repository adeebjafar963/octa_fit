import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/store.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

String formatStorePrice(String amount) => 'store_price'.tr(namedArgs: {'amount': amount});

String orderStatusLabel(String status) {
  switch (status) {
    case 'pending':
      return 'store_status_pending'.tr();
    case 'confirmed':
      return 'store_status_confirmed'.tr();
    case 'processing':
      return 'store_status_processing'.tr();
    case 'completed':
      return 'store_status_completed'.tr();
    case 'cancelled':
      return 'store_status_cancelled'.tr();
    default:
      return status;
  }
}

String paymentMethodLabel(String method) {
  switch (method) {
    case 'cash_on_delivery':
      return 'store_payment_cod'.tr();
    case 'syriatel_cash':
      return 'store_payment_syriatel_cash'.tr();
    case 'sham_cash':
      return 'store_payment_sham_cash'.tr();
    case 'demo':
      return 'store_payment_demo'.tr();
    default:
      return method;
  }
}

class StoreProductImage extends StatelessWidget {
  const StoreProductImage({
    super.key,
    this.imageUrl,
    this.size = 72,
    this.radius,
    this.expand = false,
  });

  final String? imageUrl;
  final double size;
  final double? radius;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final r = radius ?? AppRadii.sm;
    final placeholder = Container(
      color: AppColors.primaryTint,
      child: const Center(
        child: Icon(Icons.shopping_bag_outlined, color: AppColors.primaryHover, size: 28),
      ),
    );

    final child = imageUrl == null || imageUrl!.isEmpty || imageUrl!.contains('demo.octafit.test')
        ? placeholder
        : Image.network(
            imageUrl!,
            fit: BoxFit.cover,
            width: expand ? double.infinity : size,
            height: expand ? double.infinity : size,
            errorBuilder: (_, _, _) => placeholder,
          );

    return Container(
      width: expand ? double.infinity : size,
      height: expand ? null : size,
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(r),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }
}

/// Overflow-proof product tile — intrinsic height, no fixed Column crush.
class ProductCardTile extends StatelessWidget {
  const ProductCardTile({
    required this.product,
    required this.onTap,
    super.key,
  });

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final locale = context.locale.languageCode;
    final text = Theme.of(context).textTheme;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.md),
        onTap: onTap,
        child: Ink(
          decoration: BoxDecoration(
            color: AppColors.surface,
            gradient: AppGradients.storeCard,
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StoreProductImage(imageUrl: product.imageUrl, size: 88, radius: AppRadii.sm),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        product.localizedName(locale),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: text.titleMedium,
                      ),
                      if (product.category != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          product.category!.localizedName(locale),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: text.bodySmall,
                        ),
                      ],
                      const SizedBox(height: 8),
                      Text(
                        formatStorePrice(product.price),
                        style: AppText.statistic(context, size: 18, color: AppColors.primaryHover),
                      ),
                      const SizedBox(height: 8),
                      OctaBadge(
                        label: product.inStock
                            ? 'store_in_stock'.tr()
                            : 'store_out_of_stock'.tr(),
                        color: product.inStock ? AppColors.success : AppColors.danger,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 14,
                  color: AppColors.primary.withValues(alpha: 0.65),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class QuantityStepper extends StatelessWidget {
  const QuantityStepper({
    required this.quantity,
    required this.onChanged,
    required this.max,
    super.key,
    this.enabled = true,
  });

  final int quantity;
  final int max;
  final ValueChanged<int> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final canDecrease = enabled && quantity > 1;
    final canIncrease = enabled && quantity < max;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.full),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: canDecrease ? () => onChanged(quantity - 1) : null,
            icon: Icon(Icons.remove, color: canDecrease ? AppColors.primary : AppColors.textDisabled),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(
              '$quantity',
              style: AppText.statistic(context, size: 16),
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: canIncrease ? () => onChanged(quantity + 1) : null,
            icon: Icon(Icons.add, color: canIncrease ? AppColors.primary : AppColors.textDisabled),
          ),
        ],
      ),
    );
  }
}
