<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source',
    'external_id',
    'name_en',
    'name_ar',
    'brand',
    'category',
    'preparation',
    'description_en',
    'description_ar',
    'calories_kcal_per_100g',
    'protein_g_per_100g',
    'carbohydrates_g_per_100g',
    'fat_g_per_100g',
    'fiber_g_per_100g',
    'sugar_g_per_100g',
    'sodium_mg_per_100g',
    'piece_grams',
    'piece_label_en',
    'piece_label_ar',
    'is_generic',
    'is_active',
    'source_retrieved_at',
    'source_note',
    'allergen_tags',
])]
class Food extends Model
{
    protected $table = 'foods';

    protected function casts(): array
    {
        return [
            'calories_kcal_per_100g' => 'float',
            'protein_g_per_100g' => 'float',
            'carbohydrates_g_per_100g' => 'float',
            'fat_g_per_100g' => 'float',
            'fiber_g_per_100g' => 'float',
            'sugar_g_per_100g' => 'float',
            'sodium_mg_per_100g' => 'float',
            'piece_grams' => 'float',
            'is_generic' => 'boolean',
            'is_active' => 'boolean',
            'source_retrieved_at' => 'datetime',
            'allergen_tags' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $tokens = preg_split('/[\s,]+/u', mb_strtolower(trim($term))) ?: [];
        $tokens = array_values(array_filter($tokens, static function ($t) {
            return mb_strlen($t) >= 2
                && ! in_array($t, ['of', 'the', 'and', 'in', 'a', 'an', 'with', 'for', 'على', 'من', 'في'], true);
        }));

        if ($tokens === []) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

            return $query->where(function (Builder $q) use ($like) {
                $q->where('name_en', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('description_en', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('external_id', 'like', $like);
            });
        }

        foreach ($tokens as $token) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $token).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('name_en', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('description_en', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('external_id', 'like', $like)
                    ->orWhere('preparation', 'like', $like);
            });
        }

        return $query;
    }
}
