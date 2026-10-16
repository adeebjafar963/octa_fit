<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TrainingCategory */
class TrainingCategoryResource extends JsonResource
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
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'type' => $this->type,
            'level' => $this->level,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'exercises_count' => $this->when(
                isset($this->exercises_count),
                fn () => (int) $this->exercises_count,
            ),
        ];
    }
}
