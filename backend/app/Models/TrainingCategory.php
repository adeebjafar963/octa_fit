<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name_en',
    'name_ar',
    'slug',
    'description_en',
    'description_ar',
    'type',
    'level',
    'subcategory',
    'is_active',
    'sort_order',
])]
class TrainingCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('sort_order');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TrainingProgram::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfLevel(Builder $query, string $level): Builder
    {
        // Level-agnostic categories use 'all'; filter by exercises at difficulty when needed.
        return $query->where(function (Builder $q) use ($level) {
            $q->where('level', $level)->orWhere('level', 'all');
        });
    }
}
