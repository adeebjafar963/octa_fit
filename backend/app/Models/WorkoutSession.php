<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'training_program_id',
    'type',
    'level',
    'title_en',
    'title_ar',
    'status',
    'started_at',
    'paused_at',
    'paused_total_seconds',
    'completed_at',
    'duration_seconds',
    'exercises_completed',
    'sets_completed',
    'total_reps',
])]
class WorkoutSession extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_total_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'exercises_completed' => 'integer',
            'sets_completed' => 'integer',
            'total_reps' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function sessionExercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class)->orderBy('sort_order');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function activeElapsedSeconds(?\DateTimeInterface $now = null): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        $nowTs = ($now ?? now())->getTimestamp();
        $started = $this->started_at->getTimestamp();
        $pausedExtra = (int) $this->paused_total_seconds;

        if ($this->status === 'paused' && $this->paused_at !== null) {
            $pausedExtra += max(0, $nowTs - $this->paused_at->getTimestamp());
        }

        if (in_array($this->status, ['completed', 'cancelled'], true) && $this->duration_seconds !== null) {
            return (int) $this->duration_seconds;
        }

        return max(0, $nowTs - $started - $pausedExtra);
    }
}
