<?php

namespace App\Services\Coach\Nutrition;

/**
 * Phase 11E — deterministic calorie targets (Mifflin–St Jeor + documented adjustments).
 *
 * Formula (Mifflin–St Jeor BMR):
 *   male:   BMR = 10*kg + 6.25*cm − 5*age + 5
 *   female: BMR = 10*kg + 6.25*cm − 5*age − 161
 *   unknown sex: average of male/female formulas (assumption flagged)
 *
 * TDEE = BMR × activity_multiplier (from config/nutrition_plan.php)
 * Target = TDEE × (1 + goal_adjustment), clamped to floor/ceiling
 *
 * Not clinical advice. Assumptions are always returned in the result.
 */
class CalorieTargetCalculator
{
    /**
     * @param  array<string, mixed>  $userContext
     * @return array{
     *   ok: bool,
     *   bmr_kcal: float|null,
     *   tdee_kcal: float|null,
     *   calorie_target: int|null,
     *   goal: string,
     *   activity_category: string,
     *   activity_multiplier: float,
     *   goal_adjustment: float,
     *   assumptions: list<string>,
     *   errors: list<string>
     * }
     */
    public function calculate(array $userContext, string $goal, ?int $explicitCalories = null): array
    {
        $assumptions = [
            'Energy estimate uses Mifflin–St Jeor BMR × documented activity multiplier.',
            'Not a medical prescription; estimates only.',
        ];

        if ($explicitCalories !== null && $explicitCalories > 0) {
            $clamped = $this->clamp($explicitCalories);
            if ($clamped !== $explicitCalories) {
                $assumptions[] = "Explicit target {$explicitCalories} kcal clamped to {$clamped} kcal (project floor/ceiling).";
            }

            return [
                'ok' => true,
                'bmr_kcal' => null,
                'tdee_kcal' => null,
                'calorie_target' => $clamped,
                'goal' => $goal,
                'activity_category' => 'explicit_override',
                'activity_multiplier' => 1.0,
                'goal_adjustment' => 0.0,
                'assumptions' => array_merge($assumptions, ['Calorie target taken from explicit user request.']),
                'errors' => [],
            ];
        }

        $body = is_array($userContext['body'] ?? null) ? $userContext['body'] : [];
        $weight = $this->floatOrNull($body['weight_kg'] ?? $userContext['weight_kg'] ?? null);
        $height = $this->floatOrNull($body['height_cm'] ?? $userContext['height_cm'] ?? null);
        $age = $this->intOrNull($body['age'] ?? $userContext['age'] ?? null);
        $sex = mb_strtolower((string) ($body['sex'] ?? $userContext['gender'] ?? ''));

        if ($weight === null) {
            $weight = (float) config('nutrition_plan.fallback_weight_kg', 70);
            $assumptions[] = 'Weight unspecified — used documented fallback '.$weight.' kg.';
        }
        if ($height === null) {
            $height = 170.0;
            $assumptions[] = 'Height unspecified — used documented fallback 170 cm.';
        }
        if ($age === null) {
            $age = 30;
            $assumptions[] = 'Age unspecified — used documented fallback 30 years.';
        }

        if ($sex === 'male' || $sex === 'm') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } elseif ($sex === 'female' || $sex === 'f') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        } else {
            $male = 10 * $weight + 6.25 * $height - 5 * $age + 5;
            $female = 10 * $weight + 6.25 * $height - 5 * $age - 161;
            $bmr = ($male + $female) / 2.0;
            $assumptions[] = 'Sex unspecified — used average of male/female Mifflin–St Jeor formulas.';
        }

        $activityRaw = (string) (
            $userContext['training']['activity_level']
            ?? $userContext['activity_level']
            ?? ''
        );
        [$activityKey, $multiplier] = $this->resolveActivity($activityRaw, $userContext);
        if ($activityRaw === '') {
            $assumptions[] = 'Activity unspecified — used documented default '.$activityKey.' (×'.$multiplier.').';
        }

        $tdee = $bmr * $multiplier;
        $adjustments = config('nutrition_plan.goal_calorie_adjustments', []);
        $adj = (float) ($adjustments[$goal] ?? 0.0);
        $target = (int) round($tdee * (1.0 + $adj));
        $target = $this->clamp($target);

        $assumptions[] = 'Goal adjustment for '.$goal.': '.(string) ($adj * 100).'% of estimated TDEE.';

        return [
            'ok' => true,
            'bmr_kcal' => round($bmr, 1),
            'tdee_kcal' => round($tdee, 1),
            'calorie_target' => $target,
            'goal' => $goal,
            'activity_category' => $activityKey,
            'activity_multiplier' => $multiplier,
            'goal_adjustment' => $adj,
            'assumptions' => $assumptions,
            'errors' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @return array{0: string, 1: float}
     */
    private function resolveActivity(string $raw, array $userContext): array
    {
        $map = config('nutrition_plan.activity_multipliers', []);
        $default = (string) config('nutrition_plan.default_activity', 'moderately_active');
        $raw = mb_strtolower(trim($raw));

        $aliases = [
            'sedentary' => 'sedentary',
            'light' => 'lightly_active',
            'lightly_active' => 'lightly_active',
            'lightly active' => 'lightly_active',
            'moderate' => 'moderately_active',
            'moderately_active' => 'moderately_active',
            'moderately active' => 'moderately_active',
            'active' => 'very_active',
            'very_active' => 'very_active',
            'very active' => 'very_active',
            'extra_active' => 'extra_active',
            'athlete' => 'extra_active',
        ];

        // Infer from training frequency when activity blank.
        if ($raw === '') {
            $freq = (int) (
                $userContext['training']['training_frequency_per_week']
                ?? $userContext['training_frequency_per_week']
                ?? 0
            );
            if ($freq >= 5) {
                $raw = 'very_active';
            } elseif ($freq >= 3) {
                $raw = 'moderately_active';
            } elseif ($freq >= 1) {
                $raw = 'lightly_active';
            }
        }

        $key = $aliases[$raw] ?? $default;
        $mult = (float) ($map[$key] ?? $map[$default] ?? 1.55);

        return [$key, $mult];
    }

    private function clamp(int $kcal): int
    {
        $floor = (int) config('nutrition_plan.calorie_floor_kcal', 1400);
        $ceil = (int) config('nutrition_plan.calorie_ceiling_kcal', 4500);

        return max($floor, min($ceil, $kcal));
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }
}
