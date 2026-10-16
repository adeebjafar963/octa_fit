<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'training_category_id',
    'name_en',
    'name_ar',
    'slug',
    'description_en',
    'description_ar',
    'difficulty',
    'instructions_en',
    'instructions_ar',
    'safety_notes_en',
    'safety_notes_ar',
    'common_mistakes_en',
    'common_mistakes_ar',
    'contraindication_notes',
    'thumbnail_url',
    'target',
    'equipment',
    'equipment_alternatives',
    'primary_muscles',
    'secondary_muscles',
    'metric_type',
    'exercise_type',
    'movement_pattern',
    'bodyweight_supported',
    'home_friendly',
    'gym_friendly',
    'mma_specific',
    'sets',
    'reps_min',
    'reps_max',
    'duration_seconds',
    'rest_seconds',
    'rounds',
    'round_seconds',
    'round_rest_seconds',
    'recommended_rir',
    'recommended_intensity',
    'tempo',
    'progression_exercise_id',
    'regression_exercise_id',
    'evidence_level',
    'evidence_notes',
    'source_title',
    'source_year',
    'source_url',
    'last_reviewed_at',
    'is_active',
    'is_demo',
    'sort_order',
])]
class Exercise extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'sort_order' => 'integer',
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'equipment_alternatives' => 'array',
            'bodyweight_supported' => 'boolean',
            'home_friendly' => 'boolean',
            'gym_friendly' => 'boolean',
            'mma_specific' => 'boolean',
            'sets' => 'integer',
            'reps_min' => 'integer',
            'reps_max' => 'integer',
            'duration_seconds' => 'integer',
            'rest_seconds' => 'integer',
            'rounds' => 'integer',
            'round_seconds' => 'integer',
            'round_rest_seconds' => 'integer',
            'recommended_rir' => 'integer',
            'source_year' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCategory::class, 'training_category_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ExerciseVideo::class)->orderBy('sort_order');
    }

    public function progression(): BelongsTo
    {
        return $this->belongsTo(self::class, 'progression_exercise_id');
    }

    public function regression(): BelongsTo
    {
        return $this->belongsTo(self::class, 'regression_exercise_id');
    }

    public function alternatives(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'exercise_alternatives',
            'exercise_id',
            'alternative_exercise_id',
        )->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exercise_favorites')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name_en', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('description_en', 'like', $like)
                ->orWhere('description_ar', 'like', $like);
        });
    }
}
