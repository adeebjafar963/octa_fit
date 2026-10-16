<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workout_session_id',
    'exercise_id',
    'training_program_exercise_id',
    'sort_order',
    'status',
    'target_sets',
    'target_reps_min',
    'target_reps_max',
    'target_duration_seconds',
    'rest_seconds',
    'rounds',
    'round_seconds',
    'round_rest_seconds',
    'started_at',
    'completed_at',
])]
class WorkoutSessionExercise extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'target_sets' => 'integer',
            'target_reps_min' => 'integer',
            'target_reps_max' => 'integer',
            'target_duration_seconds' => 'integer',
            'rest_seconds' => 'integer',
            'rounds' => 'integer',
            'round_seconds' => 'integer',
            'round_rest_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('set_number');
    }
}
