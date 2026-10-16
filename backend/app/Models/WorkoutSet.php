<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workout_session_exercise_id',
    'set_number',
    'reps',
    'weight_kg',
    'duration_seconds',
    'is_warmup',
    'completed_at',
])]
class WorkoutSet extends Model
{
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'float',
            'duration_seconds' => 'integer',
            'is_warmup' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionExercise::class, 'workout_session_exercise_id');
    }
}
