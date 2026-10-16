<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;
use Illuminate\Support\Collection;

/**
 * Phase 11D — deterministic food search/match against OCTA FIT foods table.
 */
class FoodRetrievalService
{
    /**
     * @return array{
     *   status: string,
     *   foods: list<array<string, mixed>>,
     *   message: string|null
     * }
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['status' => 'not_found', 'foods' => [], 'message' => 'empty_query'];
        }

        $preferPrep = $options['preparation'] ?? null;
        $preferGeneric = array_key_exists('prefer_generic', $options) ? (bool) $options['prefer_generic'] : true;
        $brandRequired = $options['brand'] ?? null;

        $builder = Food::query()->active()->search($query)->limit(20);
        if ($brandRequired !== null && $brandRequired !== '') {
            $builder->where('brand', 'like', '%'.$brandRequired.'%');
        }

        /** @var Collection<int, Food> $hits */
        $hits = $builder->get();

        if ($hits->isEmpty()) {
            return ['status' => 'not_found', 'foods' => [], 'message' => 'food_not_found'];
        }

        $ranked = $hits->map(function (Food $food) use ($query, $preferPrep, $preferGeneric) {
            $score = $this->score($food, $query, $preferPrep, $preferGeneric);

            return ['food' => $food, 'score' => $score];
        })->sortByDesc('score')->values();

        $top = $ranked->first();
        $topScore = (float) ($top['score'] ?? 0);
        $strong = $ranked->filter(fn ($r) => $r['score'] >= max(40, $topScore - 8))->values();

        if ($strong->count() > 1 && abs(($strong[0]['score'] ?? 0) - ($strong[1]['score'] ?? 0)) < 8) {
            return [
                'status' => 'ambiguous',
                'foods' => $strong->take(5)->map(fn ($r) => $this->toPayload($r['food'], 'ambiguous'))->all(),
                'message' => 'food_match_uncertain',
            ];
        }

        $food = $top['food'];
        $status = $topScore >= 70 ? 'exact_match' : ($topScore >= 45 ? 'strong_match' : 'ambiguous');

        if ($status === 'ambiguous') {
            return [
                'status' => 'ambiguous',
                'foods' => $ranked->take(5)->map(fn ($r) => $this->toPayload($r['food'], 'ambiguous'))->all(),
                'message' => 'food_match_uncertain',
            ];
        }

        return [
            'status' => $status,
            'foods' => [$this->toPayload($food, $status)],
            'message' => null,
        ];
    }

    public function findById(int $id): ?Food
    {
        return Food::query()->active()->find($id);
    }

    public function findByExternalId(string $source, string $externalId): ?Food
    {
        return Food::query()->active()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(Food $food, string $matchStatus = 'exact_match'): array
    {
        return [
            'food_id' => $food->id,
            'source' => $food->source,
            'external_id' => $food->external_id,
            'name' => $food->name_en,
            'name_en' => $food->name_en,
            'name_ar' => $food->name_ar,
            'brand' => $food->brand,
            'category' => $food->category,
            'preparation' => $food->preparation,
            'description_en' => $food->description_en,
            'is_generic' => (bool) $food->is_generic,
            'is_active' => (bool) $food->is_active,
            'piece_grams' => $food->piece_grams,
            'piece_label_en' => $food->piece_label_en,
            'piece_label_ar' => $food->piece_label_ar,
            'per_100g' => [
                'calories_kcal' => $food->calories_kcal_per_100g,
                'protein_g' => $food->protein_g_per_100g,
                'carbohydrates_g' => $food->carbohydrates_g_per_100g,
                'fat_g' => $food->fat_g_per_100g,
                'fiber_g' => $food->fiber_g_per_100g,
                'sugar_g' => $food->sugar_g_per_100g,
                'sodium_mg' => $food->sodium_mg_per_100g,
            ],
            'match_status' => $matchStatus,
            'grounded' => true,
        ];
    }

    private function score(Food $food, string $query, ?string $preferPrep, bool $preferGeneric): float
    {
        $q = mb_strtolower($query);
        $name = mb_strtolower($food->name_en);
        $score = 0.0;

        if ($name === $q) {
            $score += 100;
        } elseif (str_contains($name, $q)) {
            $score += 70;
        } else {
            foreach (preg_split('/\s+/', $q) ?: [] as $token) {
                if ($token !== '' && str_contains($name, $token)) {
                    $score += 18;
                }
            }
        }

        if ($food->name_ar && str_contains(mb_strtolower($food->name_ar), $q)) {
            $score += 25;
        }

        if ($preferPrep !== null && $preferPrep !== '' && $food->preparation === $preferPrep) {
            $score += 20;
        } elseif ($preferPrep !== null && $preferPrep !== '' && $food->preparation !== $preferPrep) {
            $score -= 15;
        } elseif ($preferPrep === null || $preferPrep === '') {
            // Unspecified prep: prefer edible prepared forms for calorie/macro questions.
            if (in_array((string) $food->preparation, ['cooked', 'roasted', 'hard_boiled'], true)) {
                $score += 10;
            } elseif ($food->preparation === 'raw') {
                $score -= 6;
            }
        }

        // Prefer cooked rice when user says cooked/cook; prefer raw when user says raw/dry.
        if (str_contains($q, 'cooked') && $food->preparation === 'cooked') {
            $score += 25;
        }
        if ((str_contains($q, 'raw') || str_contains($q, 'dry')) && $food->preparation === 'raw') {
            $score += 25;
        }
        if (str_contains($q, 'cooked') && $food->preparation === 'raw') {
            $score -= 30;
        }
        if ((str_contains($q, 'raw') || str_contains($q, 'dry')) && $food->preparation === 'cooked') {
            $score -= 30;
        }

        if ($preferGeneric && $food->is_generic) {
            $score += 8;
        }
        if ($preferGeneric && ! $food->is_generic) {
            $score -= 25;
        }
        if (! $preferGeneric && ! $food->is_generic) {
            $score += 15;
        }

        return $score;
    }
}
