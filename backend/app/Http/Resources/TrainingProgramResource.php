<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TrainingProgram */
class TrainingProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'type' => $this->type,
            'level' => $this->level,
            'training_category_id' => $this->training_category_id,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'program_exercises_count' => $this->whenCounted('programExercises'),
            'category' => TrainingCategoryResource::make($this->whenLoaded('category')),
            'exercises' => $this->whenLoaded('programExercises', function () {
                return $this->programExercises->map(function ($pe) {
                    return [
                        'id' => $pe->id,
                        'sort_order' => $pe->sort_order,
                        'target_sets' => $pe->target_sets,
                        'target_reps_min' => $pe->target_reps_min,
                        'target_reps_max' => $pe->target_reps_max,
                        'target_duration_seconds' => $pe->target_duration_seconds,
                        'rest_seconds' => $pe->rest_seconds,
                        'rounds' => $pe->rounds,
                        'round_seconds' => $pe->round_seconds,
                        'round_rest_seconds' => $pe->round_rest_seconds,
                        'exercise' => $pe->relationLoaded('exercise') && $pe->exercise
                            ? (new ExerciseResource($pe->exercise))->resolve()
                            : null,
                    ];
                })->values()->all();
            }),
        ];
    }
}
