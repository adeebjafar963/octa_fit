<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'avatar_path',
    'age',
    'gender',
    'height_cm',
    'weight_kg',
    'water_goal_ml',
    'water_goal_is_custom',
    'training_goal',
    'locale',
    'fitness_level',
    'training_experience',
    'training_frequency',
    'session_duration_minutes',
    'training_location',
    'equipment',
    'secondary_goals',
    'activity_level',
    'dietary_preference',
    'meals_per_day',
    'sleep_hours',
    'injury_status',
    'injury_areas',
    'injury_description',
    'professional_evaluation',
    'onboarding_step',
    'profile_completed_at',
])]
class Profile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'sleep_hours' => 'decimal:1',
            'equipment' => 'array',
            'secondary_goals' => 'array',
            'injury_areas' => 'array',
            'professional_evaluation' => 'boolean',
            'profile_completed_at' => 'datetime',
            'training_frequency' => 'integer',
            'session_duration_minutes' => 'integer',
            'meals_per_day' => 'integer',
            'water_goal_ml' => 'integer',
            'water_goal_is_custom' => 'boolean',
            'onboarding_step' => 'integer',
            'age' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
