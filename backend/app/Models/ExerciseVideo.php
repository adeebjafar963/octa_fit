<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exercise_id',
    'title_en',
    'title_ar',
    'video_url',
    'media_type',
    'thumbnail_url',
    'duration_seconds',
    'is_placeholder',
    'is_active',
    'sort_order',
])]
class ExerciseVideo extends Model
{
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
            'is_active' => 'boolean',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
