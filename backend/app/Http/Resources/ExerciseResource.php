<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Exercise */
class ExerciseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_category_id' => $this->training_category_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'difficulty' => $this->difficulty,
            'instructions_en' => $this->instructions_en,
            'instructions_ar' => $this->instructions_ar,
            'safety_notes_en' => $this->safety_notes_en,
            'safety_notes_ar' => $this->safety_notes_ar,
            'common_mistakes_en' => $this->common_mistakes_en,
            'common_mistakes_ar' => $this->common_mistakes_ar,
            'contraindication_notes' => $this->contraindication_notes,
            'thumbnail_url' => $this->thumbnail_url,
            'target' => $this->target,
            'equipment' => $this->equipment,
            'equipment_alternatives' => $this->equipment_alternatives ?? [],
            'primary_muscles' => $this->primary_muscles ?? [],
            'secondary_muscles' => $this->secondary_muscles ?? [],
            'metric_type' => $this->metric_type ?? 'reps',
            'exercise_type' => $this->exercise_type ?? 'sets_reps',
            'movement_pattern' => $this->movement_pattern,
            'bodyweight_supported' => (bool) $this->bodyweight_supported,
            'home_friendly' => (bool) $this->home_friendly,
            'gym_friendly' => (bool) ($this->gym_friendly ?? true),
            'mma_specific' => (bool) $this->mma_specific,
            'sets' => $this->sets,
            'reps_min' => $this->reps_min,
            'reps_max' => $this->reps_max,
            'duration_seconds' => $this->duration_seconds,
            'rest_seconds' => $this->rest_seconds,
            'rounds' => $this->rounds,
            'round_seconds' => $this->round_seconds,
            'round_rest_seconds' => $this->round_rest_seconds,
            'recommended_rir' => $this->recommended_rir,
            'recommended_intensity' => $this->recommended_intensity,
            'tempo' => $this->tempo,
            'evidence_level' => $this->evidence_level,
            'evidence_notes' => $this->evidence_notes,
            'source_title' => $this->source_title,
            'source_year' => $this->source_year,
            'source_url' => $this->source_url,
            'is_active' => (bool) $this->is_active,
            'is_demo' => (bool) $this->is_demo,
            'sort_order' => $this->sort_order,
            'category' => TrainingCategoryResource::make($this->whenLoaded('category')),
            'videos' => ExerciseVideoResource::collection($this->whenLoaded('videos')),
            'media' => $this->when($this->relationLoaded('videos'), function () {
                $video = $this->videos->first(function ($item) {
                    if (! $item->is_active) {
                        return false;
                    }
                    $type = $item->media_type ?? 'video';

                    return in_array($type, ['gif', 'image', 'png', 'jpg', 'jpeg', 'webp'], true);
                });
                if ($video === null) {
                    return null;
                }

                $url = (string) $video->video_url;
                $type = $video->media_type ?? 'gif';
                $isRemote = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

                return [
                    'type' => $type,
                    'source' => $isRemote ? 'remote' : 'local',
                    'asset' => $url,
                    'url' => $url,
                ];
            }),
            'alternatives' => $this->whenLoaded('alternatives', function () {
                return $this->alternatives->map(fn ($ex) => [
                    'id' => $ex->id,
                    'name_en' => $ex->name_en,
                    'name_ar' => $ex->name_ar,
                    'difficulty' => $ex->difficulty,
                    'equipment' => $ex->equipment,
                ])->values()->all();
            }),
            'progression' => $this->when(
                $this->relationLoaded('progression') && $this->progression,
                fn () => [
                    'id' => $this->progression->id,
                    'name_en' => $this->progression->name_en,
                    'name_ar' => $this->progression->name_ar,
                ],
            ),
            'regression' => $this->when(
                $this->relationLoaded('regression') && $this->regression,
                fn () => [
                    'id' => $this->regression->id,
                    'name_en' => $this->regression->name_en,
                    'name_ar' => $this->regression->name_ar,
                ],
            ),
        ];
    }
}
