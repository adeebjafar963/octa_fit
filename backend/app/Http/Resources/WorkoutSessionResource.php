<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkoutSession */
class WorkoutSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_program_id' => $this->training_program_id,
            'type' => $this->type,
            'level' => $this->level,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'status' => $this->status,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'paused_at' => optional($this->paused_at)?->toIso8601String(),
            'paused_total_seconds' => (int) $this->paused_total_seconds,
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'elapsed_seconds' => $this->activeElapsedSeconds(),
            'exercises_completed' => (int) $this->exercises_completed,
            'sets_completed' => (int) $this->sets_completed,
            'total_reps' => (int) $this->total_reps,
            'program' => TrainingProgramResource::make($this->whenLoaded('program')),
            'session_exercises' => $this->whenLoaded('sessionExercises', function () {
                return $this->sessionExercises->map(function ($se) {
                    return [
                        'id' => $se->id,
                        'exercise_id' => $se->exercise_id,
                        'sort_order' => $se->sort_order,
                        'status' => $se->status,
                        'target_sets' => $se->target_sets,
                        'target_reps_min' => $se->target_reps_min,
                        'target_reps_max' => $se->target_reps_max,
                        'target_duration_seconds' => $se->target_duration_seconds,
                        'rest_seconds' => $se->rest_seconds,
                        'rounds' => $se->rounds,
                        'round_seconds' => $se->round_seconds,
                        'round_rest_seconds' => $se->round_rest_seconds,
                        'started_at' => optional($se->started_at)?->toIso8601String(),
                        'completed_at' => optional($se->completed_at)?->toIso8601String(),
                        'sets_completed' => $se->relationLoaded('sets') ? $se->sets->count() : 0,
                        'exercise' => $se->relationLoaded('exercise') && $se->exercise
                            ? (new ExerciseResource($se->exercise))->resolve()
                            : null,
                        'sets' => $se->relationLoaded('sets')
                            ? $se->sets->map(fn ($s) => [
                                'id' => $s->id,
                                'set_number' => $s->set_number,
                                'reps' => $s->reps,
                                'weight_kg' => $s->weight_kg,
                                'duration_seconds' => $s->duration_seconds,
                                'is_warmup' => (bool) $s->is_warmup,
                                'completed_at' => optional($s->completed_at)?->toIso8601String(),
                            ])->values()->all()
                            : [],
                    ];
                })->values()->all();
            }),
        ];
    }
}
