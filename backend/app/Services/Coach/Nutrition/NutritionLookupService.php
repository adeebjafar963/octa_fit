<?php

namespace App\Services\Coach\Nutrition;

use InvalidArgumentException;

/**
 * Phase 11D — orchestrates lookup mode: extract → match → calculate.
 */
class NutritionLookupService
{
    public function __construct(
        private readonly NutritionQueryExtractor $extractor,
        private readonly FoodRetrievalService $foods,
        private readonly NutritionCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>|null null when message is not a nutrition lookup
     */
    public function handleMessage(string $message, ?string $language = 'en'): ?array
    {
        if (! $this->extractor->isNutritionLookup($message)) {
            return null;
        }

        $items = $this->extractor->extractFoodItems($message);
        if ($items === []) {
            return [
                'schema_version' => '11d.1',
                'mode' => 'nutrition_lookup',
                'ok' => false,
                'status' => 'not_found',
                'fallthrough_general_knowledge' => true,
                'foods' => [],
                'totals' => null,
                'errors' => [['reason' => 'could_not_parse_food_quantity']],
                'calculation_notes' => ['Could not identify food and quantity from the message.'],
            ];
        }

        $calculated = [];
        $errors = [];
        $ambiguous = [];

        foreach ($items as $index => $item) {
            $match = $this->foods->search($item['query'], [
                'preparation' => $item['preparation'],
                'prefer_generic' => $item['prefer_generic'],
                'brand' => $item['brand'],
            ]);

            if ($match['status'] === 'not_found') {
                $errors[] = [
                    'index' => $index,
                    'query' => $item['query'],
                    'reason' => 'food_not_found',
                ];

                continue;
            }

            if ($match['status'] === 'ambiguous') {
                $ambiguous[] = [
                    'index' => $index,
                    'query' => $item['query'],
                    'candidates' => $match['foods'],
                    'reason' => 'food_match_uncertain',
                ];

                continue;
            }

            $foodPayload = $match['foods'][0];
            try {
                $row = $this->calculator->calculateForFoodId(
                    (int) $foodPayload['food_id'],
                    (float) $item['quantity'],
                    (string) $item['unit'],
                );
                $row['match_status'] = $match['status'];
                $calculated[] = $row;
            } catch (InvalidArgumentException $e) {
                $errors[] = [
                    'index' => $index,
                    'query' => $item['query'],
                    'food_id' => $foodPayload['food_id'] ?? null,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        if ($ambiguous !== []) {
            return [
                'schema_version' => '11d.1',
                'mode' => 'nutrition_lookup',
                'ok' => false,
                'status' => 'ambiguous',
                'foods' => [],
                'ambiguous' => $ambiguous,
                'totals' => null,
                'errors' => [],
                'calculation_notes' => ['Multiple foods matched; clarification required. No silent substitution.'],
            ];
        }

        if ($errors !== [] || $calculated === []) {
            return [
                'schema_version' => '11d.1',
                'mode' => 'nutrition_lookup',
                'ok' => false,
                'status' => 'not_found',
                // Allow CoachService to answer via general nutrition knowledge / RAG
                // instead of hard-failing when food is absent from the fixture catalog.
                'fallthrough_general_knowledge' => true,
                'foods' => $calculated,
                'totals' => null,
                'errors' => $errors,
                'calculation_notes' => ['Lookup incomplete; totals withheld. Prefer general knowledge path when fixture DB misses the food.'],
            ];
        }

        $totals = count($calculated) === 1
            ? [
                'calories_kcal' => $calculated[0]['calories_kcal'],
                'protein_g' => $calculated[0]['protein_g'],
                'carbohydrates_g' => $calculated[0]['carbohydrates_g'],
                'fat_g' => $calculated[0]['fat_g'],
                'fiber_g' => $calculated[0]['fiber_g'],
                'sugar_g' => $calculated[0]['sugar_g'],
                'sodium_mg' => $calculated[0]['sodium_mg'],
            ]
            : $this->calculator->sumTotals($calculated);

        return [
            'schema_version' => '11d.1',
            'mode' => count($calculated) > 1 ? 'meal_calculation' : 'nutrition_lookup',
            'ok' => true,
            'status' => 'exact_match',
            'foods' => $calculated,
            'totals' => $totals,
            'errors' => [],
            'calculation_notes' => ['Deterministic calculation from OCTA FIT food database (per 100 g basis).'],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function formatMessage(array $result, string $language = 'en', string $responseMode = 'direct'): string
    {
        if (! ($result['ok'] ?? false)) {
            if (($result['status'] ?? '') === 'ambiguous') {
                $names = [];
                foreach ($result['ambiguous'][0]['candidates'] ?? [] as $c) {
                    $names[] = ($c['name'] ?? '').' ['.($c['preparation'] ?? '').']';
                }

                return $language === 'ar'
                    ? 'توجد عدة أطعمة محتملة. الرجاء التوضيح: '.implode(' | ', $names)
                    : 'Multiple foods matched. Please clarify: '.implode(' | ', $names);
            }

            // Never expose internal fixture/DB failure copy for fallthrough — caller should route away.
            if (($result['fallthrough_general_knowledge'] ?? false) === true) {
                return '';
            }

            return $language === 'ar'
                ? 'ما لقيت قيمة دقيقة لهالطعام بقاعدة OCTA FIT. إذا بتقلي الاسم أو الكمية أوضح، بحاول أحسبلك بشكل أدق، أو اسأل سؤال تغذية عام.'
                : 'I could not find an exact OCTA FIT database value for that food. Share a clearer food name or quantity for a precise calculation, or ask a general nutrition question.';
        }

        if ($responseMode === 'direct' || $language === 'ar') {
            return $this->formatDirect($result, $language);
        }

        $lines = [];
        foreach ($result['foods'] as $food) {
            $lines[] = ($food['name'] ?? 'Food')
                .' — '.(string) ($food['quantity'] ?? '')
                .' '.(string) ($food['unit'] ?? 'g')
                .' ('.(string) ($food['normalized_grams'] ?? '').' g)';
            $lines[] = 'Calories: '.(string) $food['calories_kcal'].' kcal';
            $lines[] = 'Protein: '.(string) $food['protein_g']
                .' g · Carbs: '.(string) $food['carbohydrates_g']
                .' g · Fat: '.(string) $food['fat_g'].' g';
            $lines[] = '';
        }

        if (count($result['foods']) > 1 && isset($result['totals'])) {
            $t = $result['totals'];
            $lines[] = 'Meal totals — Calories: '.$t['calories_kcal'].' kcal · Protein: '.$t['protein_g']
                .' g · Carbs: '.$t['carbohydrates_g'].' g · Fat: '.$t['fat_g'].' g';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Conversational direct nutrition answer (no internal schema leaks).
     *
     * @param  array<string, mixed>  $result
     */
    private function formatDirect(array $result, string $language): string
    {
        $food = $result['foods'][0] ?? [];
        $totals = $result['totals'] ?? [];
        $kcal = $totals['calories_kcal'] ?? $food['calories_kcal'] ?? null;
        $protein = $totals['protein_g'] ?? $food['protein_g'] ?? null;
        $carbs = $totals['carbohydrates_g'] ?? $food['carbohydrates_g'] ?? null;
        $fat = $totals['fat_g'] ?? $food['fat_g'] ?? null;
        $qty = $food['quantity'] ?? null;
        $unit = $food['unit'] ?? 'g';
        $name = $food['name'] ?? ($language === 'ar' ? 'الطعام' : 'this food');
        $prep = $food['preparation'] ?? null;
        $piece = ($unit === 'piece');

        if ($language === 'ar') {
            $label = $piece
                ? 'الحصة المتوسطة من '.$this->arabicFoodLabel($name)
                : ((string) $qty).' غ من '.$this->arabicFoodLabel($name);
            $isFruitPiece = $piece && (str_contains(mb_strtolower((string) $name), 'apple')
                || str_contains(mb_strtolower((string) $name), 'banana'));
            if (! $isFruitPiece) {
                if ($prep === 'roasted' || $prep === 'cooked') {
                    $label .= ' (مطبوخ)';
                } elseif ($prep === 'raw') {
                    $label .= ' (نيء)';
                }
            }
            $parts = [];
            if ($kcal !== null) {
                $parts[] = 'حوالي '.(string) round((float) $kcal).' سعرة حرارية';
            }
            if ($protein !== null) {
                $parts[] = (string) round((float) $protein, 1).' غ بروتين';
            }
            if ($carbs !== null && count($result['foods']) === 1 && $protein !== null) {
                // keep concise — only add carbs/fat if useful for multi-metric asks; still OK
            }
            $line = $label.' تحتوي '.implode(' و', $parts).'.';
            if ($piece) {
                $line .= ' تختلف حسب الحجم.';
            }

            return $line;
        }

        $label = $piece
            ? 'A medium '.$name
            : ((string) $qty).' '.$unit.' '.$name;
        if ($prep === 'roasted' || $prep === 'cooked') {
            $label .= ' (cooked)';
        } elseif ($prep === 'raw') {
            $label .= ' (raw)';
        }
        $parts = [];
        if ($kcal !== null) {
            $parts[] = 'about '.(string) round((float) $kcal).' kcal';
        }
        if ($protein !== null) {
            $parts[] = (string) round((float) $protein, 1).' g protein';
        }
        $line = $label.' contains '.implode(' and ', $parts).'.';
        if ($piece) {
            $line .= ' Values vary by size.';
        }

        return $line;
    }

    private function arabicFoodLabel(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, 'apple')) {
            return 'التفاح';
        }
        if (str_contains($n, 'chicken')) {
            return 'صدر الدجاج';
        }
        if (str_contains($n, 'banana')) {
            return 'الموز';
        }
        if (str_contains($n, 'egg')) {
            return 'البيض';
        }
        if (str_contains($n, 'rice')) {
            return 'الأرز';
        }

        return $name;
    }
}
