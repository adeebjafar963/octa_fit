<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;
use InvalidArgumentException;

/**
 * Phase 11D — deterministic nutrition math.
 * nutrient_result = per_100g_value × grams / 100
 */
class NutritionCalculator
{
    public function __construct(
        private readonly ServingNormalizer $normalizer,
        private readonly FoodRetrievalService $foods,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculateForFoodId(int $foodId, float $quantity, string $unit): array
    {
        $food = $this->foods->findById($foodId);
        if ($food === null) {
            throw new InvalidArgumentException('food_not_found_or_inactive');
        }

        return $this->calculateForFood($food, $quantity, $unit);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateForFood(Food $food, float $quantity, string $unit): array
    {
        $normalized = $this->normalizer->normalize($quantity, $unit, $food->piece_grams);
        $grams = $normalized['grams'];
        $factor = $grams / 100.0;

        $calories = $this->scale($food->calories_kcal_per_100g, $factor);
        $protein = $this->scale($food->protein_g_per_100g, $factor);
        $carbs = $this->scale($food->carbohydrates_g_per_100g, $factor);
        $fat = $this->scale($food->fat_g_per_100g, $factor);
        $fiber = $food->fiber_g_per_100g !== null ? $this->scale($food->fiber_g_per_100g, $factor) : null;
        $sugar = $food->sugar_g_per_100g !== null ? $this->scale($food->sugar_g_per_100g, $factor) : null;
        $sodium = $food->sodium_mg_per_100g !== null ? $this->scale($food->sodium_mg_per_100g, $factor) : null;

        $derivedKcal = ($protein * 4) + ($carbs * 4) + ($fat * 9);
        $discrepancy = abs($calories - $derivedKcal);
        $notes = $normalized['notes'];
        if ($discrepancy > 5.0) {
            $notes[] = 'Source calories retained; Atwater-derived energy differs by ~'
                .round($discrepancy, 1).' kcal (informational only).';
        }

        return [
            'schema_version' => '11d.1',
            'food_id' => $food->id,
            'source' => $food->source,
            'external_id' => $food->external_id,
            'name' => $food->name_en,
            'name_en' => $food->name_en,
            'name_ar' => $food->name_ar,
            'brand' => $food->brand,
            'preparation' => $food->preparation,
            'is_generic' => (bool) $food->is_generic,
            'quantity' => $normalized['quantity'],
            'unit' => $normalized['unit'],
            'normalized_grams' => round($grams, 4),
            'calories_kcal' => $calories,
            'protein_g' => $protein,
            'carbohydrates_g' => $carbs,
            'fat_g' => $fat,
            'fiber_g' => $fiber,
            'sugar_g' => $sugar,
            'sodium_mg' => $sodium,
            'match_status' => 'exact_match',
            'grounded' => true,
            'calculation_notes' => $notes,
            'source_metadata' => [
                'source' => $food->source,
                'external_id' => $food->external_id,
                'basis' => 'per_100g',
                'source_note' => $food->source_note,
            ],
        ];
    }

    /**
     * @param  list<array{food_id: int, quantity: float, unit: string}>  $items
     * @return array<string, mixed>
     */
    public function calculateMeal(array $items): array
    {
        $foods = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $foods[] = $this->calculateForFoodId(
                    (int) $item['food_id'],
                    (float) $item['quantity'],
                    (string) $item['unit'],
                );
            } catch (InvalidArgumentException $e) {
                $errors[] = [
                    'index' => $index,
                    'food_id' => $item['food_id'] ?? null,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        if ($errors !== []) {
            return [
                'schema_version' => '11d.1',
                'mode' => 'meal_calculation',
                'ok' => false,
                'foods' => $foods,
                'totals' => null,
                'errors' => $errors,
                'calculation_notes' => ['One or more items could not be resolved; totals withheld.'],
            ];
        }

        return [
            'schema_version' => '11d.1',
            'mode' => 'meal_calculation',
            'ok' => true,
            'foods' => $foods,
            'totals' => $this->sumTotals($foods),
            'errors' => [],
            'calculation_notes' => ['Deterministic sum of grounded food calculations.'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $foods
     * @return array<string, float|null>
     */
    public function sumTotals(array $foods): array
    {
        $sum = [
            'calories_kcal' => 0.0,
            'protein_g' => 0.0,
            'carbohydrates_g' => 0.0,
            'fat_g' => 0.0,
            'fiber_g' => 0.0,
            'sugar_g' => 0.0,
            'sodium_mg' => 0.0,
        ];
        $hasFiber = false;
        $hasSugar = false;
        $hasSodium = false;

        foreach ($foods as $food) {
            $sum['calories_kcal'] += (float) $food['calories_kcal'];
            $sum['protein_g'] += (float) $food['protein_g'];
            $sum['carbohydrates_g'] += (float) $food['carbohydrates_g'];
            $sum['fat_g'] += (float) $food['fat_g'];
            if ($food['fiber_g'] !== null) {
                $sum['fiber_g'] += (float) $food['fiber_g'];
                $hasFiber = true;
            }
            if ($food['sugar_g'] !== null) {
                $sum['sugar_g'] += (float) $food['sugar_g'];
                $hasSugar = true;
            }
            if ($food['sodium_mg'] !== null) {
                $sum['sodium_mg'] += (float) $food['sodium_mg'];
                $hasSodium = true;
            }
        }

        return [
            'calories_kcal' => round($sum['calories_kcal'], 2),
            'protein_g' => round($sum['protein_g'], 2),
            'carbohydrates_g' => round($sum['carbohydrates_g'], 2),
            'fat_g' => round($sum['fat_g'], 2),
            'fiber_g' => $hasFiber ? round($sum['fiber_g'], 2) : null,
            'sugar_g' => $hasSugar ? round($sum['sugar_g'], 2) : null,
            'sodium_mg' => $hasSodium ? round($sum['sodium_mg'], 2) : null,
        ];
    }

    private function scale(float $per100, float $factor): float
    {
        $value = $per100 * $factor;
        if (! is_finite($value)) {
            throw new InvalidArgumentException('non_finite_nutrition_value');
        }

        return round($value, 2);
    }
}
