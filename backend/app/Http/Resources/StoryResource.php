<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Story */
class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'media_url' => $this->media_url,
            'caption' => $this->caption,
            'author' => PostAuthorResource::make($this->whenLoaded('user')),
            'is_owner' => $user !== null && (int) $user->id === (int) $this->user_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'is_active' => (bool) $this->is_active && $this->expires_at?->isFuture(),
        ];
    }
}
