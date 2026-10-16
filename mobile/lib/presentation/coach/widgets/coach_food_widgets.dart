import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';

/// Reusable food row for lookup + meal plans (backend values only).
class CoachFoodRow extends StatelessWidget {
  const CoachFoodRow({
    required this.name,
    this.quantityLabel,
    this.calories,
    this.protein,
    this.carbs,
    this.fat,
    this.dense = false,
    super.key,
  });

  factory CoachFoodRow.fromMap(Map<String, dynamic> food, {bool dense = false}) {
    final name = CoachPlanParsing.asString(food['name'] ?? food['name_en']) ??
        'coach_food_unknown'.tr();
    final qty = CoachPlanParsing.asNum(food['normalized_grams'] ?? food['quantity']);
    final unit = CoachPlanParsing.asString(food['unit']) ?? 'g';
    final quantityLabel = qty == null
        ? null
        : '${CoachPlanParsing.formatNumber(qty, fractionDigits: qty % 1 == 0 ? 0 : 1)} $unit';
    return CoachFoodRow(
      name: name,
      quantityLabel: quantityLabel,
      calories: CoachPlanParsing.asDouble(food['calories_kcal'] ?? food['calories']),
      protein: CoachPlanParsing.asDouble(food['protein_g'] ?? food['protein']),
      carbs: CoachPlanParsing.asDouble(
        food['carbohydrates_g'] ?? food['carbs'] ?? food['carbohydrates'],
      ),
      fat: CoachPlanParsing.asDouble(food['fat_g'] ?? food['fat']),
      dense: dense,
    );
  }

  final String name;
  final String? quantityLabel;
  final double? calories;
  final double? protein;
  final double? carbs;
  final double? fat;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 10 : 12,
        vertical: dense ? 8 : 10,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  name,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    height: 1.3,
                  ),
                ),
              ),
              if (quantityLabel != null) ...[
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    quantityLabel!,
                    textAlign: TextAlign.end,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ],
            ],
          ),
          if (calories != null || protein != null || carbs != null || fat != null) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                if (calories != null)
                  _MacroChip(
                    label: 'coach_kcal_short'.tr(
                      namedArgs: {
                        'value': CoachPlanParsing.formatNumber(calories, fractionDigits: 0),
                      },
                    ),
                  ),
                if (protein != null)
                  _MacroChip(
                    label: 'coach_macro_p'.tr(
                      namedArgs: {
                        'value': CoachPlanParsing.formatNumber(protein, fractionDigits: 1),
                      },
                    ),
                  ),
                if (carbs != null)
                  _MacroChip(
                    label: 'coach_macro_c'.tr(
                      namedArgs: {
                        'value': CoachPlanParsing.formatNumber(carbs, fractionDigits: 1),
                      },
                    ),
                  ),
                if (fat != null)
                  _MacroChip(
                    label: 'coach_macro_f'.tr(
                      namedArgs: {
                        'value': CoachPlanParsing.formatNumber(fat, fractionDigits: 1),
                      },
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _MacroChip extends StatelessWidget {
  const _MacroChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.aiTint.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(AppRadii.full),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}

/// Compact calorie / macro metric strip.
class CoachMacroMetrics extends StatelessWidget {
  const CoachMacroMetrics({
    this.calories,
    this.protein,
    this.carbs,
    this.fat,
    super.key,
  });

  factory CoachMacroMetrics.fromTotals(Map<String, dynamic>? totals) {
    final t = totals ?? const {};
    return CoachMacroMetrics(
      calories: CoachPlanParsing.asDouble(t['calories_kcal'] ?? t['calories']),
      protein: CoachPlanParsing.asDouble(t['protein_g'] ?? t['protein']),
      carbs: CoachPlanParsing.asDouble(
        t['carbohydrates_g'] ?? t['carbs'] ?? t['carbohydrates'],
      ),
      fat: CoachPlanParsing.asDouble(t['fat_g'] ?? t['fat']),
    );
  }

  final double? calories;
  final double? protein;
  final double? carbs;
  final double? fat;

  @override
  Widget build(BuildContext context) {
    final items = <({String label, String value})>[
      if (calories != null)
        (
          label: 'coach_metric_calories'.tr(),
          value: 'coach_kcal_unit'.tr(
            namedArgs: {
              'value': CoachPlanParsing.formatNumber(calories, fractionDigits: 0),
            },
          ),
        ),
      if (protein != null)
        (
          label: 'coach_metric_protein'.tr(),
          value: 'coach_grams_unit'.tr(
            namedArgs: {
              'value': CoachPlanParsing.formatNumber(protein, fractionDigits: 1),
            },
          ),
        ),
      if (carbs != null)
        (
          label: 'coach_metric_carbs'.tr(),
          value: 'coach_grams_unit'.tr(
            namedArgs: {
              'value': CoachPlanParsing.formatNumber(carbs, fractionDigits: 1),
            },
          ),
        ),
      if (fat != null)
        (
          label: 'coach_metric_fat'.tr(),
          value: 'coach_grams_unit'.tr(
            namedArgs: {
              'value': CoachPlanParsing.formatNumber(fat, fractionDigits: 1),
            },
          ),
        ),
    ];
    if (items.isEmpty) return const SizedBox.shrink();

    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 420;
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => SizedBox(
                  width: wide
                      ? (constraints.maxWidth - 24) / 4
                      : (constraints.maxWidth - 8) / 2,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                    decoration: BoxDecoration(
                      color: AppColors.elevated,
                      borderRadius: BorderRadius.circular(AppRadii.sm),
                      border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          item.value,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              )
              .toList(),
        );
      },
    );
  }
}
