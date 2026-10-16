<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'author' => PostAuthorResource::make($this->whenLoaded('user')),
            'like_count' => (int) ($this->likes_count ?? 0),
            'comment_count' => (int) ($this->comments_count ?? 0),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'is_owner' => $user !== null && (int) $user->id === (int) $this->user_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
