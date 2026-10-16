<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_program_id',
    'exercise_id',
    'sort_order',
    'target_sets',
    'target_reps_min',
    'target_reps_max',
    'target_duration_seconds',
    'rest_seconds',
    'rounds',
    'round_seconds',
    'round_rest_seconds',
])]
class TrainingProgramExercise extends Model
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
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
