<?php

namespace App\Services\Coach\Nutrition;

/**
 * Phase 11E — deterministic macro targets.
 *
 * protein_g = weight_kg × protein_g_per_kg[goal]
 * fat_g = (calorie_target × fat_fraction) / 9
 * carb_g = (calorie_target − protein_kcal − fat_kcal) / 4
 */
class MacroTargetCalculator
{
    /**
     * @param  array<string, mixed>  $userContext
     * @return array{
     *   ok: bool,
     *   protein_g: float,
     *   carbohydrates_g: float,
     *   fat_g: float,
     *   protein_kcal: float,
     *   carbohydrates_kcal: float,
     *   fat_kcal: float,
     *   assumptions: list<string>,
     *   errors: list<string>
     * }
     */
    public function calculate(int $calorieTarget, string $goal, array $userContext = []): array
    {
        $assumptions = [];
        $errors = [];

        if ($calorieTarget <= 0) {
            return [
                'ok' => false,
                'protein_g' => 0.0,
                'carbohydrates_g' => 0.0,
                'fat_g' => 0.0,
                'protein_kcal' => 0.0,
                'carbohydrates_kcal' => 0.0,
                'fat_kcal' => 0.0,
                'assumptions' => [],
                'errors' => ['calorie_target_must_be_positive'],
            ];
        }

        $body = is_array($userContext['body'] ?? null) ? $userContext['body'] : [];
        $weight = $body['weight_kg'] ?? $userContext['weight_kg'] ?? null;
        if (! is_numeric($weight) || (float) $weight <= 0) {
            $weight = (float) config('nutrition_plan.fallback_weight_kg', 70);
            $assumptions[] = 'Weight unspecified for protein — used fallback '.$weight.' kg.';
        } else {
            $weight = (float) $weight;
        }

        $proteinFactors = config('nutrition_plan.protein_g_per_kg', []);
        $pFactor = (float) ($proteinFactors[$goal] ?? config('nutrition_plan.default_protein_g_per_kg', 1.6));
        $proteinG = round($weight * $pFactor, 1);
        $assumptions[] = 'Protein factor '.$pFactor.' g/kg for goal '.$goal.'.';

        $fatFractions = config('nutrition_plan.fat_calorie_fraction', []);
        $fatFrac = (float) ($fatFractions[$goal] ?? config('nutrition_plan.default_fat_calorie_fraction', 0.28));
        $kcal = config('nutrition_plan.kcal_per_g', ['protein' => 4, 'carbohydrates' => 4, 'fat' => 9]);

        $proteinKcal = $proteinG * (float) $kcal['protein'];
        $fatKcal = $calorieTarget * $fatFrac;
        $fatG = round($fatKcal / (float) $kcal['fat'], 1);
        $fatKcal = $fatG * (float) $kcal['fat'];

        $carbKcal = $calorieTarget - $proteinKcal - $fatKcal;
        if ($carbKcal < 0) {
            // Reduce fat fraction slightly to keep carbs non-negative.
            $fatKcal = max(0, $calorieTarget - $proteinKcal);
            $fatG = round($fatKcal / (float) $kcal['fat'], 1);
            $carbKcal = $calorieTarget - $proteinKcal - ($fatG * (float) $kcal['fat']);
            $assumptions[] = 'Fat reduced so carbohydrate calories are non-negative.';
        }

        if ($carbKcal < 0) {
            $errors[] = 'macros_cannot_satisfy_calorie_target';

            return [
                'ok' => false,
                'protein_g' => $proteinG,
                'carbohydrates_g' => 0.0,
                'fat_g' => $fatG,
                'protein_kcal' => round($proteinKcal, 1),
                'carbohydrates_kcal' => 0.0,
                'fat_kcal' => round($fatG * (float) $kcal['fat'], 1),
                'assumptions' => $assumptions,
                'errors' => $errors,
            ];
        }

        $carbG = round($carbKcal / (float) $kcal['carbohydrates'], 1);
        $assumptions[] = 'Fat ≈ '.($fatFrac * 100).'% of calories; carbs fill remaining energy.';

        return [
            'ok' => true,
            'protein_g' => $proteinG,
            'carbohydrates_g' => $carbG,
            'fat_g' => $fatG,
            'protein_kcal' => round($proteinG * (float) $kcal['protein'], 1),
            'carbohydrates_kcal' => round($carbG * (float) $kcal['carbohydrates'], 1),
            'fat_kcal' => round($fatG * (float) $kcal['fat'], 1),
            'assumptions' => $assumptions,
            'errors' => [],
        ];
    }
}
