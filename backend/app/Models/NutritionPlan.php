<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_uuid',
    'user_id',
    'version',
    'status',
    'goal',
    'calorie_target',
    'meals_per_day',
    'duration_days',
    'payload',
    'assumptions',
])]
class NutritionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'assumptions' => 'array',
            'version' => 'integer',
            'calorie_target' => 'integer',
            'meals_per_day' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
