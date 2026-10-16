<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PhysioMessage */
class PhysioMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
