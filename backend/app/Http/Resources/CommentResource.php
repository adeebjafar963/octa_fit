<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'content' => $this->content,
            'author' => PostAuthorResource::make($this->whenLoaded('user')),
            'is_owner' => $user !== null && (int) $user->id === (int) $this->user_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
