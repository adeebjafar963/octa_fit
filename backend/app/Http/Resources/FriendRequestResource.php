<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FriendRequest */
class FriendRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'sender' => PublicUserResource::make($this->whenLoaded('sender')),
            'receiver' => PublicUserResource::make($this->whenLoaded('receiver')),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
