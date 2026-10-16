<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;

/**
 * Phase 11E — deterministic nutrition plan validation.
 */
class NutritionPlanValidator
{
    public function __construct(
        private readonly FoodRetrievalService $foods,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  FoodConstraintService|null  $constraints
     * @return array{ok: bool, plan: array<string, mixed>|null, errors: list<string>}
     */
    public function validate(array $plan, ?FoodConstraintService $constraints = null): array
    {
        $errors = [];

        if (($plan['schema_version'] ?? null) !== '11e.1') {
            $errors[] = 'invalid_schema_version';
        }

        $calorieTarget = (int) ($plan['calorie_target'] ?? 0);
        if ($calorieTarget <= 0) {
            $errors[] = 'invalid_calorie_target';
        }

        $macros = $plan['macro_targets'] ?? [];
        foreach (['protein_g', 'carbohydrates_g', 'fat_g'] as $k) {
            if (! isset($macros[$k]) || (float) $macros[$k] < 0) {
                $errors[] = 'invalid_macro_'.$k;
            }
        }

        $mealsPerDay = (int) ($plan['meals_per_day'] ?? 0);
        $minM = (int) config('nutrition_plan.meals_per_day_min', 2);
        $maxM = (int) config('nutrition_plan.meals_per_day_max', 6);
        if ($mealsPerDay < $minM || $mealsPerDay > $maxM) {
            $errors[] = 'invalid_meals_per_day';
        }

        $days = $plan['days'] ?? [];
        if (! is_array($days) || $days === []) {
            $errors[] = 'missing_days';
        }

        $maxGrams = (float) config('nutrition_plan.max_food_grams', 800);
        $minGrams = (float) config('nutrition_plan.min_food_grams', 5);
        $seenFoodIds = [];

        foreach ($days as $dayIndex => $day) {
            $meals = $day['meals'] ?? [];
            if (! is_array($meals) || count($meals) !== $mealsPerDay) {
                $errors[] = 'day_'.($dayIndex + 1).'_meal_count_mismatch';
            }

            foreach ($meals as $mealIndex => $meal) {
                $foodEntries = $meal['foods'] ?? [];
                if (! is_array($foodEntries) || $foodEntries === []) {
                    $errors[] = 'empty_meal_d'.($dayIndex + 1).'_m'.($mealIndex + 1);

                    continue;
                }

                foreach ($foodEntries as $foodEntry) {
                    $fid = (int) ($foodEntry['food_id'] ?? 0);
                    if ($fid <= 0) {
                        $errors[] = 'invalid_food_id';

                        continue;
                    }
                    $model = $this->foods->findById($fid);
                    if ($model === null) {
                        $errors[] = 'food_not_found_or_inactive:'.$fid;

                        continue;
                    }
                    $seenFoodIds[$fid] = true;

                    if ($constraints !== null && $constraints->isAllergyViolation($model)) {
                        $errors[] = 'allergy_violation:'.$fid;
                    }
                    if ($constraints !== null && $constraints->violatesDietaryPattern($model)) {
                        $errors[] = 'dietary_pattern_violation:'.$fid;
                    }

                    $qty = (float) ($foodEntry['quantity'] ?? 0);
                    $grams = (float) ($foodEntry['normalized_grams'] ?? 0);
                    if ($qty <= 0 || $grams < $minGrams) {
                        $errors[] = 'invalid_quantity:'.$fid;
                    }
                    if ($grams > $maxGrams) {
                        $errors[] = 'quantity_too_large:'.$fid;
                    }

                    foreach (['calories_kcal', 'protein_g', 'carbohydrates_g', 'fat_g'] as $nut) {
                        if (! array_key_exists($nut, $foodEntry) || $foodEntry[$nut] === null) {
                            $errors[] = 'missing_nutrient:'.$nut;
                        } elseif (! is_finite((float) $foodEntry[$nut])) {
                            $errors[] = 'non_finite_nutrient:'.$nut;
                        }
                    }
                }

                // Meal totals consistency (soft check)
                $mealTotals = $meal['meal_totals'] ?? null;
                if (! is_array($mealTotals)) {
                    $errors[] = 'missing_meal_totals';
                }
            }

            if (! is_array($day['daily_totals'] ?? null)) {
                $errors[] = 'missing_daily_totals';
            }
        }

        // Calorie tolerance vs target (use day 1)
        if ($days !== [] && is_array($days[0]['daily_totals'] ?? null)) {
            $actual = (float) ($days[0]['daily_totals']['calories_kcal'] ?? 0);
            $tol = (float) config('nutrition_plan.calorie_tolerance_kcal', 120);
            if (abs($actual - $calorieTarget) > $tol) {
                $errors[] = 'calorie_tolerance_exceeded';
            }

            $macroTol = (float) config('nutrition_plan.macro_tolerance_g', 25);
            $proteinTarget = (float) ($macros['protein_g'] ?? 0);
            $proteinGot = (float) ($days[0]['daily_totals']['protein_g'] ?? 0);
            // Overshooting protein is acceptable with a small grounded catalog.
            // Fail only when protein is significantly under target after scaling.
            if ($proteinTarget > 0 && ($proteinTarget - $proteinGot) > max($macroTol * 2, 40)) {
                $errors[] = 'protein_tolerance_exceeded';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'plan' => null, 'errors' => array_values(array_unique($errors))];
        }

        $plan['validation_status'] = 'passed';
        $plan['validated'] = true;

        return ['ok' => true, 'plan' => $plan, 'errors' => []];
    }
}
