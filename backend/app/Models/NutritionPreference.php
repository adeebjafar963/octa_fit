<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'allergies',
    'intolerances',
    'disliked',
    'liked',
    'excluded_foods',
    'dietary_pattern',
    'meals_per_day',
    'cost_preference',
])]
class NutritionPreference extends Model
{
    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'intolerances' => 'array',
            'disliked' => 'array',
            'liked' => 'array',
            'excluded_foods' => 'array',
            'meals_per_day' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function allergyList(): array
    {
        return $this->normalizeList($this->allergies ?? []);
    }

    /**
     * @return list<string>
     */
    public function dislikeList(): array
    {
        return $this->normalizeList($this->disliked ?? []);
    }

    /**
     * @return list<string>
     */
    public function intoleranceList(): array
    {
        return $this->normalizeList($this->intolerances ?? []);
    }

    /**
     * @return list<string>
     */
    public function excludedFoodList(): array
    {
        return $this->normalizeList($this->excluded_foods ?? []);
    }

    /**
     * @param  list<mixed>|null  $items
     * @return list<string>
     */
    private function normalizeList(?array $items): array
    {
        $out = [];
        foreach ($items ?? [] as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = mb_strtolower(trim($item));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }
}
