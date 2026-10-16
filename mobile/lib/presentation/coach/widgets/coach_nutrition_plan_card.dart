import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_food_widgets.dart';

class CoachNutritionPlanCard extends StatelessWidget {
  const CoachNutritionPlanCard({
    required this.plan,
    this.onRegenerate,
    this.onReplacePrompt,
    super.key,
  });

  final Map<String, dynamic> plan;
  final VoidCallback? onRegenerate;
  final VoidCallback? onReplacePrompt;

  @override
  Widget build(BuildContext context) {
    final goal = CoachPlanParsing.asString(plan['goal']);
    final mealsPerDay = CoachPlanParsing.asInt(plan['meals_per_day']);
    final duration = CoachPlanParsing.asInt(plan['duration_days']);
    final calorieTarget = CoachPlanParsing.asDouble(plan['calorie_target']);
    final macros = CoachPlanParsing.asMap(plan['macro_targets']) ?? {};
    final days = CoachPlanParsing.asMapList(plan['days']);
    final version = CoachPlanParsing.asInt(plan['version']);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
        boxShadow: AppShadows.lift,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.restaurant_menu, size: 18, color: AppColors.ai),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'coach_nutrition_plan_title'.tr(),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              if (version != null)
                Text(
                  'coach_plan_version'.tr(namedArgs: {'value': '$version'}),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              if (goal != null)
                _Pill(text: 'coach_plan_goal'.tr(namedArgs: {'value': goal})),
              if (mealsPerDay != null)
                _Pill(
                  text: 'coach_meals_per_day'.tr(namedArgs: {'value': '$mealsPerDay'}),
                ),
              if (duration != null)
                _Pill(
                  text: 'coach_plan_duration'.tr(namedArgs: {'value': '$duration'}),
                ),
            ],
          ),
          const SizedBox(height: 12),
          CoachMacroMetrics(
            calories: calorieTarget,
            protein: CoachPlanParsing.asDouble(macros['protein_g']),
            carbs: CoachPlanParsing.asDouble(macros['carbohydrates_g']),
            fat: CoachPlanParsing.asDouble(macros['fat_g']),
          ),
          if (days.isNotEmpty) ...[
            const SizedBox(height: 14),
            ...days.map((day) => CoachDayMealsSection(day: day)),
          ],
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              if (onRegenerate != null)
                TextButton.icon(
                  onPressed: onRegenerate,
                  icon: const Icon(Icons.refresh, size: 18),
                  label: Text('coach_action_regenerate_plan'.tr()),
                ),
              if (onReplacePrompt != null)
                TextButton.icon(
                  onPressed: onReplacePrompt,
                  icon: const Icon(Icons.swap_horiz, size: 18),
                  label: Text('coach_action_replace_food'.tr()),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class CoachDayMealsSection extends StatelessWidget {
  const CoachDayMealsSection({required this.day, super.key});

  final Map<String, dynamic> day;

  @override
  Widget build(BuildContext context) {
    final dayNumber = CoachPlanParsing.asInt(day['day_number'] ?? day['day']) ?? 1;
    final meals = CoachPlanParsing.asMapList(day['meals']);
    final totals = CoachPlanParsing.asMap(day['daily_totals']);

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'coach_day_label'.tr(namedArgs: {'value': '$dayNumber'}),
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          ...meals.map((meal) => CoachMealCard(meal: meal)),
          if (totals != null) ...[
            const SizedBox(height: 6),
            Text(
              'coach_daily_totals'.tr(),
              style: Theme.of(context).textTheme.labelMedium,
            ),
            const SizedBox(height: 6),
            CoachMacroMetrics.fromTotals(totals),
          ],
        ],
      ),
    );
  }
}

class CoachMealCard extends StatelessWidget {
  const CoachMealCard({required this.meal, super.key});

  final Map<String, dynamic> meal;

  @override
  Widget build(BuildContext context) {
    final name = CoachPlanParsing.asString(meal['name']) ??
        'coach_meal_fallback'.tr(
          namedArgs: {
            'value': '${CoachPlanParsing.asInt(meal['meal_number']) ?? ''}',
          },
        );
    final foods = CoachPlanParsing.asMapList(meal['foods']);
    final totals = CoachPlanParsing.asMap(meal['meal_totals']);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            name,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          if (totals != null) ...[
            const SizedBox(height: 8),
            CoachMacroMetrics.fromTotals(totals),
          ],
          if (foods.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'coach_meal_empty'.tr(),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
              ),
            )
          else ...[
            const SizedBox(height: 10),
            ...foods.map(
              (food) => Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: CoachFoodRow.fromMap(food, dense: true),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class CoachNutritionLookupCard extends StatelessWidget {
  const CoachNutritionLookupCard({required this.nutrition, super.key});

  final Map<String, dynamic> nutrition;

  @override
  Widget build(BuildContext context) {
    final ok = nutrition['ok'] == true;
    final foods = CoachPlanParsing.asMapList(nutrition['foods']);
    final totals = CoachPlanParsing.asMap(nutrition['totals']);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'coach_nutrition_lookup_title'.tr(),
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 10),
          if (!ok)
            Text(
              'coach_nutrition_lookup_failed'.tr(),
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            )
          else ...[
            if (totals != null) CoachMacroMetrics.fromTotals(totals),
            if (foods.isNotEmpty) ...[
              const SizedBox(height: 10),
              ...foods.map(
                (f) => Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: CoachFoodRow.fromMap(f),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.aiTint.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(AppRadii.full),
      ),
      child: Text(
        text,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}
